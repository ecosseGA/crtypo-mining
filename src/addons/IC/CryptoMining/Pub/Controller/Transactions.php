<?php

namespace IC\CryptoMining\Pub\Controller;

use XF\Mvc\ParameterBag;

class Transactions extends \XF\Pub\Controller\AbstractController
{
	/**
	 * Display transaction history with filters and pagination
	 */
	public function actionIndex()
	{
		if (!\XF::visitor()->hasPermission('icCryptoMining', 'view'))
		{
			return $this->noPermission();
		}
		
		$visitor = \XF::visitor();
		$db = \XF::db();
		
		// Get filter parameters
		$type = $this->filter('type', 'str');
		$dateRange = $this->filter('date_range', 'str', 'all');
		$page = $this->filterPage();
		$perPage = 25;
		
		// Build WHERE clause based on filters
		$whereConditions = ['user_id = ?'];
		$params = [$visitor->user_id];
		
		// Type filter
		if ($type && $type !== 'all')
		{
			$whereConditions[] = 'transaction_type = ?';
			$params[] = $type;
		}
		
		// Date range filter
		$now = \XF::$time;
		if ($dateRange && $dateRange !== 'all')
		{
			switch ($dateRange)
			{
				case 'today':
					$startDate = strtotime('today', $now);
					break;
				case 'week':
					$startDate = strtotime('-7 days', $now);
					break;
				case 'month':
					$startDate = strtotime('-30 days', $now);
					break;
				case 'year':
					$startDate = strtotime('-1 year', $now);
					break;
				default:
					$startDate = null;
			}
			
			if ($startDate)
			{
				$whereConditions[] = 'transaction_date >= ?';
				$params[] = $startDate;
			}
		}
		
		$whereClause = implode(' AND ', $whereConditions);
		
		// Get total count
		$total = $db->fetchOne("
			SELECT COUNT(*) 
			FROM xf_ic_crypto_transactions 
			WHERE {$whereClause}
		", $params);
		
		// Get transactions with pagination
		$offset = ($page - 1) * $perPage;
		$transactions = $db->fetchAll("
			SELECT * 
			FROM xf_ic_crypto_transactions 
			WHERE {$whereClause}
			ORDER BY transaction_date DESC 
			LIMIT ? OFFSET ?
		", array_merge($params, [$perPage, $offset]));
		
		// Get available transaction types for filter
		$types = $db->fetchAll("
			SELECT DISTINCT transaction_type 
			FROM xf_ic_crypto_transactions 
			WHERE user_id = ?
			ORDER BY transaction_type
		", $visitor->user_id);
		
		// Calculate totals by type
		$totals = [
			'total_mined' => 0,
			'total_sold' => 0,
			'total_bought' => 0,
			'total_spent' => 0,
			'total_earned' => 0,
			'block_rewards' => 0
		];
		
		$stats = $db->fetchAll("
			SELECT 
				transaction_type,
				SUM(amount) as total_amount,
				SUM(credits) as total_credits,
				COUNT(*) as count
			FROM xf_ic_crypto_transactions
			WHERE user_id = ?
			GROUP BY transaction_type
		", $visitor->user_id);
		
		foreach ($stats as $stat)
		{
			switch ($stat['transaction_type'])
			{
				case 'mining_reward':
					$totals['total_mined'] = $stat['total_amount'];
					break;
				case 'block_reward':
					$totals['block_rewards'] = $stat['total_amount'];
					break;
				case 'sell_crypto':
					$totals['total_sold'] = $stat['total_amount'];
					$totals['total_earned'] = $stat['total_credits'];
					break;
				case 'buy_rig':
				case 'repair':
				case 'upgrade':
					$totals['total_spent'] += abs($stat['total_credits']);
					break;
			}
		}
		
		$viewParams = [
			'transactions' => $transactions,
			'types' => $types,
			'selectedType' => $type,
			'selectedDateRange' => $dateRange,
			'totals' => $totals,
			'page' => $page,
			'perPage' => $perPage,
			'total' => $total,
			'hasTransactions' => count($transactions) > 0
		];
		
		return $this->view('IC\CryptoMining:Transactions\Index', 'ic_crypto_transactions', $viewParams);
	}
	
	/**
	 * Export transactions to CSV
	 */
	public function actionExport()
	{
		if (!\XF::visitor()->hasPermission('icCryptoMining', 'view'))
		{
			return $this->noPermission();
		}
		
		$visitor = \XF::visitor();
		$db = \XF::db();
		
		// Get filter parameters (same as actionIndex)
		$type = $this->filter('type', 'str');
		$dateRange = $this->filter('date_range', 'str', 'all');
		
		// Build WHERE clause
		$whereConditions = ['user_id = ?'];
		$params = [$visitor->user_id];
		
		if ($type && $type !== 'all')
		{
			$whereConditions[] = 'transaction_type = ?';
			$params[] = $type;
		}
		
		$now = \XF::$time;
		if ($dateRange && $dateRange !== 'all')
		{
			switch ($dateRange)
			{
				case 'today':
					$startDate = strtotime('today', $now);
					break;
				case 'week':
					$startDate = strtotime('-7 days', $now);
					break;
				case 'month':
					$startDate = strtotime('-30 days', $now);
					break;
				case 'year':
					$startDate = strtotime('-1 year', $now);
					break;
				default:
					$startDate = null;
			}
			
			if ($startDate)
			{
				$whereConditions[] = 'transaction_date >= ?';
				$params[] = $startDate;
			}
		}
		
		$whereClause = implode(' AND ', $whereConditions);
		
		// Get all transactions (no pagination for export)
		$transactions = $db->fetchAll("
			SELECT * 
			FROM xf_ic_crypto_transactions 
			WHERE {$whereClause}
			ORDER BY transaction_date DESC
		", $params);
		
		// Generate CSV
		$csv = "Date,Type,Amount (BTC),Credits,Description\n";
		
		foreach ($transactions as $transaction)
		{
			$date = date('Y-m-d H:i:s', $transaction['transaction_date']);
			$type = ucfirst(str_replace('_', ' ', $transaction['transaction_type']));
			$amount = $transaction['amount'] ? number_format($transaction['amount'], 6) : '-';
			$credits = $transaction['credits'] ? number_format($transaction['credits']) : '-';
			$description = str_replace('"', '""', $transaction['description']); // Escape quotes
			
			$csv .= "\"{$date}\",\"{$type}\",\"{$amount}\",\"{$credits}\",\"{$description}\"\n";
		}
		
		// Set headers for download
		header('Content-Type: text/csv; charset=utf-8');
		header('Content-Disposition: attachment; filename="crypto-transactions-' . date('Y-m-d') . '.csv"');
		header('Pragma: no-cache');
		header('Expires: 0');
		
		echo $csv;
		exit;
	}
}
