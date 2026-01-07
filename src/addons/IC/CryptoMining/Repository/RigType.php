<?php

namespace IC\CryptoMining\Repository;

use XF\Mvc\Entity\Repository;

/**
 * Repository for managing rig types
 */
class RigType extends Repository
{
	/**
	 * Find all active rig types available for purchase
	 */
	public function findAvailableRigs(\XF\Entity\User $user = null)
	{
		$finder = $this->finder('IC\CryptoMining:RigType')
			->where('is_active', 1)
			->order('sort_order', 'ASC');
		
		return $finder;
	}
	
	/**
	 * Find rigs by tier
	 */
	public function findRigsByTier(int $tier)
	{
		return $this->finder('IC\CryptoMining:RigType')
			->where('tier', $tier)
			->where('is_active', 1)
			->order('sort_order', 'ASC');
	}
	
	/**
	 * Get rig type by ID
	 */
	public function getRigType(int $rigTypeId, array $with = []): ?\IC\CryptoMining\Entity\RigType
	{
		return $this->finder('IC\CryptoMining:RigType')
			->where('rig_type_id', $rigTypeId)
			->with($with)
			->fetchOne();
	}
	
	/**
	 * Get all active rig types grouped by tier
	 */
	public function getRigsByTier(): array
	{
		$rigs = $this->findAvailableRigs()->fetch();
		
		$grouped = [
			1 => [],
			2 => [],
			3 => [],
			4 => []
		];
		
		foreach ($rigs as $rig)
		{
			$grouped[$rig->tier][] = $rig;
		}
		
		return $grouped;
	}
	
	/**
	 * Get tier statistics
	 */
	public function getTierStats(): array
	{
		$rigs = $this->findAvailableRigs()->fetch();
		
		$stats = [];
		
		foreach ([1, 2, 3, 4] as $tier)
		{
			$tierRigs = $rigs->filter(function($rig) use ($tier) {
				return $rig->tier == $tier;
			});
			
			$stats[$tier] = [
				'count' => $tierRigs->count(),
				'min_cost' => $tierRigs->count() ? min(array_column($tierRigs->toArray(), 'base_cost')) : 0,
				'max_cost' => $tierRigs->count() ? max(array_column($tierRigs->toArray(), 'base_cost')) : 0,
				'min_hash_rate' => $tierRigs->count() ? min(array_column($tierRigs->toArray(), 'hash_rate')) : 0,
				'max_hash_rate' => $tierRigs->count() ? max(array_column($tierRigs->toArray(), 'hash_rate')) : 0
			];
		}
		
		return $stats;
	}
}
