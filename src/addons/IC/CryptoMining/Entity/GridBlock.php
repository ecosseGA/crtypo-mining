<?php

namespace IC\CryptoMining\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int grid_block_id
 * @property int generation_id
 * @property int position
 * @property string block_type
 * @property float btc_value
 * @property float durability_cost
 * @property bool is_mined
 * @property int|null mined_by_user_id
 * @property int|null mined_date
 * 
 * RELATIONS
 * @property \IC\CryptoMining\Entity\GridGeneration Generation
 * @property \XF\Entity\User MinedByUser
 */
class GridBlock extends Entity
{
	public static function getStructure(Structure $structure)
	{
		$structure->table = 'xf_ic_crypto_grid_state';
		$structure->shortName = 'IC\CryptoMining:GridBlock';
		$structure->primaryKey = 'grid_block_id';
		
		$structure->columns = [
			'grid_block_id' => ['type' => self::UINT, 'autoIncrement' => true],
			'generation_id' => ['type' => self::UINT, 'default' => 1],
			'position' => ['type' => self::UINT, 'default' => 0],
			'block_type' => [
				'type' => self::STR, 
				'default' => 'standard',
				'allowedValues' => ['jackpot', 'rich_vein', 'standard', 'weak_vein', 'collapse']
			],
			'btc_value' => ['type' => self::FLOAT, 'default' => 0],
			'durability_cost' => ['type' => self::FLOAT, 'default' => 0],
			'is_mined' => ['type' => self::BOOL, 'default' => false],
			'mined_by_user_id' => ['type' => self::UINT, 'nullable' => true, 'default' => null],
			'mined_date' => ['type' => self::UINT, 'nullable' => true, 'default' => null]
		];
		
		$structure->relations = [
			'Generation' => [
				'entity' => 'IC\CryptoMining:GridGeneration',
				'type' => self::TO_ONE,
				'conditions' => 'generation_id',
				'primary' => true
			],
			'MinedByUser' => [
				'entity' => 'XF:User',
				'type' => self::TO_ONE,
				'conditions' => 'mined_by_user_id',
				'primary' => true
			]
		];
		
		return $structure;
	}
	
	/**
	 * Get icon for block type
	 */
	public function getIcon()
	{
		switch ($this->block_type)
		{
			case 'jackpot':
				return '💎';
			case 'rich_vein':
				return '💰';
			case 'standard':
				return '⛏️';
			case 'weak_vein':
				return '🪨';
			case 'collapse':
				return '💥';
			default:
				return '❓';
		}
	}
	
	/**
	 * Get row (0-9) from position
	 */
	public function getRow()
	{
		return floor($this->position / 10);
	}
	
	/**
	 * Get column (0-9) from position
	 */
	public function getColumn()
	{
		return $this->position % 10;
	}
}
