<?php

namespace IC\CryptoMining\Repository;

use XF\Mvc\Entity\Repository;

/**
 * Repository for managing user crypto wallets
 */
class Wallet extends Repository
{
	/**
	 * Get user's wallet (create if doesn't exist)
	 */
	public function getOrCreateWallet(int $userId): \IC\CryptoMining\Entity\Wallet
	{
		$wallet = $this->getWallet($userId);
		
		if (!$wallet)
		{
			$wallet = $this->createWallet($userId);
			$wallet->save();
		}
		
		return $wallet;
	}
	
	/**
	 * Get user's wallet
	 */
	public function getWallet(int $userId): ?\IC\CryptoMining\Entity\Wallet
	{
		return $this->finder('IC\CryptoMining:Wallet')
			->where('user_id', $userId)
			->fetchOne();
	}
	
	/**
	 * Create new wallet for user
	 */
	public function createWallet(int $userId): \IC\CryptoMining\Entity\Wallet
	{
		/** @var \IC\CryptoMining\Entity\Wallet $wallet */
		$wallet = $this->em->create('IC\CryptoMining:Wallet');
		
		$wallet->user_id = $userId;
		$wallet->created_date = \XF::$time;
		
		return $wallet;
	}
	
	/**
	 * Add crypto to user's wallet
	 */
	public function addCrypto(int $userId, float $amount, string $source = 'mining'): bool
	{
		$wallet = $this->getOrCreateWallet($userId);
		$wallet->addCrypto($amount, $source);
		
		return $wallet->save();
	}
	
	/**
	 * Remove crypto from user's wallet
	 */
	public function removeCrypto(int $userId, float $amount): bool
	{
		$wallet = $this->getWallet($userId);
		
		if (!$wallet)
		{
			return false;
		}
		
		if (!$wallet->removeCrypto($amount))
		{
			return false;
		}
		
		return $wallet->save();
	}
	
	/**
	 * Record credits spent
	 */
	public function recordCreditsSpent(int $userId, int $amount): bool
	{
		$wallet = $this->getOrCreateWallet($userId);
		$wallet->credits_spent += $amount;
		
		return $wallet->save();
	}
	
	/**
	 * Record credits earned
	 */
	public function recordCreditsEarned(int $userId, int $amount): bool
	{
		$wallet = $this->getOrCreateWallet($userId);
		$wallet->credits_earned += $amount;
		
		return $wallet->save();
	}
	
	/**
	 * Get richest users (for leaderboard)
	 */
	public function getRichestUsers(int $limit = 100): \XF\Mvc\Entity\ArrayCollection
	{
		return $this->finder('IC\CryptoMining:Wallet')
			->with('User')
			->where('crypto_balance', '>', 0)
			->order('crypto_balance', 'DESC')
			->limit($limit)
			->fetch();
	}
	
	/**
	 * Get users who've mined the most (lifetime)
	 */
	public function getTopMiners(int $limit = 100): \XF\Mvc\Entity\ArrayCollection
	{
		return $this->finder('IC\CryptoMining:Wallet')
			->with('User')
			->where('total_mined', '>', 0)
			->order('total_mined', 'DESC')
			->limit($limit)
			->fetch();
	}
}
