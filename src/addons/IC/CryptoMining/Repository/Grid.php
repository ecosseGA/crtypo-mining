<?php

namespace IC\CryptoMining\Repository;

use XF\Mvc\Entity\Repository;

/**
 * Grid Repository
 * Handles Mining Expedition Grid operations
 */
class Grid extends Repository
{
	/**
	 * Get active grid generation
	 */
	public function getActiveGeneration()
	{
		return $this->finder('IC\CryptoMining:GridGeneration')
			->where('is_active', 1)
			->fetchOne();
	}
	
	/**
	 * Get all blocks for active generation
	 */
	public function getActiveGrid()
	{
		$generation = $this->getActiveGeneration();
		if (!$generation)
		{
			return [];
		}
		
		return $this->finder('IC\CryptoMining:GridBlock')
			->where('generation_id', $generation->generation_id)
			->order('position')
			->fetch()
			->toArray();
	}
	
	/**
	 * Generate new grid (100 blocks with weighted random distribution)
	 */
	public function generateGrid($generationId)
	{
		$db = $this->db();
		
		// Block type probabilities
		$distribution = [
			'jackpot' => 1,      // 1%
			'rich_vein' => 9,   // 9%
			'standard' => 50,    // 50%
			'weak_vein' => 30,   // 30%
			'collapse' => 10     // 10%
		];
		
		// Generate 100 blocks
		for ($position = 0; $position < 100; $position++)
		{
			$blockType = $this->getWeightedRandomType($distribution);
			
			// Set values based on type
			switch ($blockType)
			{
				case 'jackpot':
					$btcValue = 3.0;
					$durabilityCost = 0.0;
					break;
				case 'rich_vein':
					$btcValue = 0.1;
					$durabilityCost = 0.0;
					break;
				case 'standard':
					$btcValue = 0.02;
					$durabilityCost = 1.5;
					break;
				case 'weak_vein':
					$btcValue = 0.000100;
					$durabilityCost = 5.0;
					break;
				case 'collapse':
					$btcValue = 0.0;
					$durabilityCost = 35.0;
					break;
				default:
					$btcValue = 0.01;
					$durabilityCost = 1.0;
			}
			
			$db->insert('xf_ic_crypto_grid_state', [
				'generation_id' => $generationId,
				'position' => $position,
				'block_type' => $blockType,
				'btc_value' => $btcValue,
				'durability_cost' => $durabilityCost,
				'is_mined' => 0
			]);
		}
		
		return true;
	}
	
	/**
	 * Get weighted random block type
	 */
	protected function getWeightedRandomType(array $distribution)
	{
		$total = array_sum($distribution);
		$random = mt_rand(1, $total);
		
		$current = 0;
		foreach ($distribution as $type => $weight)
		{
			$current += $weight;
			if ($random <= $current)
			{
				return $type;
			}
		}
		
		return 'standard'; // Fallback
	}
	
	/**
	 * Get block at position
	 */
	public function getBlockAtPosition($position)
	{
		$generation = $this->getActiveGeneration();
		if (!$generation)
		{
			return null;
		}
		
		return $this->finder('IC\CryptoMining:GridBlock')
			->where('generation_id', $generation->generation_id)
			->where('position', $position)
			->fetchOne();
	}
	
	/**
	 * Get surrounding blocks (for scout feature)
	 */
	public function getSurroundingBlocks($position)
	{
		$row = floor($position / 10);
		$col = $position % 10;
		
		$surroundingPositions = [];
		
		// Check all 8 directions
		for ($r = $row - 1; $r <= $row + 1; $r++)
		{
			for ($c = $col - 1; $c <= $col + 1; $c++)
			{
				// Skip center position
				if ($r == $row && $c == $col) continue;
				
				// Check bounds
				if ($r >= 0 && $r < 10 && $c >= 0 && $c < 10)
				{
					$surroundingPositions[] = ($r * 10) + $c;
				}
			}
		}
		
		$generation = $this->getActiveGeneration();
		if (!$generation)
		{
			return [];
		}
		
		return $this->finder('IC\CryptoMining:GridBlock')
			->where('generation_id', $generation->generation_id)
			->where('position', $surroundingPositions)
			->fetch()
			->toArray();
	}
	
	/**
	 * Regenerate grid if needed
	 */
	public function regenerateIfNeeded()
	{
		$generation = $this->getActiveGeneration();
		if (!$generation)
		{
			return $this->createNewGeneration();
		}
		
		// Check if needs regeneration
		$needsRegen = false;
		
		// Condition 1: Less than 20 blocks remaining
		if ($generation->blocks_remaining < 20)
		{
			$needsRegen = true;
		}
		
		// Condition 2: 6 hours have passed since creation
		$sixHours = 6 * 3600;
		if ((\XF::$time - $generation->created_date) >= $sixHours)
		{
			$needsRegen = true;
		}
		
		if ($needsRegen)
		{
			return $this->createNewGeneration();
		}
		
		return false;
	}
	
	/**
	 * Create new generation and grid
	 */
	public function createNewGeneration()
	{
		$db = $this->db();
		
		// End current generation
		$currentGen = $this->getActiveGeneration();
		if ($currentGen)
		{
			$currentGen->is_active = false;
			$currentGen->ended_date = \XF::$time;
			$currentGen->save();
		}
		
		// Create new generation
		$newGen = $this->em()->create('IC\CryptoMining:GridGeneration');
		$newGen->created_date = \XF::$time;
		$newGen->is_active = true;
		$newGen->save();
		
		// Generate grid blocks
		$this->generateGrid($newGen->generation_id);
		
		return $newGen;
	}
	
	/**
	 * Get user's mining stats for active generation
	 */
	public function getUserGridStats($userId)
	{
		$generation = $this->getActiveGeneration();
		if (!$generation)
		{
			return null;
		}
		
		$db = $this->db();
		
		return $db->fetchRow("
			SELECT 
				COUNT(*) as total_mines,
				SUM(btc_earned) as total_btc_earned,
				SUM(CASE WHEN block_type = 'jackpot' THEN 1 ELSE 0 END) as jackpots_found,
				SUM(CASE WHEN block_type = 'collapse' THEN 1 ELSE 0 END) as collapses_hit,
				SUM(credits_spent) as total_credits_spent
			FROM xf_ic_crypto_grid_mines
			WHERE user_id = ? AND generation_id = ?
		", [$userId, $generation->generation_id]);
	}
}
