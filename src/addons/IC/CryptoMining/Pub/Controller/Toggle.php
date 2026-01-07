<?php

namespace IC\CryptoMining\Pub\Controller;

use XF\Mvc\ParameterBag;

/**
 * Toggle Controller
 * Handles rig pause/resume functionality
 */
class Toggle extends \XF\Pub\Controller\AbstractController
{
	/**
	 * Toggle rig active/inactive status
	 */
	public function actionIndex(ParameterBag $params)
	{
		// Get the rig and verify ownership
		$rig = $this->assertUserRigExists($params->user_rig_id);
		
		if ($rig->user_id !== \XF::visitor()->user_id)
		{
			return $this->noPermission();
		}
		
		// Toggle the is_active status
		$wasActive = $rig->is_active;
		$rig->is_active = $wasActive ? 0 : 1;
		$rig->save();
		
		// Create appropriate message
		$message = $wasActive 
			? sprintf('%s paused. No longer mining (power costs stopped).', $rig->RigType->rig_name)
			: sprintf('%s resumed! Now mining %s BTC/hr.', $rig->RigType->rig_name, number_format($rig->RigType->hash_rate * (1 + ($rig->upgrade_level * 0.10)), 6));
		
		// Log transaction
		$transaction = $this->em()->create('IC\CryptoMining:Transaction');
		$transaction->user_id = \XF::visitor()->user_id;
		$transaction->transaction_type = 'power_cost'; // Using existing type for pause/resume events
		$transaction->credits = 0; // No cost for toggling
		$transaction->rig_type_id = $rig->rig_type_id;
		$transaction->description = $wasActive 
			? sprintf('Paused %s (mining stopped)', $rig->RigType->rig_name)
			: sprintf('Resumed %s (mining restarted)', $rig->RigType->rig_name);
		$transaction->transaction_date = \XF::$time;
		$transaction->save();
		
		return $this->redirect(
			$this->buildLink('crypto-mining'),
			$message
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
