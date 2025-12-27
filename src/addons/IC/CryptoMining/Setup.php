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
 * Version: 1.0.2 (Database Tables)
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
			$table->addColumn('credits', 'int')->nullable(); // Forum currency
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
			$table->addColumn('price_impact_percent', 'decimal', '6,3'); // +/- percent
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
			'price_impact_percent' => 20.000,
			'duration_hours' => 48,
			'is_active' => 0
		]);
		
		$db->insert('xf_ic_crypto_market_events', [
			'event_type' => 'crash',
			'event_title' => 'Regulatory Crackdown',
			'event_description' => 'Government announces stricter cryptocurrency regulations',
			'price_impact_percent' => -15.000,
			'duration_hours' => 72,
			'is_active' => 0
		]);
		
		$db->insert('xf_ic_crypto_market_events', [
			'event_type' => 'halving',
			'event_title' => 'Bitcoin Halving Event',
			'event_description' => 'Block rewards cut in half - scarcity increases',
			'price_impact_percent' => 10.000,
			'duration_hours' => 168,
			'is_active' => 0
		]);
		
		$db->insert('xf_ic_crypto_market_events', [
			'event_type' => 'difficulty_increase',
			'event_title' => 'Mining Difficulty Spike',
			'event_description' => 'Network difficulty adjustment makes mining harder',
			'price_impact_percent' => -5.000,
			'duration_hours' => 24,
			'is_active' => 0
		]);
		
		$db->insert('xf_ic_crypto_market_events', [
			'event_type' => 'regulation',
			'event_title' => 'New KYC Requirements',
			'event_description' => 'Exchanges must implement identity verification',
			'price_impact_percent' => -8.000,
			'duration_hours' => 48,
			'is_active' => 0
		]);
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
}
