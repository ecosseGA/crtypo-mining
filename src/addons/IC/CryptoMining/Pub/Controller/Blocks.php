<?php

namespace IC\CryptoMining\Pub\Controller;

use XF\Mvc\ParameterBag;

/**
 * Blocks Controller
 * Displays block competition history
 */
class Blocks extends \XF\Pub\Controller\AbstractController
{
	/**
	 * Block history page with pagination
	 */
	public function actionIndex()
	{
		if (!\XF::visitor()->hasPermission('icCryptoMining', 'view'))
		{
			return $this->noPermission();
		}
		
		$page = $this->filterPage();
		$perPage = 25;
		
		/** @var \IC\CryptoMining\Repository\Block $blockRepo */
		$blockRepo = $this->repository('IC\CryptoMining:Block');
		
		// Get current block
		$currentBlock = $blockRepo->getCurrentBlock();
		
		// Get solved blocks with pagination
		$blockFinder = $this->finder('IC\CryptoMining:Block')
			->where('is_solved', 1)
			->with('Winner')
			->with('WinnerRig.RigType')
			->order('solved_date', 'DESC')
			->limitByPage($page, $perPage);
		
		$blocks = $blockFinder->fetch();
		$total = $blockFinder->total();
		
		// Get user stats
		$visitor = \XF::visitor();
		$userBlockWins = $blockRepo->getUserBlockWins($visitor->user_id);
		
		$viewParams = [
			'currentBlock' => $currentBlock,
			'blocks' => $blocks,
			'total' => $total,
			'page' => $page,
			'perPage' => $perPage,
			'userBlockWins' => $userBlockWins,
			'activeNav' => 'crypto-blocks'
		];
		
		return $this->view('IC\CryptoMining:Blocks\Index', 'ic_crypto_blocks', $viewParams);
	}
}
