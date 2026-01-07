<?php

namespace IC\CryptoMining\Cron;

/**
 * Update Bitcoin market prices with random fluctuations and events
 * Runs: Daily at midnight
 */
class UpdateMarket
{
	public static function runUpdate()
	{
		try
		{
			$db = \XF::db();
			$now = \XF::$time;
			
			// ========== GET CURRENT MARKET DATA ==========
			$market = $db->fetchRow("
				SELECT * FROM xf_ic_crypto_market 
				WHERE crypto_name = 'Bitcoin'
			");
			
			if (!$market)
			{
				// Initialize market if doesn't exist
				$db->insert('xf_ic_crypto_market', [
					'crypto_name' => 'Bitcoin',
					'current_price' => 50000.00,
					'last_updated' => $now
				]);
				return;
			}
			
			$currentPrice = $market['current_price'];
			
			// ========== BASE RANDOM FLUCTUATION (-5% to +5%) ==========
			$randomFactor = mt_rand(-500, 500) / 10000; // CORRECT - allows negatives
			
			// ========== CHECK FOR ACTIVE EVENTS ==========
			$activeEvent = $db->fetchRow("
				SELECT * FROM xf_ic_crypto_market_events
				WHERE is_active = 1
				LIMIT 1
			");
			
			$eventImpact = 0;
			$eventExpired = false;
			
			if ($activeEvent)
			{
				// Apply event impact (divide by duration to spread impact over time)
				$totalDuration = $activeEvent['duration_hours'];
				$eventImpact = ($activeEvent['price_impact_percent'] / 100) / max(1, $totalDuration / 24);
				
				// Check if event should end
				$hoursSinceTriggered = ($now - $activeEvent['triggered_date']) / 3600;
				if ($hoursSinceTriggered >= $activeEvent['duration_hours'])
				{
					// Event expired - deactivate it
					$db->update('xf_ic_crypto_market_events', [
						'is_active' => 0
					], 'event_id = ?', $activeEvent['event_id']);
					
					$eventExpired = true;
					
					// Post notification that event ended
					self::sendEventNotification(
						'Market Event Ended',
						"{$activeEvent['event_title']} has concluded. Market returning to normal volatility.",
						'event_ended'
					);
				}
			}
			
			// ========== TRIGGER NEW RANDOM EVENT (10% chance if none active) ==========
			if (!$activeEvent || $eventExpired)
			{
				// 10% chance to trigger new event
				if (mt_rand(1, 20) == 1)
				{
					// Get random inactive event
					$availableEvents = $db->fetchAll("
						SELECT * FROM xf_ic_crypto_market_events
						WHERE is_active = 0
						ORDER BY RAND()
						LIMIT 1
					");
					
					if ($availableEvents)
					{
						$newEvent = $availableEvents[0];
						
						// Activate event
						$db->update('xf_ic_crypto_market_events', [
							'is_active' => 1,
							'triggered_date' => $now
						], 'event_id = ?', $newEvent['event_id']);
						
						// Apply first day's event impact
						$eventImpact = ($newEvent['price_impact_percent'] / 100) / max(1, $newEvent['duration_hours'] / 24);
						
						// Notify users of new event
						self::sendEventNotification(
							$newEvent['event_title'],
							$newEvent['event_description'],
							$newEvent['event_type']
						);
					}
				}
			}
			
			// ========== CALCULATE NEW PRICE ==========
			$totalChange = $randomFactor + $eventImpact;
			$newPrice = $currentPrice * (1 + $totalChange);
			
			// Clamp to reasonable bounds ($10k - $100k)
			$newPrice = max(10000, min(100000, $newPrice));
			
			// Calculate percent change
			$priceChangePercent = (($newPrice - $currentPrice) / $currentPrice) * 100;
			
			// ========== UPDATE MARKET ==========
			$db->update('xf_ic_crypto_market', [
				'previous_price' => $currentPrice,
				'current_price' => $newPrice,
				'price_change_percent' => $priceChangePercent,
				'last_updated' => $now
			], 'crypto_name = ?', 'Bitcoin');
			
			// ========== RECORD PRICE HISTORY ==========
			$db->insert('xf_ic_crypto_market_history', [
				'crypto_name' => 'Bitcoin',
				'price' => $newPrice,
				'recorded_date' => $now
			]);
			
			// ========== CLEANUP OLD HISTORY (keep 90 days) ==========
			$ninetyDaysAgo = $now - (90 * 86400);
			$db->delete('xf_ic_crypto_market_history', 
				'recorded_date < ?', 
				$ninetyDaysAgo
			);
			
			// ========== CALCULATE VOLATILITY ==========
			// Get last 30 days of prices
			$thirtyDaysAgo = $now - (30 * 86400);
			$recentPrices = $db->fetchAll("
				SELECT price FROM xf_ic_crypto_market_history
				WHERE crypto_name = 'Bitcoin'
				AND recorded_date >= ?
				ORDER BY recorded_date ASC
			", $thirtyDaysAgo);
			
			if (count($recentPrices) > 1)
			{
				// Calculate standard deviation
				$prices = array_column($recentPrices, 'price');
				$mean = array_sum($prices) / count($prices);
				$variance = 0;
				
				foreach ($prices as $price)
				{
					$variance += pow($price - $mean, 2);
				}
				
				$variance = $variance / count($prices);
				$stdDev = sqrt($variance);
				
				// Volatility as percentage of mean
				$volatility = ($stdDev / $mean) * 100;
				
				// Store volatility in market table (we'll add this column in upgrade)
				// For now, we'll calculate it on-the-fly in the controller
			}
			
		}
		catch (\Exception $e)
		{
			\XF::logException($e, false, "Crypto Mining - Market Update Error: ");
		}
	}
	
	/**
	 * Send event notification to all miners
	 */
	protected static function sendEventNotification($title, $description, $eventType)
	{
		try
		{
			// Get all users with crypto wallets (active miners)
			$db = \XF::db();
			$activeMiners = $db->fetchAll("
				SELECT DISTINCT user_id 
				FROM xf_ic_crypto_wallet
				WHERE crypto_balance > 0 OR total_mined > 0
				LIMIT 1000
			");
			
			if (empty($activeMiners))
			{
				return;
			}
			
			// Determine alert color based on event type
			$alertClass = 'info';
			switch ($eventType)
			{
				case 'bull_run':
				case 'halving':
					$alertClass = 'success';
					break;
				case 'crash':
				case 'regulation':
					$alertClass = 'danger';
					break;
				case 'difficulty_increase':
					$alertClass = 'warning';
					break;
				case 'event_ended':
					$alertClass = 'info';
					break;
			}
			
			// Create alert for each miner
			foreach ($activeMiners as $miner)
			{
				/** @var \XF\Repository\UserAlert $alertRepo */
				$alertRepo = \XF::repository('XF:UserAlert');
				
				$alertRepo->alert(
					\XF::em()->find('XF:User', $miner['user_id']),
					0, // from_user_id (0 = system)
					'crypto_market',
					0, // content_id
					'market_event',
					[
						'title' => $title,
						'description' => $description,
						'event_type' => $eventType
					]
				);
			}
		}
		catch (\Exception $e)
		{
			// Don't throw - notifications are non-critical
			\XF::logError("Failed to send market event notifications: " . $e->getMessage());
		}
	}
}
