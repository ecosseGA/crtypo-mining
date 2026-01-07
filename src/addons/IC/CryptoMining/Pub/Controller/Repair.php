<?php

namespace IC\CryptoMining\Pub\Controller;

use XF\Mvc\ParameterBag;

/**
 * Repair Controller
 * Handles rig repair functionality
 */
class Repair extends \XF\Pub\Controller\AbstractController
{
	/**
	 * Show repair confirmation and process repair
	 */
	public function actionIndex(ParameterBag $params)
	{
		// Get the rig and verify ownership
		$rig = $this->assertUserRigExists($params->user_rig_id);
		
		if ($rig->user_id !== \XF::visitor()->user_id)
		{
			return $this->noPermission();
		}
		
		// Can't repair if already at 100%
		if ($rig->current_durability >= 100)
		{
			return $this->error('This rig is already at full durability.');
		}
		
		// Calculate repair cost
		$durabilityToRestore = 100 - $rig->current_durability;
		$baseCost = $rig->RigType->base_cost;
		$repairCost = ceil(($baseCost * 0.10) * ($durabilityToRestore / 100));
		
		// Show confirmation form
		if (!$this->isPost())
		{
			$viewParams = [
				'rig' => $rig,
				'repairCost' => $repairCost,
				'durabilityToRestore' => $durabilityToRestore
			];
			return $this->view('IC\CryptoMining:Repair', 'ic_crypto_repair', $viewParams);
		}
		
		// Process repair
		$visitor = \XF::visitor();
		$walletRepo = $this->repository('IC\CryptoMining:Wallet');
		$wallet = $walletRepo->getOrCreateWallet($visitor->user_id);
		
		// Check if user has enough credits
		if ($wallet->cash_balance < $repairCost)
		{
			return $this->error('You need ' . number_format($repairCost) . ' credits to repair this rig. You only have ' . number_format($wallet->cash_balance) . ' credits.');
		}
		
		// Deduct credits from wallet
		$wallet->cash_balance -= $repairCost;
		$wallet->credits_spent += $repairCost;
		$wallet->save();
		
		// Restore durability to 100%
		$rig->current_durability = 100;
		$rig->save();
		
		// Log transaction
		$transaction = $this->em()->create('IC\CryptoMining:Transaction');
		$transaction->user_id = $visitor->user_id;
		$transaction->transaction_type = 'repair';
		$transaction->credits = -$repairCost;
		$transaction->rig_type_id = $rig->rig_type_id;
		$transaction->description = sprintf(
			'Repaired %s from %.1f%% to 100%% durability',
			$rig->RigType->rig_name,
			100 - $durabilityToRestore
		);
		$transaction->transaction_date = \XF::$time;
		$transaction->save();
		
		// Check achievements (maintenance_master)
		/** @var \IC\CryptoMining\Service\Achievement $achievementService */
		$achievementService = $this->service('IC\CryptoMining:Achievement');
		$achievementService->checkAchievements(\XF::visitor(), 'rig_repaired');
		
		return $this->redirect(
			$this->buildLink('crypto-mining'),
			sprintf('Repaired %s for %d credits!', $rig->RigType->rig_name, $repairCost)
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
