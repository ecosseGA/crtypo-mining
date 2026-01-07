<?php

namespace IC\CryptoMining\Repository;

use XF\Mvc\Entity\Repository;

/**
 * UserBuff Repository
 * Manages user buffs (Lucky Pickaxe, etc.)
 */
class UserBuff extends Repository
{
	/**
	 * Get all active buffs for a user
	 */
	public function getActiveBuffs($userId)
	{
		return $this->finder('IC\CryptoMining:UserBuff')
			->where('user_id', $userId)
			->where('is_active', 1)
			->where('expires_date', '>', \XF::$time)
			->fetch();
	}
	
	/**
	 * Get specific active buff for a user
	 */
	public function getActiveBuff($userId, $buffType)
	{
		return $this->finder('IC\CryptoMining:UserBuff')
			->where('user_id', $userId)
			->where('buff_type', $buffType)
			->where('is_active', 1)
			->where('expires_date', '>', \XF::$time)
			->fetchOne();
	}
	
	/**
	 * Create a new buff for user
	 */
	public function createBuff($userId, $buffType, $buffValue, $durationSeconds)
	{
		$buff = $this->em()->create('IC\CryptoMining:UserBuff');
		$buff->user_id = $userId;
		$buff->buff_type = $buffType;
		$buff->buff_value = $buffValue;
		$buff->started_date = \XF::$time;
		$buff->expires_date = \XF::$time + $durationSeconds;
		$buff->is_active = true;
		$buff->save();
		
		return $buff;
	}
	
	/**
	 * Expire old buffs (called by cron)
	 */
	public function expireOldBuffs()
	{
		$db = $this->db();
		
		$db->update('xf_ic_crypto_user_buffs', [
			'is_active' => 0
		], 'expires_date < ? AND is_active = 1', \XF::$time);
		
		return true;
	}
	
	/**
	 * Check if user has specific buff active
	 */
	public function hasBuff($userId, $buffType)
	{
		return $this->getActiveBuff($userId, $buffType) !== null;
	}
}
