<?php

namespace IC\CryptoMining\Admin\Controller;

use XF\Admin\Controller\AbstractController;
use XF\Mvc\FormAction;

/**
 * Admin Controller for Crypto Mining settings
 */
class Settings extends AbstractController
{
	/**
	 * Display settings page
	 */
	public function actionIndex()
	{
		$options = \XF::options();
		
		$viewParams = [
			'options' => $options
		];
		
		return $this->view('IC\CryptoMining:Settings', 'ic_crypto_admin_settings', $viewParams);
	}
	
	/**
	 * Save settings
	 */
	public function actionSave()
	{
		$this->assertPostOnly();
		
		$input = $this->filter([
			'ic_crypto_starting_balance' => 'uint',
			'ic_crypto_btc_price' => 'float',
			'ic_crypto_repair_cost_multiplier' => 'float',
			'ic_crypto_durability_enabled' => 'bool',
			'ic_crypto_power_costs_enabled' => 'bool',
			'ic_crypto_leaderboard_enabled' => 'bool',
		]);
		
		/** @var \XF\Repository\Option $optionRepo */
		$optionRepo = $this->repository('XF:Option');
		
		foreach ($input as $optionName => $value)
		{
			$optionRepo->updateOption($optionName, $value);
		}
		
		return $this->redirect($this->buildLink('crypto-mining/settings'), 'Settings saved successfully!');
	}
	
	/**
	 * Manually run mining cron
	 */
	public function actionRunCron()
	{
		$this->assertPostOnly();
		
		\IC\CryptoMining\Cron\UpdateMining::runUpdate();
		
		return $this->redirect($this->buildLink('crypto-mining/settings'), 'Mining cron executed successfully!');
	}
	
	/**
	 * Reset all user data (dangerous!)
	 */
	public function actionReset()
	{
		if ($this->isPost())
		{
			$confirm = $this->filter('confirm', 'str');
			
			if ($confirm !== 'RESET')
			{
				return $this->error('You must type RESET to confirm.');
			}
			
			$db = $this->app->db();
			
			// Truncate all user data
			$db->query('TRUNCATE TABLE xf_ic_crypto_user_rigs');
			$db->query('TRUNCATE TABLE xf_ic_crypto_wallet');
			$db->query('TRUNCATE TABLE xf_ic_crypto_transactions');
			$db->query('TRUNCATE TABLE xf_ic_crypto_leaderboard');
			
			return $this->redirect($this->buildLink('crypto-mining/settings'), 'All user data reset!');
		}
		
		return $this->view('IC\CryptoMining:Reset', 'ic_crypto_admin_reset');
	}
}
