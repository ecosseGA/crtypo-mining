<?php

namespace IC\CryptoMining\Repository;

use XF\Mvc\Entity\Repository;

class Achievement extends Repository
{
	/**
	 * @return \IC\CryptoMining\Finder\Achievement
	 */
	public function findAchievementsForList()
	{
		return $this->finder('IC\CryptoMining:Achievement')
			->order(['achievement_category', 'display_order']);
	}

	/**
	 * @return \IC\CryptoMining\Finder\Achievement
	 */
	public function findActiveAchievements()
	{
		return $this->finder('IC\CryptoMining:Achievement')
			->where('is_active', 1)
			->order(['achievement_category', 'display_order']);
	}

	/**
	 * Get achievements grouped by category
	 *
	 * @return array
	 */
	public function getAchievementsByCategory()
	{
		$achievements = $this->findActiveAchievements()->fetch();
		return $achievements->groupBy('achievement_category');
	}
	
	/**
	 * Get total achievement count
	 */
	public function getTotalAchievementCount()
	{
		return $this->findActiveAchievements()->total();
	}
	
	/**
	 * Get user's earned achievements
	 */
	public function getUserAchievements($userId)
	{
		return $this->finder('IC\CryptoMining:UserAchievement')
			->with('Achievement')
			->where('user_id', $userId)
			->order('earned_date', 'DESC')
			->fetch();
	}
	
	/**
	 * Check if user has earned specific achievement
	 */
	public function hasEarnedAchievement($userId, $achievementKey)
	{
		$achievement = $this->finder('IC\CryptoMining:Achievement')
			->where('achievement_key', $achievementKey)
			->fetchOne();
		
		if (!$achievement)
		{
			return false;
		}
		
		$userAchievement = $this->finder('IC\CryptoMining:UserAchievement')
			->where('user_id', $userId)
			->where('achievement_id', $achievement->achievement_id)
			->fetchOne();
		
		return $userAchievement ? true : false;
	}
	
	/**
	 * Get achievement stats for user
	 */
	public function getUserAchievementStats($userId)
	{
		$earned = $this->getUserAchievements($userId)->count();
		$total = $this->getTotalAchievementCount();
		$percentage = $total > 0 ? round(($earned / $total) * 100, 1) : 0;
		
		return [
			'earned' => $earned,
			'total' => $total,
			'percentage' => $percentage
		];
	}
}
