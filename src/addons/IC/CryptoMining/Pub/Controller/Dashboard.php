<?php

namespace IC\CryptoMining\Pub\Controller;

use XF\Pub\Controller\AbstractController;

/**
 * Dashboard controller - View owned rigs and mining stats
 */
class Dashboard extends AbstractController
{
	/**
	 * Main dashboard page
	 */
	public function actionIndex()
	{
		// Check view permission
		if (!\XF::visitor()->hasPermission('icCryptoMining', 'view'))
		{
			return $this->noPermission();
		}
		
		$visitor = \XF::visitor();
		
		/** @var \IC\CryptoMining\Repository\UserRig $userRigRepo */
		$userRigRepo = $this->repository('IC\CryptoMining:UserRig');
		
		/** @var \IC\CryptoMining\Repository\Wallet $walletRepo */
		$walletRepo = $this->repository('IC\CryptoMining:Wallet');
		
		/** @var \IC\CryptoMining\Repository\Market $marketRepo */
		$marketRepo = $this->repository('IC\CryptoMining:Market');
		
		// Get user's rigs
		$rigs = $userRigRepo->getUserRigs($visitor->user_id);
		
		// Get wallet
		$wallet = $walletRepo->getOrCreateWallet($visitor->user_id);
		
		// Get stats
		$stats = $userRigRepo->getUserStats($visitor->user_id);
		
		// Get current BTC price
		$btcPrice = $marketRepo->getCurrentPrice();
		
		// Calculate USD value of crypto balance
		$balanceUsd = $wallet->crypto_balance * $btcPrice;
		
		// Get Stock Market portfolio value (optional - if addon installed)
		$db = \XF::db();
		
		$stockCash = 0;
		$stockHoldingsValue = 0;
		$totalStockValue = 0;
		
		try
		{
			// Get active Stock Market season first
			$activeSeason = $db->fetchRow("
				SELECT season_id 
				FROM xf_ic_sm_season 
				WHERE is_active = 1 
				ORDER BY season_id DESC 
				LIMIT 1
			");
			
			if (!$activeSeason)
			{
				// No active season - try most recent
				$activeSeason = $db->fetchRow("
					SELECT season_id 
					FROM xf_ic_sm_season 
					ORDER BY season_id DESC 
					LIMIT 1
				");
			}
			
			$seasonId = $activeSeason ? $activeSeason['season_id'] : 1;
			
			// Check if Stock Market account exists for active season
			$stockAccount = $db->fetchRow("
				SELECT * FROM xf_ic_sm_account 
				WHERE user_id = ? AND season_id = ?
			", [$visitor->user_id, $seasonId]);
			
			if ($stockAccount)
			{
				$stockCash = $stockAccount['cash_balance'];
				// Stock Market has portfolio_value column in xf_ic_sm_account
				$stockHoldingsValue = isset($stockAccount['portfolio_value']) ? $stockAccount['portfolio_value'] : 0;
			}
			
			$totalStockValue = $stockCash + $stockHoldingsValue;
		}
		catch (\Exception $e)
		{
			// Stock Market addon not installed or tables missing - that's fine
			$stockCash = 0;
			$stockHoldingsValue = 0;
			$totalStockValue = 0;
		}
		
		// Get active market event
		$activeEvent = $marketRepo->getActiveEvent();
		
		// Get block competition data
		/** @var \IC\CryptoMining\Repository\Block $blockRepo */
		$blockRepo = $this->repository('IC\CryptoMining:Block');
		$currentBlock = $blockRepo->getCurrentBlock();
		$userOdds = $currentBlock ? $blockRepo->getUserOdds($visitor->user_id) : 0;
		$networkHashrate = $blockRepo->getNetworkHashrate();
		$recentBlocks = $blockRepo->getRecentBlocks(5);
		$userBlockWins = $blockRepo->getUserBlockWins($visitor->user_id);
		
		$viewParams = [
			'userRigs' => $rigs,
			'wallet' => $wallet,
			'balanceUsd' => $balanceUsd,
			'stats' => $stats,
			'btcPrice' => $btcPrice,
			'activeEvent' => $activeEvent,
			'hasRigs' => $rigs->count() > 0,
			'stockCash' => $stockCash,
			'stockHoldingsValue' => $stockHoldingsValue,
			'totalStockValue' => $totalStockValue,
			'currentBlock' => $currentBlock,
			'userOdds' => $userOdds,
			'networkHashrate' => $networkHashrate,
			'recentBlocks' => $recentBlocks,
			'userBlockWins' => $userBlockWins
		];
		
		// Check passive achievements (mining totals, wealth, efficiency, competition)
		/** @var \IC\CryptoMining\Service\Achievement $achievementService */
		$achievementService = $this->service('IC\CryptoMining:Achievement');
		$achievementService->checkAchievements($visitor, 'general');
		
		return $this->view('IC\CryptoMining:Dashboard\Index', 'ic_crypto_dashboard', $viewParams);
	}
	
	/**
	 * Repair a rig - restore durability by paying credits
	 */
	public function actionRepair(\XF\Mvc\ParameterBag $params)
	{
		// Get the rig and verify ownership
		$rig = $this->assertUserRigExists($params->user_rig_id);
		
		if ($rig->user_id !== \XF::visitor()->user_id)
		{
			return $this->noPermission();
		}
		
		// Can't repair if already at 100%
		if ($rig->current_durability >= 100)
		{
			return $this->error('This rig is already at full durability.');
		}
		
		// Calculate repair cost
		$durabilityToRestore = 100 - $rig->current_durability;
		$baseCost = $rig->RigType->base_cost;
		$repairCost = ceil(($baseCost * 0.10) * ($durabilityToRestore / 100));
		
		// Show confirmation form
		if (!$this->isPost())
		{
			$viewParams = [
				'rig' => $rig,
				'repairCost' => $repairCost,
				'durabilityToRestore' => $durabilityToRestore
			];
			return $this->view('IC\CryptoMining:Repair', 'ic_crypto_repair', $viewParams);
		}
		
		// Process repair
		$visitor = \XF::visitor();
		$walletRepo = $this->repository('IC\CryptoMining:Wallet');
		$wallet = $walletRepo->getOrCreateWallet($visitor->user_id);
		
		// Check if user has enough credits
		if ($wallet->cash_balance < $repairCost)
		{
			return $this->error('You need ' . number_format($repairCost) . ' credits to repair this rig. You only have ' . number_format($wallet->cash_balance) . ' credits.');
		}
		
		// Deduct credits from wallet
		$wallet->cash_balance -= $repairCost;
		$wallet->credits_spent += $repairCost;
		$wallet->save();
		
		// Restore durability to 100%
		$rig->current_durability = 100;
		$rig->save();
		
		// Log transaction
		$transaction = $this->em()->create('IC\CryptoMining:Transaction');
		$transaction->user_id = $visitor->user_id;
		$transaction->transaction_type = 'repair';
		$transaction->credits = -$repairCost;
		$transaction->rig_type_id = $rig->rig_type_id;
		$transaction->description = sprintf(
			'Repaired %s from %.1f%% to 100%% durability',
			$rig->RigType->rig_name,
			100 - $durabilityToRestore
		);
		$transaction->transaction_date = \XF::$time;
		$transaction->save();
		
		return $this->redirect(
			$this->buildLink('crypto-mining'),
			sprintf('Repaired %s for %d credits!', $rig->RigType->rig_name, $repairCost)
		);
	}
	
	/**
	 * Assert that a user rig exists and return it
	 */
	protected function assertUserRigExists($id)
	{
		return $this->assertRecordExists('IC\CryptoMining:UserRig', $id, null, 'requested_rig_not_found');
	}
}
