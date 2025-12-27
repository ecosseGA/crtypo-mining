<?php

namespace IC\CryptoMining\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int $history_id
 * @property string $crypto_name
 * @property float $price
 * @property int $recorded_date
 */
class MarketHistory extends Entity
{
	public static function getStructure(Structure $structure)
	{
		$structure->table = 'xf_ic_crypto_market_history';
		$structure->shortName = 'IC\CryptoMining:MarketHistory';
		$structure->primaryKey = 'history_id';
		
		$structure->columns = [
			'history_id' => ['type' => self::UINT, 'autoIncrement' => true],
			'crypto_name' => ['type' => self::STR, 'maxLength' => 50, 'default' => 'Bitcoin'],
			'price' => ['type' => self::FLOAT, 'required' => true],
			'recorded_date' => ['type' => self::UINT, 'required' => true]
		];
		
		$structure->getters = [];
		$structure->relations = [];
		
		return $structure;
	}
}
