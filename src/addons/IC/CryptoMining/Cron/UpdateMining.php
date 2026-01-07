<?php

namespace IC\CryptoMining\Cron;

/**
 * Mining Cron Job - Runs every hour
 * Processes passive BTC income for all active rigs
 */
class UpdateMining
{
	/**
	 * Run mining updates for all active rigs
	 * Called by cron every hour
	 */
	public static function runUpdate()
	{
		try
		{
			$db = \XF::db();
			$now = \XF::$time;
			
			// Get all active rigs with durability > 0
			$activeRigs = $db->fetchAll("
				SELECT 
					ur.user_rig_id,
					ur.user_id,
					ur.last_mined,
					ur.total_mined,
					ur.current_durability,
					ur.upgrade_level,
					rt.rig_name,
					rt.hash_rate,
					rt.power_consumption
				FROM xf_ic_crypto_user_rigs ur
				INNER JOIN xf_ic_crypto_rig_types rt ON ur.rig_type_id = rt.rig_type_id
				WHERE ur.is_active = 1 
				AND ur.current_durability > 0
			");
			
			if (!$activeRigs)
			{
				// No active rigs, nothing to do
				return;
			}
			
			$totalProcessed = 0;
			$totalBTCMined = 0;
			$totalPowerCost = 0;
			
			// Get degradation rate from options (default 4% per day - rigs last ~25 days)
			$degradationRatePerDay = \XF::options()->ic_crypto_durability_rate ?? 4.0;
			$enableLogging = \XF::options()->ic_crypto_cron_logging ?? true;
			
			foreach ($activeRigs as $rig)
			{
				// Calculate hours since last mined
				$hoursSince = ($now - $rig['last_mined']) / 3600;
				
				// Only process if at least 1 hour has passed
				if ($hoursSince < 1.0)
				{
					continue;
				}
				
				// Calculate base mining output
				$baseHashRate = $rig['hash_rate'];
				
				// Apply upgrade bonus (10% per level)
				$upgradeBonus = 1.0 + ($rig['upgrade_level'] * 0.10);
				
				// Apply durability penalty
				$durabilityMultiplier = 1.0;
				if ($rig['current_durability'] < 50)
				{
					$durabilityMultiplier = 0.75; // 25% reduction
				}
				if ($rig['current_durability'] < 25)
				{
					$durabilityMultiplier = 0.50; // 50% reduction
				}
				
				// Calculate total BTC mined
				$minedBTC = $baseHashRate * $hoursSince * $upgradeBonus * $durabilityMultiplier;
				
				// Calculate durability loss using configurable rate
				// e.g., 2% per day = 2.0 / 24 = 0.0833% per hour
				$durabilityLossPerHour = $degradationRatePerDay / 24;
				$durabilityLoss = $durabilityLossPerHour * $hoursSince;
				$newDurability = max(0, $rig['current_durability'] - $durabilityLoss);
				
				// Optional debug logging
				if ($enableLogging)
				{
					\XF::logError(sprintf(
						'[Mining Debug] User %d, Rig %d (%s): Hours: %.2f, Durability: %.2f%% → %.2f%% (lost %.2f%%), Mined: %.6f BTC',
						$rig['user_id'],
						$rig['user_rig_id'],
						$rig['rig_name'],
						$hoursSince,
						$rig['current_durability'],
						$newDurability,
						$durabilityLoss,
						$minedBTC
					));
				}
				
				// Calculate power costs (in credits from wallet cash_balance)
				$dailyPowerCost = $rig['power_consumption'];
				$powerCostCredits = ($dailyPowerCost / 24) * $hoursSince;
				
				// Ensure wallet exists for this user
				$walletExists = $db->fetchOne("
					SELECT COUNT(*) FROM xf_ic_crypto_wallet WHERE user_id = ?
				", $rig['user_id']);
				
				if (!$walletExists)
				{
					// Create wallet if it doesn't exist
					$db->insert('xf_ic_crypto_wallet', [
						'user_id' => $rig['user_id'],
						'crypto_balance' => 0,
						'total_mined' => 0,
						'cash_balance' => 10000, // Starting balance
						'credits_spent' => 0,
						'created_date' => $now,
						'last_mining_payout' => $now
					]);
				}
				
				// Update rig
				$db->update('xf_ic_crypto_user_rigs', [
					'last_mined' => $now,
					'total_mined' => $rig['total_mined'] + $minedBTC,
					'current_durability' => $newDurability
				], 'user_rig_id = ?', $rig['user_rig_id']);
				
				// Update wallet - add mined BTC and deduct power costs
				$db->query("
					UPDATE xf_ic_crypto_wallet
					SET 
						crypto_balance = crypto_balance + ?,
						total_mined = total_mined + ?,
						cash_balance = GREATEST(0, cash_balance - ?),
						last_mining_payout = ?
					WHERE user_id = ?
				", [$minedBTC, $minedBTC, $powerCostCredits, $now, $rig['user_id']]);
				
				// Log mining reward transaction
				$db->insert('xf_ic_crypto_transactions', [
					'user_id' => $rig['user_id'],
					'transaction_type' => 'mining_reward',
					'amount' => $minedBTC,
					'credits' => -$powerCostCredits, // Negative = cost
					'description' => sprintf(
						'%s mined %.6f BTC (%.1f hrs, power: %.2f cr)',
						$rig['rig_name'],
						$minedBTC,
						$hoursSince,
						$powerCostCredits // Changed from %d to %.2f to show decimals!
					),
					'transaction_date' => $now
				]);
				
				$totalProcessed++;
				$totalBTCMined += $minedBTC;
				$totalPowerCost += $powerCostCredits;
			}
			
			// Don't log anything on success - only errors need logging
			// Success messages clutter the error log unnecessarily
		}
		catch (\Exception $e)
		{
			\XF::logException($e, false, '[Crypto Mining Cron Error] ');
		}
	}
}
