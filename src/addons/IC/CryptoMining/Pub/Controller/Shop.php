<?php

namespace IC\CryptoMining\Pub\Controller;

use XF\Pub\Controller\AbstractController;
use XF\Mvc\ParameterBag;

/**
 * Shop controller - Browse and purchase mining rigs
 */
class Shop extends AbstractController
{
	/**
	 * Main shop page - Browse all available rigs
	 */
	public function actionIndex()
	{
		// Check view permission
		if (!\XF::visitor()->hasPermission('icCryptoMining', 'view'))
		{
			return $this->noPermission();
		}
		
		/** @var \IC\CryptoMining\Repository\RigType $rigRepo */
		$rigRepo = $this->repository('IC\CryptoMining:RigType');
		
		/** @var \IC\CryptoMining\Repository\Market $marketRepo */
		$marketRepo = $this->repository('IC\CryptoMining:Market');
		
		// Get all rigs grouped by tier
		$rigsByTier = $rigRepo->getRigsByTier();
		
		// Get current BTC price
		$btcPrice = $marketRepo->getCurrentPrice();
		
		// Get tier stats
		$tierStats = $rigRepo->getTierStats();
		
		$viewParams = [
			'rigsByTier' => $rigsByTier,
			'btcPrice' => $btcPrice,
			'tierStats' => $tierStats,
			'visitor' => \XF::visitor()
		];
		
		return $this->view('IC\CryptoMining:Shop\Index', 'ic_crypto_shop_index', $viewParams);
	}
	
	/**
	 * Purchase confirmation page
	 */
	public function actionBuy(ParameterBag $params)
	{
		// Check mine permission
		if (!\XF::visitor()->hasPermission('icCryptoMining', 'mine'))
		{
			return $this->noPermission();
		}
		
		// Get rig type
		$rigType = $this->assertRigTypeExists($params->rig_type_id);
		
		/** @var \IC\CryptoMining\Repository\Market $marketRepo */
		$marketRepo = $this->repository('IC\CryptoMining:Market');
		$btcPrice = $marketRepo->getCurrentPrice();
		
		// Show confirmation form
		if (!$this->isPost())
		{
			$viewParams = [
				'rigType' => $rigType,
				'btcPrice' => $btcPrice
			];
			
			return $this->view('IC\CryptoMining:Shop\Buy', 'ic_crypto_shop_buy', $viewParams);
		}
		
		// Validate purchase
		$visitor = \XF::visitor();
		
		if (!$rigType->canPurchase($visitor, $error))
		{
			return $this->error($error);
		}
		
		// Process purchase
		$this->assertValidCsrfToken($this->filter('_xfToken', 'str'));
		
		/** @var \IC\CryptoMining\Repository\UserRig $userRigRepo */
		$userRigRepo = $this->repository('IC\CryptoMining:UserRig');
		
		/** @var \IC\CryptoMining\Repository\Wallet $walletRepo */
		$walletRepo = $this->repository('IC\CryptoMining:Wallet');
		
		$db = $this->app->db();
		$db->beginTransaction();
		
		try
		{
			// Create user rig
			$userRig = $userRigRepo->purchaseRig($visitor, $rigType);
			$userRig->save();
			
			// Deduct credits (assuming DBTech Credits addon)
			// Adjust this if using different currency system
			$creditsRepo = $this->repository('DBTech\Credits:Currency');
			$creditsRepo->updateUserCurrency(
				$visitor->user_id,
				-$rigType->base_cost,
				'Purchased ' . $rigType->rig_name
			);
			
			// Create/update wallet
			$wallet = $walletRepo->getOrCreateWallet($visitor->user_id);
			$wallet->credits_spent += $rigType->base_cost;
			$wallet->save();
			
			// Log transaction
			/** @var \IC\CryptoMining\Entity\Transaction $transaction */
			$transaction = $this->em()->create('IC\CryptoMining:Transaction');
			$transaction->user_id = $visitor->user_id;
			$transaction->transaction_type = 'buy_rig';
			$transaction->credits = $rigType->base_cost;
			$transaction->rig_type_id = $rigType->rig_type_id;
			$transaction->description = 'Purchased ' . $rigType->rig_name;
			$transaction->transaction_date = \XF::$time;
			$transaction->save();
			
			$db->commit();
		}
		catch (\Exception $e)
		{
			$db->rollback();
			\XF::logException($e, false, 'Crypto mining rig purchase error: ');
			return $this->error(\XF::phrase('ic_crypto_purchase_error'));
		}
		
		return $this->redirect(
			$this->buildLink('crypto-mining'),
			\XF::phrase('ic_crypto_rig_purchased', ['rig' => $rigType->rig_name])
		);
	}
	
	/**
	 * Assert rig type exists
	 */
	protected function assertRigTypeExists($rigTypeId, array $with = [])
	{
		$rigType = $this->em()->find('IC\CryptoMining:RigType', $rigTypeId, $with);
		
		if (!$rigType)
		{
			throw $this->exception($this->notFound(\XF::phrase('ic_crypto_rig_not_found')));
		}
		
		return $rigType;
	}
}
