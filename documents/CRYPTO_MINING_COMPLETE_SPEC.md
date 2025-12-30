# 💎 CRYPTO MINING SIMULATION - COMPLETE SPECIFICATION
**XenForo 2.3+ Addon**
**Version: 1.0.0 (Target)**

---

## 📊 **DATABASE SCHEMA**

### **Table 1: xf_ic_crypto_rig_types**
Defines available mining rig models users can purchase.

```sql
CREATE TABLE xf_ic_crypto_rig_types (
    rig_type_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    rig_name VARCHAR(100) NOT NULL,
    rig_description TEXT,
    hash_rate DECIMAL(10,6) NOT NULL,           -- BTC per hour
    power_consumption DECIMAL(10,2) NOT NULL,   -- USD per day
    base_cost INT UNSIGNED NOT NULL,             -- Forum currency
    durability_max INT UNSIGNED DEFAULT 100,     -- Max durability points
    efficiency_rating INT UNSIGNED DEFAULT 100,  -- 0-100 scale
    required_level INT UNSIGNED DEFAULT 1,       -- User level required
    image_url VARCHAR(255),
    is_active TINYINT DEFAULT 1,
    sort_order INT UNSIGNED DEFAULT 0,
    
    INDEX (is_active),
    INDEX (required_level)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Initial Rig Types:**
```
1. Basic Miner
   - Hash Rate: 0.001 BTC/hour
   - Power Cost: $10/day
   - Base Cost: 500 credits
   - Durability: 100 days
   - Level Required: 1

2. Advanced Miner
   - Hash Rate: 0.005 BTC/hour
   - Power Cost: $25/day
   - Base Cost: 2000 credits
   - Durability: 150 days
   - Level Required: 5

3. ASIC Miner
   - Hash Rate: 0.02 BTC/hour
   - Power Cost: $100/day
   - Base Cost: 10000 credits
   - Durability: 200 days
   - Level Required: 10
```

---

### **Table 2: xf_ic_crypto_user_rigs**
Tracks each rig owned by users.

```sql
CREATE TABLE xf_ic_crypto_user_rigs (
    user_rig_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    rig_type_id INT UNSIGNED NOT NULL,
    purchase_date INT UNSIGNED NOT NULL,
    purchase_price INT UNSIGNED NOT NULL,
    current_durability DECIMAL(5,2) DEFAULT 100.00,  -- 0.00-100.00
    upgrade_level TINYINT UNSIGNED DEFAULT 0,         -- 0-5
    is_active TINYINT DEFAULT 1,                      -- Mining on/off
    last_mined INT UNSIGNED NOT NULL,                 -- Timestamp
    total_mined DECIMAL(10,6) DEFAULT 0,              -- Total BTC mined
    
    INDEX (user_id),
    INDEX (rig_type_id),
    INDEX (is_active),
    INDEX (last_mined),
    FOREIGN KEY (user_id) REFERENCES xf_user(user_id) ON DELETE CASCADE,
    FOREIGN KEY (rig_type_id) REFERENCES xf_ic_crypto_rig_types(rig_type_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Key Fields:**
- `current_durability`: Decreases over time, affects output
- `upgrade_level`: 0-5, each level improves hash rate by 10%
- `is_active`: User can pause mining to save power costs
- `last_mined`: Tracks when last mining payout occurred

---

### **Table 3: xf_ic_crypto_wallet**
User cryptocurrency wallet balances.

```sql
CREATE TABLE xf_ic_crypto_wallet (
    wallet_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL UNIQUE,
    crypto_balance DECIMAL(12,6) DEFAULT 0,          -- BTC balance
    total_mined DECIMAL(12,6) DEFAULT 0,             -- Lifetime mined
    total_sold DECIMAL(12,6) DEFAULT 0,              -- Lifetime sold
    total_bought DECIMAL(12,6) DEFAULT 0,            -- Lifetime bought
    credits_earned INT UNSIGNED DEFAULT 0,           -- From selling crypto
    credits_spent INT UNSIGNED DEFAULT 0,            -- On rigs/upgrades
    last_mining_payout INT UNSIGNED,
    created_date INT UNSIGNED NOT NULL,
    
    INDEX (user_id),
    FOREIGN KEY (user_id) REFERENCES xf_user(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### **Table 4: xf_ic_crypto_market**
Tracks cryptocurrency market prices over time.

```sql
CREATE TABLE xf_ic_crypto_market (
    market_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    crypto_name VARCHAR(50) NOT NULL DEFAULT 'Bitcoin',
    current_price DECIMAL(12,2) NOT NULL,            -- USD per BTC
    previous_price DECIMAL(12,2),
    price_change_percent DECIMAL(6,3),               -- % change
    daily_volume DECIMAL(15,2) DEFAULT 0,
    market_cap DECIMAL(18,2),
    last_updated INT UNSIGNED NOT NULL,
    
    INDEX (crypto_name),
    INDEX (last_updated)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Initial Data:**
```sql
INSERT INTO xf_ic_crypto_market (crypto_name, current_price, last_updated)
VALUES ('Bitcoin', 50000.00, UNIX_TIMESTAMP());
```

---

### **Table 5: xf_ic_crypto_market_history**
Historical price data for charts.

```sql
CREATE TABLE xf_ic_crypto_market_history (
    history_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    crypto_name VARCHAR(50) NOT NULL DEFAULT 'Bitcoin',
    price DECIMAL(12,2) NOT NULL,
    recorded_date INT UNSIGNED NOT NULL,
    
    INDEX (crypto_name, recorded_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### **Table 6: xf_ic_crypto_transactions**
All buy/sell/repair/upgrade transactions.

```sql
CREATE TABLE xf_ic_crypto_transactions (
    transaction_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    transaction_type ENUM('buy_rig', 'sell_crypto', 'buy_crypto', 'repair', 'upgrade', 'power_cost', 'mining_reward') NOT NULL,
    amount DECIMAL(12,6),                             -- Crypto amount
    credits INT,                                      -- Forum currency
    price_per_unit DECIMAL(12,2),                    -- USD per BTC at time
    description TEXT,
    transaction_date INT UNSIGNED NOT NULL,
    
    INDEX (user_id),
    INDEX (transaction_type),
    INDEX (transaction_date),
    FOREIGN KEY (user_id) REFERENCES xf_user(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### **Table 7: xf_ic_crypto_market_events**
Random market events affecting prices.

```sql
CREATE TABLE xf_ic_crypto_market_events (
    event_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_type ENUM('bull_run', 'crash', 'halving', 'difficulty_increase', 'regulation', 'neutral') NOT NULL,
    event_title VARCHAR(200) NOT NULL,
    event_description TEXT,
    price_impact_percent DECIMAL(6,3) NOT NULL,      -- +/- percent
    duration_hours INT UNSIGNED DEFAULT 24,
    is_active TINYINT DEFAULT 0,
    triggered_date INT UNSIGNED,
    
    INDEX (is_active),
    INDEX (triggered_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Initial Events:**
```sql
INSERT INTO xf_ic_crypto_market_events (event_type, event_title, event_description, price_impact_percent, duration_hours) VALUES
('bull_run', 'Bitcoin ETF Approved!', 'Major institutional investment approved', 20.00, 48),
('crash', 'China Bans Mining', 'Regulatory crackdown causes panic selling', -15.00, 72),
('halving', 'Bitcoin Halving Event', 'Block rewards cut in half', 10.00, 168),
('difficulty_increase', 'Mining Difficulty +10%', 'Network difficulty adjustment', -5.00, 24),
('regulation', 'New KYC Requirements', 'Government oversight increases', -8.00, 48);
```

---

### **Table 8: xf_ic_crypto_leaderboard**
Cached leaderboard data for performance.

```sql
CREATE TABLE xf_ic_crypto_leaderboard (
    leaderboard_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    leaderboard_type ENUM('richest', 'most_mined', 'most_efficient', 'most_rigs') NOT NULL,
    rank_position INT UNSIGNED NOT NULL,
    stat_value DECIMAL(15,6) NOT NULL,
    last_updated INT UNSIGNED NOT NULL,
    
    UNIQUE KEY (leaderboard_type, user_id),
    INDEX (leaderboard_type, rank_position),
    FOREIGN KEY (user_id) REFERENCES xf_user(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 🔧 **CORE MECHANICS**

### **1. Mining Process (Passive Income)**

**Cron Job: UpdateMining (Runs Every Hour)**

```php
public static function runUpdate()
{
    $db = \XF::db();
    
    // Get all active rigs
    $activeRigs = $db->fetchAll("
        SELECT ur.*, rt.hash_rate, rt.power_consumption, rt.efficiency_rating
        FROM xf_ic_crypto_user_rigs ur
        INNER JOIN xf_ic_crypto_rig_types rt ON ur.rig_type_id = rt.rig_type_id
        WHERE ur.is_active = 1 AND ur.current_durability > 0
    ");
    
    foreach ($activeRigs as $rig) {
        // Calculate hours since last mined
        $now = time();
        $hoursSince = ($now - $rig['last_mined']) / 3600;
        if ($hoursSince < 1) continue; // Not yet 1 hour
        
        // Calculate base mining output
        $baseHashRate = $rig['hash_rate'];
        
        // Apply upgrade bonus
        $upgradeBonus = 1 + ($rig['upgrade_level'] * 0.10); // 10% per level
        
        // Apply durability penalty
        $durabilityMultiplier = 1.0;
        if ($rig['current_durability'] < 50) {
            $durabilityMultiplier = 0.75; // 25% reduction
        }
        if ($rig['current_durability'] < 25) {
            $durabilityMultiplier = 0.50; // 50% reduction
        }
        
        // Calculate total mined
        $minedBTC = $baseHashRate * $hoursSince * $upgradeBonus * $durabilityMultiplier;
        
        // Calculate power costs (in forum credits)
        $dailyPowerCost = $rig['power_consumption'];
        $powerCostCredits = ($dailyPowerCost / 24) * $hoursSince;
        
        // Deduct durability (1% per day)
        $durabilityLoss = (1.0 / 24) * $hoursSince;
        $newDurability = max(0, $rig['current_durability'] - $durabilityLoss);
        
        // Update rig
        $db->update('xf_ic_crypto_user_rigs', [
            'current_durability' => $newDurability,
            'last_mined' => $now,
            'total_mined' => $rig['total_mined'] + $minedBTC
        ], 'user_rig_id = ?', $rig['user_rig_id']);
        
        // Update wallet
        $db->query("
            UPDATE xf_ic_crypto_wallet
            SET crypto_balance = crypto_balance + ?,
                total_mined = total_mined + ?,
                last_mining_payout = ?
            WHERE user_id = ?
        ", [$minedBTC, $minedBTC, $now, $rig['user_id']]);
        
        // Deduct power costs from user credits
        \XF::app()->currency()->updateUserCurrency(
            $rig['user_id'], 
            -$powerCostCredits, 
            'Mining power costs'
        );
        
        // Log transaction
        $db->insert('xf_ic_crypto_transactions', [
            'user_id' => $rig['user_id'],
            'transaction_type' => 'mining_reward',
            'amount' => $minedBTC,
            'credits' => -$powerCostCredits,
            'description' => "Mined {$minedBTC} BTC, power cost {$powerCostCredits} credits",
            'transaction_date' => $now
        ]);
    }
}
```

---

### **2. Market Price Fluctuation**

**Cron Job: UpdateMarket (Runs Daily)**

```php
public static function runUpdate()
{
    $db = \XF::db();
    
    // Get current market data
    $market = $db->fetchRow("
        SELECT * FROM xf_ic_crypto_market 
        WHERE crypto_name = 'Bitcoin'
    ");
    
    $currentPrice = $market['current_price'];
    
    // Base random fluctuation (-5% to +5%)
    $randomFactor = (mt_rand(-500, 500) / 10000); // -0.05 to 0.05
    
    // Check for active events
    $activeEvent = $db->fetchRow("
        SELECT * FROM xf_ic_crypto_market_events
        WHERE is_active = 1
        LIMIT 1
    ");
    
    $eventImpact = 0;
    if ($activeEvent) {
        $eventImpact = $activeEvent['price_impact_percent'] / 100;
        
        // Check if event should end
        $hoursSinceTriggered = (time() - $activeEvent['triggered_date']) / 3600;
        if ($hoursSinceTriggered >= $activeEvent['duration_hours']) {
            $db->update('xf_ic_crypto_market_events', [
                'is_active' => 0
            ], 'event_id = ?', $activeEvent['event_id']);
        }
    }
    
    // 10% chance to trigger new event if none active
    if (!$activeEvent && mt_rand(1, 10) == 1) {
        $allEvents = $db->fetchAll("
            SELECT * FROM xf_ic_crypto_market_events
            WHERE is_active = 0
            ORDER BY RAND()
            LIMIT 1
        ");
        
        if ($allEvents) {
            $newEvent = $allEvents[0];
            $db->update('xf_ic_crypto_market_events', [
                'is_active' => 1,
                'triggered_date' => time()
            ], 'event_id = ?', $newEvent['event_id']);
            
            $eventImpact = $newEvent['price_impact_percent'] / 100;
            
            // Notify users of event
            \XF::app()->notifier()->notify(
                'crypto_market_event',
                $newEvent['event_title'],
                $newEvent['event_description']
            );
        }
    }
    
    // Calculate new price
    $totalChange = $randomFactor + $eventImpact;
    $newPrice = $currentPrice * (1 + $totalChange);
    
    // Clamp to reasonable bounds ($10k - $100k)
    $newPrice = max(10000, min(100000, $newPrice));
    
    // Update market
    $priceChangePercent = (($newPrice - $currentPrice) / $currentPrice) * 100;
    
    $db->update('xf_ic_crypto_market', [
        'previous_price' => $currentPrice,
        'current_price' => $newPrice,
        'price_change_percent' => $priceChangePercent,
        'last_updated' => time()
    ], 'crypto_name = ?', 'Bitcoin');
    
    // Record history
    $db->insert('xf_ic_crypto_market_history', [
        'crypto_name' => 'Bitcoin',
        'price' => $newPrice,
        'recorded_date' => time()
    ]);
}
```

---

### **3. Durability & Repair System**

**Repair Cost Formula:**
```
Repair Cost = (Base Rig Cost * 0.10) * (Durability to Restore / 100)

Example:
- Basic Rig costs 500 credits
- Current durability: 40%
- Want to restore to 100%
- Repair Cost = (500 * 0.10) * (60 / 100) = 30 credits
```

**Repair Logic:**
```php
public function repairRig($userRigId, $targetDurability = 100)
{
    $rig = $this->em()->find('IC\Crypto:UserRig', $userRigId);
    $rigType = $rig->RigType;
    
    $currentDurability = $rig->current_durability;
    $durabilityToRestore = $targetDurability - $currentDurability;
    
    if ($durabilityToRestore <= 0) {
        return ['error' => 'Rig is already at target durability'];
    }
    
    $repairCost = ($rigType->base_cost * 0.10) * ($durabilityToRestore / 100);
    $repairCost = ceil($repairCost);
    
    // Check user has enough credits
    if (\XF::visitor()->currency_balance < $repairCost) {
        return ['error' => 'Insufficient credits'];
    }
    
    // Deduct credits
    \XF::app()->currency()->updateUserCurrency(
        \XF::visitor()->user_id,
        -$repairCost,
        "Repaired {$rigType->rig_name}"
    );
    
    // Update rig
    $rig->current_durability = $targetDurability;
    $rig->save();
    
    // Log transaction
    $this->logTransaction([
        'transaction_type' => 'repair',
        'credits' => $repairCost,
        'description' => "Repaired rig to {$targetDurability}% durability"
    ]);
    
    return ['success' => true, 'cost' => $repairCost];
}
```

---

### **4. Upgrade System**

**Upgrade Costs & Benefits:**
```
Level 0 → Level 1: 20% of rig cost, +10% hash rate
Level 1 → Level 2: 40% of rig cost, +10% hash rate (20% total)
Level 2 → Level 3: 60% of rig cost, +10% hash rate (30% total)
Level 3 → Level 4: 80% of rig cost, +10% hash rate (40% total)
Level 4 → Level 5: 100% of rig cost, +10% hash rate (50% total)
```

**Upgrade Logic:**
```php
public function upgradeRig($userRigId)
{
    $rig = $this->em()->find('IC\Crypto:UserRig', $userRigId);
    
    if ($rig->upgrade_level >= 5) {
        return ['error' => 'Rig is already at max level'];
    }
    
    $nextLevel = $rig->upgrade_level + 1;
    $upgradeCost = ($rig->RigType->base_cost * 0.20) * $nextLevel;
    $upgradeCost = ceil($upgradeCost);
    
    // Check credits
    if (\XF::visitor()->currency_balance < $upgradeCost) {
        return ['error' => 'Insufficient credits'];
    }
    
    // Deduct credits
    \XF::app()->currency()->updateUserCurrency(
        \XF::visitor()->user_id,
        -$upgradeCost,
        "Upgraded {$rig->RigType->rig_name} to level {$nextLevel}"
    );
    
    // Upgrade rig
    $rig->upgrade_level = $nextLevel;
    $rig->save();
    
    // Log transaction
    $this->logTransaction([
        'transaction_type' => 'upgrade',
        'credits' => $upgradeCost,
        'description' => "Upgraded rig to level {$nextLevel}"
    ]);
    
    return ['success' => true, 'newLevel' => $nextLevel, 'cost' => $upgradeCost];
}
```

---

## 🎨 **USER INTERFACE WIREFRAMES**

### **Dashboard Page (/crypto-mining)**

```
╔═══════════════════════════════════════════════════════════╗
║  CRYPTO MINING DASHBOARD                                   ║
╠═══════════════════════════════════════════════════════════╣
║  💰 Wallet                                                 ║
║  ┌─────────────────────────────────────────────────────┐  ║
║  │ Balance: 2.5 BTC ($125,000)                         │  ║
║  │ Daily Profit: +$200 (after costs)                   │  ║
║  │ Total Mined: 10.8 BTC (lifetime)                    │  ║
║  │ [Sell Crypto] [Buy Crypto] [View History]           │  ║
║  └─────────────────────────────────────────────────────┘  ║
║                                                            ║
║  ⚙️ Active Rigs (3)                                       ║
║  ┌─────────────────────────────────────────────────────┐  ║
║  │ 🖥️ Basic Miner #1                                    │  ║
║  │ Output: 0.001 BTC/hr  |  Durability: 80% 🟢         │  ║
║  │ Power Cost: -$10/day  |  Status: Active              │  ║
║  │ Upgrade Level: 2 (+20% bonus)                        │  ║
║  │ [Repair] [Upgrade] [Deactivate] [Sell]              │  ║
║  └─────────────────────────────────────────────────────┘  ║
║  ┌─────────────────────────────────────────────────────┐  ║
║  │ 🖥️ Advanced Miner #1                                 │  ║
║  │ Output: 0.005 BTC/hr  |  Durability: 45% 🟡         │  ║
║  │ Power Cost: -$25/day  |  Status: Active              │  ║
║  │ Upgrade Level: 0 (no upgrades)                       │  ║
║  │ [Repair] [Upgrade] [Deactivate] [Sell]              │  ║
║  └─────────────────────────────────────────────────────┘  ║
║  ┌─────────────────────────────────────────────────────┐  ║
║  │ 🖥️ ASIC Miner #1                                     │  ║
║  │ Output: 0.02 BTC/hr   |  Durability: 20% 🔴         │  ║
║  │ Power Cost: -$100/day |  Status: Low Efficiency!     │  ║
║  │ Upgrade Level: 5 (MAX)                               │  ║
║  │ [Repair] [Deactivate] [Sell]                         │  ║
║  └─────────────────────────────────────────────────────┘  ║
║                                                            ║
║  [Buy New Rig] [Marketplace] [Leaderboard]                ║
╚═══════════════════════════════════════════════════════════╝
```

---

### **Buy Rig Page (/crypto-mining/shop)**

```
╔═══════════════════════════════════════════════════════════╗
║  MINING RIG SHOP                                           ║
╠═══════════════════════════════════════════════════════════╣
║  ┌───────────────┐  ┌───────────────┐  ┌───────────────┐ ║
║  │ Basic Miner   │  │ Advanced Miner│  │  ASIC Miner   │ ║
║  │ [Image]       │  │  [Image]      │  │   [Image]     │ ║
║  │               │  │               │  │               │ ║
║  │ Cost: 500 cr  │  │ Cost: 2000 cr │  │ Cost: 10000cr │ ║
║  │ Output: 0.001 │  │ Output: 0.005 │  │ Output: 0.02  │ ║
║  │ Power: $10/d  │  │ Power: $25/d  │  │ Power: $100/d │ ║
║  │ Durability:100│  │ Durability:150│  │ Durability:200│ ║
║  │               │  │               │  │               │ ║
║  │  [Purchase]   │  │  [Purchase]   │  │ 🔒 Level 10   │ ║
║  └───────────────┘  └───────────────┘  └───────────────┘ ║
╚═══════════════════════════════════════════════════════════╝
```

---

### **Marketplace Page (/crypto-mining/market)**

```
╔═══════════════════════════════════════════════════════════╗
║  CRYPTO MARKETPLACE                                        ║
╠═══════════════════════════════════════════════════════════╣
║  📊 Bitcoin Price                                          ║
║  ┌─────────────────────────────────────────────────────┐  ║
║  │ Current: $52,345 🟢 +4.2% (24h)                     │  ║
║  │                                                       │  ║
║  │ [Price Chart - Last 30 Days]                         │  ║
║  │                              /\                       │  ║
║  │                          /\/    \                     │  ║
║  │                      /\/          \/                  │  ║
║  │                  /\/                  \               │  ║
║  │              /\/                                      │  ║
║  │          /\/                                          │  ║
║  └─────────────────────────────────────────────────────┘  ║
║                                                            ║
║  🔔 Active Market Event                                   ║
║  ┌─────────────────────────────────────────────────────┐  ║
║  │ 🚀 Bitcoin ETF Approved!                            │  ║
║  │ Major institutional investment approved              │  ║
║  │ Impact: +20% | Ends in: 36 hours                     │  ║
║  └─────────────────────────────────────────────────────┘  ║
║                                                            ║
║  💱 Trade                                                  ║
║  ┌─────────────────────────────────────────────────────┐  ║
║  │ Your Balance: 2.5 BTC                                │  ║
║  │                                                       │  ║
║  │ Sell BTC                                              │  ║
║  │ Amount: [____] BTC                                    │  ║
║  │ You'll receive: ~[___] credits                        │  ║
║  │ [Sell]                                                │  ║
║  │                                                       │  ║
║  │ Buy BTC                                               │  ║
║  │ Spend: [____] credits                                 │  ║
║  │ You'll get: ~[___] BTC                                │  ║
║  │ [Buy]                                                 │  ║
║  └─────────────────────────────────────────────────────┘  ║
╚═══════════════════════════════════════════════════════════╝
```

---

### **Leaderboard Page (/crypto-mining/leaderboard)**

```
╔═══════════════════════════════════════════════════════════╗
║  CRYPTO MINING LEADERBOARDS                                ║
╠═══════════════════════════════════════════════════════════╣
║  [Richest] [Most Mined] [Most Efficient] [Most Rigs]      ║
║                                                            ║
║  🏆 Richest Miners (Current Balance)                      ║
║  ┌─────────────────────────────────────────────────────┐  ║
║  │ Rank | User          | Balance    | USD Value       │  ║
║  │──────┼───────────────┼────────────┼─────────────────│  ║
║  │  1   │ CryptoKing    │ 150.5 BTC  │ $7,525,000     │  ║
║  │  2   │ HashMaster    │ 120.8 BTC  │ $6,040,000     │  ║
║  │  3   │ MiningLegend  │  95.2 BTC  │ $4,760,000     │  ║
║  │  4   │ WhaleWatcher  │  78.5 BTC  │ $3,925,000     │  ║
║  │  5   │ DiamondHands  │  65.3 BTC  │ $3,265,000     │  ║
║  │ ...  │ ...           │  ...       │ ...             │  ║
║  │  42  │ YOU           │   2.5 BTC  │ $125,000       │  ║
║  └─────────────────────────────────────────────────────┘  ║
╚═══════════════════════════════════════════════════════════╝
```

---

## 🎮 **USER FLOWS**

### **Flow 1: New User First Rig Purchase**

1. User visits `/crypto-mining` (Dashboard)
2. Sees empty state: "You don't have any mining rigs yet!"
3. Clicks [Buy Your First Rig]
4. Redirected to `/crypto-mining/shop`
5. Views available rigs (Basic, Advanced, ASIC)
6. Clicks [Purchase] on Basic Miner (500 credits)
7. Confirmation modal: "Purchase Basic Miner for 500 credits?"
8. Clicks [Confirm]
9. Credits deducted, rig added to user_rigs table
10. Redirected to Dashboard with success message
11. Sees rig in "Active Rigs" section
12. Mining begins automatically (cron handles payouts)

---

### **Flow 2: Selling Mined Crypto**

1. User has 2.5 BTC in wallet
2. Visits `/crypto-mining/market`
3. Current BTC price: $50,000
4. Enters "1.0 BTC" in Sell field
5. Calculator shows: "You'll receive ~50,000 credits"
6. Clicks [Sell]
7. Confirmation: "Sell 1.0 BTC for 50,000 credits?"
8. Clicks [Confirm]
9. Transaction recorded
10. Wallet updated: 1.5 BTC remaining
11. User credits increased: +50,000
12. Success message shown
13. Transaction appears in history

---

### **Flow 3: Repairing Low Durability Rig**

1. User sees ASIC Miner at 20% durability (red indicator)
2. Output reduced by 50% due to low durability
3. Clicks [Repair] on the rig
4. Modal shows: "Restore durability to 100%"
5. Cost calculation: (10,000 * 0.10) * (80 / 100) = 800 credits
6. Modal displays: "Repair cost: 800 credits"
7. Clicks [Confirm Repair]
8. Credits deducted
9. Durability restored to 100%
10. Output returns to full capacity
11. Success message: "Rig repaired successfully!"

---

### **Flow 4: Upgrading Rig**

1. User has Basic Miner at Level 0
2. Clicks [Upgrade]
3. Modal shows: "Upgrade to Level 1 (+10% hash rate)"
4. Cost: 20% of rig cost = (500 * 0.20) = 100 credits
5. Modal displays: "Upgrade cost: 100 credits"
6. User has 1,500 credits
7. Clicks [Confirm Upgrade]
8. Credits deducted: 1,500 → 1,400
9. Rig upgraded: Level 0 → Level 1
10. Dashboard shows: "Upgrade Level: 1 (+10% bonus)"
11. Mining output increases from 0.001 to 0.0011 BTC/hr

---

## 🚀 **PHASED DEVELOPMENT PLAN**

### **Phase 1: MVP (v1.0.0 - v1.0.5)**

**v1.0.1 - Addon Skeleton**
- Create addon structure (`_data` folders, XML files)
- Setup.php with database tables
- Permissions structure
- Navigation entries

**v1.0.2 - Basic Rig Shop**
- RigType Entity/Repository
- Shop controller & template
- Purchase rig functionality
- User can buy Basic Miner

**v1.0.3 - Wallet & Mining Cron**
- Wallet Entity/Repository
- UpdateMining cron job
- Passive mining calculation
- Wallet display on dashboard

**v1.0.4 - Dashboard UI**
- Dashboard controller & template
- Display user's rigs
- Show wallet balance
- Calculate daily profit

**v1.0.5 - Basic Marketplace**
- Market Entity/Repository
- Sell crypto for credits
- Buy crypto with credits
- Simple price tracking

---

### **Phase 2: Depth (v1.1.0 - v1.1.5)**

**v1.1.1 - Durability System**
- Durability degrades over time
- Output penalty at <50%, <25%
- Visual indicators (green/yellow/red)
- Rigs stop at 0% durability

**v1.1.2 - Repair System**
- Repair controller action
- Cost calculation (10% of base cost)
- Partial repair option
- Full repair option

**v1.1.3 - Upgrade System**
- 5 upgrade levels
- Cost scaling (20%, 40%, 60%, 80%, 100%)
- +10% hash rate per level
- Visual indicators

**v1.1.4 - Multiple Rig Types**
- Add Advanced Miner
- Add ASIC Miner
- Level requirements
- Different stats/costs

**v1.1.5 - Power Costs**
- Deduct credits hourly
- Display in dashboard
- Net profit calculations
- Pause mining option

---

### **Phase 3: Competition (v1.2.0 - v1.2.5)**

**v1.2.1 - Market Fluctuation**
- UpdateMarket cron (daily)
- Random price changes
- Price history tracking
- 30-day chart display

**v1.2.2 - Market Events**
- Random event system
- Bull runs, crashes, halvings
- Event notifications
- Duration-based impacts

**v1.2.3 - Leaderboards**
- Richest miners
- Most mined (lifetime)
- Most efficient (avg durability)
- Most rigs owned

**v1.2.4 - Transaction History**
- Full transaction log
- Filter by type
- Export functionality
- Pagination

**v1.2.5 - Achievements**
- "First Rig" achievement
- "Crypto Millionaire" (1M earned)
- "Power Player" (10+ rigs)
- "Efficient Operator" (90%+ avg durability)

---

## 🎯 **SUCCESS METRICS**

### **Phase 1 Complete When:**
- ✅ Users can purchase rigs
- ✅ Rigs mine crypto passively
- ✅ Users can sell crypto for credits
- ✅ Dashboard shows all relevant info
- ✅ No errors in server logs
- ✅ Mobile responsive

### **Phase 2 Complete When:**
- ✅ Durability system working
- ✅ Repair functionality complete
- ✅ Upgrade system functional
- ✅ Multiple rig types available
- ✅ Power costs calculated

### **Phase 3 Complete When:**
- ✅ Market prices fluctuate daily
- ✅ Random events trigger
- ✅ Leaderboards populate
- ✅ Transaction history complete
- ✅ Achievements unlock

---

## 📝 **TECHNICAL NOTES**

### **XenForo Integration Points:**

1. **Currency System:**
   ```php
   // Deduct credits
   \XF::repository('DBTech\Credits:Currency')->updateUserCurrency(
       $userId, 
       -$amount, 
       $description
   );
   ```

2. **Permissions:**
   ```xml
   <permission_group permission_group_id="icCryptoMining" />
   <permission permission_id="view" permission_group_id="icCryptoMining" />
   <permission permission_id="mine" permission_group_id="icCryptoMining" />
   <permission permission_id="trade" permission_group_id="icCryptoMining" />
   ```

3. **Navigation:**
   ```xml
   <navigation navigation_id="icCryptoMining" display_order="50" />
   ```

4. **Widgets:**
   - Wallet widget (sidebar)
   - Top miner widget
   - Market price widget

---

## 🎨 **STYLING NOTES**

**Reuse from Stock Market:**
- `.stockMarketContainer` → `.cryptoMiningContainer`
- `.stockCard` → `.rigCard`  
- `.portfolioView` → `.rigCollectionView`
- `.transactionHistory` → `.miningHistory`

**New LESS Variables:**
```less
@crypto-accent: #f7931a; // Bitcoin orange
@crypto-success: #16c784; // Green (gains)
@crypto-danger: #ea3943; // Red (losses)
@crypto-warning: #f6b71a; // Yellow (caution)
```

---

## 🔐 **SECURITY CONSIDERATIONS**

1. **Transaction Validation:**
   - Always verify user has sufficient credits before purchase
   - Validate crypto amounts before selling
   - Check rig ownership before actions

2. **Cron Job Protection:**
   - Prevent duplicate mining payouts
   - Use database transactions for atomic operations
   - Log all currency changes

3. **XSS Prevention:**
   - Escape all user inputs
   - Use XenForo's built-in sanitization
   - Validate numeric inputs

4. **Rate Limiting:**
   - Limit rig purchases per day
   - Throttle marketplace trades
   - Prevent cron spam

---

**This specification provides everything needed to build Phase 1 of the Crypto Mining Simulation addon!** 🚀
