<?php

namespace IC\CryptoMining\Cron;

/**
 * Update Leaderboard Cron Job
 * Calculates and caches leaderboard rankings
 * Runs hourly
 */
class UpdateLeaderboard
{
	/**
	 * Update all 5 leaderboards
	 */
	public static function runUpdate()
	{
		try
		{
			$db = \XF::db();
			$now = \XF::$time;
			
			// Clear old leaderboard data (older than 1 hour)
			$db->query("
				DELETE FROM xf_ic_crypto_leaderboard 
				WHERE last_updated < ?
			", $now - 3600);
			
			// Update all 5 leaderboards
			self::updateRichestLeaderboard($db, $now);
			self::updateMostMinedLeaderboard($db, $now);
			self::updateMostEfficientLeaderboard($db, $now);
			self::updateMostRigsLeaderboard($db, $now);
			self::updateBlockChampionLeaderboard($db, $now);
			
			// Success - no logging needed
		}
		catch (\Exception $e)
		{
			\XF::logException($e, false, '[Leaderboard Cron Error] ');
		}
	}
	
	/**
	 * Leaderboard #1: Richest Miners (Current BTC Balance)
	 */
	protected static function updateRichestLeaderboard($db, $now)
	{
		// Get top 100 by crypto_balance
		$richest = $db->fetchAll("
			SELECT 
				user_id,
				crypto_balance as stat_value
			FROM xf_ic_crypto_wallet
			WHERE crypto_balance > 0
			ORDER BY crypto_balance DESC
			LIMIT 100
		");
		
		$rank = 1;
		foreach ($richest as $user)
		{
			$db->query("
				INSERT INTO xf_ic_crypto_leaderboard 
				(user_id, leaderboard_type, rank_position, stat_value, last_updated)
				VALUES (?, 'richest', ?, ?, ?)
				ON DUPLICATE KEY UPDATE
					rank_position = VALUES(rank_position),
					stat_value = VALUES(stat_value),
					last_updated = VALUES(last_updated)
			", [$user['user_id'], $rank, $user['stat_value'], $now]);
			
			$rank++;
		}
	}
	
	/**
	 * Leaderboard #2: Most Mined (Lifetime Total)
	 */
	protected static function updateMostMinedLeaderboard($db, $now)
	{
		// Get top 100 by total_mined
		$mostMined = $db->fetchAll("
			SELECT 
				user_id,
				total_mined as stat_value
			FROM xf_ic_crypto_wallet
			WHERE total_mined > 0
			ORDER BY total_mined DESC
			LIMIT 100
		");
		
		$rank = 1;
		foreach ($mostMined as $user)
		{
			$db->query("
				INSERT INTO xf_ic_crypto_leaderboard 
				(user_id, leaderboard_type, rank_position, stat_value, last_updated)
				VALUES (?, 'most_mined', ?, ?, ?)
				ON DUPLICATE KEY UPDATE
					rank_position = VALUES(rank_position),
					stat_value = VALUES(stat_value),
					last_updated = VALUES(last_updated)
			", [$user['user_id'], $rank, $user['stat_value'], $now]);
			
			$rank++;
		}
	}
	
	/**
	 * Leaderboard #3: Most Efficient (Average Fleet Durability)
	 */
	protected static function updateMostEfficientLeaderboard($db, $now)
	{
		// Get top 100 by average rig durability
		$mostEfficient = $db->fetchAll("
			SELECT 
				user_id,
				AVG(current_durability) as stat_value
			FROM xf_ic_crypto_user_rigs
			GROUP BY user_id
			HAVING COUNT(*) >= 1
			ORDER BY stat_value DESC
			LIMIT 100
		");
		
		$rank = 1;
		foreach ($mostEfficient as $user)
		{
			$db->query("
				INSERT INTO xf_ic_crypto_leaderboard 
				(user_id, leaderboard_type, rank_position, stat_value, last_updated)
				VALUES (?, 'most_efficient', ?, ?, ?)
				ON DUPLICATE KEY UPDATE
					rank_position = VALUES(rank_position),
					stat_value = VALUES(stat_value),
					last_updated = VALUES(last_updated)
			", [$user['user_id'], $rank, $user['stat_value'], $now]);
			
			$rank++;
		}
	}
	
	/**
	 * Leaderboard #4: Most Rigs (Empire Builders)
	 */
	protected static function updateMostRigsLeaderboard($db, $now)
	{
		// Get top 100 by rig count
		$mostRigs = $db->fetchAll("
			SELECT 
				user_id,
				COUNT(*) as stat_value
			FROM xf_ic_crypto_user_rigs
			GROUP BY user_id
			HAVING COUNT(*) >= 1
			ORDER BY stat_value DESC
			LIMIT 100
		");
		
		$rank = 1;
		foreach ($mostRigs as $user)
		{
			$db->query("
				INSERT INTO xf_ic_crypto_leaderboard 
				(user_id, leaderboard_type, rank_position, stat_value, last_updated)
				VALUES (?, 'most_rigs', ?, ?, ?)
				ON DUPLICATE KEY UPDATE
					rank_position = VALUES(rank_position),
					stat_value = VALUES(stat_value),
					last_updated = VALUES(last_updated)
			", [$user['user_id'], $rank, $user['stat_value'], $now]);
			
			$rank++;
		}
	}
	
	/**
	 * Leaderboard #5: Block Champions (Most Blocks Won)
	 */
	protected static function updateBlockChampionLeaderboard($db, $now)
	{
		// Get top 100 by blocks won
		$champions = $db->fetchAll("
			SELECT 
				winner_user_id as user_id,
				COUNT(*) as stat_value
			FROM xf_ic_crypto_blocks
			WHERE is_solved = 1
			AND winner_user_id IS NOT NULL
			GROUP BY winner_user_id
			HAVING COUNT(*) >= 1
			ORDER BY stat_value DESC
			LIMIT 100
		");
		
		$rank = 1;
		foreach ($champions as $user)
		{
			$db->query("
				INSERT INTO xf_ic_crypto_leaderboard 
				(user_id, leaderboard_type, rank_position, stat_value, last_updated)
				VALUES (?, 'block_champion', ?, ?, ?)
				ON DUPLICATE KEY UPDATE
					rank_position = VALUES(rank_position),
					stat_value = VALUES(stat_value),
					last_updated = VALUES(last_updated)
			", [$user['user_id'], $rank, $user['stat_value'], $now]);
			
			$rank++;
		}
	}
}
