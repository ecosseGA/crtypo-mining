<?php

namespace IC\CryptoMining\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int $wallet_id
 * @property int $user_id
 * @property float $crypto_balance
 * @property float $total_mined
 * @property float $total_sold
 * @property float $total_bought
 * @property int $credits_earned
 * @property int $credits_spent
 * @property int|null $last_mining_payout
 * @property int $created_date
 * 
 * RELATIONS
 * @property \XF\Entity\User $User
 * 
 * GETTERS
 * @property-read float $balance_usd
 * @property-read float $net_credits
 * @property-read float $net_crypto
 */
class Wallet extends Entity
{
	/**
	 * Get wallet balance in USD (at current market price)
	 */
	public function getBalanceUsd(): float
	{
		/** @var \IC\CryptoMining\Repository\Market $marketRepo */
		$marketRepo = \XF::repository('IC\CryptoMining:Market');
		$currentPrice = $marketRepo->getCurrentPrice();
		
		return $this->crypto_balance * $currentPrice;
	}
	
	/**
	 * Get net credits (earned - spent)
	 */
	public function getNetCredits(): int
	{
		return $this->credits_earned - $this->credits_spent;
	}
	
	/**
	 * Get net crypto (mined + bought - sold)
	 */
	public function getNetCrypto(): float
	{
		return $this->total_mined + $this->total_bought - $this->total_sold;
	}
	
	/**
	 * Add crypto to wallet
	 */
	public function addCrypto(float $amount, string $source = 'mining'): void
	{
		$this->crypto_balance += $amount;
		
		if ($source === 'mining')
		{
			$this->total_mined += $amount;
			$this->last_mining_payout = \XF::$time;
		}
		elseif ($source === 'purchase')
		{
			$this->total_bought += $amount;
		}
	}
	
	/**
	 * Remove crypto from wallet
	 */
	public function removeCrypto(float $amount): bool
	{
		if ($this->crypto_balance < $amount)
		{
			return false;
		}
		
		$this->crypto_balance -= $amount;
		$this->total_sold += $amount;
		
		return true;
	}
	
	/**
	 * Check if wallet has sufficient balance
	 */
	public function hasSufficientBalance(float $amount): bool
	{
		return $this->crypto_balance >= $amount;
	}
	
	public static function getStructure(Structure $structure)
	{
		$structure->table = 'xf_ic_crypto_wallet';
		$structure->shortName = 'IC\CryptoMining:Wallet';
		$structure->primaryKey = 'wallet_id';
		
		$structure->columns = [
			'wallet_id' => ['type' => self::UINT, 'autoIncrement' => true],
			'user_id' => ['type' => self::UINT, 'required' => true],
			'cash_balance' => ['type' => self::UINT, 'default' => 10000], // Starting cash for purchases
			'crypto_balance' => ['type' => self::FLOAT, 'default' => 0.0],
			'total_mined' => ['type' => self::FLOAT, 'default' => 0.0],
			'total_sold' => ['type' => self::FLOAT, 'default' => 0.0],
			'total_bought' => ['type' => self::FLOAT, 'default' => 0.0],
			'credits_earned' => ['type' => self::UINT, 'default' => 0],
			'credits_spent' => ['type' => self::UINT, 'default' => 0],
			'last_mining_payout' => ['type' => self::UINT, 'nullable' => true, 'default' => null],
			'created_date' => ['type' => self::UINT, 'required' => true]
		];
		
		$structure->getters = [
			'balance_usd' => true,
			'net_credits' => true,
			'net_crypto' => true
		];
		
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
