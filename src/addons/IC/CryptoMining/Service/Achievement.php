<?php

namespace IC\CryptoMining\Service;

use IC\CryptoMining\Entity\Achievement as AchievementEntity;
use XF\Entity\User;
use XF\Service\AbstractService;

/**
 * Service for awarding and checking achievements
 */
class Achievement extends AbstractService
{
	/**
	 * Award an achievement to a user
	 */
	public function awardAchievement(User $user, AchievementEntity $achievement): bool
	{
		// Check if already earned (unless repeatable)
		if (!$achievement->is_repeatable)
		{
			$existing = $this->em()->findOne('IC\CryptoMining:UserAchievement', [
				'user_id' => $user->user_id,
				'achievement_id' => $achievement->achievement_id
			]);
			
			if ($existing)
			{
				return false; // Already earned
			}
		}
		
		// Create user achievement record
		/** @var \IC\CryptoMining\Entity\UserAchievement $userAchievement */
		$userAchievement = $this->em()->create('IC\CryptoMining:UserAchievement');
		$userAchievement->user_id = $user->user_id;
		$userAchievement->achievement_id = $achievement->achievement_id;
		$userAchievement->earned_date = \XF::$time;
		$userAchievement->xp_awarded = $achievement->xp_points;
		$userAchievement->save();
		
		return true;
	}
	
	/**
	 * Check all achievements for a user in a given context
	 */
	public function checkAchievements(User $user, string $context = 'general', array $data = []): void
	{
		// Get all active achievements
		$achievements = $this->finder('IC\CryptoMining:Achievement')
			->where('is_active', true)
			->fetch();
		
		foreach ($achievements as $achievement)
		{
			if ($this->checkAchievementCriteria($user, $achievement, $context, $data))
			{
				$this->awardAchievement($user, $achievement);
			}
		}
	}
	
	/**
	 * Check if user meets achievement criteria
	 */
	protected function checkAchievementCriteria(User $user, AchievementEntity $achievement, string $context, array $data): bool
	{
		$key = $achievement->achievement_key;
		$db = $this->db();
		
		// MINING CATEGORY
		
		// first_dig - Purchase first rig
		if ($key === 'first_dig' && $context === 'rig_purchased')
		{
			$rigCount = $db->fetchOne('
				SELECT COUNT(*)
				FROM xf_ic_crypto_user_rigs
				WHERE user_id = ?
			', $user->user_id);
			return $rigCount == 1;
		}
		
		// prospector - Mine 1 BTC total
		if ($key === 'prospector')
		{
			$totalMined = $db->fetchOne('
				SELECT COALESCE(SUM(total_mined), 0)
				FROM xf_ic_crypto_user_rigs
				WHERE user_id = ?
			', $user->user_id);
			return $totalMined >= 1;
		}
		
		// gold_rush - Mine 10 BTC total
		if ($key === 'gold_rush')
		{
			$totalMined = $db->fetchOne('
				SELECT COALESCE(SUM(total_mined), 0)
				FROM xf_ic_crypto_user_rigs
				WHERE user_id = ?
			', $user->user_id);
			return $totalMined >= 10;
		}
		
		// mining_empire - Own 5 rigs
		if ($key === 'mining_empire')
		{
			$rigCount = $db->fetchOne('
				SELECT COUNT(*)
				FROM xf_ic_crypto_user_rigs
				WHERE user_id = ?
			', $user->user_id);
			return $rigCount >= 5;
		}
		
		// industrial_scale - Own 10 rigs
		if ($key === 'industrial_scale')
		{
			$rigCount = $db->fetchOne('
				SELECT COUNT(*)
				FROM xf_ic_crypto_user_rigs
				WHERE user_id = ?
			', $user->user_id);
			return $rigCount >= 10;
		}
		
		// TRADING CATEGORY
		
		// first_sale - First crypto sale
		if ($key === 'first_sale' && $context === 'crypto_sold')
		{
			$tradeCount = $db->fetchOne('
				SELECT COUNT(*)
				FROM xf_ic_crypto_transactions
				WHERE user_id = ? AND transaction_type = ?
			', [$user->user_id, 'sell_crypto']);
			return $tradeCount == 1;
		}
		
		// market_trader - Complete 10 trades
		if ($key === 'market_trader')
		{
			$tradeCount = $db->fetchOne('
				SELECT COUNT(*)
				FROM xf_ic_crypto_transactions
				WHERE user_id = ? AND transaction_type IN (?, ?)
			', [$user->user_id, 'sell_crypto', 'buy_crypto']);
			return $tradeCount >= 10;
		}
		
		// whale - Sell 10+ BTC in single transaction
		if ($key === 'whale' && $context === 'crypto_sold')
		{
			if (isset($data['amount']))
			{
				return $data['amount'] >= 10;
			}
			return false;
		}
		
		// WEALTH CATEGORY
		
		// crypto_holder - Hold 5 BTC
		if ($key === 'crypto_holder')
		{
			$balance = $db->fetchOne('
				SELECT COALESCE(crypto_balance, 0)
				FROM xf_ic_crypto_wallet
				WHERE user_id = ?
			', $user->user_id);
			return $balance >= 5;
		}
		
		// bitcoin_millionaire - $1M portfolio
		if ($key === 'bitcoin_millionaire')
		{
			$balance = $db->fetchOne('
				SELECT COALESCE(crypto_balance, 0)
				FROM xf_ic_crypto_wallet
				WHERE user_id = ?
			', $user->user_id);
			
			$btcPrice = $db->fetchOne('
				SELECT current_price
				FROM xf_ic_crypto_market
				WHERE crypto_name = ?
			', 'Bitcoin') ?: 50000;
			
			$portfolioValue = $balance * $btcPrice;
			return $portfolioValue >= 1000000;
		}
		
		// EFFICIENCY CATEGORY
		
		// maintenance_master - Repair a rig
		if ($key === 'maintenance_master' && $context === 'rig_repaired')
		{
			return true; // Award on first repair
		}
		
		// tech_upgrade - Fully upgrade rig to level 5
		if ($key === 'tech_upgrade' && $context === 'rig_upgraded')
		{
			if (isset($data['new_level']) && $data['new_level'] == 5)
			{
				return true;
			}
			return false;
		}
		
		// well_oiled_machine - All rigs above 80% durability
		if ($key === 'well_oiled_machine')
		{
			$lowDurabilityRigs = $db->fetchOne('
				SELECT COUNT(*)
				FROM xf_ic_crypto_user_rigs
				WHERE user_id = ? AND current_durability < 80
			', $user->user_id);
			
			$totalRigs = $db->fetchOne('
				SELECT COUNT(*)
				FROM xf_ic_crypto_user_rigs
				WHERE user_id = ?
			', $user->user_id);
			
			return $totalRigs > 0 && $lowDurabilityRigs == 0;
		}
		
		// COMPETITION CATEGORY
		
		// block_winner - Win a block
		if ($key === 'block_winner' && $context === 'block_won')
		{
			$blockWins = $db->fetchOne('
				SELECT COALESCE(blocks_won, 0)
				FROM xf_ic_crypto_wallet
				WHERE user_id = ?
			', $user->user_id);
			return $blockWins >= 1;
		}
		
		// top_miner - Top 10 on any leaderboard
		if ($key === 'top_miner')
		{
			$topRank = $db->fetchOne('
				SELECT MIN(rank_position)
				FROM xf_ic_crypto_leaderboard
				WHERE user_id = ?
			', $user->user_id);
			return $topRank && $topRank <= 10;
		}
		
		return false;
	}
	
	/**
	 * Get achievement progress for user
	 */
	public function getAchievementProgress(User $user): array
	{
		$achievements = $this->finder('IC\CryptoMining:Achievement')
			->where('is_active', true)
			->order(['achievement_category', 'display_order'])
			->fetch();
		
		$earned = $this->finder('IC\CryptoMining:UserAchievement')
			->where('user_id', $user->user_id)
			->fetch()
			->pluckNamed('achievement_id', 'achievement_id');
		
		$progress = [];
		
		foreach ($achievements as $achievement)
		{
			$progress[$achievement->achievement_id] = [
				'achievement' => $achievement,
				'earned' => isset($earned[$achievement->achievement_id]),
				'earned_date' => isset($earned[$achievement->achievement_id]) ? $earned[$achievement->achievement_id]->earned_date : null
			];
		}
		
		return $progress;
	}
}
