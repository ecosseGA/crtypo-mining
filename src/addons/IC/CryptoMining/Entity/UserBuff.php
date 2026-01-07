<?php

namespace IC\CryptoMining\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int buff_id
 * @property int user_id
 * @property string buff_type
 * @property float buff_value
 * @property int started_date
 * @property int expires_date
 * @property bool is_active
 * 
 * RELATIONS
 * @property \XF\Entity\User User
 * 
 * GETTERS
 * @property int time_remaining
 * @property bool is_expired
 */
class UserBuff extends Entity
{
	public static function getStructure(Structure $structure)
	{
		$structure->table = 'xf_ic_crypto_user_buffs';
		$structure->shortName = 'IC\CryptoMining:UserBuff';
		$structure->primaryKey = 'buff_id';
		
		$structure->columns = [
			'buff_id' => ['type' => self::UINT, 'autoIncrement' => true],
			'user_id' => ['type' => self::UINT, 'required' => true],
			'buff_type' => [
				'type' => self::STR,
				'allowedValues' => ['lucky_pickaxe', 'double_rewards', 'iron_will']
			],
			'buff_value' => ['type' => self::FLOAT, 'default' => 0],
			'started_date' => ['type' => self::UINT, 'default' => \XF::$time],
			'expires_date' => ['type' => self::UINT, 'required' => true],
			'is_active' => ['type' => self::BOOL, 'default' => true]
		];
		
		$structure->relations = [
			'User' => [
				'entity' => 'XF:User',
				'type' => self::TO_ONE,
				'conditions' => 'user_id',
				'primary' => true
			]
		];
		
		$structure->getters = [
			'time_remaining' => true,
			'is_expired' => true
		];
		
		return $structure;
	}
	
	/**
	 * Get time remaining in seconds
	 */
	public function getTimeRemaining()
	{
		$remaining = $this->expires_date - \XF::$time;
		return max(0, $remaining);
	}
	
	/**
	 * Check if buff has expired
	 */
	public function getIsExpired()
	{
		return \XF::$time >= $this->expires_date;
	}
	
	/**
	 * Get buff name
	 */
	public function getBuffName()
	{
		switch ($this->buff_type)
		{
			case 'lucky_pickaxe':
				return 'Lucky Pickaxe';
			case 'double_rewards':
				return 'Double Rewards';
			case 'iron_will':
				return 'Iron Will';
			default:
				return 'Unknown Buff';
		}
	}
	
	/**
	 * Get buff description
	 */
	public function getBuffDescription()
	{
		switch ($this->buff_type)
		{
			case 'lucky_pickaxe':
				return '+10% chance to find Jackpot blocks';
			case 'double_rewards':
				return '2x BTC rewards from all blocks';
			case 'iron_will':
				return 'No durability loss from Collapse blocks';
			default:
				return '';
		}
	}
	
	/**
	 * Get buff icon
	 */
	public function getBuffIcon()
	{
		switch ($this->buff_type)
		{
			case 'lucky_pickaxe':
				return '🍀';
			case 'double_rewards':
				return '💰';
			case 'iron_will':
				return '🛡️';
			default:
				return '✨';
		}
	}
}
