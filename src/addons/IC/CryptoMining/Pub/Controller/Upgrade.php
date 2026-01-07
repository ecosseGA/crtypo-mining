<?php

namespace IC\CryptoMining\Pub\Controller;

use XF\Mvc\ParameterBag;

/**
 * Upgrade Controller
 * Handles rig upgrade functionality
 */
class Upgrade extends \XF\Pub\Controller\AbstractController
{
	/**
	 * Show upgrade confirmation and process upgrade
	 */
	public function actionIndex(ParameterBag $params)
	{
		// Get the rig and verify ownership
		$rig = $this->assertUserRigExists($params->user_rig_id);
		
		if ($rig->user_id !== \XF::visitor()->user_id)
		{
			return $this->noPermission();
		}
		
		// Can't upgrade if already at max level (5)
		if ($rig->upgrade_level >= 5)
		{
			return $this->error('This rig is already at maximum upgrade level (5).');
		}
		
		// Calculate upgrade cost and benefits
		$currentLevel = $rig->upgrade_level;
		$nextLevel = $currentLevel + 1;
		$baseCost = $rig->RigType->base_cost;
		
		// Cost formula: (Base Cost * 0.20) * Next Level
		// Level 1 = 20%, Level 2 = 40%, Level 3 = 60%, Level 4 = 80%, Level 5 = 100%
		$upgradeCost = ceil(($baseCost * 0.20) * $nextLevel);
		
		// Calculate hash rate improvements
		$baseHashRate = $rig->RigType->hash_rate;
		$currentBonus = $currentLevel * 10; // Current bonus percentage
		$nextBonus = $nextLevel * 10; // Next level bonus percentage
		$currentHashRate = $baseHashRate * (1 + ($currentLevel * 0.10));
		$nextHashRate = $baseHashRate * (1 + ($nextLevel * 0.10));
		$hashRateIncrease = $nextHashRate - $currentHashRate;
		
		// Show confirmation form
		if (!$this->isPost())
		{
			$viewParams = [
				'rig' => $rig,
				'upgradeCost' => $upgradeCost,
				'currentLevel' => $currentLevel,
				'nextLevel' => $nextLevel,
				'currentBonus' => $currentBonus,
				'nextBonus' => $nextBonus,
				'baseHashRate' => $baseHashRate,
				'currentHashRate' => $currentHashRate,
				'nextHashRate' => $nextHashRate,
				'hashRateIncrease' => $hashRateIncrease
			];
			return $this->view('IC\CryptoMining:Upgrade', 'ic_crypto_upgrade', $viewParams);
		}
		
		// Process upgrade
		$visitor = \XF::visitor();
		$walletRepo = $this->repository('IC\CryptoMining:Wallet');
		$wallet = $walletRepo->getOrCreateWallet($visitor->user_id);
		
		// Check if user has enough credits
		if ($wallet->cash_balance < $upgradeCost)
		{
			return $this->error('You need ' . number_format($upgradeCost) . ' credits to upgrade this rig. You only have ' . number_format($wallet->cash_balance) . ' credits.');
		}
		
		// Deduct credits from wallet
		$wallet->cash_balance -= $upgradeCost;
		$wallet->credits_spent += $upgradeCost;
		$wallet->save();
		
		// Upgrade rig to next level
		$rig->upgrade_level = $nextLevel;
		$rig->save();
		
		// Log transaction
		$transaction = $this->em()->create('IC\CryptoMining:Transaction');
		$transaction->user_id = $visitor->user_id;
		$transaction->transaction_type = 'upgrade';
		$transaction->credits = -$upgradeCost;
		$transaction->rig_type_id = $rig->rig_type_id;
		$transaction->description = sprintf(
			'Upgraded %s from Level %d to Level %d (+%d%% hash rate)',
			$rig->RigType->rig_name,
			$currentLevel,
			$nextLevel,
			$nextBonus
		);
		$transaction->transaction_date = \XF::$time;
		$transaction->save();
		
		// Check achievements (tech_upgrade when reaching level 5)
		/** @var \IC\CryptoMining\Service\Achievement $achievementService */
		$achievementService = $this->service('IC\CryptoMining:Achievement');
		$achievementService->checkAchievements(\XF::visitor(), 'rig_upgraded', [
			'new_level' => $nextLevel
		]);
		
		return $this->redirect(
			$this->buildLink('crypto-mining'),
			sprintf('Upgraded %s to Level %d for %d credits!', $rig->RigType->rig_name, $nextLevel, $upgradeCost)
		);
	}
	
	/**
	 * Assert that a user rig exists and return it
	 */
	protected function assertUserRigExists($id)
	{
		return $this->assertRecordExists('IC\CryptoMining:UserRig', $id, null, 'requested_rig_not_found');
	}
}
