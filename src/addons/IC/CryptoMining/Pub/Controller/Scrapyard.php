<?php

namespace IC\CryptoMining\Pub\Controller;

use XF\Pub\Controller\AbstractController;
use XF\Mvc\ParameterBag;

/**
 * Scrapyard Controller - Sell old/broken rigs for partial refund
 */
class Scrapyard extends AbstractController
{
	/**
	 * Display scrapyard page with user's scrapable rigs
	 */
	public function actionIndex()
	{
		// Check permission
		if (!\XF::visitor()->hasPermission('icCryptoMining', 'view'))
		{
			return $this->noPermission();
		}
		
		$visitor = \XF::visitor();
		
		// Get user's rigs
		$rigRepo = $this->repository('IC\CryptoMining:UserRig');
		$allRigs = $rigRepo->getUserRigs($visitor->user_id);
		
		// Separate scrapable rigs (durability <= 20%) from others
		$scrapableRigs = [];
		$activeRigs = [];
		$scrapValues = []; // Store scrap values separately
		
		foreach ($allRigs as $rig)
		{
			if ($rig->current_durability <= 20)
			{
				$scrapableRigs[] = $rig;
				$scrapValues[$rig->user_rig_id] = $this->calculateScrapValue($rig);
			}
			else
			{
				$activeRigs[] = $rig;
			}
		}
		
		// Get wallet for cash balance display
		$walletRepo = $this->repository('IC\CryptoMining:Wallet');
		$wallet = $walletRepo->getOrCreateWallet($visitor->user_id);
		
		$viewParams = [
			'scrapableRigs' => $scrapableRigs,
			'activeRigs' => $activeRigs,
			'scrapValues' => $scrapValues,
			'wallet' => $wallet,
			'activeNav' => 'crypto-scrapyard'
		];
		
		return $this->view('IC\CryptoMining:Scrapyard\Index', 'ic_crypto_scrapyard', $viewParams);
	}
	
	/**
	 * Scrap a rig (sell for parts)
	 */
	public function actionScrap(ParameterBag $params)
	{
		$this->assertPostOnly();
		
		// Check permission
		if (!\XF::visitor()->hasPermission('icCryptoMining', 'view'))
		{
			return $this->noPermission();
		}
		
		$rigId = $this->filter('rig_id', 'uint');
		$visitor = \XF::visitor();
		
		// Get the rig
		$rig = $this->assertRecordExists('IC\CryptoMining:UserRig', $rigId);
		
		// Verify ownership
		if ($rig->user_id != $visitor->user_id)
		{
			return $this->error('You do not own this rig.');
		}
		
		// Verify durability is low enough to scrap
		if ($rig->current_durability > 20)
		{
			return $this->error('This rig is still too functional to scrap. Durability must be 20% or below.');
		}
		
		// Calculate scrap value
		$scrapValue = $this->calculateScrapValue($rig);
		
		// Get wallet
		$walletRepo = $this->repository('IC\CryptoMining:Wallet');
		$wallet = $walletRepo->getOrCreateWallet($visitor->user_id);
		
		// Add credits to wallet
		$wallet->cash_balance += $scrapValue;
		if (!$wallet->save())
		{
			throw new \Exception('Failed to update wallet: ' . print_r($wallet->getErrors(), true));
		}
		
		// Log transaction
		/** @var \IC\CryptoMining\Entity\Transaction $transaction */
		$transaction = $this->em()->create('IC\CryptoMining:Transaction');
		$transaction->user_id = $visitor->user_id;
		$transaction->transaction_type = 'scrap_rig';
		$transaction->credits = $scrapValue;
		$transaction->rig_type_id = $rig->rig_type_id;
		$transaction->description = sprintf(
			'Scrapped %s (Durability: %.1f%%, Level: %d)',
			$rig->RigType->rig_name,
			$rig->current_durability,
			$rig->upgrade_level
		);
		$transaction->transaction_date = \XF::$time;
		$transaction->save();
		
		// Check achievement progress
		$this->checkScrapAchievements($visitor);
		
		// Delete the rig
		$rigName = $rig->RigType->rig_name;
		$rig->delete();
		
		return $this->redirect(
			$this->buildLink('crypto-scrapyard'),
			\XF::phrase('ic_crypto_rig_scrapped', [
				'rig_name' => $rigName,
				'credits' => number_format($scrapValue)
			])
		);
	}
	
	/**
	 * Calculate scrap value for a rig
	 * 
	 * Formula:
	 * Base: 20% of original purchase price
	 * Durability bonus: 0-20% durability adds 0-20% more
	 * Upgrade bonus: Each upgrade level adds 5%
	 * 
	 * @param \IC\CryptoMining\Entity\UserRig $rig
	 * @return int Credits to refund
	 */
	protected function calculateScrapValue($rig)
	{
		$originalPrice = $rig->purchase_price;
		
		// Base scrap value: 20% of purchase price
		$baseValue = $originalPrice * 0.20;
		
		// Durability bonus: 0-20% durability = 0-20% additional value
		// Example: 15% durability = 15% bonus, 5% durability = 5% bonus
		$durabilityBonus = ($rig->current_durability / 20) * 0.20 * $originalPrice;
		
		// Upgrade bonus: Each level adds 5% of original price
		$upgradeBonus = $rig->upgrade_level * 0.05 * $originalPrice;
		
		// Total scrap value
		$totalValue = $baseValue + $durabilityBonus + $upgradeBonus;
		
		// Round to whole number
		return (int)ceil($totalValue);
	}
	
	/**
	 * Check and award scrap-related achievements
	 */
	protected function checkScrapAchievements($user)
	{
		// Count total scrapped rigs
		$db = \XF::db();
		$totalScrapped = $db->fetchOne("
			SELECT COUNT(*)
			FROM xf_ic_crypto_transactions
			WHERE user_id = ?
			AND transaction_type = 'scrap_rig'
		", $user->user_id);
		
		// Check achievements
		$achievementService = $this->service('IC\CryptoMining:Achievement');
		
		// "Recycler" achievement - Scrap 5 rigs
		if ($totalScrapped >= 5)
		{
			$achievementService->checkAchievements($user, 'recycler');
		}
		
		// "Scrapyard King" achievement - Scrap 25 rigs
		if ($totalScrapped >= 25)
		{
			$achievementService->checkAchievements($user, 'scrapyard_king');
		}
	}
}
