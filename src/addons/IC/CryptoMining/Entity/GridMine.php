<?php

namespace IC\CryptoMining\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int mine_id
 * @property int user_id
 * @property int rig_id
 * @property int generation_id
 * @property int position
 * @property string block_type
 * @property float btc_earned
 * @property float durability_lost
 * @property int credits_spent
 * @property bool used_scout
 * @property bool used_insurance
 * @property int mined_date
 * 
 * RELATIONS
 * @property \XF\Entity\User User
 * @property \IC\CryptoMining\Entity\UserRig Rig
 * @property \IC\CryptoMining\Entity\GridGeneration Generation
 */
class GridMine extends Entity
{
	public static function getStructure(Structure $structure)
	{
		$structure->table = 'xf_ic_crypto_grid_mines';
		$structure->shortName = 'IC\CryptoMining:GridMine';
		$structure->primaryKey = 'mine_id';
		
		$structure->columns = [
			'mine_id' => ['type' => self::UINT, 'autoIncrement' => true],
			'user_id' => ['type' => self::UINT, 'required' => true],
			'rig_id' => ['type' => self::UINT, 'required' => true],
			'generation_id' => ['type' => self::UINT, 'required' => true],
			'position' => ['type' => self::UINT, 'required' => true],
			'block_type' => [
				'type' => self::STR,
				'allowedValues' => ['jackpot', 'rich_vein', 'standard', 'weak_vein', 'collapse']
			],
			'btc_earned' => ['type' => self::FLOAT, 'default' => 0],
			'durability_lost' => ['type' => self::FLOAT, 'default' => 0],
			'credits_spent' => ['type' => self::UINT, 'default' => 100],
			'used_scout' => ['type' => self::BOOL, 'default' => false],
			'used_insurance' => ['type' => self::BOOL, 'default' => false],
			'mined_date' => ['type' => self::UINT, 'default' => \XF::$time]
		];
		
		$structure->relations = [
			'User' => [
				'entity' => 'XF:User',
				'type' => self::TO_ONE,
				'conditions' => 'user_id',
				'primary' => true
			],
			'Rig' => [
				'entity' => 'IC\CryptoMining:UserRig',
				'type' => self::TO_ONE,
				'conditions' => 'rig_id',
				'primary' => true
			],
			'Generation' => [
				'entity' => 'IC\CryptoMining:GridGeneration',
				'type' => self::TO_ONE,
				'conditions' => 'generation_id',
				'primary' => true
			]
		];
		
		return $structure;
	}
	
	/**
	 * Was this a successful mine?
	 */
	public function wasSuccessful()
	{
		return in_array($this->block_type, ['jackpot', 'rich_vein', 'standard']);
	}
	
	/**
	 * Get profit (BTC value - costs)
	 */
	public function getProfit()
	{
		// Convert credits spent to rough BTC equivalent for display
		$market = \XF::repository('IC\CryptoMining:Market');
		$btcPrice = $market->getCurrentPrice();
		$creditValueInBTC = $btcPrice > 0 ? ($this->credits_spent / $btcPrice) : 0;
		
		return $this->btc_earned - $creditValueInBTC;
	}
}
