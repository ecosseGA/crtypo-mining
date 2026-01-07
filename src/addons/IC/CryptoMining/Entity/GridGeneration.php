<?php

namespace IC\CryptoMining\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int generation_id
 * @property int created_date
 * @property int|null ended_date
 * @property int total_mined
 * @property int total_jackpots
 * @property int total_collapses
 * @property bool is_active
 * 
 * GETTERS
 * @property int blocks_remaining
 * @property float completion_percentage
 */
class GridGeneration extends Entity
{
	public static function getStructure(Structure $structure)
	{
		$structure->table = 'xf_ic_crypto_grid_generations';
		$structure->shortName = 'IC\CryptoMining:GridGeneration';
		$structure->primaryKey = 'generation_id';
		
		$structure->columns = [
			'generation_id' => ['type' => self::UINT, 'autoIncrement' => true],
			'created_date' => ['type' => self::UINT, 'default' => 0],
			'ended_date' => ['type' => self::UINT, 'nullable' => true, 'default' => null],
			'total_mined' => ['type' => self::UINT, 'default' => 0],
			'total_jackpots' => ['type' => self::UINT, 'default' => 0],
			'total_collapses' => ['type' => self::UINT, 'default' => 0],
			'is_active' => ['type' => self::BOOL, 'default' => true]
		];
		
		$structure->getters = [
			'blocks_remaining' => true,
			'completion_percentage' => true
		];
		
		return $structure;
	}
	
	/**
	 * Get blocks remaining (out of 100)
	 */
	public function getBlocksRemaining()
	{
		return 100 - $this->total_mined;
	}
	
	/**
	 * Get completion percentage
	 */
	public function getCompletionPercentage()
	{
		return round(($this->total_mined / 100) * 100, 1);
	}
	
	/**
	 * Check if grid needs regeneration (< 20 blocks remaining)
	 */
	public function needsRegeneration()
	{
		return $this->blocks_remaining < 20;
	}
	
	/**
	 * Get time until next scheduled regeneration (6 hours from creation)
	 */
	public function getTimeUntilRegeneration()
	{
		$sixHoursFromCreation = $this->created_date + (6 * 3600);
		$remaining = $sixHoursFromCreation - \XF::$time;
		return max(0, $remaining);
	}
}
