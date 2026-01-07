<?php

namespace IC\CryptoMining\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int user_achievement_id
 * @property int user_id
 * @property int achievement_id
 * @property int earned_date
 * @property int xp_awarded
 * @property array|null progress_data
 *
 * RELATIONS
 * @property \XF\Entity\User User
 * @property \IC\CryptoMining\Entity\Achievement Achievement
 */
class UserAchievement extends Entity
{
	protected function _postSave()
	{
		if ($this->isInsert())
		{
			// Send achievement alert - DISABLED until alert handler is set up
			// $this->sendAchievementAlert();
			
			// Award credits if configured
			$this->awardCredits();
		}
	}

	protected function sendAchievementAlert()
	{
		// TODO: Set up proper XenForo alert handler for achievement alerts
		// For now, achievements are awarded silently with credit rewards
		
		/** @var \XF\Repository\UserAlert $alertRepo */
		$alertRepo = $this->repository('XF:UserAlert');
		
		$alertRepo->alert(
			$this->User,
			0,
			'',
			'ic_crypto_achievement',
			$this->user_achievement_id,
			'earned',
			[
				'title' => $this->Achievement->title,
				'xp' => $this->Achievement->xp_points
			]
		);
	}

	protected function awardCredits()
	{
		if (!$this->Achievement->credits_reward)
		{
			return;
		}

		try
		{
			// Get user's crypto wallet
			/** @var \IC\CryptoMining\Repository\Wallet $walletRepo */
			$walletRepo = $this->repository('IC\CryptoMining:Wallet');
			$wallet = $walletRepo->getOrCreateWallet($this->user_id);
			
			// Add credits to wallet cash balance
			$wallet->cash_balance += $this->Achievement->credits_reward;
			$wallet->credits_earned += $this->Achievement->credits_reward;
			$wallet->save();
			
			// Log transaction
			/** @var \IC\CryptoMining\Entity\Transaction $transaction */
			$transaction = $this->em()->create('IC\CryptoMining:Transaction');
			$transaction->user_id = $this->user_id;
			$transaction->transaction_type = 'achievement_reward';
			$transaction->credits = $this->Achievement->credits_reward;
			
			// Use phrased achievement title
			$achievementTitle = \XF::phrase('ic_crypto_achievement_title.' . $this->Achievement->achievement_key)->render();
			
			$transaction->description = sprintf(
				'Achievement earned: %s (+%d credits)',
				$achievementTitle,
				$this->Achievement->credits_reward
			);
			$transaction->transaction_date = \XF::$time;
			$transaction->save();
		}
		catch (\Exception $e)
		{
			\XF::logException($e, false, 'Achievement credits awarding error: ');
		}
	}

	public static function getStructure(Structure $structure)
	{
		$structure->table = 'xf_ic_crypto_user_achievement';
		$structure->shortName = 'IC\CryptoMining:UserAchievement';
		$structure->primaryKey = 'user_achievement_id';
		$structure->columns = [
			'user_achievement_id' => ['type' => self::UINT, 'autoIncrement' => true],
			'user_id' => ['type' => self::UINT, 'required' => true],
			'achievement_id' => ['type' => self::UINT, 'required' => true],
			'earned_date' => ['type' => self::UINT, 'default' => \XF::$time],
			'xp_awarded' => ['type' => self::UINT, 'default' => 0],
			'progress_data' => ['type' => self::JSON_ARRAY, 'nullable' => true]
		];
		$structure->relations = [
			'User' => [
				'entity' => 'XF:User',
				'type' => self::TO_ONE,
				'conditions' => 'user_id',
				'primary' => true
			],
			'Achievement' => [
				'entity' => 'IC\CryptoMining:Achievement',
				'type' => self::TO_ONE,
				'conditions' => 'achievement_id',
				'primary' => true
			]
		];

		return $structure;
	}
}
