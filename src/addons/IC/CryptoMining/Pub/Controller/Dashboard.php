<?php

namespace IC\CryptoMining\Pub\Controller;

use XF\Pub\Controller\AbstractController;

/**
 * Dashboard controller - View owned rigs and mining stats
 */
class Dashboard extends AbstractController
{
	/**
	 * Main dashboard page
	 */
	public function actionIndex()
	{
		// Check view permission
		if (!\XF::visitor()->hasPermission('icCryptoMining', 'view'))
		{
			return $this->noPermission();
		}
		
		$visitor = \XF::visitor();
		
		/** @var \IC\CryptoMining\Repository\UserRig $userRigRepo */
		$userRigRepo = $this->repository('IC\CryptoMining:UserRig');
		
		/** @var \IC\CryptoMining\Repository\Wallet $walletRepo */
		$walletRepo = $this->repository('IC\CryptoMining:Wallet');
		
		/** @var \IC\CryptoMining\Repository\Market $marketRepo */
		$marketRepo = $this->repository('IC\CryptoMining:Market');
		
		// Get user's rigs
		$rigs = $userRigRepo->getUserRigs($visitor->user_id);
		
		// Get wallet
		$wallet = $walletRepo->getOrCreateWallet($visitor->user_id);
		
		// Get stats
		$stats = $userRigRepo->getUserStats($visitor->user_id);
		
		// Get current BTC price
		$btcPrice = $marketRepo->getCurrentPrice();
		
		// Get active market event
		$activeEvent = $marketRepo->getActiveEvent();
		
		$viewParams = [
			'rigs' => $rigs,
			'wallet' => $wallet,
			'stats' => $stats,
			'btcPrice' => $btcPrice,
			'activeEvent' => $activeEvent,
			'hasRigs' => $rigs->count() > 0
		];
		
		return $this->view('IC\CryptoMining:Dashboard\Index', 'ic_crypto_dashboard', $viewParams);
	}
}
