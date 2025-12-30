# 📊 STOCK MARKET ADDON - REFERENCE GUIDE FOR CRYPTO MINING
**Pattern Reuse Map**

---

## 🎯 **WHY REUSE STOCK MARKET PATTERNS?**

The Stock Market addon already solves 80% of what Crypto Mining needs:
- ✅ Buy/sell transactions
- ✅ Portfolio/collection management
- ✅ Value tracking over time  
- ✅ Transaction history
- ✅ Leaderboards
- ✅ Market fluctuation mechanics
- ✅ Clean, professional UI

**Strategy:** Copy Stock Market structure, rename terms, modify mechanics slightly.

---

## 📁 **STOCK MARKET FILE STRUCTURE**

```
src/addons/IC/StockMarket/
├── addon.json
├── Setup.php
├── Entity/
│   ├── Stock.php
│   ├── UserPortfolio.php
│   ├── Transaction.php
│   └── Leaderboard.php
├── Repository/
│   ├── Stock.php
│   ├── Portfolio.php
│   └── Transaction.php
├── Pub/Controller/
│   ├── Market.php
│   ├── Portfolio.php
│   └── Leaderboard.php
├── Cron/
│   ├── UpdateStockPrices.php
│   └── UpdateDividends.php
└── _data/
    ├── routes.xml
    ├── navigation.xml
    ├── permissions.xml
    ├── templates.xml
    └── phrases.xml
```

**Crypto Mining Equivalent:**
```
src/addons/IC/CryptoMining/
├── addon.json
├── Setup.php
├── Entity/
│   ├── RigType.php
│   ├── UserRig.php
│   ├── Wallet.php
│   ├── Transaction.php
│   └── Market.php
├── Repository/
│   ├── RigType.php
│   ├── UserRig.php
│   ├── Wallet.php
│   └── Market.php
├── Pub/Controller/
│   ├── Dashboard.php
│   ├── Shop.php
│   ├── Market.php
│   └── Leaderboard.php
├── Cron/
│   ├── UpdateMining.php
│   └── UpdateMarket.php
└── _data/
    ├── routes.xml
    ├── navigation.xml
    ├── permissions.xml
    ├── templates.xml
    └── phrases.xml
```

---

## 🔄 **DIRECT PATTERN MAPPING**

### **Database Tables**

| Stock Market | Crypto Mining | Notes |
|-------------|--------------|-------|
| `xf_ic_stock_stocks` | `xf_ic_crypto_rig_types` | Stocks → Rig types |
| `xf_ic_stock_user_portfolio` | `xf_ic_crypto_user_rigs` | Holdings → Owned rigs |
| `xf_ic_stock_transactions` | `xf_ic_crypto_transactions` | **Exact same structure!** |
| `xf_ic_stock_market_data` | `xf_ic_crypto_market` | Price tracking |
| `xf_ic_stock_leaderboard` | `xf_ic_crypto_leaderboard` | **Exact same structure!** |

---

### **Entity Classes**

#### **Stock Market: Stock.php**
```php
namespace IC\StockMarket\Entity;

class Stock extends \XF\Mvc\Entity\Entity
{
    protected function _postSave()
    {
        // Update market data after stock changes
    }
    
    public function canPurchase(\XF\Entity\User $user)
    {
        // Check if user can buy this stock
    }
}
```

**Crypto Mining Equivalent: RigType.php**
```php
namespace IC\CryptoMining\Entity;

class RigType extends \XF\Mvc\Entity\Entity
{
    protected function _postSave()
    {
        // Update rig data after changes
    }
    
    public function canPurchase(\XF\Entity\User $user)
    {
        // Check if user meets level requirement
        // Check if user has enough credits
    }
}
```

---

#### **Stock Market: UserPortfolio.php**
```php
namespace IC\StockMarket\Entity;

class UserPortfolio extends \XF\Mvc\Entity\Entity
{
    public function getCurrentValue()
    {
        return $this->shares * $this->Stock->current_price;
    }
    
    public function getTotalReturn()
    {
        $currentValue = $this->getCurrentValue();
        $invested = $this->purchase_price * $this->shares;
        return $currentValue - $invested;
    }
}
```

**Crypto Mining Equivalent: UserRig.php**
```php
namespace IC\CryptoMining\Entity;

class UserRig extends \XF\Mvc\Entity\Entity
{
    public function getCurrentHashRate()
    {
        $baseRate = $this->RigType->hash_rate;
        $upgradeBonus = 1 + ($this->upgrade_level * 0.10);
        return $baseRate * $upgradeBonus;
    }
    
    public function getDurabilityPenalty()
    {
        if ($this->current_durability >= 50) return 1.0;
        if ($this->current_durability >= 25) return 0.75;
        return 0.50;
    }
    
    public function getEffectiveHashRate()
    {
        return $this->getCurrentHashRate() * $this->getDurabilityPenalty();
    }
}
```

---

### **Repository Classes**

#### **Stock Market: Stock.php Repository**
```php
namespace IC\StockMarket\Repository;

class Stock extends \XF\Mvc\Entity\Repository
{
    public function findAvailableStocks()
    {
        return $this->finder('IC\StockMarket:Stock')
            ->where('is_active', 1)
            ->order('market_cap', 'DESC');
    }
    
    public function getStockBySymbol($symbol)
    {
        return $this->finder('IC\StockMarket:Stock')
            ->where('symbol', $symbol)
            ->fetchOne();
    }
}
```

**Crypto Mining Equivalent: RigType.php Repository**
```php
namespace IC\CryptoMining\Repository;

class RigType extends \XF\Mvc\Entity\Repository
{
    public function findAvailableRigs(\XF\Entity\User $user)
    {
        return $this->finder('IC\CryptoMining:RigType')
            ->where('is_active', 1)
            ->where('required_level', '<=', $user->level) // Level check
            ->order('base_cost', 'ASC');
    }
    
    public function getRigTypeById($rigTypeId)
    {
        return $this->finder('IC\CryptoMining:RigType')
            ->where('rig_type_id', $rigTypeId)
            ->fetchOne();
    }
}
```

---

#### **Stock Market: Portfolio.php Repository**
```php
namespace IC\StockMarket\Repository;

class Portfolio extends \XF\Mvc\Entity\Repository
{
    public function getUserPortfolio($userId)
    {
        return $this->finder('IC\StockMarket:UserPortfolio')
            ->where('user_id', $userId)
            ->with('Stock')
            ->order('purchase_date', 'DESC')
            ->fetch();
    }
    
    public function getTotalPortfolioValue($userId)
    {
        $portfolio = $this->getUserPortfolio($userId);
        $total = 0;
        foreach ($portfolio as $holding) {
            $total += $holding->getCurrentValue();
        }
        return $total;
    }
}
```

**Crypto Mining Equivalent: UserRig.php Repository**
```php
namespace IC\CryptoMining\Repository;

class UserRig extends \XF\Mvc\Entity\Repository
{
    public function getUserRigs($userId)
    {
        return $this->finder('IC\CryptoMining:UserRig')
            ->where('user_id', $userId)
            ->with('RigType')
            ->order('purchase_date', 'DESC')
            ->fetch();
    }
    
    public function getActiveRigs($userId)
    {
        return $this->finder('IC\CryptoMining:UserRig')
            ->where('user_id', $userId)
            ->where('is_active', 1)
            ->where('current_durability', '>', 0)
            ->with('RigType')
            ->fetch();
    }
    
    public function getTotalDailyOutput($userId)
    {
        $rigs = $this->getActiveRigs($userId);
        $total = 0;
        foreach ($rigs as $rig) {
            $total += $rig->getEffectiveHashRate() * 24; // BTC per day
        }
        return $total;
    }
}
```

---

### **Controller Classes**

#### **Stock Market: Market.php Controller**
```php
namespace IC\StockMarket\Pub\Controller;

class Market extends \XF\Pub\Controller\AbstractController
{
    public function actionIndex()
    {
        $stockRepo = $this->repository('IC\StockMarket:Stock');
        $stocks = $stockRepo->findAvailableStocks()->fetch();
        
        $viewParams = [
            'stocks' => $stocks,
            'activeNav' => 'market'
        ];
        
        return $this->view('IC\StockMarket:Market\Index', 'ic_stock_market_index', $viewParams);
    }
    
    public function actionBuy(ParameterBag $params)
    {
        $stock = $this->assertStockExists($params->stock_id);
        
        if ($this->isPost()) {
            $shares = $this->filter('shares', 'uint');
            $totalCost = $shares * $stock->current_price;
            
            // Validate user has credits
            if (\XF::visitor()->currency_balance < $totalCost) {
                return $this->error('Insufficient credits');
            }
            
            // Create portfolio entry
            // Deduct credits
            // Log transaction
            
            return $this->redirect($this->buildLink('stock-market/portfolio'));
        }
        
        $viewParams = ['stock' => $stock];
        return $this->view('IC\StockMarket:Market\Buy', 'ic_stock_market_buy', $viewParams);
    }
}
```

**Crypto Mining Equivalent: Shop.php Controller**
```php
namespace IC\CryptoMining\Pub\Controller;

class Shop extends \XF\Pub\Controller\AbstractController
{
    public function actionIndex()
    {
        $rigRepo = $this->repository('IC\CryptoMining:RigType');
        $rigs = $rigRepo->findAvailableRigs(\XF::visitor())->fetch();
        
        $viewParams = [
            'rigs' => $rigs,
            'activeNav' => 'crypto-shop'
        ];
        
        return $this->view('IC\CryptoMining:Shop\Index', 'ic_crypto_shop_index', $viewParams);
    }
    
    public function actionBuy(ParameterBag $params)
    {
        $rigType = $this->assertRigTypeExists($params->rig_type_id);
        
        if ($this->isPost()) {
            // Validate user level
            if (\XF::visitor()->level < $rigType->required_level) {
                return $this->error('Level requirement not met');
            }
            
            // Validate credits
            if (\XF::visitor()->currency_balance < $rigType->base_cost) {
                return $this->error('Insufficient credits');
            }
            
            // Create user rig
            $userRig = $this->em()->create('IC\CryptoMining:UserRig');
            $userRig->user_id = \XF::visitor()->user_id;
            $userRig->rig_type_id = $rigType->rig_type_id;
            $userRig->purchase_date = \XF::$time;
            $userRig->purchase_price = $rigType->base_cost;
            $userRig->last_mined = \XF::$time;
            $userRig->save();
            
            // Deduct credits
            \XF::repository('DBTech\Credits:Currency')->updateUserCurrency(
                \XF::visitor()->user_id,
                -$rigType->base_cost,
                "Purchased {$rigType->rig_name}"
            );
            
            // Log transaction
            
            return $this->redirect($this->buildLink('crypto-mining'));
        }
        
        $viewParams = ['rigType' => $rigType];
        return $this->view('IC\CryptoMining:Shop\Buy', 'ic_crypto_shop_buy', $viewParams);
    }
}
```

---

### **Cron Jobs**

#### **Stock Market: UpdateStockPrices.php**
```php
namespace IC\StockMarket\Cron;

class UpdateStockPrices
{
    public static function runUpdate()
    {
        $stockRepo = \XF::repository('IC\StockMarket:Stock');
        $stocks = $stockRepo->findAvailableStocks()->fetch();
        
        foreach ($stocks as $stock) {
            // Fetch from Yahoo Finance API
            $newPrice = $this->fetchStockPrice($stock->symbol);
            
            $stock->previous_price = $stock->current_price;
            $stock->current_price = $newPrice;
            $stock->last_updated = \XF::$time;
            $stock->save();
        }
    }
}
```

**Crypto Mining Equivalent: UpdateMining.php**
```php
namespace IC\CryptoMining\Cron;

class UpdateMining
{
    public static function runUpdate()
    {
        $rigRepo = \XF::repository('IC\CryptoMining:UserRig');
        $activeRigs = $rigRepo->finder('IC\CryptoMining:UserRig')
            ->where('is_active', 1)
            ->where('current_durability', '>', 0)
            ->with('RigType')
            ->fetch();
        
        foreach ($activeRigs as $rig) {
            // Calculate hours since last mined
            $hoursSince = (\XF::$time - $rig->last_mined) / 3600;
            if ($hoursSince < 1) continue;
            
            // Calculate mined amount
            $minedBTC = $rig->getEffectiveHashRate() * $hoursSince;
            
            // Update wallet
            $walletRepo = \XF::repository('IC\CryptoMining:Wallet');
            $walletRepo->addCrypto($rig->user_id, $minedBTC);
            
            // Deduct power costs
            $powerCost = ($rig->RigType->power_consumption / 24) * $hoursSince;
            \XF::repository('DBTech\Credits:Currency')->updateUserCurrency(
                $rig->user_id,
                -$powerCost,
                'Mining power costs'
            );
            
            // Reduce durability
            $rig->current_durability -= (1.0 / 24) * $hoursSince;
            $rig->last_mined = \XF::$time;
            $rig->save();
        }
    }
}
```

---

## 🎨 **TEMPLATE PATTERNS**

### **Stock Market: Portfolio View**
```html
<div class="stockMarketContainer">
    <div class="block-container">
        <h2 class="block-header">My Portfolio</h2>
        <div class="block-body">
            <xf:foreach loop="$portfolio" value="$holding">
                <div class="stockCard">
                    <div class="stockCard-header">
                        <h3>{$holding.Stock.name}</h3>
                        <span class="stockCard-symbol">{$holding.Stock.symbol}</span>
                    </div>
                    <div class="stockCard-body">
                        <div class="stockCard-stat">
                            <label>Shares:</label>
                            <span>{$holding.shares}</span>
                        </div>
                        <div class="stockCard-stat">
                            <label>Current Value:</label>
                            <span>${{number($holding.getCurrentValue())}}</span>
                        </div>
                    </div>
                </div>
            </xf:foreach>
        </div>
    </div>
</div>
```

**Crypto Mining Equivalent: Rig Collection**
```html
<div class="cryptoMiningContainer">
    <div class="block-container">
        <h2 class="block-header">My Mining Rigs</h2>
        <div class="block-body">
            <xf:foreach loop="$rigs" value="$rig">
                <div class="rigCard">
                    <div class="rigCard-header">
                        <h3>{$rig.RigType.rig_name}</h3>
                        <span class="rigCard-status 
                            <xf:if is="$rig.current_durability >= 50">rigCard-status--good
                            <xf:elseif is="$rig.current_durability >= 25" />rigCard-status--warning
                            <xf:else />rigCard-status--danger</xf:if>">
                            {$rig.current_durability}%
                        </span>
                    </div>
                    <div class="rigCard-body">
                        <div class="rigCard-stat">
                            <label>Output:</label>
                            <span>{{number($rig.getEffectiveHashRate(), 6)}} BTC/hr</span>
                        </div>
                        <div class="rigCard-stat">
                            <label>Power Cost:</label>
                            <span>-${{number($rig.RigType.power_consumption)}}/day</span>
                        </div>
                        <div class="rigCard-actions">
                            <a href="{{link('crypto-mining/repair', $rig)}}" class="button button--link">Repair</a>
                            <a href="{{link('crypto-mining/upgrade', $rig)}}" class="button button--link">Upgrade</a>
                        </div>
                    </div>
                </div>
            </xf:foreach>
        </div>
    </div>
</div>
```

---

## 📊 **LEADERBOARD PATTERN**

**Stock Market uses this exact structure - reuse it!**

```php
// Repository method
public function updateLeaderboards()
{
    // Clear old data
    \XF::db()->delete('xf_ic_crypto_leaderboard', 'last_updated < ?', \XF::$time - 86400);
    
    // Richest miners
    $richest = \XF::db()->fetchAll("
        SELECT user_id, crypto_balance 
        FROM xf_ic_crypto_wallet 
        ORDER BY crypto_balance DESC 
        LIMIT 100
    ");
    
    $rank = 1;
    foreach ($richest as $user) {
        \XF::db()->insert('xf_ic_crypto_leaderboard', [
            'user_id' => $user['user_id'],
            'leaderboard_type' => 'richest',
            'rank_position' => $rank,
            'stat_value' => $user['crypto_balance'],
            'last_updated' => \XF::$time
        ], false, 'stat_value = VALUES(stat_value), rank_position = VALUES(rank_position)');
        $rank++;
    }
}
```

---

## 🎯 **KEY TAKEAWAYS**

### **What to Copy Exactly:**
1. ✅ Transaction table structure
2. ✅ Leaderboard table structure
3. ✅ Controller action patterns (buy/sell)
4. ✅ Repository finder methods
5. ✅ Permission structure
6. ✅ Navigation XML structure
7. ✅ Template CSS classes (rename appropriately)

### **What to Modify:**
1. 🔄 Entity relationships (stocks → rigs)
2. 🔄 Calculation formulas (prices → mining)
3. 🔄 Cron logic (price updates → mining payouts)
4. 🔄 UI terminology (portfolio → dashboard)

### **What to Add (New Features):**
1. ➕ Durability system (stocks don't degrade)
2. ➕ Upgrade system (stocks don't level up)
3. ➕ Power costs (stocks don't have operating costs)
4. ➕ Active/inactive toggle (stocks are always "active")

---

## 🚀 **IMPLEMENTATION STRATEGY**

1. **Copy Stock Market folder structure**
2. **Rename all classes** (Stock → RigType, Portfolio → UserRig, etc.)
3. **Modify database schema** (add durability, upgrade_level, is_active)
4. **Rewrite cron logic** (stock prices → mining rewards)
5. **Update templates** (portfolio UI → rig dashboard UI)
6. **Test incrementally** (one feature at a time!)

---

**The Stock Market addon is 80% of what you need - don't start from scratch!** 🎯
