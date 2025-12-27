<?php

namespace IC\CryptoMining\Repository;

use XF\Mvc\Entity\Repository;

/**
 * Repository for managing user-owned rigs
 */
class UserRig extends Repository
{
	/**
	 * Find all rigs owned by a user
	 */
	public function findUserRigs(int $userId)
	{
		return $this->finder('IC\CryptoMining:UserRig')
			->where('user_id', $userId)
			->with('RigType')
			->order('purchase_date', 'DESC');
	}
	
	/**
	 * Find active rigs (mining enabled, durability > 0)
	 */
	public function findActiveRigs(int $userId)
	{
		return $this->finder('IC\CryptoMining:UserRig')
			->where('user_id', $userId)
			->where('is_active', 1)
			->where('current_durability', '>', 0)
			->with('RigType')
			->order('purchase_date', 'DESC');
	}
	
	/**
	 * Get user's rigs
	 */
	public function getUserRigs(int $userId): \XF\Mvc\Entity\ArrayCollection
	{
		return $this->findUserRigs($userId)->fetch();
	}
	
	/**
	 * Get active rigs
	 */
	public function getActiveRigs(int $userId): \XF\Mvc\Entity\ArrayCollection
	{
		return $this->findActiveRigs($userId)->fetch();
	}
	
	/**
	 * Get user rig by ID
	 */
	public function getUserRig(int $userRigId, array $with = []): ?\IC\CryptoMining\Entity\UserRig
	{
		return $this->finder('IC\CryptoMining:UserRig')
			->where('user_rig_id', $userRigId)
			->with(array_merge(['RigType', 'User'], $with))
			->fetchOne();
	}
	
	/**
	 * Calculate total daily output for user's active rigs
	 */
	public function getTotalDailyOutput(int $userId): float
	{
		$rigs = $this->getActiveRigs($userId);
		$totalOutput = 0;
		
		foreach ($rigs as $rig)
		{
			$totalOutput += $rig->effective_hash_rate * 24; // BTC per day
		}
		
		return $totalOutput;
	}
	
	/**
	 * Calculate total daily power cost for user's active rigs
	 */
	public function getTotalDailyPowerCost(int $userId): float
	{
		$rigs = $this->getActiveRigs($userId);
		$totalCost = 0;
		
		foreach ($rigs as $rig)
		{
			$totalCost += $rig->RigType->power_consumption;
		}
		
		return $totalCost;
	}
	
	/**
	 * Calculate net daily profit for user (earnings - power costs)
	 */
	public function getNetDailyProfit(int $userId): float
	{
		/** @var \IC\CryptoMining\Repository\Market $marketRepo */
		$marketRepo = $this->repository('IC\CryptoMining:Market');
		$currentPrice = $marketRepo->getCurrentPrice();
		
		$dailyOutput = $this->getTotalDailyOutput($userId);
		$dailyEarnings = $dailyOutput * $currentPrice;
		$dailyCost = $this->getTotalDailyPowerCost($userId);
		
		return $dailyEarnings - $dailyCost;
	}
	
	/**
	 * Get user's rig statistics
	 */
	public function getUserStats(int $userId): array
	{
		$allRigs = $this->getUserRigs($userId);
		$activeRigs = $this->getActiveRigs($userId);
		
		$totalMined = 0;
		$avgDurability = 0;
		
		foreach ($allRigs as $rig)
		{
			$totalMined += $rig->total_mined;
			$avgDurability += $rig->current_durability;
		}
		
		if ($allRigs->count() > 0)
		{
			$avgDurability = $avgDurability / $allRigs->count();
		}
		
		return [
			'total_rigs' => $allRigs->count(),
			'active_rigs' => $activeRigs->count(),
			'inactive_rigs' => $allRigs->count() - $activeRigs->count(),
			'total_mined' => $totalMined,
			'avg_durability' => $avgDurability,
			'daily_output' => $this->getTotalDailyOutput($userId),
			'daily_profit' => $this->getNetDailyProfit($userId)
		];
	}
	
	/**
	 * Find rigs needing repair (durability < 50%)
	 */
	public function findRigsNeedingRepair(int $userId)
	{
		return $this->finder('IC\CryptoMining:UserRig')
			->where('user_id', $userId)
			->where('current_durability', '<', 50)
			->with('RigType')
			->order('current_durability', 'ASC');
	}
	
	/**
	 * Purchase a new rig for user
	 */
	public function purchaseRig(\XF\Entity\User $user, \IC\CryptoMining\Entity\RigType $rigType): \IC\CryptoMining\Entity\UserRig
	{
		/** @var \IC\CryptoMining\Entity\UserRig $userRig */
		$userRig = $this->em->create('IC\CryptoMining:UserRig');
		
		$userRig->user_id = $user->user_id;
		$userRig->rig_type_id = $rigType->rig_type_id;
		$userRig->purchase_date = \XF::$time;
		$userRig->purchase_price = $rigType->base_cost;
		$userRig->current_durability = $rigType->durability_max;
		$userRig->last_mined = \XF::$time;
		
		return $userRig;
	}
}
