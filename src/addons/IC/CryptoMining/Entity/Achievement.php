<?php

namespace IC\CryptoMining\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int achievement_id
 * @property string achievement_key
 * @property string achievement_category
 * @property int xp_points
 * @property string difficulty_tier
 * @property int credits_reward
 * @property bool is_repeatable
 * @property bool is_active
 * @property int display_order
 *
 * GETTERS
 * @property string title
 * @property string description
 *
 * RELATIONS
 * @property \XF\Entity\Phrase MasterTitle
 * @property \XF\Entity\Phrase MasterDescription
 */
class Achievement extends Entity
{
	/**
	 * Get achievement title phrase
	 */
	public function getTitle()
	{
		switch ($this->achievement_key)
		{
			case 'first_dig':
				return \XF::phrase('ic_crypto_achievement_title.first_dig');
			case 'prospector':
				return \XF::phrase('ic_crypto_achievement_title.prospector');
			case 'gold_rush':
				return \XF::phrase('ic_crypto_achievement_title.gold_rush');
			case 'mining_empire':
				return \XF::phrase('ic_crypto_achievement_title.mining_empire');
			case 'industrial_scale':
				return \XF::phrase('ic_crypto_achievement_title.industrial_scale');
			case 'first_sale':
				return \XF::phrase('ic_crypto_achievement_title.first_sale');
			case 'market_trader':
				return \XF::phrase('ic_crypto_achievement_title.market_trader');
			case 'whale':
				return \XF::phrase('ic_crypto_achievement_title.whale');
			case 'crypto_holder':
				return \XF::phrase('ic_crypto_achievement_title.crypto_holder');
			case 'bitcoin_millionaire':
				return \XF::phrase('ic_crypto_achievement_title.bitcoin_millionaire');
			case 'maintenance_master':
				return \XF::phrase('ic_crypto_achievement_title.maintenance_master');
			case 'tech_upgrade':
				return \XF::phrase('ic_crypto_achievement_title.tech_upgrade');
			case 'well_oiled_machine':
				return \XF::phrase('ic_crypto_achievement_title.well_oiled_machine');
			case 'block_winner':
				return \XF::phrase('ic_crypto_achievement_title.block_winner');
			case 'top_miner':
				return \XF::phrase('ic_crypto_achievement_title.top_miner');
			default:
				return $this->achievement_key;
		}
	}

	/**
	 * Get achievement description phrase
	 */
	public function getDescription()
	{
		switch ($this->achievement_key)
		{
			case 'first_dig':
				return \XF::phrase('ic_crypto_achievement_desc.first_dig');
			case 'prospector':
				return \XF::phrase('ic_crypto_achievement_desc.prospector');
			case 'gold_rush':
				return \XF::phrase('ic_crypto_achievement_desc.gold_rush');
			case 'mining_empire':
				return \XF::phrase('ic_crypto_achievement_desc.mining_empire');
			case 'industrial_scale':
				return \XF::phrase('ic_crypto_achievement_desc.industrial_scale');
			case 'first_sale':
				return \XF::phrase('ic_crypto_achievement_desc.first_sale');
			case 'market_trader':
				return \XF::phrase('ic_crypto_achievement_desc.market_trader');
			case 'whale':
				return \XF::phrase('ic_crypto_achievement_desc.whale');
			case 'crypto_holder':
				return \XF::phrase('ic_crypto_achievement_desc.crypto_holder');
			case 'bitcoin_millionaire':
				return \XF::phrase('ic_crypto_achievement_desc.bitcoin_millionaire');
			case 'maintenance_master':
				return \XF::phrase('ic_crypto_achievement_desc.maintenance_master');
			case 'tech_upgrade':
				return \XF::phrase('ic_crypto_achievement_desc.tech_upgrade');
			case 'well_oiled_machine':
				return \XF::phrase('ic_crypto_achievement_desc.well_oiled_machine');
			case 'block_winner':
				return \XF::phrase('ic_crypto_achievement_desc.block_winner');
			case 'top_miner':
				return \XF::phrase('ic_crypto_achievement_desc.top_miner');
			default:
				return $this->achievement_key;
		}
	}

	/**
	 * Get phrase name for this achievement
	 */
	public function getPhraseName($title)
	{
		return 'ic_crypto_achievement_' . ($title ? 'title' : 'desc') . '.' . $this->achievement_key;
	}

	/**
	 * Get master phrase for editing
	 */
	public function getMasterPhrase($title)
	{
		$phrase = $title ? $this->MasterTitle : $this->MasterDescription;
		if (!$phrase)
		{
			$phrase = $this->_em->create('XF:Phrase');
			$phrase->title = $this->_getDeferredValue(function () use ($title) {
				return $this->getPhraseName($title);
			}, 'save');
			$phrase->language_id = 0;
			$phrase->addon_id = 'IC/CryptoMining';
		}

		return $phrase;
	}

	/**
	 * Get icon for this achievement based on category
	 */
	public function getIcon()
	{
		$icons = [
			'mining' => 'fa-hammer',
			'trading' => 'fa-exchange-alt',
			'wealth' => 'fa-coins',
			'efficiency' => 'fa-cogs',
			'competition' => 'fa-trophy'
		];

		return $icons[$this->achievement_category] ?? 'fa-star';
	}
	
	/**
	 * Get phrased difficulty tier text
	 */
	public function getDifficultyText()
	{
		switch ($this->difficulty_tier)
		{
			case 'easy':
				return \XF::phrase('ic_crypto_difficulty.easy')->render();
			case 'medium':
				return \XF::phrase('ic_crypto_difficulty.medium')->render();
			case 'hard':
				return \XF::phrase('ic_crypto_difficulty.hard')->render();
			case 'very_hard':
				return \XF::phrase('ic_crypto_difficulty.very_hard')->render();
			case 'epic':
				return \XF::phrase('ic_crypto_difficulty.epic')->render();
			case 'legendary':
				return \XF::phrase('ic_crypto_difficulty.legendary')->render();
			default:
				return $this->difficulty_tier;
		}
	}
	
	/**
	 * Get difficulty color class
	 */
	public function getDifficultyClass()
	{
		$classes = [
			'easy' => 'badge--easy',
			'medium' => 'badge--medium',
			'hard' => 'badge--hard',
			'very_hard' => 'badge--very-hard',
			'epic' => 'badge--epic',
			'legendary' => 'badge--legendary'
		];
		
		return $classes[$this->difficulty_tier] ?? 'badge--medium';
	}

	/**
	 * Check if achievement can be deleted
	 */
	public function canDelete()
	{
		return true;
	}

	protected function _postSave()
	{
		// Save or update title and description phrases
		$titlePhrase = $this->getMasterPhrase(true);
		$descPhrase = $this->getMasterPhrase(false);
		
		if ($titlePhrase)
		{
			$titlePhrase->save();
		}
		
		if ($descPhrase)
		{
			$descPhrase->save();
		}
	}
	
	protected function _postDelete()
	{
		// Delete associated phrases
		if ($this->MasterTitle)
		{
			$this->MasterTitle->delete();
		}
		
		if ($this->MasterDescription)
		{
			$this->MasterDescription->delete();
		}
	}

	public static function getStructure(Structure $structure)
	{
		$structure->table = 'xf_ic_crypto_achievement';
		$structure->shortName = 'IC\CryptoMining:Achievement';
		$structure->primaryKey = 'achievement_id';
		$structure->columns = [
			'achievement_id' => ['type' => self::UINT, 'autoIncrement' => true],
			'achievement_key' => ['type' => self::STR, 'maxLength' => 50, 'required' => true, 'unique' => true],
			'achievement_category' => ['type' => self::STR, 'allowedValues' => ['mining', 'trading', 'wealth', 'efficiency', 'competition'], 'required' => true],
			'xp_points' => ['type' => self::UINT, 'default' => 10],
			'difficulty_tier' => ['type' => self::STR, 'allowedValues' => ['easy', 'medium', 'hard', 'very_hard', 'epic', 'legendary'], 'default' => 'easy'],
			'credits_reward' => ['type' => self::UINT, 'default' => 0],
			'is_repeatable' => ['type' => self::BOOL, 'default' => false],
			'is_active' => ['type' => self::BOOL, 'default' => true],
			'display_order' => ['type' => self::UINT, 'default' => 0]
		];
		$structure->getters = [
			'title' => true,
			'description' => true
		];
		$structure->relations = [
			'MasterTitle' => [
				'entity' => 'XF:Phrase',
				'type' => self::TO_ONE,
				'conditions' => [
					['language_id', '=', 0],
					['title', '=', 'ic_crypto_achievement_title.', '$achievement_key']
				]
			],
			'MasterDescription' => [
				'entity' => 'XF:Phrase',
				'type' => self::TO_ONE,
				'conditions' => [
					['language_id', '=', 0],
					['title', '=', 'ic_crypto_achievement_desc.', '$achievement_key']
				]
			]
		];

		return $structure;
	}
}
