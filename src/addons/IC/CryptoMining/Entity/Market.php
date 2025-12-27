<?php

namespace IC\CryptoMining\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int $market_id
 * @property string $crypto_name
 * @property float $current_price
 * @property float|null $previous_price
 * @property float|null $price_change_percent
 * @property float $daily_volume
 * @property float|null $market_cap
 * @property int $last_updated
 * 
 * GETTERS
 * @property-read bool $is_up
 * @property-read bool $is_down
 * @property-read string $trend_direction
 */
class Market extends Entity
{
	/**
	 * Check if price is up
	 */
	public function getIsUp(): bool
	{
		return $this->price_change_percent > 0;
	}
	
	/**
	 * Check if price is down
	 */
	public function getIsDown(): bool
	{
		return $this->price_change_percent < 0;
	}
	
	/**
	 * Get trend direction (up/down/neutral)
	 */
	public function getTrendDirection(): string
	{
		if ($this->price_change_percent > 0)
		{
			return 'up';
		}
		elseif ($this->price_change_percent < 0)
		{
			return 'down';
		}
		else
		{
			return 'neutral';
		}
	}
	
	public static function getStructure(Structure $structure)
	{
		$structure->table = 'xf_ic_crypto_market';
		$structure->shortName = 'IC\CryptoMining:Market';
		$structure->primaryKey = 'market_id';
		
		$structure->columns = [
			'market_id' => ['type' => self::UINT, 'autoIncrement' => true],
			'crypto_name' => ['type' => self::STR, 'maxLength' => 50, 'default' => 'Bitcoin'],
			'current_price' => ['type' => self::FLOAT, 'required' => true],
			'previous_price' => ['type' => self::FLOAT, 'nullable' => true, 'default' => null],
			'price_change_percent' => ['type' => self::FLOAT, 'nullable' => true, 'default' => null],
			'daily_volume' => ['type' => self::FLOAT, 'default' => 0.0],
			'market_cap' => ['type' => self::FLOAT, 'nullable' => true, 'default' => null],
			'last_updated' => ['type' => self::UINT, 'required' => true]
		];
		
		$structure->getters = [
			'is_up' => true,
			'is_down' => true,
			'trend_direction' => true
		];
		
		$structure->relations = [];
		
		return $structure;
	}
}
