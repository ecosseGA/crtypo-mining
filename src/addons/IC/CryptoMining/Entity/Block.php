<?php

namespace IC\CryptoMining\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * Block Entity
 * Represents a mining block in the competition system
 * 
 * @property int block_id
 * @property int block_number
 * @property float block_reward
 * @property float total_hashrate
 * @property int|null winner_user_id
 * @property int|null winner_rig_id
 * @property int|null solved_date
 * @property int spawned_date
 * @property bool is_solved
 * 
 * @property \XF\Entity\User|null Winner
 * @property UserRig|null WinnerRig
 */
class Block extends Entity
{
	/**
	 * Get time until this block will be solved (for countdown)
	 */
	public function getTimeUntilSolve()
	{
		if ($this->is_solved)
		{
			return 0;
		}
		
		// Blocks solve every 6 hours
		$solveInterval = 6 * 3600; // 6 hours in seconds
		$timeSinceSpawn = \XF::$time - $this->spawned_date;
		$remaining = $solveInterval - $timeSinceSpawn;
		
		return max(0, $remaining);
	}
	
	/**
	 * Get formatted time remaining string
	 */
	public function getTimeRemainingFormatted()
	{
		$seconds = $this->getTimeUntilSolve();
		
		if ($seconds == 0)
		{
			return 'Solving now...';
		}
		
		$hours = floor($seconds / 3600);
		$minutes = floor(($seconds % 3600) / 60);
		
		if ($hours > 0)
		{
			return sprintf('%dh %dm', $hours, $minutes);
		}
		else
		{
			return sprintf('%dm', $minutes);
		}
	}
	
	/**
	 * Check if block is ready to be solved
	 */
	public function isReadyToSolve()
	{
   		 if ($this->is_solved)
   		 {
       		 return false;  // KEEP THIS
    		}
    
   	 // Changed from 6 hours to 1 minute for testing
    	$solveInterval = 6 * 3600;  // Changed from: 6 * 3600
    	$timeSinceSpawn = \XF::$time - $this->spawned_date;
    
   	 return $timeSinceSpawn >= $solveInterval;
	}	
	public static function getStructure(Structure $structure)
	{
		$structure->table = 'xf_ic_crypto_blocks';
		$structure->shortName = 'IC\CryptoMining:Block';
		$structure->primaryKey = 'block_id';
		
		$structure->columns = [
			'block_id' => ['type' => self::UINT, 'autoIncrement' => true],
			'block_number' => ['type' => self::UINT, 'required' => true],
			'block_reward' => ['type' => self::FLOAT, 'default' => 0.01],
			'total_hashrate' => ['type' => self::FLOAT, 'default' => 0],
			'winner_user_id' => ['type' => self::UINT, 'nullable' => true],
			'winner_rig_id' => ['type' => self::UINT, 'nullable' => true],
			'solved_date' => ['type' => self::UINT, 'nullable' => true],
			'spawned_date' => ['type' => self::UINT, 'required' => true],
			'is_solved' => ['type' => self::BOOL, 'default' => false]
		];
		
		$structure->relations = [
			'Winner' => [
				'entity' => 'XF:User',
				'type' => self::TO_ONE,
				'conditions' => [
					['user_id', '=', '$winner_user_id']
				],
				'primary' => true
			],
			'WinnerRig' => [
				'entity' => 'IC\CryptoMining:UserRig',
				'type' => self::TO_ONE,
				'conditions' => [
					['user_rig_id', '=', '$winner_rig_id']
				],
				'primary' => true
			]
		];
		
		return $structure;
	}
}
