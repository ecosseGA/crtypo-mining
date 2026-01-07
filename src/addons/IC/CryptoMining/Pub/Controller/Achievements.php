<?php

namespace IC\CryptoMining\Pub\Controller;

use XF\Mvc\ParameterBag;
use XF\Pub\Controller\AbstractController;

class Achievements extends AbstractController
{
	public function actionIndex()
	{
		if (!\XF::visitor()->hasPermission('icCryptoMining', 'view'))
		{
			return $this->noPermission();
		}
		
		$achievementRepo = $this->repository('IC\CryptoMining:Achievement');
		$achievementService = $this->service('IC\CryptoMining:Achievement');
		
		// Get all achievements grouped by category
		$achievementsByCategory = $achievementRepo->getAchievementsByCategory();
		
		// Get user's earned achievements
		$earnedAchievements = $achievementRepo->getUserAchievements(\XF::visitor()->user_id);
		$earnedIds = $earnedAchievements->pluckNamed('achievement_id', 'achievement_id');
				
		// Get achievement stats
		$stats = $achievementRepo->getUserAchievementStats(\XF::visitor()->user_id);
		
		// Calculate total XP earned
		$totalXp = 0;
		foreach ($earnedAchievements as $userAch)
		{
			$totalXp += $userAch->xp_awarded;
		}
		
		$viewParams = [
			'achievementsByCategory' => $achievementsByCategory,
			'earnedIds' => $earnedIds,
			'earnedAchievements' => $earnedAchievements,
			'stats' => $stats,
			'totalXp' => $totalXp,
			'activeNav' => 'crypto-achievements'
		];
		
		return $this->view('IC\CryptoMining:Achievements\Index', 'ic_crypto_achievements', $viewParams);
	}
}
