<?php

namespace IC\CryptoMining\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int $leaderboard_id
 * @property int $user_id
 * @property string $leaderboard_type
 * @property int $rank_position
 * @property float $stat_value
 * @property int $last_updated
 * 
 * RELATIONS
 * @property \XF\Entity\User $User
 */
class Leaderboard extends Entity
{
	public static function getStructure(Structure $structure)
	{
		$structure->table = 'xf_ic_crypto_leaderboard';
		$structure->shortName = 'IC\CryptoMining:Leaderboard';
		$structure->primaryKey = 'leaderboard_id';
		
		$structure->columns = [
			'leaderboard_id' => ['type' => self::UINT, 'autoIncrement' => true],
			'user_id' => ['type' => self::UINT, 'required' => true],
			'leaderboard_type' => ['type' => self::STR, 'maxLength' => 50, 'required' => true],
			'rank_position' => ['type' => self::UINT, 'required' => true],
			'stat_value' => ['type' => self::FLOAT, 'required' => true],
			'last_updated' => ['type' => self::UINT, 'required' => true]
		];
		
		$structure->getters = [];
		
		$structure->relations = [
			'User' => [
				'entity' => 'XF:User',
				'type' => self::TO_ONE,
				'conditions' => 'user_id',
				'primary' => true
			]
		];
		
		return $structure;
	}
}
