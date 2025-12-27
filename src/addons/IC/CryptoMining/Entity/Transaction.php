<?php

namespace IC\CryptoMining\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int $transaction_id
 * @property int $user_id
 * @property string $transaction_type
 * @property float|null $amount
 * @property int|null $credits
 * @property float|null $price_per_unit
 * @property string $description
 * @property int $transaction_date
 * @property int|null $related_user_id
 * @property int|null $rig_type_id
 * 
 * RELATIONS
 * @property \XF\Entity\User $User
 */
class Transaction extends Entity
{
	public static function getStructure(Structure $structure)
	{
		$structure->table = 'xf_ic_crypto_transactions';
		$structure->shortName = 'IC\CryptoMining:Transaction';
		$structure->primaryKey = 'transaction_id';
		
		$structure->columns = [
			'transaction_id' => ['type' => self::UINT, 'autoIncrement' => true],
			'user_id' => ['type' => self::UINT, 'required' => true],
			'transaction_type' => ['type' => self::STR, 'maxLength' => 50, 'required' => true],
			'amount' => ['type' => self::FLOAT, 'nullable' => true, 'default' => null],
			'credits' => ['type' => self::INT, 'nullable' => true, 'default' => null],
			'price_per_unit' => ['type' => self::FLOAT, 'nullable' => true, 'default' => null],
			'description' => ['type' => self::STR, 'default' => ''],
			'transaction_date' => ['type' => self::UINT, 'required' => true],
			'related_user_id' => ['type' => self::UINT, 'nullable' => true, 'default' => null],
			'rig_type_id' => ['type' => self::UINT, 'nullable' => true, 'default' => null]
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
