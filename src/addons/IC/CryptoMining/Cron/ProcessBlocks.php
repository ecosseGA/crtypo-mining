<?php

namespace IC\CryptoMining\Cron;

/**
 * Block Competition Cron Job - Runs every 6 hours
 * Solves current block and spawns next one
 */
class ProcessBlocks
{
	/**
	 * Process block competition
	 * Called by cron every 6 hours
	 */
	public static function runUpdate()
	{
		try
		{
			$blockRepo = \XF::repository('IC\CryptoMining:Block');
			$currentBlock = $blockRepo->getCurrentBlock();
			
			if (!$currentBlock)
			{
				// No block exists, create first one
				$blockRepo->createNextBlock();
				return;
			}
			
			// Check if block is ready to solve
			// First block (block_number = 1) always solves immediately for testing
			$isFirstBlock = ($currentBlock->block_number == 1);
			
			if ($isFirstBlock || $currentBlock->isReadyToSolve())
			{
				// Solve current block
				$winner = $blockRepo->solveBlock($currentBlock->block_id);
				
				if ($winner)
				{
					// Log successful block solve
					\XF::logError(sprintf(
						'[Block Competition] Block #%d won by User %d with Rig #%d (%s). Hashrate: %.6f',
						$currentBlock->block_number,
						$winner['user_id'],
						$winner['user_rig_id'],
						$winner['rig_name'],
						$winner['effective_hashrate']
					));
				}
				else
				{
					// No competitors
					\XF::logError(sprintf(
						'[Block Competition] Block #%d had no competitors. Block remains unsolved.',
						$currentBlock->block_number
					));
				}
				
				// Create next block
				$blockRepo->createNextBlock();
			}
		}
		catch (\Exception $e)
		{
			\XF::logException($e, false, '[Block Competition Cron Error] ');
		}
	}
}
