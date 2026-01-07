<?php

namespace IC\CryptoMining\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int $rig_type_id
 * @property string $rig_name
 * @property string $rig_description
 * @property int $tier
 * @property float $hash_rate
 * @property float $power_consumption
 * @property int $base_cost
 * @property int $durability_max
 * @property int $efficiency_rating
 * @property int $required_level
 * @property string $image_url
 * @property bool $is_active
 * @property int $sort_order
 * 
 * GETTERS
 * @property-read string $tier_name
 * @property-read float $daily_output
 * @property-read float $daily_profit_base
 * @property-read int $roi_days
 */
class RigType extends Entity
{
	/**
	 * Get tier name (Budget, Consumer, Professional, Elite)
	 */
	public function getTierName(): string
	{
		$tiers = [
			1 => 'Budget',
			2 => 'Consumer', 
			3 => 'Professional',
			4 => 'Elite'
		];
		
		return $tiers[$this->tier] ?? 'Unknown';
	}
	
	/**
	 * Get daily BTC output (hash_rate * 24 hours)
	 */
	public function getDailyOutput(): float
	{
		return $this->hash_rate * 24;
	}
	
	/**
	 * Get base daily profit (before durability/upgrades)
	 * Calculated at current market price
	 */
	public function getDailyProfitBase(): float
	{
		/** @var \IC\CryptoMining\Repository\Market $marketRepo */
		$marketRepo = \XF::repository('IC\CryptoMining:Market');
		$currentPrice = $marketRepo->getCurrentPrice();
		
		$dailyEarnings = $this->getDailyOutput() * $currentPrice;
		$dailyCost = $this->power_consumption;
		
		return $dailyEarnings - $dailyCost;
	}
	
	/**
	 * Get ROI in days (at current market price)
	 */
	public function getRoiDays(): int
	{
		$dailyProfit = $this->getDailyProfitBase();
		
		if ($dailyProfit <= 0)
		{
			return 999; // Never profitable
		}
		
		return (int)ceil($this->base_cost / $dailyProfit);
	}
	
	/**
	 * Check if user can purchase this rig type
	 * 
	 * @param User $user The user attempting purchase
	 * @param Wallet|null $wallet The user's wallet
	 * @param string|null $error Error message if cannot purchase
	 * @return bool
	 */
	public function canPurchase(\XF\Entity\User $user, $wallet = null, &$error = null): bool
	{
		// Check if rig is active
		if (!$this->is_active)
		{
			$error = \XF::phrase('ic_crypto_rig_not_available');
			return false;
		}
		
		// Check wallet cash balance
		if (!$wallet || $wallet->cash_balance < $this->base_cost)
		{
			$error = \XF::phrase('ic_crypto_insufficient_credits');
			return false;
		}
		
		return true;
	}
	
	public static function getStructure(Structure $structure)
	{
		$structure->table = 'xf_ic_crypto_rig_types';
		$structure->shortName = 'IC\CryptoMining:RigType';
		$structure->primaryKey = 'rig_type_id';
		
		$structure->columns = [
			'rig_type_id' => ['type' => self::UINT, 'autoIncrement' => true],
			'rig_name' => ['type' => self::STR, 'maxLength' => 100, 'required' => true],
			'rig_description' => ['type' => self::STR, 'default' => ''],
			'tier' => ['type' => self::UINT, 'default' => 1],
			'hash_rate' => ['type' => self::FLOAT, 'required' => true],
			'power_consumption' => ['type' => self::FLOAT, 'required' => true],
			'base_cost' => ['type' => self::UINT, 'required' => true],
			'durability_max' => ['type' => self::UINT, 'default' => 100],
			'efficiency_rating' => ['type' => self::UINT, 'default' => 100],
			'required_level' => ['type' => self::UINT, 'default' => 1],
			'image_url' => ['type' => self::STR, 'default' => '', 'maxLength' => 255],
			'is_active' => ['type' => self::BOOL, 'default' => true],
			'sort_order' => ['type' => self::UINT, 'default' => 0]
		];
		
		$structure->getters = [
			'tier_name' => true,
			'daily_output' => true,
			'daily_profit_base' => true,
			'roi_days' => true
		];
		
		$structure->relations = [];
		
		return $structure;
	}
}
