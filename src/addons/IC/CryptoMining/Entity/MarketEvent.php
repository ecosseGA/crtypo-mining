<?php

namespace IC\CryptoMining\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int $event_id
 * @property string $event_type
 * @property string $event_title
 * @property string $event_description
 * @property float $price_impact_percent
 * @property int $duration_hours
 * @property bool $is_active
 * @property int|null $triggered_date
 * 
 * GETTERS
 * @property-read bool $is_positive
 * @property-read bool $is_negative
 * @property-read int|null $hours_remaining
 */
class MarketEvent extends Entity
{
	/**
	 * Check if event is positive (bull run, halving, etc.)
	 */
	public function getIsPositive(): bool
	{
		return $this->price_impact_percent > 0;
	}
	
	/**
	 * Check if event is negative (crash, regulation, etc.)
	 */
	public function getIsNegative(): bool
	{
		return $this->price_impact_percent < 0;
	}
	
	/**
	 * Get hours remaining in event
	 */
	public function getHoursRemaining(): ?int
	{
		if (!$this->is_active || !$this->triggered_date)
		{
			return null;
		}
		
		$elapsedHours = (\XF::$time - $this->triggered_date) / 3600;
		$remaining = $this->duration_hours - $elapsedHours;
		
		return max(0, (int)ceil($remaining));
	}
	
	public static function getStructure(Structure $structure)
	{
		$structure->table = 'xf_ic_crypto_market_events';
		$structure->shortName = 'IC\CryptoMining:MarketEvent';
		$structure->primaryKey = 'event_id';
		
		$structure->columns = [
			'event_id' => ['type' => self::UINT, 'autoIncrement' => true],
			'event_type' => ['type' => self::STR, 'maxLength' => 50, 'required' => true],
			'event_title' => ['type' => self::STR, 'maxLength' => 200, 'required' => true],
			'event_description' => ['type' => self::STR, 'default' => ''],
			'price_impact_percent' => ['type' => self::FLOAT, 'required' => true],
			'duration_hours' => ['type' => self::UINT, 'default' => 24],
			'is_active' => ['type' => self::BOOL, 'default' => false],
			'triggered_date' => ['type' => self::UINT, 'nullable' => true, 'default' => null]
		];
		
		$structure->getters = [
			'is_positive' => true,
			'is_negative' => true,
			'hours_remaining' => true
		];
		
		$structure->relations = [];
		
		return $structure;
	}
}
