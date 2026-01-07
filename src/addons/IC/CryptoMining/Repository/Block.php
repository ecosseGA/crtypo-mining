<?php

namespace IC\CryptoMining\Repository;

use XF\Mvc\Entity\Repository;

/**
 * Block Repository
 * Handles block competition logic
 */
class Block extends Repository
{
	/**
	 * Get the current active (unsolved) block
	 */
	public function getCurrentBlock()
	{
		return $this->finder('IC\CryptoMining:Block')
			->where('is_solved', 0)
			->order('block_id', 'DESC')
			->fetchOne();
	}
	
	/**
	 * Get recent solved blocks with winners
	 */
	public function getRecentBlocks($limit = 10)
	{
		return $this->finder('IC\CryptoMining:Block')
			->where('is_solved', 1)
			->with('Winner')
			->with('WinnerRig.RigType')
			->order('solved_date', 'DESC')
			->limit($limit)
			->fetch();
	}
	
	/**
	 * Calculate user's winning odds for current block
	 */
	public function getUserOdds($userId)
	{
		$db = $this->db();
		
		// Get user's total active hashrate
		$userHashrate = $db->fetchOne("
			SELECT SUM(rt.hash_rate * (1 + ur.upgrade_level * 0.10))
			FROM xf_ic_crypto_user_rigs ur
			INNER JOIN xf_ic_crypto_rig_types rt ON ur.rig_type_id = rt.rig_type_id
			WHERE ur.user_id = ?
			AND ur.is_active = 1
			AND ur.current_durability > 0
		", $userId) ?: 0;
		
		// Get total network hashrate
		$totalHashrate = $db->fetchOne("
			SELECT SUM(rt.hash_rate * (1 + ur.upgrade_level * 0.10))
			FROM xf_ic_crypto_user_rigs ur
			INNER JOIN xf_ic_crypto_rig_types rt ON ur.rig_type_id = rt.rig_type_id
			WHERE ur.is_active = 1
			AND ur.current_durability > 0
		") ?: 0;
		
		if ($totalHashrate == 0)
		{
			return 0;
		}
		
		return ($userHashrate / $totalHashrate) * 100;
	}
	
	/**
	 * Get total network hashrate
	 */
	public function getNetworkHashrate()
	{
		$db = $this->db();
		
		return $db->fetchOne("
			SELECT SUM(rt.hash_rate * (1 + ur.upgrade_level * 0.10))
			FROM xf_ic_crypto_user_rigs ur
			INNER JOIN xf_ic_crypto_rig_types rt ON ur.rig_type_id = rt.rig_type_id
			WHERE ur.is_active = 1
			AND ur.current_durability > 0
		") ?: 0;
	}
	
	/**
	 * Solve a block using weighted probability
	 */
	public function solveBlock($blockId)
{
	$db = $this->db();
	
	// Get block
	$block = \XF::em()->find('IC\CryptoMining:Block', $blockId);
	if (!$block || $block->is_solved)
	{
		return null;
	}
	
	// Get all active rigs competing (NO ORDER BY - we'll shuffle in PHP)
	$competitors = $db->fetchAll("
		SELECT 
			ur.user_rig_id,
			ur.user_id,
			rt.rig_name,
			(rt.hash_rate * (1 + ur.upgrade_level * 0.10)) as effective_hashrate
		FROM xf_ic_crypto_user_rigs ur
		INNER JOIN xf_ic_crypto_rig_types rt ON ur.rig_type_id = rt.rig_type_id
		WHERE ur.is_active = 1 
		AND ur.current_durability > 0
	");
	
	if (!$competitors)
	{
		// No one mining, block stays unsolved
		return null;
	}
	
	// Shuffle competitors array in PHP for true randomization
	shuffle($competitors);
	
	// Calculate total network hashrate
	$totalHashrate = 0;
	foreach ($competitors as $competitor)
	{
		$totalHashrate += $competitor['effective_hashrate'];
	}
	
	// Generate random float between 0 and totalHashrate
	// Use better precision (10 million instead of 1 million)
	$random = (float)mt_rand(0, (int)($totalHashrate * 10000000)) / 10000000;
	
	// Weighted random selection with <= instead of <
	$cumulative = 0;
	$winner = null;
	
	foreach ($competitors as $competitor)
	{
		$cumulative += $competitor['effective_hashrate'];
		if ($random <= $cumulative)  // CHANGED: < to <=
		{
			$winner = $competitor;
			break;
		}
	}
	
	// Fallback: if no winner selected (shouldn't happen), pick random competitor
	if (!$winner)
	{
		$winner = $competitors[array_rand($competitors)];
	}
	
	// Award block
	$now = \XF::$time;
	
	$db->update('xf_ic_crypto_blocks', [
		'winner_user_id' => $winner['user_id'],
		'winner_rig_id' => $winner['user_rig_id'],
		'solved_date' => $now,
		'is_solved' => 1,
		'total_hashrate' => $totalHashrate
	], 'block_id = ?', $blockId);
	
	// Award BTC to winner
	$db->query("
		UPDATE xf_ic_crypto_wallet
		SET crypto_balance = crypto_balance + ?,
			total_mined = total_mined + ?
		WHERE user_id = ?
	", [$block->block_reward, $block->block_reward, $winner['user_id']]);
	
	// Log transaction
	$db->insert('xf_ic_crypto_transactions', [
		'user_id' => $winner['user_id'],
		'transaction_type' => 'block_reward',
		'amount' => $block->block_reward,
		'description' => sprintf('Won Block #%d with %s', $block->block_number, $winner['rig_name']),
		'transaction_date' => $now
	]);
	
	return $winner;
}	
	/**
	 * Create next block
	 */
	public function createNextBlock()
	{
		$db = $this->db();
		
		// Get last block number
		$lastBlockNumber = $db->fetchOne("
			SELECT MAX(block_number) FROM xf_ic_crypto_blocks
		") ?: 0;
		
		// Create new block
		$db->insert('xf_ic_crypto_blocks', [
			'block_number' => $lastBlockNumber + 1,
			'block_reward' => 0.01, // Configurable (could add halving logic later)
			'total_hashrate' => 0,
			'spawned_date' => \XF::$time,
			'is_solved' => 0
		]);
		
		return $db->lastInsertId();
	}
	
	/**
	 * Get user's block wins count
	 */
	public function getUserBlockWins($userId)
	{
		return $this->db()->fetchOne("
			SELECT COUNT(*)
			FROM xf_ic_crypto_blocks
			WHERE winner_user_id = ?
			AND is_solved = 1
		", $userId) ?: 0;
	}
}
