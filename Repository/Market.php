<?php

namespace IC\CryptoMining\Repository;

use XF\Mvc\Entity\Repository;

/**
 * Repository for managing crypto market data
 */
class Market extends Repository
{
	/**
	 * Get current Bitcoin market data
	 */
	public function getMarket(string $cryptoName = 'Bitcoin'): ?\IC\CryptoMining\Entity\Market
	{
		return $this->finder('IC\CryptoMining:Market')
			->where('crypto_name', $cryptoName)
			->fetchOne();
	}
	
	/**
	 * Get current Bitcoin price
	 */
	public function getCurrentPrice(string $cryptoName = 'Bitcoin'): float
	{
		$market = $this->getMarket($cryptoName);
		
		return $market ? $market->current_price : 50000.00;
	}
	
	/**
	 * Update market price
	 */
	public function updatePrice(float $newPrice, string $cryptoName = 'Bitcoin'): bool
	{
		$market = $this->getMarket($cryptoName);
		
		if (!$market)
		{
			return false;
		}
		
		// Calculate price change
		$priceChange = (($newPrice - $market->current_price) / $market->current_price) * 100;
		
		// Update market data
		$market->previous_price = $market->current_price;
		$market->current_price = $newPrice;
		$market->price_change_percent = $priceChange;
		$market->last_updated = \XF::$time;
		
		$saved = $market->save();
		
		// Record in history
		if ($saved)
		{
			$this->recordPriceHistory($newPrice, $cryptoName);
		}
		
		return $saved;
	}
	
	/**
	 * Record price in history for charts
	 */
	public function recordPriceHistory(float $price, string $cryptoName = 'Bitcoin'): void
	{
		/** @var \IC\CryptoMining\Entity\MarketHistory $history */
		$history = $this->em->create('IC\CryptoMining:MarketHistory');
		
		$history->crypto_name = $cryptoName;
		$history->price = $price;
		$history->recorded_date = \XF::$time;
		
		$history->save();
	}
	
	/**
	 * Get price history for charts
	 */
	public function getPriceHistory(int $days = 30, string $cryptoName = 'Bitcoin'): \XF\Mvc\Entity\ArrayCollection
	{
		$cutoff = \XF::$time - ($days * 86400);
		
		return $this->finder('IC\CryptoMining:MarketHistory')
			->where('crypto_name', $cryptoName)
			->where('recorded_date', '>=', $cutoff)
			->order('recorded_date', 'ASC')
			->fetch();
	}
	
	/**
	 * Get active market event
	 */
	public function getActiveEvent(): ?\IC\CryptoMining\Entity\MarketEvent
	{
		return $this->finder('IC\CryptoMining:MarketEvent')
			->where('is_active', 1)
			->fetchOne();
	}
	
	/**
	 * Trigger a random market event
	 */
	public function triggerRandomEvent(): ?\IC\CryptoMining\Entity\MarketEvent
	{
		// Get all inactive events
		$events = $this->finder('IC\CryptoMining:MarketEvent')
			->where('is_active', 0)
			->fetch();
		
		if ($events->count() == 0)
		{
			return null;
		}
		
		// Pick random event
		$randomEvent = $events->random();
		
		// Activate it
		$randomEvent->is_active = 1;
		$randomEvent->triggered_date = \XF::$time;
		$randomEvent->save();
		
		return $randomEvent;
	}
	
	/**
	 * End active event
	 */
	public function endActiveEvent(): bool
	{
		$event = $this->getActiveEvent();
		
		if (!$event)
		{
			return false;
		}
		
		$event->is_active = 0;
		
		return $event->save();
	}
	
	/**
	 * Calculate crypto value in credits
	 */
	public function cryptoToCredits(float $btcAmount): int
	{
		$price = $this->getCurrentPrice();
		
		return (int)floor($btcAmount * $price);
	}
	
	/**
	 * Calculate credits value in crypto
	 */
	public function creditsToCrypto(int $credits): float
	{
		$price = $this->getCurrentPrice();
		
		return $credits / $price;
	}
}
