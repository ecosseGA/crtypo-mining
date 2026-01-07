<?php

namespace IC\CryptoMining\Pub\Controller;

use XF\Pub\Controller\AbstractController;
use XF\Mvc\ParameterBag;

/**
 * Grid Controller - Mining Expedition Grid
 */
class Grid extends AbstractController
{
	/**
	 * Display the 10x10 mining grid
	 */
	public function actionIndex()
	{
		// Check permission
		if (!\XF::visitor()->hasPermission('icCryptoMining', 'view'))
		{
			return $this->noPermission();
		}
		
		$gridRepo = $this->repository('IC\CryptoMining:Grid');
		
		// Get or create active generation
		$generation = $gridRepo->getActiveGeneration();
		if (!$generation)
		{
			$generation = $gridRepo->createNewGeneration();
		}
		
		// Get grid blocks
		$blocks = $gridRepo->getActiveGrid();
		
		// If no blocks exist, generate them
		if (empty($blocks))
		{
			$gridRepo->generateGrid($generation->generation_id);
			$blocks = $gridRepo->getActiveGrid();
		}
		
		// Convert to 10x10 array for template
		$grid = [];
		foreach ($blocks as $block)
		{
			$row = floor($block->position / 10);
			$col = $block->position % 10;
			$grid[$row][$col] = $block;
		}
		
		// Get user's rigs (for rig selection)
		$rigRepo = $this->repository('IC\CryptoMining:UserRig');
		$userRigs = $rigRepo->getUserRigs(\XF::visitor()->user_id);
		
		// Filter rigs that can mine (>20% durability)
		$availableRigs = $userRigs->filter(function($rig) {
			return $rig->current_durability > 20;
		});
		
		// Get user's stats for this generation
		$userStats = $gridRepo->getUserGridStats(\XF::visitor()->user_id);
		
		// Get user's wallet for cash balance
		$walletRepo = $this->repository('IC\CryptoMining:Wallet');
		$wallet = $walletRepo->getOrCreateWallet(\XF::visitor()->user_id);
		$credits = $wallet->cash_balance;
		
		// Get active buffs
		$activeBuffs = $this->repository('IC\CryptoMining:UserBuff')->getActiveBuffs(\XF::visitor()->user_id);
		
		$viewParams = [
			'generation' => $generation,
			'grid' => $grid,
			'blocks' => $blocks,
			'userRigs' => $availableRigs,
			'userStats' => $userStats,
			'credits' => $credits,
			'activeBuffs' => $activeBuffs,
			'activeNav' => 'crypto-grid'
		];
		
		return $this->view('IC\CryptoMining:Grid\Index', 'ic_crypto_grid', $viewParams);
	}
	
	/**
	 * Mine a block (AJAX)
	 */
	public function actionMine(ParameterBag $params)
	{
		$this->assertPostOnly();
		
		// Check permission
		if (!\XF::visitor()->hasPermission('icCryptoMining', 'mine'))
		{
			return $this->noPermission();
		}
		
		$position = $this->filter('position', 'uint');
		$rigId = $this->filter('rig_id', 'uint');
		$useInsurance = $this->filter('use_insurance', 'bool');
		
		// Get the rig
		$rig = $this->assertRecordExists('IC\CryptoMining:UserRig', $rigId);
		
		// Verify ownership
		if ($rig->user_id != \XF::visitor()->user_id)
		{
			return $this->error('You do not own this rig.');
		}
		
		// Verify durability
		if ($rig->current_durability <= 20)
		{
			return $this->error('Rig durability too low! Must be above 20% to mine.');
		}
		
		// Get the block
		$gridRepo = $this->repository('IC\CryptoMining:Grid');
		$block = $gridRepo->getBlockAtPosition($position);
		
		if (!$block)
		{
			return $this->error('Block not found.');
		}
		
		// Check if already mined
		if ($block->is_mined)
		{
			return $this->error('This block has already been mined!');
		}
		
		// Calculate costs
		$baseCost = 500; // Base mining cost
		$insuranceCost = $useInsurance ? 200 : 0;
		$totalCost = $baseCost + $insuranceCost;
		
		// Get user's wallet
		$walletRepo = $this->repository('IC\CryptoMining:Wallet');
		$wallet = $walletRepo->getOrCreateWallet(\XF::visitor()->user_id);
		
		// Check credits
		if ($wallet->cash_balance < $totalCost)
		{
			return $this->error('Insufficient credits! Need ' . $totalCost . ' credits (you have ' . number_format($wallet->cash_balance) . ').');
		}
		
		// Get active buffs
		$buffRepo = $this->repository('IC\CryptoMining:UserBuff');
		$luckyPickaxe = $buffRepo->getActiveBuff(\XF::visitor()->user_id, 'lucky_pickaxe');
		
		// Calculate rewards (with buffs)
		$btcEarned = $block->btc_value;
		
		// Lucky Pickaxe might change jackpot chance (handled in generation, but we can show it)
		// For now, just apply the block's actual value
		
		// Calculate durability loss
		$durabilityLost = $block->durability_cost;
		
		// Apply insurance (reduces collapse damage from 20% to 5%)
		if ($useInsurance && $block->block_type == 'collapse')
		{
			$durabilityLost = 5.0;
		}
		
		// Deduct credits from wallet
		$wallet->cash_balance -= $totalCost;
		$wallet->credits_spent += $totalCost;
		
		// Add BTC to wallet
		if ($btcEarned > 0)
		{
			$wallet->crypto_balance += $btcEarned;
			$wallet->total_mined += $btcEarned;
		}
		
		// Save wallet
		if (!$wallet->save())
		{
			throw new \Exception('Failed to save wallet: ' . print_r($wallet->getErrors(), true));
		}
		
		// Reduce rig durability
		$rig->current_durability = max(0, $rig->current_durability - $durabilityLost);
		$rig->save();
		
		// Mark block as mined
		$block->is_mined = true;
		$block->mined_by_user_id = \XF::visitor()->user_id;
		$block->mined_date = \XF::$time;
		$block->save();
		
		// Update generation stats
		$generation = $block->Generation;
		$generation->total_mined++;
		if ($block->block_type == 'jackpot')
		{
			$generation->total_jackpots++;
		}
		if ($block->block_type == 'collapse')
		{
			$generation->total_collapses++;
		}
		$generation->save();
		
		// Log the mine
		$mine = $this->em()->create('IC\CryptoMining:GridMine');
		$mine->user_id = \XF::visitor()->user_id;
		$mine->rig_id = $rigId;
		$mine->generation_id = $generation->generation_id;
		$mine->position = $position;
		$mine->block_type = $block->block_type;
		$mine->btc_earned = $btcEarned;
		$mine->durability_lost = $durabilityLost;
		$mine->credits_spent = $totalCost;
		$mine->used_insurance = $useInsurance;
		$mine->mined_date = \XF::$time;
		$mine->save();
		
		// Log transaction
		/** @var \IC\CryptoMining\Entity\Transaction $transaction */
		$transaction = $this->em()->create('IC\CryptoMining:Transaction');
		$transaction->user_id = \XF::visitor()->user_id;
		$transaction->transaction_type = 'grid_mine';
		$transaction->amount = $btcEarned;
		$transaction->credits = -$totalCost;
		$transaction->price_per_unit = 0;
		$transaction->description = 'Grid mining - ' . ucfirst(str_replace('_', ' ', $block->block_type));
		$transaction->transaction_date = \XF::$time;
		if (!$transaction->save())
		{
			\XF::logError('Failed to save grid mining transaction: ' . print_r($transaction->getErrors(), true));
		}
		
		// Check for Lucky Pickaxe drop (2% chance from jackpot)
		$luckyDrop = false;
		if ($block->block_type == 'jackpot' && mt_rand(1, 100) <= 2)
		{
			$buffRepo->createBuff(\XF::visitor()->user_id, 'lucky_pickaxe', 10, 24 * 3600);
			$luckyDrop = true;
		}
		
		// Build success message
		$blockTypeLabel = ucfirst(str_replace('_', ' ', $block->block_type));
		$message = '';
		
		switch ($block->block_type)
		{
			case 'jackpot':
				$message = sprintf('🎰 JACKPOT! Mined %.6f BTC! %s', $btcEarned, $luckyDrop ? '🎁 Lucky Pickaxe dropped!' : '');
				break;
			case 'rich':
				$message = sprintf('💎 Rich vein! Mined %.6f BTC!', $btcEarned);
				break;
			case 'collapse':
				$message = sprintf('⚠️ Tunnel collapse! Lost %.1f%% durability!%s', $durabilityLost, $useInsurance ? ' (Insurance saved you!)' : '');
				break;
			case 'empty':
				$message = '😔 Empty block... Better luck next time!';
				break;
			default:
				$message = sprintf('⛏️ Mined %.6f BTC!', $btcEarned);
		}
		
		// Add durability warning if rig is low
		if ($rig->current_durability <= 20)
		{
			$message .= sprintf(' ⚠️ Rig at %.1f%% durability!', $rig->current_durability);
		}
		
		// Return JSON response for AJAX
		return $this->view('IC\CryptoMining:Grid\Mine', '', [
			'status' => 'ok',
			'message' => $message,
			'blockType' => $block->block_type,
			'btcEarned' => $btcEarned,
			'durabilityLost' => $durabilityLost,
			'rigDurability' => $rig->current_durability,
			'walletBalance' => $wallet->crypto_balance,
			'creditsBalance' => $wallet->cash_balance
		]);
	}
	
	/**
	 * Scout surrounding blocks (AJAX)
	 */
	public function actionScout(ParameterBag $params)
	{
		$this->assertPostOnly();
		
		// Check permission
		if (!\XF::visitor()->hasPermission('icCryptoMining', 'mine'))
		{
			return $this->noPermission();
		}
		
		$position = $this->filter('position', 'uint');
		$scoutCost = 250;
		
		// Get user's wallet
		$walletRepo = $this->repository('IC\CryptoMining:Wallet');
		$wallet = $walletRepo->getOrCreateWallet(\XF::visitor()->user_id);
		
		// Check credits
		if ($wallet->cash_balance < $scoutCost)
		{
			return $this->error('Insufficient credits! Scout costs ' . $scoutCost . ' credits (you have ' . number_format($wallet->cash_balance) . ').');
		}
		
		// Get surrounding blocks
		$gridRepo = $this->repository('IC\CryptoMining:Grid');
		$surroundingBlocks = $gridRepo->getSurroundingBlocks($position);
		
		// Deduct credits from wallet
		$wallet->cash_balance -= $scoutCost;
		$wallet->credits_spent += $scoutCost;
		if (!$wallet->save())
		{
			throw new \Exception('Failed to save wallet');
		}
		
		// Build response array
		$scoutData = [];
		foreach ($surroundingBlocks as $block)
		{
			if (!$block->is_mined)
			{
				$scoutData[$block->position] = [
					'type' => $block->block_type,
					'icon' => $block->getIcon()
				];
			}
		}
		
		return $this->view('IC\CryptoMining:Grid\Scout', '', [
			'success' => true,
			'scoutData' => $scoutData,
			'creditsSpent' => $scoutCost
		]);
	}
	
	/**
	 * Buy Lucky Pickaxe buff
	 */
	public function actionBuyBuff()
	{
		$this->assertPostOnly();
		
		$buffType = $this->filter('buff_type', 'str');
		$cost = 500; // Lucky Pickaxe costs 500 credits
		
		// Get user's wallet
		$walletRepo = $this->repository('IC\CryptoMining:Wallet');
		$wallet = $walletRepo->getOrCreateWallet(\XF::visitor()->user_id);
		
		// Check credits
		if ($wallet->cash_balance < $cost)
		{
			return $this->error('Insufficient credits! Lucky Pickaxe costs ' . $cost . ' credits (you have ' . number_format($wallet->cash_balance) . ').');
		}
		
		// Check if already has active buff
		$buffRepo = $this->repository('IC\CryptoMining:UserBuff');
		if ($buffRepo->getActiveBuff(\XF::visitor()->user_id, $buffType))
		{
			return $this->error('You already have this buff active!');
		}
		
		// Deduct credits from wallet
		$wallet->cash_balance -= $cost;
		$wallet->credits_spent += $cost;
		if (!$wallet->save())
		{
			throw new \Exception('Failed to save wallet');
		}
		
		// Create buff (24 hour duration)
		$buffRepo->createBuff(\XF::visitor()->user_id, $buffType, 10, 24 * 3600);
		
		return $this->redirect($this->buildLink('crypto-grid'), 'Lucky Pickaxe purchased! +10% jackpot chance for 24 hours.');
	}
}
