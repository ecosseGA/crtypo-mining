<?php

namespace IC\CryptoMining\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int $user_rig_id
 * @property int $user_id
 * @property int $rig_type_id
 * @property int $purchase_date
 * @property int $purchase_price
 * @property float $current_durability
 * @property int $upgrade_level
 * @property bool $is_active
 * @property int $last_mined
 * @property float $total_mined
 * @property bool $is_custom
 * @property int|null $custom_build_id
 * 
 * RELATIONS
 * @property \XF\Entity\User $User
 * @property \IC\CryptoMining\Entity\RigType $RigType
 * 
 * GETTERS
 * @property-read float $current_hash_rate
 * @property-read float $durability_penalty
 * @property-read float $effective_hash_rate
 * @property-read float $hourly_power_cost
 * @property-read string $durability_status
 * @property-read bool $needs_repair
 */
class UserRig extends Entity
{
	/**
	 * Get current hash rate (base * upgrade bonus)
	 */
	public function getCurrentHashRate(): float
	{
		$baseRate = $this->RigType->hash_rate;
		$upgradeBonus = 1 + ($this->upgrade_level * 0.10); // 10% per level
		
		return $baseRate * $upgradeBonus;
	}
	
	/**
	 * Get durability penalty multiplier
	 */
	public function getDurabilityPenalty(): float
	{
		if ($this->current_durability >= 50)
		{
			return 1.0; // No penalty
		}
		elseif ($this->current_durability >= 25)
		{
			return 0.75; // 25% reduction
		}
		else
		{
			return 0.50; // 50% reduction
		}
	}
	
	/**
	 * Get effective hash rate (with all modifiers)
	 */
	public function getEffectiveHashRate(): float
	{
		$currentRate = $this->getCurrentHashRate();
		$penalty = $this->getDurabilityPenalty();
		
		return $currentRate * $penalty;
	}
	
	/**
	 * Get hourly power cost in credits
	 */
	public function getHourlyPowerCost(): float
	{
		return $this->RigType->power_consumption / 24;
	}
	
	/**
	 * Get durability status (good/warning/danger)
	 */
	public function getDurabilityStatus(): string
	{
		if ($this->current_durability >= 50)
		{
			return 'good';
		}
		elseif ($this->current_durability >= 25)
		{
			return 'warning';
		}
		else
		{
			return 'danger';
		}
	}
	
	/**
	 * Check if rig needs repair
	 */
	public function getNeedsRepair(): bool
	{
		return $this->current_durability < 50;
	}
	
	/**
	 * Calculate repair cost to restore to target durability
	 */
	public function getRepairCost(float $targetDurability = 100.0): int
	{
		$durabilityToRestore = $targetDurability - $this->current_durability;
		
		if ($durabilityToRestore <= 0)
		{
			return 0;
		}
		
		$repairCost = ($this->RigType->base_cost * 0.10) * ($durabilityToRestore / 100);
		
		return (int)ceil($repairCost);
	}
	
	/**
	 * Calculate upgrade cost for next level
	 */
	public function getUpgradeCost(): int
	{
		if ($this->upgrade_level >= 5)
		{
			return 0; // Max level
		}
		
		$nextLevel = $this->upgrade_level + 1;
		$upgradeCost = ($this->RigType->base_cost * 0.20) * $nextLevel;
		
		return (int)ceil($upgradeCost);
	}
	
	/**
	 * Check if rig can be upgraded
	 */
	public function canUpgrade(&$error = null): bool
	{
		if ($this->upgrade_level >= 5)
		{
			$error = \XF::phrase('ic_crypto_max_upgrade_level');
			return false;
		}
		
		return true;
	}
	
	public static function getStructure(Structure $structure)
	{
		$structure->table = 'xf_ic_crypto_user_rigs';
		$structure->shortName = 'IC\CryptoMining:UserRig';
		$structure->primaryKey = 'user_rig_id';
		
		$structure->columns = [
			'user_rig_id' => ['type' => self::UINT, 'autoIncrement' => true],
			'user_id' => ['type' => self::UINT, 'required' => true],
			'rig_type_id' => ['type' => self::UINT, 'required' => true],
			'purchase_date' => ['type' => self::UINT, 'required' => true],
			'purchase_price' => ['type' => self::UINT, 'required' => true],
			'current_durability' => ['type' => self::FLOAT, 'default' => 100.0],
			'upgrade_level' => ['type' => self::UINT, 'default' => 0],
			'is_active' => ['type' => self::BOOL, 'default' => true],
			'last_mined' => ['type' => self::UINT, 'required' => true],
			'total_mined' => ['type' => self::FLOAT, 'default' => 0.0],
			'is_custom' => ['type' => self::BOOL, 'default' => false],
			'custom_build_id' => ['type' => self::UINT, 'nullable' => true, 'default' => null]
		];
		
		$structure->getters = [
			'current_hash_rate' => true,
			'durability_penalty' => true,
			'effective_hash_rate' => true,
			'hourly_power_cost' => true,
			'durability_status' => true,
			'needs_repair' => true
		];
		
		$structure->relations = [
			'User' => [
				'entity' => 'XF:User',
				'type' => self::TO_ONE,
				'conditions' => 'user_id',
				'primary' => true
			],
			'RigType' => [
				'entity' => 'IC\CryptoMining:RigType',
				'type' => self::TO_ONE,
				'conditions' => 'rig_type_id',
				'primary' => true
			]
		];
		
		return $structure;
	}
}
