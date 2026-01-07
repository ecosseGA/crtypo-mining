<?php

namespace IC\CryptoMining;

use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;
use XF\AddOn\StepRunnerUninstallTrait;
use XF\Db\Schema\Create;
use XF\Db\Schema\Alter;

/**
 * Crypto Mining Simulation - Setup
 * Version: 1.6.0 (Feature #7: Leaderboards)
 */
class Setup extends AbstractSetup
{
	use StepRunnerInstallTrait;
	use StepRunnerUpgradeTrait;
	use StepRunnerUninstallTrait;
	
	// ==================== INSTALL STEPS ====================
	
	/**
	 * Step 1: Create rig types table (16 rigs across 4 tiers)
	 */
	public function installStep1()
	{
		$this->schemaManager()->createTable('xf_ic_crypto_rig_types', function(Create $table)
		{
			$table->addColumn('rig_type_id', 'int')->autoIncrement();
			$table->addColumn('rig_name', 'varchar', 100);
			$table->addColumn('rig_description', 'text')->nullable();
			$table->addColumn('tier', 'tinyint')->setDefault(1); // 1-4 (Budget, Consumer, Pro, Elite)
			$table->addColumn('hash_rate', 'decimal', '10,6'); // BTC per hour
			$table->addColumn('power_consumption', 'decimal', '10,2'); // USD per day
			$table->addColumn('base_cost', 'int')->setDefault(0); // Forum currency
			$table->addColumn('durability_max', 'int')->setDefault(100); // Max durability (in days)
			$table->addColumn('efficiency_rating', 'int')->setDefault(100); // 0-100 scale
			$table->addColumn('required_level', 'int')->setDefault(1); // User level required
			$table->addColumn('image_url', 'varchar', 255)->nullable();
			$table->addColumn('is_active', 'tinyint')->setDefault(1);
			$table->addColumn('sort_order', 'int')->setDefault(0);
			
			$table->addPrimaryKey('rig_type_id');
			$table->addKey('is_active');
			$table->addKey('tier');
			$table->addKey('required_level');
			$table->addKey('sort_order');
		});
	}
	
	/**
	 * Step 2: Create user rigs table (purchased rigs)
	 */
	public function installStep2()
	{
		$this->schemaManager()->createTable('xf_ic_crypto_user_rigs', function(Create $table)
		{
			$table->addColumn('user_rig_id', 'int')->autoIncrement();
			$table->addColumn('user_id', 'int');
			$table->addColumn('rig_type_id', 'int');
			$table->addColumn('purchase_date', 'int');
			$table->addColumn('purchase_price', 'int');
			$table->addColumn('current_durability', 'decimal', '5,2')->setDefault(100.00); // 0.00-100.00
			$table->addColumn('upgrade_level', 'tinyint')->setDefault(0); // 0-5 upgrades
			$table->addColumn('is_active', 'tinyint')->setDefault(1); // Mining on/off
			$table->addColumn('last_mined', 'int'); // Timestamp of last mining payout
			$table->addColumn('total_mined', 'decimal', '12,6')->setDefault(0); // Total BTC mined by this rig
			
			// Phase 3: Custom builder support (future-proof)
			$table->addColumn('is_custom', 'tinyint')->setDefault(0); // 0 = pre-built, 1 = custom
			$table->addColumn('custom_build_id', 'int')->nullable(); // Future: link to custom build
			
			$table->addPrimaryKey('user_rig_id');
			$table->addKey('user_id');
			$table->addKey('rig_type_id');
			$table->addKey(['is_active', 'user_id']);
			$table->addKey('last_mined');
		});
	}
	
	/**
	 * Step 3: Create wallet table (user crypto balances)
	 */
	public function installStep3()
	{
		$this->schemaManager()->createTable('xf_ic_crypto_wallet', function(Create $table)
		{
			$table->addColumn('wallet_id', 'int')->autoIncrement();
			$table->addColumn('user_id', 'int');
			$table->addColumn('crypto_balance', 'decimal', '12,6')->setDefault(0); // Current BTC balance
			$table->addColumn('total_mined', 'decimal', '12,6')->setDefault(0); // Lifetime mined
			$table->addColumn('total_sold', 'decimal', '12,6')->setDefault(0); // Lifetime sold
			$table->addColumn('total_bought', 'decimal', '12,6')->setDefault(0); // Lifetime bought
			$table->addColumn('credits_earned', 'int')->setDefault(0); // From selling crypto
			$table->addColumn('credits_spent', 'int')->setDefault(0); // On rigs/upgrades/repairs
			$table->addColumn('last_mining_payout', 'int')->nullable(); // Last time any rig mined
			$table->addColumn('created_date', 'int');
			
			$table->addPrimaryKey('wallet_id');
			$table->addUniqueKey('user_id');
		});
	}
	
	/**
	 * Step 4: Create market table (crypto prices)
	 */
	public function installStep4()
	{
		$this->schemaManager()->createTable('xf_ic_crypto_market', function(Create $table)
		{
			$table->addColumn('market_id', 'int')->autoIncrement();
			$table->addColumn('crypto_name', 'varchar', 50)->setDefault('Bitcoin');
			$table->addColumn('current_price', 'decimal', '12,2'); // USD per BTC
			$table->addColumn('previous_price', 'decimal', '12,2')->nullable();
			$table->addColumn('price_change_percent', 'decimal', '6,3')->nullable(); // % change
			$table->addColumn('daily_volume', 'decimal', '15,2')->setDefault(0);
			$table->addColumn('market_cap', 'decimal', '18,2')->nullable();
			$table->addColumn('last_updated', 'int');
			
			$table->addPrimaryKey('market_id');
			$table->addKey('crypto_name');
			$table->addKey('last_updated');
		});
	}
	
	/**
	 * Step 5: Create market history table (for charts)
	 */
	public function installStep5()
	{
		$this->schemaManager()->createTable('xf_ic_crypto_market_history', function(Create $table)
		{
			$table->addColumn('history_id', 'int')->autoIncrement();
			$table->addColumn('crypto_name', 'varchar', 50)->setDefault('Bitcoin');
			$table->addColumn('price', 'decimal', '12,2');
			$table->addColumn('recorded_date', 'int');
			
			$table->addPrimaryKey('history_id');
			$table->addKey(['crypto_name', 'recorded_date']);
		});
	}
	
	/**
	 * Step 6: Create transactions table (all buy/sell/repair/upgrade)
	 */
	public function installStep6()
	{
		$this->schemaManager()->createTable('xf_ic_crypto_transactions', function(Create $table)
		{
			$table->addColumn('transaction_id', 'int')->autoIncrement();
			$table->addColumn('user_id', 'int');
			$table->addColumn('transaction_type', 'varchar', 50); // buy_rig, sell_crypto, buy_crypto, repair, upgrade, power_cost, mining_reward, trade_buy, trade_sell, scrapyard_dump, scrapyard_claim
			$table->addColumn('amount', 'decimal', '12,6')->nullable(); // Crypto amount
			$table->addColumn('credits', 'int')->nullable()->unsigned(false); // Forum currency (can be negative for costs!)
			$table->addColumn('price_per_unit', 'decimal', '12,2')->nullable(); // USD per BTC at time
			$table->addColumn('description', 'text')->nullable();
			$table->addColumn('transaction_date', 'int');
			
			// Phase 2: Trading support (future-proof)
			$table->addColumn('related_user_id', 'int')->nullable(); // For trades with other users
			$table->addColumn('rig_type_id', 'int')->nullable(); // Which rig was involved
			
			$table->addPrimaryKey('transaction_id');
			$table->addKey('user_id');
			$table->addKey('transaction_type');
			$table->addKey('transaction_date');
			$table->addKey('related_user_id'); // For Phase 2 trading
		});
	}
	
	/**
	 * Step 7: Create market events table (bull runs, crashes, etc.)
	 */
	public function installStep7()
	{
		$this->schemaManager()->createTable('xf_ic_crypto_market_events', function(Create $table)
		{
			$table->addColumn('event_id', 'int')->autoIncrement();
			$table->addColumn('event_type', 'varchar', 50); // bull_run, crash, halving, difficulty_increase, regulation, neutral
			$table->addColumn('event_title', 'varchar', 200);
			$table->addColumn('event_description', 'text')->nullable();
			$table->addColumn('price_impact_percent', 'decimal')->length(10, 3); // +/- percent
			$table->addColumn('duration_hours', 'int')->setDefault(24);
			$table->addColumn('is_active', 'tinyint')->setDefault(0);
			$table->addColumn('triggered_date', 'int')->nullable();
			
			$table->addPrimaryKey('event_id');
			$table->addKey('is_active');
			$table->addKey('triggered_date');
		});
	}
	
	/**
	 * Step 8: Create leaderboard table (cached rankings)
	 */
	public function installStep8()
	{
		$this->schemaManager()->createTable('xf_ic_crypto_leaderboard', function(Create $table)
		{
			$table->addColumn('leaderboard_id', 'int')->autoIncrement();
			$table->addColumn('user_id', 'int');
			$table->addColumn('leaderboard_type', 'varchar', 50); // richest, most_mined, most_efficient, most_rigs
			$table->addColumn('rank_position', 'int');
			$table->addColumn('stat_value', 'decimal', '15,6');
			$table->addColumn('last_updated', 'int');
			
			$table->addPrimaryKey('leaderboard_id');
			$table->addUniqueKey(['leaderboard_type', 'user_id']);
			$table->addKey(['leaderboard_type', 'rank_position']);
		});
	}
	
	/**
	 * Step 9: Populate initial data (16 rigs, Bitcoin market, events)
	 */
	public function installStep9()
	{
		$db = $this->db();
		
		// ========== TIER 1: BUDGET MINING (4 rigs) ==========
		$db->insert('xf_ic_crypto_rig_types', [
			'rig_name' => 'USB ASIC Miner',
			'rig_description' => 'Entry-level USB mining stick. Perfect for beginners!',
			'tier' => 1,
			'hash_rate' => 0.0001,
			'power_consumption' => 5.00,
			'base_cost' => 100,
			'durability_max' => 30,
			'efficiency_rating' => 50,
			'required_level' => 1,
			'is_active' => 1,
			'sort_order' => 1
		]);
		
		$db->insert('xf_ic_crypto_rig_types', [
			'rig_name' => 'Raspberry Pi Miner',
			'rig_description' => 'DIY mining setup using a Raspberry Pi. Low power, low output.',
			'tier' => 1,
			'hash_rate' => 0.0003,
			'power_consumption' => 8.00,
			'base_cost' => 250,
			'durability_max' => 45,
			'efficiency_rating' => 60,
			'required_level' => 1,
			'is_active' => 1,
			'sort_order' => 2
		]);
		
		$db->insert('xf_ic_crypto_rig_types', [
			'rig_name' => 'GPU Solo Rig',
			'rig_description' => 'Single GPU mining rig. Your first real mining setup.',
			'tier' => 1,
			'hash_rate' => 0.0008,
			'power_consumption' => 15.00,
			'base_cost' => 500,
			'durability_max' => 60,
			'efficiency_rating' => 70,
			'required_level' => 1,
			'is_active' => 1,
			'sort_order' => 3
		]);
		
		$db->insert('xf_ic_crypto_rig_types', [
			'rig_name' => 'Basic ASIC S9',
			'rig_description' => 'Classic Antminer S9. Reliable workhorse for budget miners.',
			'tier' => 1,
			'hash_rate' => 0.0015,
			'power_consumption' => 20.00,
			'base_cost' => 800,
			'durability_max' => 75,
			'efficiency_rating' => 75,
			'required_level' => 2,
			'is_active' => 1,
			'sort_order' => 4
		]);
		
		// ========== TIER 2: CONSUMER GRADE (4 rigs) ==========
		$db->insert('xf_ic_crypto_rig_types', [
			'rig_name' => 'Dual GPU Rig',
			'rig_description' => 'Two GPUs working together. Double the power!',
			'tier' => 2,
			'hash_rate' => 0.003,
			'power_consumption' => 30.00,
			'base_cost' => 1500,
			'durability_max' => 90,
			'efficiency_rating' => 80,
			'required_level' => 3,
			'is_active' => 1,
			'sort_order' => 5
		]);
		
		$db->insert('xf_ic_crypto_rig_types', [
			'rig_name' => 'Antminer S17',
			'rig_description' => 'Popular mid-range ASIC. Great balance of cost and performance.',
			'tier' => 2,
			'hash_rate' => 0.006,
			'power_consumption' => 50.00,
			'base_cost' => 2500,
			'durability_max' => 120,
			'efficiency_rating' => 85,
			'required_level' => 4,
			'is_active' => 1,
			'sort_order' => 6
		]);
		
		$db->insert('xf_ic_crypto_rig_types', [
			'rig_name' => 'Quad GPU Farm',
			'rig_description' => 'Four GPUs in a single frame. Serious mining begins here.',
			'tier' => 2,
			'hash_rate' => 0.010,
			'power_consumption' => 75.00,
			'base_cost' => 4000,
			'durability_max' => 120,
			'efficiency_rating' => 85,
			'required_level' => 5,
			'is_active' => 1,
			'sort_order' => 7
		]);
		
		$db->insert('xf_ic_crypto_rig_types', [
			'rig_name' => 'Whatsminer M20',
			'rig_description' => 'High-performance ASIC from MicroBT. Efficient and powerful.',
			'tier' => 2,
			'hash_rate' => 0.015,
			'power_consumption' => 100.00,
			'base_cost' => 6000,
			'durability_max' => 150,
			'efficiency_rating' => 90,
			'required_level' => 6,
			'is_active' => 1,
			'sort_order' => 8
		]);
		
		// ========== TIER 3: PROFESSIONAL (4 rigs) ==========
		$db->insert('xf_ic_crypto_rig_types', [
			'rig_name' => '6x GPU Mining Rig',
			'rig_description' => 'Professional 6-GPU setup. Built for serious miners.',
			'tier' => 3,
			'hash_rate' => 0.025,
			'power_consumption' => 150.00,
			'base_cost' => 10000,
			'durability_max' => 180,
			'efficiency_rating' => 90,
			'required_level' => 7,
			'is_active' => 1,
			'sort_order' => 9
		]);
		
		$db->insert('xf_ic_crypto_rig_types', [
			'rig_name' => 'Antminer S19',
			'rig_description' => 'Industry-leading ASIC. The gold standard of mining.',
			'tier' => 3,
			'hash_rate' => 0.040,
			'power_consumption' => 200.00,
			'base_cost' => 15000,
			'durability_max' => 200,
			'efficiency_rating' => 95,
			'required_level' => 8,
			'is_active' => 1,
			'sort_order' => 10
		]);
		
		$db->insert('xf_ic_crypto_rig_types', [
			'rig_name' => '8x GPU Beast',
			'rig_description' => 'Eight GPUs running in harmony. A mining powerhouse.',
			'tier' => 3,
			'hash_rate' => 0.060,
			'power_consumption' => 250.00,
			'base_cost' => 20000,
			'durability_max' => 200,
			'efficiency_rating' => 95,
			'required_level' => 9,
			'is_active' => 1,
			'sort_order' => 11
		]);
		
		$db->insert('xf_ic_crypto_rig_types', [
			'rig_name' => 'AvalonMiner 1246',
			'rig_description' => 'High-end ASIC from Canaan. Professional-grade equipment.',
			'tier' => 3,
			'hash_rate' => 0.080,
			'power_consumption' => 300.00,
			'base_cost' => 25000,
			'durability_max' => 250,
			'efficiency_rating' => 95,
			'required_level' => 10,
			'is_active' => 1,
			'sort_order' => 12
		]);
		
		// ========== TIER 4: ELITE (4 rigs) ==========
		$db->insert('xf_ic_crypto_rig_types', [
			'rig_name' => '12x GPU Powerhouse',
			'rig_description' => 'Twelve GPUs working as one. Elite mining operation.',
			'tier' => 4,
			'hash_rate' => 0.120,
			'power_consumption' => 450.00,
			'base_cost' => 40000,
			'durability_max' => 300,
			'efficiency_rating' => 98,
			'required_level' => 12,
			'is_active' => 1,
			'sort_order' => 13
		]);
		
		$db->insert('xf_ic_crypto_rig_types', [
			'rig_name' => 'Antminer S19 XP',
			'rig_description' => 'The ultimate ASIC. Top-tier performance and efficiency.',
			'tier' => 4,
			'hash_rate' => 0.180,
			'power_consumption' => 600.00,
			'base_cost' => 60000,
			'durability_max' => 350,
			'efficiency_rating' => 98,
			'required_level' => 14,
			'is_active' => 1,
			'sort_order' => 14
		]);
		
		$db->insert('xf_ic_crypto_rig_types', [
			'rig_name' => 'Industrial Container Farm',
			'rig_description' => 'Complete mining facility in a shipping container. Industrial scale.',
			'tier' => 4,
			'hash_rate' => 0.250,
			'power_consumption' => 800.00,
			'base_cost' => 80000,
			'durability_max' => 400,
			'efficiency_rating' => 99,
			'required_level' => 16,
			'is_active' => 1,
			'sort_order' => 15
		]);
		
		$db->insert('xf_ic_crypto_rig_types', [
			'rig_name' => 'Quantum Mining Array',
			'rig_description' => 'Legendary cutting-edge technology. The pinnacle of mining.',
			'tier' => 4,
			'hash_rate' => 0.350,
			'power_consumption' => 1000.00,
			'base_cost' => 100000,
			'durability_max' => 500,
			'efficiency_rating' => 100,
			'required_level' => 20,
			'is_active' => 1,
			'sort_order' => 16
		]);
		
		// ========== INITIAL BITCOIN MARKET DATA ==========
		$db->insert('xf_ic_crypto_market', [
			'crypto_name' => 'Bitcoin',
			'current_price' => 50000.00,
			'previous_price' => 50000.00,
			'price_change_percent' => 0.000,
			'daily_volume' => 0,
			'market_cap' => null,
			'last_updated' => \XF::$time
		]);
		
		// Record initial price history
		$db->insert('xf_ic_crypto_market_history', [
			'crypto_name' => 'Bitcoin',
			'price' => 50000.00,
			'recorded_date' => \XF::$time
		]);
		
		// ========== MARKET EVENTS ==========
		$db->insert('xf_ic_crypto_market_events', [
			'event_type' => 'bull_run',
			'event_title' => 'Bitcoin ETF Approved!',
			'event_description' => 'Major institutional investment approved by regulators',
			'price_impact_percent' => '20.000',
			'duration_hours' => 48,
			'is_active' => 0
		]);
		
		$db->insert('xf_ic_crypto_market_events', [
			'event_type' => 'crash',
			'event_title' => 'Regulatory Crackdown',
			'event_description' => 'Government announces stricter cryptocurrency regulations',
			'price_impact_percent' => '-15.000',
			'duration_hours' => 72,
			'is_active' => 0
		]);
		
		$db->insert('xf_ic_crypto_market_events', [
			'event_type' => 'halving',
			'event_title' => 'Bitcoin Halving Event',
			'event_description' => 'Block rewards cut in half - scarcity increases',
			'price_impact_percent' => '10.000',
			'duration_hours' => 168,
			'is_active' => 0
		]);
		
		$db->insert('xf_ic_crypto_market_events', [
			'event_type' => 'difficulty_increase',
			'event_title' => 'Mining Difficulty Spike',
			'event_description' => 'Network difficulty adjustment makes mining harder',
			'price_impact_percent' => '-5.000',
			'duration_hours' => 24,
			'is_active' => 0
		]);
		
		$db->insert('xf_ic_crypto_market_events', [
			'event_type' => 'regulation',
			'event_title' => 'New KYC Requirements',
			'event_description' => 'Exchanges must implement identity verification',
			'price_impact_percent' => '-8.000',
			'duration_hours' => 48,
			'is_active' => 0
		]);
	}
	
	/**
	 * Step 10: Create blocks table for competition system
	 */
	public function installStep10()
	{
		$this->schemaManager()->createTable('xf_ic_crypto_blocks', function(Create $table)
		{
			$table->addColumn('block_id', 'int')->autoIncrement();
			$table->addColumn('block_number', 'int')->setDefault(0);
			$table->addColumn('block_reward', 'decimal', '10,6')->setDefault(0.01);
			$table->addColumn('total_hashrate', 'decimal', '12,6')->setDefault(0);
			$table->addColumn('winner_user_id', 'int')->nullable();
			$table->addColumn('winner_rig_id', 'int')->nullable();
			$table->addColumn('solved_date', 'int')->nullable();
			$table->addColumn('spawned_date', 'int')->setDefault(0);
			$table->addColumn('is_solved', 'tinyint')->setDefault(0);
			
			$table->addPrimaryKey('block_id');
			$table->addKey(['is_solved']);
			$table->addKey(['solved_date']);
			$table->addKey(['block_number']);
		});
		
		// Create initial block
		$this->db()->insert('xf_ic_crypto_blocks', [
			'block_number' => 1,
			'block_reward' => 0.01,
			'total_hashrate' => 0,
			'spawned_date' => \XF::$time,
			'is_solved' => 0
		]);
	}
	
	/**
	 * Step 11: Create mining grid state table
	 * Stores current state of 10x10 expedition grid (100 blocks)
	 */
	public function installStep11()
	{
		$this->schemaManager()->createTable('xf_ic_crypto_grid_state', function(Create $table)
		{
			$table->addColumn('grid_block_id', 'int')->autoIncrement();
			$table->addColumn('generation_id', 'int')->setDefault(1);
			$table->addColumn('position', 'tinyint')->setDefault(0); // 0-99 (10x10 grid)
			$table->addColumn('block_type', 'enum', ['jackpot', 'rich_vein', 'standard', 'weak_vein', 'collapse']);
			$table->addColumn('btc_value', 'decimal', '10,6')->setDefault(0);
			$table->addColumn('durability_cost', 'decimal', '5,2')->setDefault(0);
			$table->addColumn('is_mined', 'tinyint')->setDefault(0);
			$table->addColumn('mined_by_user_id', 'int')->nullable();
			$table->addColumn('mined_date', 'int')->nullable();
			
			$table->addPrimaryKey('grid_block_id');
			$table->addKey(['generation_id', 'position']);
			$table->addKey(['is_mined']);
		});
	}
	
	/**
	 * Step 12: Create grid generations table
	 * Tracks each grid cycle (regenerates every 6 hours)
	 */
	public function installStep12()
	{
		$this->schemaManager()->createTable('xf_ic_crypto_grid_generations', function(Create $table)
		{
			$table->addColumn('generation_id', 'int')->autoIncrement();
			$table->addColumn('created_date', 'int')->setDefault(0);
			$table->addColumn('ended_date', 'int')->nullable();
			$table->addColumn('total_mined', 'int')->setDefault(0);
			$table->addColumn('total_jackpots', 'int')->setDefault(0);
			$table->addColumn('total_collapses', 'int')->setDefault(0);
			$table->addColumn('is_active', 'tinyint')->setDefault(1);
			
			$table->addPrimaryKey('generation_id');
			$table->addKey(['is_active']);
			$table->addKey(['created_date']);
		});
	}
	
	/**
	 * Step 13: Create grid mining history table
	 * Records every mining attempt for stats/history
	 */
	public function installStep13()
	{
		$this->schemaManager()->createTable('xf_ic_crypto_grid_mines', function(Create $table)
		{
			$table->addColumn('mine_id', 'int')->autoIncrement();
			$table->addColumn('user_id', 'int')->setDefault(0);
			$table->addColumn('rig_id', 'int')->setDefault(0);
			$table->addColumn('generation_id', 'int')->setDefault(0);
			$table->addColumn('position', 'tinyint')->setDefault(0);
			$table->addColumn('block_type', 'enum', ['jackpot', 'rich_vein', 'standard', 'weak_vein', 'collapse']);
			$table->addColumn('btc_earned', 'decimal', '10,6')->setDefault(0);
			$table->addColumn('durability_lost', 'decimal', '5,2')->setDefault(0);
			$table->addColumn('credits_spent', 'int')->setDefault(100); // Base cost
			$table->addColumn('used_scout', 'tinyint')->setDefault(0);
			$table->addColumn('used_insurance', 'tinyint')->setDefault(0);
			$table->addColumn('mined_date', 'int')->setDefault(0);
			
			$table->addPrimaryKey('mine_id');
			$table->addKey(['user_id']);
			$table->addKey(['generation_id']);
			$table->addKey(['mined_date']);
		});
	}
	
	/**
	 * Step 14: Create user buffs table
	 * Tracks active buffs (Lucky Pickaxe, etc.)
	 */
	public function installStep14()
	{
		$this->schemaManager()->createTable('xf_ic_crypto_user_buffs', function(Create $table)
		{
			$table->addColumn('buff_id', 'int')->autoIncrement();
			$table->addColumn('user_id', 'int')->setDefault(0);
			$table->addColumn('buff_type', 'enum', ['lucky_pickaxe', 'double_rewards', 'iron_will']);
			$table->addColumn('buff_value', 'decimal', '5,2')->setDefault(0); // e.g., 10 for +10%
			$table->addColumn('started_date', 'int')->setDefault(0);
			$table->addColumn('expires_date', 'int')->setDefault(0);
			$table->addColumn('is_active', 'tinyint')->setDefault(1);
			
			$table->addPrimaryKey('buff_id');
			$table->addKey(['user_id', 'is_active']);
			$table->addKey(['expires_date']);
		});
	}
	
	// ==================== UPGRADE STEPS ====================
	
	/**
	 * Upgrade to v1.0.5 - Template and styling improvements
	 * No database changes needed, just template updates
	 */
	public function upgrade1000005Step1()
	{
		// Template changes only - XenForo will auto-import from _data
		return true;
	}
	
	/**
	 * Upgrade to v1.0.6 - Complete template rewrite with custom classes
	 * Removes all XenForo structural dependencies to prevent theme conflicts
	 */
	public function upgrade1000006Step1()
	{
		// Template changes only - XenForo will auto-import from _data
		return true;
	}
	
	/**
	 * Upgrade to v1.0.7 - Template import fix
	 * Ensures all three templates (dashboard, shop, buy) get imported
	 */
	public function upgrade1000007Step1()
	{
		// Template changes only - XenForo will auto-import from _data
		return true;
	}
	
	/**
	 * Upgrade to v1.0.8 - Mining cron system
	 * Adds passive BTC generation for active rigs
	 * Cron entry imported from _data/cron_entries.xml
	 */
	public function upgrade1000008Step1()
	{
		// Cron entry imported automatically from _data
		return true;
	}
	
	/**
	 * Upgrade to v1.0.9 - Cron XML filename fix
	 * Corrects cron_entries.xml to cron.xml (proper XenForo filename)
	 */
	public function upgrade1000009Step1()
	{
		// Cron entry imported automatically from _data/cron.xml
		return true;
	}
	
	/**
	 * Upgrade to v1.1.0 - Cron XML structure fix
	 * Corrects run_rules from attribute to child element (XenForo requirement)
	 */
	public function upgrade1001000Step1()
	{
		// Cron entry imported automatically from _data/cron.xml
		return true;
	}
	
	/**
	 * Upgrade to v1.1.1 - Cron XML format correction
	 * Uses proper XenForo cron format: run_rules as text content, not child element
	 */
	public function upgrade1001001Step1()
	{
		// Cron entry imported automatically from _data/cron.xml
		return true;
	}
	
	/**
	 * Upgrade to v1.1.2 - Fix credits column to allow negative values
	 * Power costs need to be stored as negative credits
	 */
	public function upgrade1001002Step1()
	{
		// Change credits column from UNSIGNED to SIGNED to allow negative values
		$this->schemaManager()->alterTable('xf_ic_crypto_transactions', function(\XF\Db\Schema\Alter $table)
		{
			$table->changeColumn('credits', 'int')->nullable()->unsigned(false);
		});
		
		return true;
	}
	
	/**
	 * Upgrade to v1.1.3 - Repair system
	 * Adds repair action, route, and template
	 * No database changes needed - uses existing tables
	 */
	public function upgrade1001003Step1()
	{
		// Routes and templates imported automatically from _data
		return true;
	}
	
	/**
	 * Upgrade to v1.1.4 - Fix routes and cron logging
	 * Corrects routes.xml structure (all routes need route_type)
	 * Removes success logging from cron (only log errors)
	 */
	public function upgrade1001004Step1()
	{
		// Routes imported automatically from _data/routes.xml
		return true;
	}
	
	/**
	 * Upgrade to v1.1.5 - Repair route fix and wallet auto-creation
	 * Changes repair route from sub_name to separate route prefix
	 * Adds auto-creation of wallet in cron if missing
	 */
	public function upgrade1001005Step1()
	{
		// Routes and templates imported automatically from _data
		return true;
	}
	
	/**
	 * Upgrade to v1.1.6 - Transaction display fix and debug logging
	 * Fixes transaction description to show decimal power costs
	 * Adds temporary debug logging to track power cost deductions
	 */
	public function upgrade1001006Step1()
	{
		// No database changes, just code updates
		return true;
	}
	
	/**
	 * Upgrade to v1.1.7 - Fix syntax error in UpdateMining.php
	 * Removes duplicate sprintf parameters that caused parse error
	 */
	public function upgrade1001007Step1()
	{
		// Code fix only
		return true;
	}
	
	/**
	 * Upgrade to v1.1.8 - Separate Repair controller
	 * Creates dedicated Repair controller instead of using Dashboard::actionRepair()
	 * Fixes route so /crypto-repair/X actually shows repair page
	 */
	public function upgrade1001008Step1()
	{
		// New controller file and updated route imported from _data
		return true;
	}
	
	/**
	 * Upgrade to v1.2.0 - Upgrade System (Feature #3)
	 * Adds upgrade controller, route, and template
	 * Users can now upgrade rigs 5 times for +10% hash rate per level
	 * Cost scales: 20%, 40%, 60%, 80%, 100% of base rig cost
	 */
	public function upgrade1002000Step1()
	{
		// New Upgrade controller and updated templates/routes imported from _data
		return true;
	}
	
	/**
	 * Upgrade to v1.2.1 - Upgrade System UI Fixes
	 * Fixes: Removed duplicate upgrade button, hash rate now shows upgraded value
	 */
	public function upgrade1002001Step1()
	{
		// Updated dashboard template to fix hash rate display
		return true;
	}
	
	/**
	 * Upgrade to v1.2.2 - Fix Output Display
	 * Fixes: Output line in rig stats now shows upgraded hash rate (was showing 0.000100 instead of 0.000150)
	 */
	public function upgrade1002002Step1()
	{
		// Updated dashboard template Output display line
		return true;
	}
	
	/**
	 * Upgrade to v1.3.0 - Toggle Active/Inactive (Feature #4)
	 * Adds toggle controller, route, and button to pause/resume mining
	 * When paused: No mining, no power costs
	 * When active: Normal mining resumes
	 */
	public function upgrade1003000Step1()
	{
		// New Toggle controller and updated templates/routes imported from _data
		return true;
	}
	
	/**
	 * Upgrade to v1.3.1 - Remove Duplicate Toggle Button
	 * Fixes: Removed old disabled toggle placeholder button
	 */
	public function upgrade1003001Step1()
	{
		// Updated dashboard template to remove old toggle button
		return true;
	}
	
	/**
	 * Upgrade to v1.4.0 - Marketplace (Feature #5)
	 * Adds marketplace controller, route, and template
	 * Users can sell mined BTC for credits
	 * Formula: BTC × Price = USD = Credits
	 */
	public function upgrade1004000Step1()
	{
		// New Market controller and updated templates/routes imported from _data
		return true;
	}
	
	/**
	 * Upgrade to v1.5.0 - Durability Degradation (Feature #6)
	 * Rigs now degrade 1% per day automatically
	 * At <50% durability: 25% output reduction
	 * At <25% durability: 50% output reduction
	 * At 0% durability: Rig stops mining completely
	 */
	public function upgrade1005000Step1()
	{
		// Updated cron to reduce durability over time
		return true;
	}
	
	/**
	 * Upgrade to v1.5.1 - Navigation Phrases + Admin Panel
	 * Fixes: Navigation items now show proper titles
	 * Fixes: Cron has proper title/description
	 * Added: Admin settings page for configuration
	 * Added: Options imported from option_groups.xml and options.xml
	 */
	public function upgrade1005001Step1()
	{
		// Phrases, cron title, admin navigation, and options imported from _data
		return true;
	}
	
	/**
	 * Upgrade to v1.6.0 - LEADERBOARDS (Feature #7) 🏆
	 * Added: 4 leaderboard types (richest, most_mined, most_efficient, most_rigs)
	 * Added: Leaderboard page (/crypto-leaderboard)
	 * Added: UpdateLeaderboard cron (runs hourly)
	 * Added: Cached rankings for performance
	 * Added: User rank display
	 * Added: Top 100 rankings
	 * Added: Medal badges for top 3
	 */
	public function upgrade1006000Step1()
	{
		// Leaderboard table already exists from installStep8
		// Route, navigation, and phrases imported from _data
		// Cron entry imported from _data
		// Leaderboard will populate on first cron run
		
		return true;
	}
	
	/**
	 * Upgrade to v1.6.1 - Fix admin options and increase degradation challenge
	 * - Admin navigation now points to proper options group
	 * - Degradation rate increased to 4% per day (via options.xml default)
	 * - Cron logging enabled (via options.xml default)
	 */
	public function upgrade1006001Step1()
	{
		// Options are set via XML defaults
		// No need to call cron during upgrade
		return true;
	}
	
	/**
	 * Upgrade to v1.7.0 - Block Competition System
	 * - Adds competitive block mining every 6 hours
	 * - Weighted probability based on hashrate
	 * - Winner gets bonus BTC reward
	 * - New leaderboard type: Block Champion
	 */
	public function upgrade1070000Step1()
	{
		// Create blocks table
		$this->schemaManager()->createTable('xf_ic_crypto_blocks', function(Create $table)
		{
			$table->addColumn('block_id', 'int')->autoIncrement();
			$table->addColumn('block_number', 'int')->setDefault(0);
			$table->addColumn('block_reward', 'decimal', '10,6')->setDefault(0.01);
			$table->addColumn('total_hashrate', 'decimal', '12,6')->setDefault(0);
			$table->addColumn('winner_user_id', 'int')->nullable();
			$table->addColumn('winner_rig_id', 'int')->nullable();
			$table->addColumn('solved_date', 'int')->nullable();
			$table->addColumn('spawned_date', 'int')->setDefault(0);
			$table->addColumn('is_solved', 'tinyint')->setDefault(0);
			
			$table->addPrimaryKey('block_id');
			$table->addKey(['is_solved']);
			$table->addKey(['solved_date']);
			$table->addKey(['block_number']);
		});
		
		// Create initial block
		$this->db()->insert('xf_ic_crypto_blocks', [
			'block_number' => 1,
			'block_reward' => 0.01,
			'total_hashrate' => 0,
			'spawned_date' => \XF::$time,
			'is_solved' => 0
		]);
		
		// Cron entry will be imported from _data/cron.xml
		return true;
	}
	
	/**
	 * Upgrade to v1.7.2 - Stock portfolio fix, color scheme standardization, UI polish
	 */
	public function upgrade1070200Step1()
	{
		// No database changes needed
		// This version fixes:
		// - Stock portfolio display (now reads portfolio_value from xf_ic_sm_account)
		// - Unified color scheme across all pages
		// - Navigation consistency
		
		// Rebuild templates to apply color scheme updates
		\XF::runOnce('rebuildCryptoTemplates', function()
		{
			\XF::app()->jobManager()->enqueueUnique('templateRebuild', 'XF:TemplateRebuild', [
				'addon' => 'IC/CryptoMining'
			]);
		});
		
		return true;
	}
	
	/**
	 * Upgrade to 1.8.2 - Market Fluctuations
	 * Add initial price history for chart
	 */
	public function upgrade1080200Step1()
	{
		$db = $this->db();
		
		// Check if price history is empty
		$count = $db->fetchOne("SELECT COUNT(*) FROM xf_ic_crypto_market_history");
		
		if ($count == 0)
		{
			// Add 30 days of historical data with realistic fluctuations
			$now = \XF::$time;
			$basePrice = 50000;
			
			for ($i = 30; $i >= 0; $i--)
			{
				$date = $now - ($i * 86400); // Go back i days
				
				// Simulate realistic price movement
				$randomChange = (mt_rand(-300, 300) / 10000); // ±3%
				$basePrice = $basePrice * (1 + $randomChange);
				
				// Clamp to reasonable bounds
				$basePrice = max(45000, min(55000, $basePrice));
				
				$db->insert('xf_ic_crypto_market_history', [
					'crypto_name' => 'Bitcoin',
					'price' => round($basePrice, 2),
					'recorded_date' => $date
				]);
			}
		}
	}
	
	/**
	 * Upgrade to 1.8.2.1 - Force template and cron import
	 * Fixes template error and ensures market update cron is imported
	 */
	public function upgrade1080201Step1()
	{
		// Force template rebuild - XenForo will automatically re-import
		// all _data/*.xml files including cron entries during this process
		$this->app()->jobManager()->enqueueUnique('templateRebuild', 'XF:TemplateRebuild', [
			'addon' => 'IC/CryptoMining'
		]);
		
		return true;
	}
	
	/**
	 * Upgrade to 1.8.2.2 - Fix closure serialization error from 1.8.2.1
	 * Same as 1.8.2.1 but without the broken closure
	 */
	public function upgrade1080202Step1()
	{
		// Force template rebuild - XenForo automatically re-imports
		// all _data/*.xml files (including cron entries) during upgrade
		$this->app()->jobManager()->enqueueUnique('templateRebuild', 'XF:TemplateRebuild', [
			'addon' => 'IC/CryptoMining'
		]);
		
		return true;
	}
	
	/**
	 * Upgrade to 1.8.3 - Market Fluctuations with proper file names
	 * Fixes: Template array_column error + cron.xml (not cron_entries.xml)
	 */
	public function upgrade1080300Step1()
	{
		// Seed 30 days of price history if needed
		$db = $this->db();
		$historyCount = $db->fetchOne("SELECT COUNT(*) FROM xf_ic_crypto_market_history");
		
		if ($historyCount == 0)
		{
			// Seed 30 days of historical data
			$basePrice = 50000;
			for ($i = 30; $i >= 0; $i--)
			{
				$date = \XF::$time - ($i * 86400);
				$variation = (mt_rand(-300, 300) / 100); // ±3%
				$price = $basePrice * (1 + $variation);
				$price = max(45000, min(55000, $price));
				
				$db->insert('xf_ic_crypto_market_history', [
					'crypto_name' => 'Bitcoin',
					'price' => $price,
					'recorded_date' => $date
				]);
			}
		}
		
		return true;
	}
	
	/**
	 * Upgrade to 1.9.0 Step 1 - Create achievements table
	 */
	public function upgrade1090000Step1()
	{
		$this->schemaManager()->createTable('xf_ic_crypto_achievement', function(Create $table)
		{
			$table->addColumn('achievement_id', 'int')->autoIncrement();
			$table->addColumn('achievement_key', 'varchar', 50);
			$table->addColumn('achievement_category', 'varchar', 50);
			$table->addColumn('xp_points', 'int')->setDefault(10);
			$table->addColumn('difficulty_tier', 'varchar', 20)->setDefault('easy');
			$table->addColumn('credits_reward', 'int')->setDefault(0);
			$table->addColumn('is_repeatable', 'tinyint')->setDefault(0);
			$table->addColumn('is_active', 'tinyint')->setDefault(1);
			$table->addColumn('display_order', 'int')->setDefault(0);
			$table->addPrimaryKey('achievement_id');
			$table->addUniqueKey('achievement_key');
			$table->addKey('achievement_category');
			$table->addKey('is_active');
		});
		
		return true;
	}
	
	/**
	 * Upgrade to 1.9.0 Step 2 - Create user achievements table
	 */
	public function upgrade1090000Step2()
	{
		$this->schemaManager()->createTable('xf_ic_crypto_user_achievement', function(Create $table)
		{
			$table->addColumn('user_achievement_id', 'int')->autoIncrement();
			$table->addColumn('user_id', 'int');
			$table->addColumn('achievement_id', 'int');
			$table->addColumn('earned_date', 'int')->setDefault(0);
			$table->addColumn('xp_awarded', 'int')->setDefault(0);
			$table->addColumn('progress_data', 'mediumblob')->nullable();
			$table->addPrimaryKey('user_achievement_id');
			$table->addKey(['user_id', 'achievement_id']);
			$table->addKey('earned_date');
		});
		
		return true;
	}
	
	/**
	 * Upgrade to 1.9.0 Step 3 - Populate initial achievements
	 */
	public function upgrade1090000Step3()
	{
		$db = $this->db();
		
		$achievements = [
			// [key, category, xp, tier, credits, title, description, display_order]
			['first_dig', 'mining', 10, 'easy', 100, 'Purchase your first mining rig', 'The journey of a thousand blocks begins with a single rig.', 1],
			['prospector', 'mining', 25, 'easy', 500, 'Mine 1 BTC total', 'Your first Bitcoin! Many more to come.', 2],
			['gold_rush', 'mining', 100, 'medium', 2000, 'Mine 10 BTC total', 'The gold rush is on! You\'re mining serious coin.', 3],
			['mining_empire', 'mining', 150, 'medium', 5000, 'Own 5 active mining rigs', 'Building an empire, one rig at a time.', 4],
			['industrial_scale', 'mining', 300, 'hard', 10000, 'Own 10 active mining rigs', 'Industrial-scale mining operations achieved!', 5],
			
			['first_sale', 'trading', 10, 'easy', 100, 'Sell crypto for the first time', 'You\'ve made your first trade! The market awaits.', 10],
			['market_trader', 'trading', 50, 'easy', 1000, 'Complete 10 crypto trades', 'A seasoned trader in the making.', 11],
			['whale', 'trading', 200, 'hard', 5000, 'Sell 10+ BTC in single transaction', 'Making whale-sized moves in the market!', 12],
			
			['crypto_holder', 'wealth', 75, 'medium', 2000, 'Hold 5 BTC balance', 'Accumulating serious wealth in crypto.', 20],
			['bitcoin_millionaire', 'wealth', 500, 'legendary', 25000, 'Portfolio worth $1,000,000', 'You\'re officially a Bitcoin millionaire!', 21],
			
			['maintenance_master', 'efficiency', 25, 'easy', 250, 'Repair a mining rig', 'Keeping your equipment in top condition.', 30],
			['tech_upgrade', 'efficiency', 100, 'medium', 3000, 'Fully upgrade rig to level 5', 'Maximum efficiency achieved!', 31],
			['well_oiled_machine', 'efficiency', 150, 'hard', 5000, 'Keep all rigs above 80% durability', 'Your operation runs like clockwork.', 32],
			
			['block_winner', 'competition', 50, 'medium', 1000, 'Win a block mining competition', 'First place in the block race!', 40],
			['top_miner', 'competition', 250, 'very_hard', 10000, 'Reach top 10 on any leaderboard', 'You\'re among the elite miners!', 41],
		];
		
		foreach ($achievements as $ach)
		{
			$db->insert('xf_ic_crypto_achievement', [
				'achievement_key' => $ach[0],
				'achievement_category' => $ach[1],
				'xp_points' => $ach[2],
				'difficulty_tier' => $ach[3],
				'credits_reward' => $ach[4],
				'is_repeatable' => 0,
				'is_active' => 1,
				'display_order' => $ach[7]
			], false, 'display_order = VALUES(display_order)');
			
			// Insert title phrase
			$db->insert('xf_phrase', [
				'language_id' => 0,
				'title' => 'ic_crypto_achievement_title.' . $ach[0],
				'phrase_text' => $ach[5],
				'addon_id' => 'IC/CryptoMining',
				'version_id' => 1090000,
				'version_string' => '1.9.0'
			], false, 'phrase_text = VALUES(phrase_text)');
			
			// Insert description phrase
			$db->insert('xf_phrase', [
				'language_id' => 0,
				'title' => 'ic_crypto_achievement_desc.' . $ach[0],
				'phrase_text' => $ach[6],
				'addon_id' => 'IC/CryptoMining',
				'version_id' => 1090000,
				'version_string' => '1.9.0'
			], false, 'phrase_text = VALUES(phrase_text)');
		}
		
		return true;
	}
	
	/**
	 * Upgrade to v1.10.0.1 - Mining Expedition Grid (Step 1/4)
	 * Create grid state table for 10x10 expedition grid
	 */
	public function upgrade1100001Step1()
	{
		$this->schemaManager()->createTable('xf_ic_crypto_grid_state', function(Create $table)
		{
			$table->addColumn('grid_block_id', 'int')->autoIncrement();
			$table->addColumn('generation_id', 'int')->setDefault(1);
			$table->addColumn('position', 'tinyint')->setDefault(0); // 0-99 (10x10 grid)
			$table->addColumn('block_type', 'enum', ['jackpot', 'rich_vein', 'standard', 'weak_vein', 'collapse']);
			$table->addColumn('btc_value', 'decimal', '10,6')->setDefault(0);
			$table->addColumn('durability_cost', 'decimal', '5,2')->setDefault(0);
			$table->addColumn('is_mined', 'tinyint')->setDefault(0);
			$table->addColumn('mined_by_user_id', 'int')->nullable();
			$table->addColumn('mined_date', 'int')->nullable();
			
			$table->addPrimaryKey('grid_block_id');
			$table->addKey(['generation_id', 'position']);
			$table->addKey(['is_mined']);
		});
	}
	
	/**
	 * Upgrade to v1.10.0.1 - Mining Expedition Grid (Step 2/4)
	 * Create grid generations table to track cycles
	 */
	public function upgrade1100001Step2()
	{
		$this->schemaManager()->createTable('xf_ic_crypto_grid_generations', function(Create $table)
		{
			$table->addColumn('generation_id', 'int')->autoIncrement();
			$table->addColumn('created_date', 'int')->setDefault(0);
			$table->addColumn('ended_date', 'int')->nullable();
			$table->addColumn('total_mined', 'int')->setDefault(0);
			$table->addColumn('total_jackpots', 'int')->setDefault(0);
			$table->addColumn('total_collapses', 'int')->setDefault(0);
			$table->addColumn('is_active', 'tinyint')->setDefault(1);
			
			$table->addPrimaryKey('generation_id');
			$table->addKey(['is_active']);
			$table->addKey(['created_date']);
		});
		
		// Create first generation
		$this->db()->insert('xf_ic_crypto_grid_generations', [
			'created_date' => \XF::$time,
			'is_active' => 1
		]);
	}
	
	/**
	 * Upgrade to v1.10.0.1 - Mining Expedition Grid (Step 3/4)
	 * Create grid mining history table
	 */
	public function upgrade1100001Step3()
	{
		$this->schemaManager()->createTable('xf_ic_crypto_grid_mines', function(Create $table)
		{
			$table->addColumn('mine_id', 'int')->autoIncrement();
			$table->addColumn('user_id', 'int')->setDefault(0);
			$table->addColumn('rig_id', 'int')->setDefault(0);
			$table->addColumn('generation_id', 'int')->setDefault(0);
			$table->addColumn('position', 'tinyint')->setDefault(0);
			$table->addColumn('block_type', 'enum', ['jackpot', 'rich_vein', 'standard', 'weak_vein', 'collapse']);
			$table->addColumn('btc_earned', 'decimal', '10,6')->setDefault(0);
			$table->addColumn('durability_lost', 'decimal', '5,2')->setDefault(0);
			$table->addColumn('credits_spent', 'int')->setDefault(100);
			$table->addColumn('used_scout', 'tinyint')->setDefault(0);
			$table->addColumn('used_insurance', 'tinyint')->setDefault(0);
			$table->addColumn('mined_date', 'int')->setDefault(0);
			
			$table->addPrimaryKey('mine_id');
			$table->addKey(['user_id']);
			$table->addKey(['generation_id']);
			$table->addKey(['mined_date']);
		});
	}
	
	/**
	 * Upgrade to v1.10.0.1 - Mining Expedition Grid (Step 4/4)
	 * Create user buffs table for Lucky Pickaxe, etc.
	 */
	public function upgrade1100001Step4()
	{
		$this->schemaManager()->createTable('xf_ic_crypto_user_buffs', function(Create $table)
		{
			$table->addColumn('buff_id', 'int')->autoIncrement();
			$table->addColumn('user_id', 'int')->setDefault(0);
			$table->addColumn('buff_type', 'enum', ['lucky_pickaxe', 'double_rewards', 'iron_will']);
			$table->addColumn('buff_value', 'decimal', '5,2')->setDefault(0);
			$table->addColumn('started_date', 'int')->setDefault(0);
			$table->addColumn('expires_date', 'int')->setDefault(0);
			$table->addColumn('is_active', 'tinyint')->setDefault(1);
			
			$table->addPrimaryKey('buff_id');
			$table->addKey(['user_id', 'is_active']);
			$table->addKey(['expires_date']);
		});
	}
	
	/**
	 * Upgrade to v1.10.0.3 - Grid Interface & Mining (Bug Fix)
	 * Fix rig filtering and rebuild templates to ensure CSS loads
	 */
	public function upgrade1100003Step1()
	{
		// Force template rebuild
		\XF::app()->jobManager()->enqueueUnique('templateRebuild', 'XF:TemplateRebuild', [
			'style_id' => 0
		]);
		
		return true;
	}
	
	/**
	 * Upgrade to v1.10.0.4 - Credits & CSS Fix
	 * Fix wallet credits integration and undefined LESS variable
	 */
	public function upgrade1100004Step1()
	{
		// Force template rebuild for @crypto-bg-body fix
		\XF::app()->jobManager()->enqueueUnique('templateRebuild', 'XF:TemplateRebuild', [
			'style_id' => 0
		]);
		
		return true;
	}
	
	// ==================== UNINSTALL STEPS ====================
	
	public function uninstallStep1()
	{
		$this->schemaManager()->dropTable('xf_ic_crypto_rig_types');
	}
	
	public function uninstallStep2()
	{
		$this->schemaManager()->dropTable('xf_ic_crypto_user_rigs');
	}
	
	public function uninstallStep3()
	{
		$this->schemaManager()->dropTable('xf_ic_crypto_wallet');
	}
	
	public function uninstallStep4()
	{
		$this->schemaManager()->dropTable('xf_ic_crypto_market');
	}
	
	public function uninstallStep5()
	{
		$this->schemaManager()->dropTable('xf_ic_crypto_market_history');
	}
	
	public function uninstallStep6()
	{
		$this->schemaManager()->dropTable('xf_ic_crypto_transactions');
	}
	
	public function uninstallStep7()
	{
		$this->schemaManager()->dropTable('xf_ic_crypto_market_events');
	}
	
	public function uninstallStep8()
	{
		$this->schemaManager()->dropTable('xf_ic_crypto_leaderboard');
	}
	
	public function uninstallStep9()
	{
		$this->schemaManager()->dropTable('xf_ic_crypto_blocks');
	}
	
	public function uninstallStep10()
	{
		$this->schemaManager()->dropTable('xf_ic_crypto_achievement');
	}
	
	public function uninstallStep11()
	{
		$this->schemaManager()->dropTable('xf_ic_crypto_user_achievement');
	}
	
	public function uninstallStep12()
	{
		$this->schemaManager()->dropTable('xf_ic_crypto_grid_state');
	}
	
	public function uninstallStep13()
	{
		$this->schemaManager()->dropTable('xf_ic_crypto_grid_generations');
	}
	
	public function uninstallStep14()
	{
		$this->schemaManager()->dropTable('xf_ic_crypto_grid_mines');
	}
	
	public function uninstallStep15()
	{
		$this->schemaManager()->dropTable('xf_ic_crypto_user_buffs');
	}
}
