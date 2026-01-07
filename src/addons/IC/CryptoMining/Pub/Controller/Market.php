<?php

namespace IC\CryptoMining\Pub\Controller;

use XF\Mvc\ParameterBag;

/**
 * Market Controller
 * Handles marketplace - buying/selling crypto for credits
 */
class Market extends \XF\Pub\Controller\AbstractController
{
	/**
	 * Show marketplace with current BTC price and sell form
	 */
	public function actionIndex()
	{
		if (!\XF::visitor()->hasPermission('icCryptoMining', 'view'))
		{
			return $this->noPermission();
		}
		
		$visitor = \XF::visitor();
		$db = \XF::db();
		
		// Get user's wallet
		$walletRepo = $this->repository('IC\CryptoMining:Wallet');
		$wallet = $walletRepo->getOrCreateWallet($visitor->user_id);
		
		// Get current market data
		$marketRepo = $this->repository('IC\CryptoMining:Market');
		$market = $marketRepo->getCurrentMarket();
		
		if (!$market)
		{
			// Create default market entry if doesn't exist
			$market = $this->em()->create('IC\CryptoMining:Market');
			$market->crypto_name = 'Bitcoin';
			$market->current_price = 50000.00;
			$market->last_updated = \XF::$time;
			$market->save();
		}
		
		// Get active market event (if any)
		$activeEvent = $db->fetchRow("
			SELECT * FROM xf_ic_crypto_market_events
			WHERE is_active = 1
			LIMIT 1
		");
		
		// Calculate hours remaining for active event
		$hoursRemaining = 0;
		if ($activeEvent)
		{
			$hoursSinceTriggered = (\XF::$time - $activeEvent['triggered_date']) / 3600;
			$hoursRemaining = max(0, $activeEvent['duration_hours'] - $hoursSinceTriggered);
		}
		
		// Get price history (last 30 days)
		$thirtyDaysAgo = \XF::$time - (30 * 86400);
		$priceHistory = $db->fetchAll("
			SELECT price, recorded_date
			FROM xf_ic_crypto_market_history
			WHERE crypto_name = 'Bitcoin'
			AND recorded_date >= ?
			ORDER BY recorded_date ASC
		", $thirtyDaysAgo);
		
		// Calculate volatility (standard deviation as % of mean)
		$volatility = 0;
		$volatilityLevel = 'low';
		if (count($priceHistory) > 1)
		{
			$prices = array_column($priceHistory, 'price');
			$mean = array_sum($prices) / count($prices);
			$variance = 0;
			
			foreach ($prices as $price)
			{
				$variance += pow($price - $mean, 2);
			}
			
			$variance = $variance / count($prices);
			$stdDev = sqrt($variance);
			$volatility = ($stdDev / $mean) * 100;
			
			// Classify volatility level
			if ($volatility < 5)
			{
				$volatilityLevel = 'low';
			}
			elseif ($volatility < 10)
			{
				$volatilityLevel = 'medium';
			}
			else
			{
				$volatilityLevel = 'high';
			}
		}
		
		// Prepare chart data (sample every 2 days for 30-day chart to keep it clean)
		$chartData = [];
		$chartMinPrice = 0;
		$chartMaxPrice = 0;
		
		if (!empty($priceHistory))
		{
			// Take every nth entry to get ~15 data points
			$step = max(1, floor(count($priceHistory) / 15));
			for ($i = 0; $i < count($priceHistory); $i += $step)
			{
				$chartData[] = $priceHistory[$i];
			}
			
			// Always include the last entry
			if (end($chartData)['recorded_date'] !== end($priceHistory)['recorded_date'])
			{
				$chartData[] = end($priceHistory);
			}
			
			// Calculate min/max for chart
			$prices = array_column($chartData, 'price');
			$chartMinPrice = !empty($prices) ? min($prices) : 0;
			$chartMaxPrice = !empty($prices) ? max($prices) : 0;
		}
		
		$viewParams = [
			'wallet' => $wallet,
			'market' => $market,
			'activeEvent' => $activeEvent,
			'hoursRemaining' => round($hoursRemaining, 1),
			'priceHistory' => $priceHistory,
			'chartData' => $chartData,
			'chartMinPrice' => $chartMinPrice,
			'chartMaxPrice' => $chartMaxPrice,
			'volatility' => round($volatility, 2),
			'volatilityLevel' => $volatilityLevel,
			'activeNav' => 'crypto-market'
		];
		
		return $this->view('IC\CryptoMining:Market\Index', 'ic_crypto_market', $viewParams);
	}
	
	/**
	 * Process selling crypto for credits
	 */
	public function actionSell()
	{
		$this->assertPostOnly();
		
		if (!\XF::visitor()->hasPermission('icCryptoMining', 'view'))
		{
			return $this->noPermission();
		}
		
		$visitor = \XF::visitor();
		
		// Get amount to sell
		$amountToSell = $this->filter('amount', 'float');
		
		if ($amountToSell <= 0)
		{
			return $this->error('Please enter a valid amount greater than 0.');
		}
		
		// Get wallet
		$walletRepo = $this->repository('IC\CryptoMining:Wallet');
		$wallet = $walletRepo->getOrCreateWallet($visitor->user_id);
		
		// Check if user has enough crypto
		if ($wallet->crypto_balance < $amountToSell)
		{
			return $this->error(sprintf(
				'You only have %s BTC. Cannot sell %s BTC.',
				number_format($wallet->crypto_balance, 6),
				number_format($amountToSell, 6)
			));
		}
		
		// Get current market price
		$marketRepo = $this->repository('IC\CryptoMining:Market');
		$market = $marketRepo->getCurrentMarket();
		$btcPrice = $market ? $market->current_price : 50000.00;
		
		// Calculate stock market cash to receive
		// Formula: BTC amount × BTC price = USD value = Stock Market Cash
		$stockCashToReceive = $amountToSell * $btcPrice; // No floor - keep decimals
		
		if ($stockCashToReceive < 0.01)
		{
			return $this->error('Amount too small. Must receive at least $0.01 in stock market cash.');
		}
		
		// Update crypto wallet (deduct BTC)
		$wallet->crypto_balance -= $amountToSell;
		$wallet->total_sold += $amountToSell;
		$wallet->credits_earned += $stockCashToReceive; // Track total stock cash earned
		$wallet->save();
		
		// Add cash to Stock Market account (create if doesn't exist)
		$db = \XF::db();
		
		try
		{
			// Get active Stock Market season
			$activeSeason = $db->fetchRow("
				SELECT season_id 
				FROM xf_ic_sm_season 
				WHERE is_active = 1 
				ORDER BY season_id DESC 
				LIMIT 1
			");
			
			if (!$activeSeason)
			{
				// No active season - try to get the most recent season
				$activeSeason = $db->fetchRow("
					SELECT season_id 
					FROM xf_ic_sm_season 
					ORDER BY season_id DESC 
					LIMIT 1
				");
			}
			
			$seasonId = $activeSeason ? $activeSeason['season_id'] : null;
			
			if (!$seasonId)
			{
				// No seasons exist at all - fallback to season_id = 1
				$seasonId = 1;
			}
			
			// Check if account exists for this season
			$existingAccount = $db->fetchRow("
				SELECT * FROM xf_ic_sm_account 
				WHERE user_id = ? AND season_id = ?
			", [$visitor->user_id, $seasonId]);
			
			if ($existingAccount)
			{
				// Account exists - update cash balance
				$db->update('xf_ic_sm_account', [
					'cash_balance' => $existingAccount['cash_balance'] + $stockCashToReceive
				], 'user_id = ? AND season_id = ?', [$visitor->user_id, $seasonId]);
			}
			else
			{
				// Account doesn't exist - create it
				$db->insert('xf_ic_sm_account', [
					'user_id' => $visitor->user_id,
					'season_id' => $seasonId,
					'cash_balance' => $stockCashToReceive,
					'initial_balance' => 0,
					'created_date' => \XF::$time
				]);
			}
		}
		catch (\Exception $e)
		{
			// Log the error but continue
			\XF::logError("Stock Market account update failed: " . $e->getMessage());
			
			// Return error to user
			return $this->error(sprintf(
				'BTC sold but failed to add cash to Stock Market account. Error: %s. Check that Stock Market addon is installed.',
				$e->getMessage()
			));
		}
		
		// Log transaction
		$transaction = $this->em()->create('IC\CryptoMining:Transaction');
		$transaction->user_id = $visitor->user_id;
		$transaction->transaction_type = 'sell_crypto';
		$transaction->amount = $amountToSell;
		$transaction->credits = $stockCashToReceive;
		$transaction->price_per_unit = $btcPrice;
		$transaction->description = sprintf(
			'Sold %s BTC at $%s each for $%s Stock Market cash',
			number_format($amountToSell, 6),
			number_format($btcPrice, 2),
			number_format($stockCashToReceive, 2)
		);
		$transaction->transaction_date = \XF::$time;
		$transaction->save();
		
		// Check achievements after successful sale
		/** @var \IC\CryptoMining\Service\Achievement $achievementService */
		$achievementService = $this->service('IC\CryptoMining:Achievement');
		$achievementService->checkAchievements($visitor, 'crypto_sold', [
			'amount' => $amountToSell
		]);
		
		return $this->redirect(
			$this->buildLink('crypto-market'),
			sprintf('Sold %s BTC for $%s Stock Market cash! Ready to invest in stocks.', 
				number_format($amountToSell, 6), 
				number_format($stockCashToReceive, 2))
		);
	}
}
