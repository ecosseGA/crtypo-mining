<?php

namespace IC\CryptoMining\Pub\Controller;

use XF\Mvc\ParameterBag;

/**
 * Leaderboard Controller
 * Displays top miners across 5 categories
 */
class Leaderboard extends \XF\Pub\Controller\AbstractController
{
	/**
	 * Display leaderboard page
	 */
	public function actionIndex()
	{
		if (!\XF::visitor()->hasPermission('icCryptoMining', 'view'))
		{
			return $this->noPermission();
		}
		
		$visitor = \XF::visitor();
		$db = \XF::db();
		
		// Get active tab from query parameter
		$activeTab = $this->filter('type', 'str') ?: 'richest';
		
		// Validate tab
		$validTabs = ['richest', 'most_mined', 'most_efficient', 'most_rigs', 'block_champion'];
		if (!in_array($activeTab, $validTabs))
		{
			$activeTab = 'richest';
		}
		
		// Pagination
		$page = $this->filterPage();
		$perPage = 25;
		$offset = ($page - 1) * $perPage;
		
		// Get total count for pagination
		$total = $db->fetchOne("
			SELECT COUNT(*)
			FROM xf_ic_crypto_leaderboard
			WHERE leaderboard_type = ?
		", $activeTab);
		
		// Get leaderboard data for active tab with pagination
		$leaderboard = $db->fetchAll("
			SELECT 
				l.rank_position,
				l.stat_value,
				u.user_id,
				u.username,
				u.avatar_date,
				u.gravatar
			FROM xf_ic_crypto_leaderboard l
			INNER JOIN xf_user u ON l.user_id = u.user_id
			WHERE l.leaderboard_type = ?
			ORDER BY l.rank_position ASC
			LIMIT ? OFFSET ?
		", [$activeTab, $perPage, $offset]);
		
		// Get visitor's rank on this leaderboard
		$visitorRank = $db->fetchRow("
			SELECT rank_position, stat_value
			FROM xf_ic_crypto_leaderboard
			WHERE leaderboard_type = ? AND user_id = ?
		", [$activeTab, $visitor->user_id]);
		
		// Get current BTC price for USD calculations
		$market = $db->fetchRow("SELECT current_price FROM xf_ic_crypto_market WHERE crypto_name = 'Bitcoin'");
		$btcPrice = $market ? $market['current_price'] : 50000;
		
		// Get leaderboard metadata
		$lastUpdated = $db->fetchOne("
			SELECT MAX(last_updated) 
			FROM xf_ic_crypto_leaderboard 
			WHERE leaderboard_type = ?
		", $activeTab);
		
		// Get total participants (for display at bottom)
		$totalParticipants = $total; // Same as pagination total
		
		$viewParams = [
			'activeTab' => $activeTab,
			'leaderboard' => $leaderboard,
			'visitorRank' => $visitorRank,
			'btcPrice' => $btcPrice,
			'total' => $total,
			'totalParticipants' => $totalParticipants,
			'page' => $page,
			'perPage' => $perPage,
			'lastUpdated' => $lastUpdated,
			'activeNav' => 'crypto-leaderboard'
		];
		
		return $this->view('IC\CryptoMining:Leaderboard', 'ic_crypto_leaderboard', $viewParams);
	}
}
