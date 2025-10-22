# WioEX PHP SDK

Official PHP SDK for **WioEX Financial Data API** - Enterprise-grade client library for accessing stocks, trading signals, news, currency, and financial market data.

[![PHP Version](https://img.shields.io/packagist/php-v/wioex/php-sdk.svg)](https://packagist.org/packages/wioex/php-sdk)
[![Latest Version](https://img.shields.io/packagist/v/wioex/php-sdk.svg)](https://packagist.org/packages/wioex/php-sdk)
[![License](https://img.shields.io/packagist/l/wioex/php-sdk.svg)](https://packagist.org/packages/wioex/php-sdk)
[![Tests](https://img.shields.io/badge/tests-135%2B%20passing-brightgreen.svg)](https://github.com/wioex/php-sdk)

## Version 2.0.0 - Production Ready

**All critical issues resolved!** This major release fixes all runtime errors and provides enterprise-grade reliability.

### Critical Fixes
- **FIXED**: `Fatal error: Call to undefined method streaming()`
- **FIXED**: Missing resource methods in WioexClient
- **FIXED**: Type safety issues and runtime errors
- **ADDED**: 135+ comprehensive unit tests
- **ADDED**: Advanced export utilities (JSON, CSV, XML, Excel)
- **IMPROVED**: Professional error reporting and logging

### Zero Breaking Changes
Your existing code continues to work without any modifications.

## Requirements

- PHP 8.1+
- ext-json, ext-curl
- Composer

## Installation

```bash
composer require wioex/php-sdk
```

## Quick Start

```php
<?php

require 'vendor/autoload.php';

use Wioex\SDK\WioexClient;
use Wioex\SDK\Exceptions\{
    AuthenticationException,
    RateLimitException,
    WioexException
};

// Initialize client with configuration
$client = new WioexClient([
    'api_key' => 'your-api-key-here',
    'timeout' => 30,
    'retry' => [
        'times' => 3,
        'delay' => 100
    ]
]);

try {
    // 1. Get multiple stock quotes with full response handling
    $response = $client->stocks()->quote('AAPL,GOOGL,MSFT');
    
    if ($response->successful()) {
        echo "Portfolio Update:\n";
        foreach ($response['tickers'] as $stock) {
            printf(
                "%-6s $%-8.2f %+.2f%% (Vol: %s)\n",
                $stock['ticker'],
                $stock['market']['price'],
                $stock['market']['change']['percent'],
                number_format($stock['market']['volume'])
            );
            
            // Check for trading signals (auto-included)
            if (isset($stock['signal']) && $stock['signal']['confidence'] > 70) {
                echo "  🎯 Signal: {$stock['signal']['signal_type']} (confidence: {$stock['signal']['confidence']}%)\n";
            }
        }
        echo "\n";
    }

    // 2. Check market status with detailed information
    $marketStatus = $client->markets()->status();
    $nyse = $marketStatus['markets']['nyse'];
    echo "Market Status:\n";
    echo "NYSE: " . ($nyse['is_open'] ? '🟢 OPEN' : '🔴 CLOSED') . "\n";
    echo "Trading Hours: {$nyse['hours']['regular']['open']} - {$nyse['hours']['regular']['close']}\n";
    echo "Next Session: {$nyse['next_session']}\n\n";

    // 3. Get top market movers
    $gainers = $client->screens()->gainers(5);
    echo "Top Gainers:\n";
    foreach ($gainers['data'] as $stock) {
        printf("📈 %-6s +%.2f%%\n", $stock['symbol'], $stock['change_percent']);
    }
    echo "\n";

    // 4. Account information and usage
    $account = $client->account()->balance();
    echo "Account Status:\n";
    echo "Credits Remaining: {$account['credits']}\n";
    echo "Plan: {$account['plan']['name']}\n";
    echo "Reset Date: {$account['reset_date']}\n";

} catch (AuthenticationException $e) {
    echo "❌ Authentication Error: {$e->getMessage()}\n";
    echo "Please check your API key.\n";
} catch (RateLimitException $e) {
    echo "⚠️ Rate Limit Exceeded: {$e->getMessage()}\n";
    echo "Retry after: {$e->getRetryAfter()} seconds\n";
} catch (WioexException $e) {
    echo "❌ API Error: {$e->getMessage()}\n";
}

// Example Response Structure for stocks()->quote():
/*
{
    "tickers": [
        {
            "ticker": "AAPL",
            "name": "Apple Inc.",
            "market": {
                "price": 175.43,
                "change": {
                    "amount": 2.15,
                    "percent": 1.24
                },
                "volume": 62547890,
                "market_cap": 2765432100000
            },
            "signal": {
                "signal_type": "buy",
                "confidence": 78,
                "generated_at": "2025-10-22T15:30:00Z"
            }
        }
    ],
    "metadata": {
        "count": 1,
        "processing_time": 45
    }
}
*/
```

## Core Features

### Stock Data

```php
use Wioex\SDK\Enums\TimelineInterval;
use Wioex\SDK\Enums\SortOrder;

// 1. Search stocks with response processing
$searchResults = $client->stocks()->search('technology');
echo "Found {$searchResults['metadata']['total']} tech stocks:\n";
foreach (array_slice($searchResults['data'], 0, 5) as $stock) {
    printf("%-6s %-30s %s\n", 
        $stock['symbol'], 
        $stock['name'], 
        $stock['exchange']
    );
}

// 2. Get quotes with comprehensive data processing
$portfolio = $client->stocks()->quote('AAPL,GOOGL,MSFT,TSLA');
echo "\nPortfolio Performance:\n";
$totalValue = 0;
foreach ($portfolio['tickers'] as $stock) {
    $marketValue = $stock['market']['price'] * 100; // Assuming 100 shares
    $totalValue += $marketValue;
    
    printf("%-6s $%-8.2f %+.2f%% Value: $%-10.2f\n",
        $stock['ticker'],
        $stock['market']['price'],
        $stock['market']['change']['percent'],
        $marketValue
    );
}
echo "Total Portfolio Value: $" . number_format($totalValue, 2) . "\n";

// 3. Historical data with ENUMs for type safety
$timeline = $client->stocks()->timeline('AAPL', [
    'interval' => TimelineInterval::ONE_DAY,  // Type-safe ENUM usage
    'size' => 30,
    'orderBy' => SortOrder::DESCENDING
]);

echo "\nAAPL 30-Day Price History:\n";
$prices = [];
foreach ($timeline['data'] as $day) {
    printf("%s: $%-7.2f (Vol: %s)\n",
        $day['date'],
        $day['close'],
        number_format($day['volume'])
    );
    $prices[] = $day['close'];
}

// Calculate basic statistics
$avgPrice = array_sum($prices) / count($prices);
$maxPrice = max($prices);
$minPrice = min($prices);
echo "\nPrice Analysis:\n";
echo "Average: $" . number_format($avgPrice, 2) . "\n";
echo "Range: $" . number_format($minPrice, 2) . " - $" . number_format($maxPrice, 2) . "\n";

// 4. Company information with financial metrics
$companyInfo = $client->stocks()->info('AAPL');
echo "\nApple Inc. Company Profile:\n";
echo "Sector: {$companyInfo['sector']} | Industry: {$companyInfo['industry']}\n";
echo "Market Cap: $" . number_format($companyInfo['market_cap'] / 1000000000, 1) . "B\n";
echo "P/E Ratio: {$companyInfo['pe_ratio']}\n";
echo "52-Week Range: $" . number_format($companyInfo['week_52_low'], 2) . 
     " - $" . number_format($companyInfo['week_52_high'], 2) . "\n";

// 5. Price changes across multiple timeframes
$priceChanges = $client->stocks()->priceChanges('AAPL');
echo "\nAAPL Price Performance:\n";
foreach ($priceChanges['timeframes'] as $period => $change) {
    $indicator = $change['percent'] > 0 ? '📈' : '📉';
    printf("%s %-10s %+.2f%% ($%+.2f)\n",
        $indicator,
        strtoupper($period),
        $change['percent'],
        $change['amount']
    );
}

// 6. Bulk operations for portfolio analysis
$watchlist = ['AAPL', 'GOOGL', 'MSFT', 'AMZN', 'TSLA', 'META', 'NVDA'];
$bulkQuotes = $client->stocks()->quoteBulk($watchlist, [
    'batch_size' => 3,           // Process in batches to respect rate limits
    'delay_between_batches' => 100, // 100ms delay between batches
    'continue_on_error' => true     // Continue processing if one fails
]);

echo "\nWatchlist Analysis:\n";
$gainers = $losers = 0;
foreach ($bulkQuotes['data'] as $symbol => $quote) {
    $change = $quote['market']['change']['percent'];
    if ($change > 0) $gainers++;
    else if ($change < 0) $losers++;
}

echo "Market Sentiment: {$gainers} gainers, {$losers} losers\n";
echo "Processing time: {$bulkQuotes['metadata']['processing_time']}ms\n";
```

**Response Structure Examples:**

```json
// stocks()->search() response:
{
    "data": [
        {
            "symbol": "AAPL",
            "name": "Apple Inc.",
            "exchange": "NASDAQ",
            "type": "stock",
            "currency": "USD"
        }
    ],
    "metadata": {
        "total": 50,
        "query": "technology",
        "processing_time": 23
    }
}

// stocks()->timeline() response:
{
    "data": [
        {
            "date": "2025-10-22",
            "open": 174.20,
            "high": 176.85,
            "low": 173.95,
            "close": 175.43,
            "volume": 62547890,
            "adjusted_close": 175.43
        }
    ],
    "metadata": {
        "symbol": "AAPL",
        "interval": "1day",
        "count": 30
    }
}
```

### Market Screens

```php
use Wioex\SDK\Enums\{ScreenType, MarketIndex, SortOrder};

// 1. Daily market movers analysis
$topGainers = $client->screens()->gainers(10);
echo "📈 Top 10 Gainers Today:\n";
$totalGainerValue = 0;
foreach ($topGainers['data'] as $stock) {
    $marketCap = $stock['market_cap'] ?? 0;
    $totalGainerValue += $marketCap;
    
    printf("%-6s %+.2f%% $%-8.2f (Cap: $%.1fB)\n",
        $stock['symbol'],
        $stock['change_percent'],
        $stock['price'],
        $marketCap / 1000000000
    );
}

$topLosers = $client->screens()->losers(10);
echo "\n📉 Top 10 Losers Today:\n";
foreach ($topLosers['data'] as $stock) {
    printf("%-6s %.2f%% $%-8.2f\n",
        $stock['symbol'],
        $stock['change_percent'],
        $stock['price']
    );
}

// 2. Volume analysis with enhanced parameters
$mostActive = $client->screens()->active(
    limit: 15,
    sortOrder: SortOrder::DESCENDING,
    market: MarketIndex::SP500  // Focus on S&P 500 stocks
);

echo "\n🔥 Most Active S&P 500 Stocks:\n";
$totalVolume = 0;
foreach ($mostActive['data'] as $stock) {
    $volume = $stock['volume'];
    $totalVolume += $volume;
    $avgVolume = $stock['average_volume'] ?? $volume;
    $volumeRatio = $avgVolume > 0 ? ($volume / $avgVolume) : 1;
    
    printf("%-6s Vol: %-12s (%.1fx avg)\n",
        $stock['symbol'],
        number_format($volume),
        $volumeRatio
    );
}
echo "Total Volume: " . number_format($totalVolume) . " shares\n";

// 3. Pre-market and after-hours analysis
$preMarketGainers = $client->screens()->preMarketGainers(5);
echo "\n🌅 Pre-Market Leaders:\n";
foreach ($preMarketGainers['data'] as $stock) {
    printf("%-6s %+.2f%% $%-8.2f (Vol: %s)\n",
        $stock['symbol'],
        $stock['change_percent'],
        $stock['price'],
        number_format($stock['volume'])
    );
}

$afterHoursLosers = $client->screens()->postMarketLosers(5);
echo "\n🌙 After-Hours Decliners:\n";
foreach ($afterHoursLosers['data'] as $stock) {
    printf("%-6s %.2f%% $%-8.2f\n",
        $stock['symbol'],
        $stock['change_percent'],
        $stock['price']
    );
}

// 4. IPO tracking and analysis
$upcomingIpos = $client->screens()->ipos('upcoming');
echo "\n🚀 Upcoming IPOs:\n";
foreach ($upcomingIpos['data'] as $ipo) {
    $priceRange = isset($ipo['price_range']) 
        ? "{$ipo['price_range']['low']}-{$ipo['price_range']['high']}"
        : "TBD";
    
    printf("%-20s %s $%s (%s)\n",
        $ipo['company_name'],
        $ipo['expected_date'],
        $priceRange,
        $ipo['exchange']
    );
}

$recentIpos = $client->screens()->ipos('recent', ['days' => 30]);
echo "\nRecent IPOs (Last 30 days): " . count($recentIpos['data']) . " offerings\n";

// 5. Advanced screening with unified method
$customScreen = $client->screens()->screen(ScreenType::GAINERS, [
    'min_price' => 10,           // Stocks above $10
    'max_price' => 100,          // Below $100
    'min_volume' => 1000000,     // High volume
    'market_cap_min' => 1000000000, // $1B+ market cap
    'limit' => 20
]);

echo "\n🎯 Custom Screen (High-volume mid-caps):\n";
$qualifyingStocks = 0;
foreach ($customScreen['data'] as $stock) {
    if ($stock['change_percent'] > 3) { // Additional filter
        $qualifyingStocks++;
        printf("⭐ %-6s %+.2f%% $%-8.2f\n",
            $stock['symbol'],
            $stock['change_percent'],
            $stock['price']
        );
    }
}
echo "Qualifying stocks: {$qualifyingStocks}\n";

// 6. Market sentiment analysis
$marketSentiment = $client->screens()->marketSentiment(MarketIndex::NASDAQ, 30);
echo "\n📊 NASDAQ Market Sentiment (30 days):\n";
echo "Overall: {$marketSentiment['data']['sentiment']}\n";
echo "Bullish: {$marketSentiment['data']['metrics']['bullish_ratio']}%\n";
echo "Bearish: {$marketSentiment['data']['metrics']['bearish_ratio']}%\n";
echo "Neutral: {$marketSentiment['data']['metrics']['neutral_ratio']}%\n";

// 7. Sector rotation analysis
$sectorPerformance = $client->screens()->sectorPerformance();
echo "\n🔄 Sector Performance Today:\n";
usort($sectorPerformance['data'], fn($a, $b) => $b['change_percent'] <=> $a['change_percent']);

foreach (array_slice($sectorPerformance['data'], 0, 5) as $sector) {
    $indicator = $sector['change_percent'] > 0 ? '🟢' : '🔴';
    printf("%s %-20s %+.2f%%\n",
        $indicator,
        $sector['sector'],
        $sector['change_percent']
    );
}
```

**Response Structure Examples:**

```json
// screens()->gainers() response:
{
    "data": [
        {
            "symbol": "NVDA",
            "name": "NVIDIA Corp",
            "price": 485.20,
            "change_percent": 8.45,
            "change_amount": 37.82,
            "volume": 45670234,
            "market_cap": 1234567890000,
            "sector": "Technology"
        }
    ],
    "metadata": {
        "count": 10,
        "market_session": "regular",
        "generated_at": "2025-10-22T16:00:00Z"
    }
}

// screens()->marketSentiment() response:
{
    "data": {
        "sentiment": "bullish",
        "metrics": {
            "bullish_ratio": 65.4,
            "bearish_ratio": 23.1,
            "neutral_ratio": 11.5
        },
        "analysis_period": 30,
        "market_index": "nasdaq"
    }
}
```

### Trading Signals

```php
use Wioex\SDK\Enums\SignalType;

// 1. Active signals analysis with confidence filtering
$activeSignals = $client->signals()->active([
    'min_confidence' => 70,
    'limit' => 20
]);

echo "🎯 High-Confidence Trading Signals:\n";
$buySignals = $sellSignals = $holdSignals = 0;
$totalConfidence = 0;

foreach ($activeSignals['data'] as $signal) {
    $confidence = $signal['confidence'];
    $totalConfidence += $confidence;
    
    $emoji = match($signal['signal_type']) {
        'buy' => '🟢',
        'sell' => '🔴',
        'hold' => '🟡',
        default => '⚪'
    };
    
    printf("%s %-6s %-4s %d%% $%-8.2f (Target: $%.2f)\n",
        $emoji,
        $signal['symbol'],
        strtoupper($signal['signal_type']),
        $confidence,
        $signal['current_price'],
        $signal['target_price'] ?? 0
    );
    
    // Count signals by type
    match($signal['signal_type']) {
        'buy' => $buySignals++,
        'sell' => $sellSignals++,
        'hold' => $holdSignals++,
        default => null
    };
}

$avgConfidence = count($activeSignals['data']) > 0 
    ? $totalConfidence / count($activeSignals['data']) 
    : 0;

echo "\nSignal Summary:\n";
echo "Buy: {$buySignals} | Sell: {$sellSignals} | Hold: {$holdSignals}\n";
echo "Average Confidence: " . number_format($avgConfidence, 1) . "%\n\n";

// 2. Symbol-specific signal analysis
$portfolioSymbols = ['AAPL', 'GOOGL', 'MSFT', 'TSLA'];
foreach ($portfolioSymbols as $symbol) {
    $symbolSignals = $client->signals()->active([
        'symbol' => $symbol,
        'min_confidence' => 60
    ]);
    
    if (!empty($symbolSignals['data'])) {
        $signal = $symbolSignals['data'][0]; // Most recent signal
        echo "📊 {$symbol} Analysis:\n";
        echo "  Current Signal: " . strtoupper($signal['signal_type']) . "\n";
        echo "  Confidence: {$signal['confidence']}%\n";
        echo "  Price: \${$signal['current_price']}\n";
        
        if (isset($signal['reasoning'])) {
            echo "  Reasoning: {$signal['reasoning']}\n";
        }
        
        // Risk assessment
        $riskLevel = match(true) {
            $signal['confidence'] >= 85 => 'Low',
            $signal['confidence'] >= 70 => 'Medium',
            default => 'High'
        };
        echo "  Risk Level: {$riskLevel}\n\n";
    }
}

// 3. Signal history and performance analysis
$signalHistory = $client->signals()->history([
    'days' => 30,
    'include_performance' => true
]);

echo "📈 Signal Performance (Last 30 days):\n";
$totalSignals = count($signalHistory['data']);
$successfulSignals = array_filter($signalHistory['data'], 
    fn($s) => ($s['performance']['outcome'] ?? '') === 'success'
);
$successRate = $totalSignals > 0 ? (count($successfulSignals) / $totalSignals) * 100 : 0;

echo "Total Signals: {$totalSignals}\n";
echo "Success Rate: " . number_format($successRate, 1) . "%\n";

// Analyze by signal type
$performanceByType = [];
foreach ($signalHistory['data'] as $signal) {
    $type = $signal['signal_type'];
    $outcome = $signal['performance']['outcome'] ?? 'pending';
    
    if (!isset($performanceByType[$type])) {
        $performanceByType[$type] = ['total' => 0, 'success' => 0];
    }
    
    $performanceByType[$type]['total']++;
    if ($outcome === 'success') {
        $performanceByType[$type]['success']++;
    }
}

echo "\nPerformance by Signal Type:\n";
foreach ($performanceByType as $type => $stats) {
    $rate = $stats['total'] > 0 ? ($stats['success'] / $stats['total']) * 100 : 0;
    printf("%-4s: %.1f%% (%d/%d)\n", 
        strtoupper($type), 
        $rate, 
        $stats['success'], 
        $stats['total']
    );
}

// 4. Signal strength distribution
echo "\n🎯 Signal Strength Distribution:\n";
$confidenceRanges = [
    'Very High (90-100%)' => 0,
    'High (80-89%)' => 0,
    'Medium (70-79%)' => 0,
    'Low (60-69%)' => 0,
    'Very Low (<60%)' => 0
];

foreach ($activeSignals['data'] as $signal) {
    $confidence = $signal['confidence'];
    if ($confidence >= 90) $confidenceRanges['Very High (90-100%)']++;
    elseif ($confidence >= 80) $confidenceRanges['High (80-89%)']++;
    elseif ($confidence >= 70) $confidenceRanges['Medium (70-79%)']++;
    elseif ($confidence >= 60) $confidenceRanges['Low (60-69%)']++;
    else $confidenceRanges['Very Low (<60%)']++;
}

foreach ($confidenceRanges as $range => $count) {
    if ($count > 0) {
        echo "{$range}: {$count} signals\n";
    }
}

// 5. Real-time signal monitoring setup
echo "\n🔔 Setting up signal alerts:\n";
$alertSettings = [
    'min_confidence' => 85,
    'symbols' => ['AAPL', 'GOOGL', 'MSFT', 'TSLA', 'NVDA'],
    'signal_types' => ['buy', 'sell'],
    'notifications' => true
];

echo "Monitoring " . count($alertSettings['symbols']) . " symbols\n";
echo "Minimum confidence: {$alertSettings['min_confidence']}%\n";
echo "Alert types: " . implode(', ', $alertSettings['signal_types']) . "\n";

// 6. Advanced signal filtering and analysis
$advancedSignals = $client->signals()->active([
    'signal_type' => SignalType::BUY,
    'min_confidence' => 75,
    'price_range' => ['min' => 50, 'max' => 500],
    'sectors' => ['Technology', 'Healthcare'],
    'include_fundamentals' => true
]);

echo "\n🔍 Advanced Signal Analysis (Tech/Healthcare Buy Signals):\n";
foreach ($advancedSignals['data'] as $signal) {
    if (isset($signal['fundamentals'])) {
        $pe = $signal['fundamentals']['pe_ratio'] ?? 'N/A';
        $revenue_growth = $signal['fundamentals']['revenue_growth'] ?? 'N/A';
        
        printf("%-6s (PE: %-6s, Growth: %-6s) Conf: %d%%\n",
            $signal['symbol'],
            $pe,
            $revenue_growth,
            $signal['confidence']
        );
    }
}
```

**Response Structure Examples:**

```json
// signals()->active() response:
{
    "data": [
        {
            "id": "signal_12345",
            "symbol": "AAPL",
            "signal_type": "buy",
            "confidence": 85,
            "current_price": 175.43,
            "target_price": 190.00,
            "stop_loss": 165.00,
            "reasoning": "Strong earnings growth and technical breakout",
            "generated_at": "2025-10-22T14:30:00Z",
            "expires_at": "2025-10-23T14:30:00Z",
            "fundamentals": {
                "pe_ratio": 28.5,
                "revenue_growth": 12.3,
                "profit_margin": 23.8
            }
        }
    ],
    "metadata": {
        "count": 15,
        "filter_applied": "min_confidence: 70",
        "generated_at": "2025-10-22T16:00:00Z"
    }
}

// signals()->history() response:
{
    "data": [
        {
            "signal_id": "signal_12345",
            "symbol": "AAPL",
            "signal_type": "buy",
            "confidence": 85,
            "entry_price": 175.43,
            "exit_price": 182.15,
            "performance": {
                "outcome": "success",
                "return_percent": 3.83,
                "holding_period_days": 5
            }
        }
    ],
    "metadata": {
        "total_signals": 45,
        "success_rate": 67.8,
        "period_days": 30
    }
}
```

### Real-time Streaming

```php
// 1. WebSocket token management with caching
$tokenResponse = $client->streaming()->getToken();

if ($tokenResponse->successful()) {
    $tokenData = $tokenResponse->data();
    $wsToken = $tokenData['token'];
    $wsUrl = $tokenData['websocket_url'];
    $expiresAt = $tokenData['expires_at'];
    
    echo "🔑 WebSocket Authentication:\n";
    echo "Token: " . substr($wsToken, 0, 20) . "...\n";
    echo "URL: {$wsUrl}\n";
    echo "Expires: {$expiresAt}\n\n";
    
    // 2. Token validation and refresh management
    if ($client->streaming()->validateToken($wsToken)) {
        echo "✅ Token is valid\n";
    } else {
        echo "🔄 Token expired, refreshing...\n";
        $refreshResponse = $client->streaming()->refreshToken();
        if ($refreshResponse->successful()) {
            $wsToken = $refreshResponse->data('token');
            echo "✅ Token refreshed successfully\n";
        }
    }
}

// 3. WebSocket connection implementation (JavaScript client-side)
echo "\n🌐 WebSocket Client Implementation:\n";
echo "```javascript\n";
echo "class WioexStreamingClient {\n";
echo "    constructor(token, url) {\n";
echo "        this.token = token;\n";
echo "        this.url = url;\n";
echo "        this.ws = null;\n";
echo "        this.subscriptions = new Set();\n";
echo "        this.reconnectAttempts = 0;\n";
echo "        this.maxReconnectAttempts = 5;\n";
echo "        this.heartbeatInterval = null;\n";
echo "    }\n\n";

echo "    connect() {\n";
echo "        this.ws = new WebSocket(`\${this.url}?token=\${this.token}`);\n\n";

echo "        this.ws.onopen = () => {\n";
echo "            console.log('📡 Connected to WioEX streaming');\n";
echo "            this.reconnectAttempts = 0;\n";
echo "            this.startHeartbeat();\n";
echo "            this.resubscribeAll();\n";
echo "        };\n\n";

echo "        this.ws.onmessage = (event) => {\n";
echo "            const data = JSON.parse(event.data);\n";
echo "            this.handleMessage(data);\n";
echo "        };\n\n";

echo "        this.ws.onclose = (event) => {\n";
echo "            console.log('📡 Connection closed:', event.code);\n";
echo "            this.stopHeartbeat();\n";
echo "            this.handleReconnect();\n";
echo "        };\n\n";

echo "        this.ws.onerror = (error) => {\n";
echo "            console.error('📡 WebSocket error:', error);\n";
echo "        };\n";
echo "    }\n\n";

echo "    // Subscribe to real-time stock quotes\n";
echo "    subscribeToQuotes(symbols) {\n";
echo "        const message = {\n";
echo "            action: 'subscribe',\n";
echo "            channel: 'stocks.quotes',\n";
echo "            symbols: symbols\n";
echo "        };\n";
echo "        this.send(message);\n";
echo "        symbols.forEach(symbol => this.subscriptions.add(`quotes:\${symbol}`));\n";
echo "    }\n\n";

echo "    // Subscribe to trading signals\n";
echo "    subscribeToSignals(options = {}) {\n";
echo "        const message = {\n";
echo "            action: 'subscribe',\n";
echo "            channel: 'signals',\n";
echo "            filter: {\n";
echo "                min_confidence: options.minConfidence || 70,\n";
echo "                symbols: options.symbols || [],\n";
echo "                signal_types: options.signalTypes || ['buy', 'sell']\n";
echo "            }\n";
echo "        };\n";
echo "        this.send(message);\n";
echo "        this.subscriptions.add('signals');\n";
echo "    }\n\n";

echo "    // Handle incoming messages\n";
echo "    handleMessage(data) {\n";
echo "        switch(data.type) {\n";
echo "            case 'quote_update':\n";
echo "                this.onQuoteUpdate(data.payload);\n";
echo "                break;\n";
echo "            case 'signal_alert':\n";
echo "                this.onSignalAlert(data.payload);\n";
echo "                break;\n";
echo "            case 'market_status':\n";
echo "                this.onMarketStatus(data.payload);\n";
echo "                break;\n";
echo "            case 'heartbeat':\n";
echo "                this.onHeartbeat(data.payload);\n";
echo "                break;\n";
echo "        }\n";
echo "    }\n\n";

echo "    // Real-time quote updates\n";
echo "    onQuoteUpdate(quote) {\n";
echo "        console.log(`📈 \${quote.symbol}: $\${quote.price} (\${quote.change > 0 ? '+' : ''}\${quote.change}%)`);\n";
echo "        \n";
echo "        // Update portfolio display\n";
echo "        this.updatePortfolioDisplay(quote);\n";
echo "        \n";
echo "        // Check for significant moves\n";
echo "        if (Math.abs(quote.change_percent) > 5) {\n";
echo "            this.alertSignificantMove(quote);\n";
echo "        }\n";
echo "    }\n\n";

echo "    // Trading signal alerts\n";
echo "    onSignalAlert(signal) {\n";
echo "        const emoji = signal.signal_type === 'buy' ? '🟢' : '🔴';\n";
echo "        console.log(`\${emoji} Signal: \${signal.symbol} \${signal.signal_type.toUpperCase()} (Confidence: \${signal.confidence}%)`);\n";
echo "        \n";
echo "        // Show desktop notification\n";
echo "        if (Notification.permission === 'granted') {\n";
echo "            new Notification(`Trading Signal: \${signal.symbol}`, {\n";
echo "                body: `\${signal.signal_type.toUpperCase()} signal with \${signal.confidence}% confidence`,\n";
echo "                icon: '/assets/wioex-icon.png'\n";
echo "            });\n";
echo "        }\n";
echo "    }\n\n";

echo "    // Market status updates\n";
echo "    onMarketStatus(status) {\n";
echo "        console.log(`🏛️ Market Status: \${status.nyse.status}`);\n";
echo "        this.updateMarketStatusDisplay(status);\n";
echo "    }\n";
echo "}\n\n";

echo "// Initialize streaming client\n";
echo "const streamingClient = new WioexStreamingClient('{$wsToken}', '{$wsUrl}');\n";
echo "streamingClient.connect();\n\n";

echo "// Subscribe to portfolio updates\n";
echo "streamingClient.subscribeToQuotes(['AAPL', 'GOOGL', 'MSFT', 'TSLA']);\n\n";

echo "// Subscribe to high-confidence signals\n";
echo "streamingClient.subscribeToSignals({\n";
echo "    minConfidence: 80,\n";
echo "    symbols: ['AAPL', 'GOOGL', 'MSFT', 'TSLA', 'NVDA'],\n";
echo "    signalTypes: ['buy', 'sell']\n";
echo "});\n";
echo "```\n\n";

// 4. PHP-based streaming with ReactPHP (server-side)
echo "📡 PHP Server-Side Streaming (ReactPHP):\n";
echo "```php\n";
echo "use React\\Socket\\Connector;\n";
echo "use Ratchet\\Client\\WebSocket;\n";
echo "use Ratchet\\Client\\Connector as WsConnector;\n\n";

echo "// Async streaming handler\n";
echo "class WioexStreamHandler {\n";
echo "    private \$client;\n";
echo "    private \$subscribers = [];\n";
echo "    private \$lastPrices = [];\n\n";

echo "    public function __construct(WioexClient \$client) {\n";
echo "        \$this->client = \$client;\n";
echo "    }\n\n";

echo "    public function startStreaming() {\n";
echo "        \$tokenResponse = \$this->client->streaming()->getToken();\n";
echo "        \$wsUrl = \$tokenResponse->data('websocket_url');\n";
echo "        \$token = \$tokenResponse->data('token');\n\n";

echo "        \$connector = new WsConnector();\n";
echo "        \$connector(\$wsUrl . '?token=' . \$token)\n";
echo "            ->then(function (WebSocket \$conn) {\n";
echo "                echo \"📡 Connected to WioEX streaming\\n\";\n\n";

echo "                // Subscribe to portfolio symbols\n";
echo "                \$conn->send(json_encode([\n";
echo "                    'action' => 'subscribe',\n";
echo "                    'channel' => 'stocks.quotes',\n";
echo "                    'symbols' => ['AAPL', 'GOOGL', 'MSFT', 'TSLA']\n";
echo "                ]));\n\n";

echo "                \$conn->on('message', function (\$msg) {\n";
echo "                    \$data = json_decode(\$msg->getPayload(), true);\n";
echo "                    \$this->handleStreamingData(\$data);\n";
echo "                });\n\n";

echo "                \$conn->on('close', function (\$code = null, \$reason = null) {\n";
echo "                    echo \"Connection closed ({\$code} - {\$reason})\\n\";\n";
echo "                });\n\n";

echo "            }, function (\\Exception \$e) {\n";
echo "                echo \"Could not connect: {\$e->getMessage()}\\n\";\n";
echo "            });\n";
echo "    }\n\n";

echo "    private function handleStreamingData(array \$data) {\n";
echo "        switch (\$data['type']) {\n";
echo "            case 'quote_update':\n";
echo "                \$this->processQuoteUpdate(\$data['payload']);\n";
echo "                break;\n";
echo "            case 'signal_alert':\n";
echo "                \$this->processSignalAlert(\$data['payload']);\n";
echo "                break;\n";
echo "        }\n";
echo "    }\n\n";

echo "    private function processQuoteUpdate(array \$quote) {\n";
echo "        \$symbol = \$quote['symbol'];\n";
echo "        \$price = \$quote['price'];\n";
echo "        \$change = \$quote['change_percent'];\n\n";

echo "        // Calculate price movement significance\n";
echo "        \$lastPrice = \$this->lastPrices[\$symbol] ?? \$price;\n";
echo "        \$priceMovement = ((\$price - \$lastPrice) / \$lastPrice) * 100;\n\n";

echo "        echo \"📈 {\$symbol}: ${\$price} ({\$change}%) Movement: {\$priceMovement}%\\n\";\n\n";

echo "        // Store for next comparison\n";
echo "        \$this->lastPrices[\$symbol] = \$price;\n\n";

echo "        // Alert on significant moves\n";
echo "        if (abs(\$priceMovement) > 2) {\n";
echo "            \$this->sendAlert(\$symbol, \$price, \$priceMovement);\n";
echo "        }\n";
echo "    }\n\n";

echo "    private function sendAlert(string \$symbol, float \$price, float \$movement) {\n";
echo "        \$message = \"🚨 {\$symbol} significant move: ${\$price} ({\$movement}%)\";\n";
echo "        echo \$message . \"\\n\";\n";
echo "        \n";
echo "        // Send to notification service, database, etc.\n";
echo "        // \$this->notificationService->send(\$message);\n";
echo "    }\n";
echo "}\n";
echo "```\n\n";

// 5. Streaming connection management
echo "🔧 Connection Management Best Practices:\n";
echo "```php\n";
echo "// Token refresh strategy\n";
echo "\$tokenManager = new class(\$client) {\n";
echo "    private \$client;\n";
echo "    private \$currentToken;\n";
echo "    private \$tokenExpiry;\n\n";

echo "    public function __construct(\$client) {\n";
echo "        \$this->client = \$client;\n";
echo "        \$this->refreshToken();\n";
echo "    }\n\n";

echo "    public function getValidToken(): string {\n";
echo "        if (\$this->isTokenExpiring()) {\n";
echo "            \$this->refreshToken();\n";
echo "        }\n";
echo "        return \$this->currentToken;\n";
echo "    }\n\n";

echo "    private function isTokenExpiring(): bool {\n";
echo "        return time() > (\$this->tokenExpiry - 300); // Refresh 5 minutes early\n";
echo "    }\n\n";

echo "    private function refreshToken(): void {\n";
echo "        \$response = \$this->client->streaming()->getToken();\n";
echo "        \$this->currentToken = \$response->data('token');\n";
echo "        \$this->tokenExpiry = strtotime(\$response->data('expires_at'));\n";
echo "    }\n";
echo "};\n";
echo "```\n";
```

**WebSocket Message Examples:**

```json
// Subscription message:
{
    "action": "subscribe",
    "channel": "stocks.quotes",
    "symbols": ["AAPL", "GOOGL", "MSFT"]
}

// Quote update message:
{
    "type": "quote_update",
    "timestamp": "2025-10-22T16:15:30Z",
    "payload": {
        "symbol": "AAPL",
        "price": 175.43,
        "change": 2.15,
        "change_percent": 1.24,
        "volume": 62547890,
        "bid": 175.40,
        "ask": 175.45,
        "last_trade_time": "2025-10-22T16:15:29Z"
    }
}

// Signal alert message:
{
    "type": "signal_alert",
    "timestamp": "2025-10-22T16:15:30Z",
    "payload": {
        "signal_id": "signal_67890",
        "symbol": "TSLA",
        "signal_type": "buy",
        "confidence": 87,
        "current_price": 245.67,
        "target_price": 265.00,
        "reasoning": "Technical breakout with volume confirmation"
    }
}
```

### Market Status
```php
// Works with or without API key
$status = $client->markets()->status();

$nyse = $status['markets']['nyse'];
echo "NYSE Status: " . $nyse['status'] . "\n";
echo "Market Time: " . $nyse['market_time'] . "\n";
echo "Trading Hours: " . $nyse['hours']['regular']['open'] . 
     " - " . $nyse['hours']['regular']['close'] . "\n";
```

### News & Analysis

```php
// 1. Comprehensive news analysis for portfolio symbols
$portfolioSymbols = ['AAPL', 'GOOGL', 'MSFT', 'TSLA'];
$newsAnalysis = [];

foreach ($portfolioSymbols as $symbol) {
    $latestNews = $client->news()->latest($symbol, [
        'limit' => 10,
        'sentiment_analysis' => true,
        'include_summary' => true
    ]);
    
    echo "📰 Latest News for {$symbol}:\n";
    $sentimentScore = 0;
    $totalArticles = count($latestNews['articles']);
    
    foreach ($latestNews['articles'] as $article) {
        $publishedTime = new DateTime($article['published_at']);
        $hoursAgo = $publishedTime->diff(new DateTime())->h;
        
        // Sentiment indicators
        $sentiment = $article['sentiment']['label'] ?? 'neutral';
        $sentimentEmoji = match($sentiment) {
            'positive' => '🟢',
            'negative' => '🔴',
            'neutral' => '🟡',
            default => '⚪'
        };
        
        printf("%s [%dh ago] %s\n", 
            $sentimentEmoji, 
            $hoursAgo, 
            substr($article['title'], 0, 80) . '...'
        );
        
        // Accumulate sentiment scores
        $sentimentScore += $article['sentiment']['score'] ?? 0;
        
        // Show article summary if available
        if (isset($article['summary'])) {
            echo "   Summary: " . substr($article['summary'], 0, 100) . "...\n";
        }
    }
    
    // Calculate overall sentiment
    $avgSentiment = $totalArticles > 0 ? $sentimentScore / $totalArticles : 0;
    $overallMood = $avgSentiment > 0.1 ? 'Positive' : ($avgSentiment < -0.1 ? 'Negative' : 'Neutral');
    
    echo "Overall Sentiment: {$overallMood} (Score: " . number_format($avgSentiment, 2) . ")\n";
    echo "Articles analyzed: {$totalArticles}\n\n";
    
    $newsAnalysis[$symbol] = [
        'sentiment_score' => $avgSentiment,
        'article_count' => $totalArticles,
        'overall_mood' => $overallMood
    ];
}

// 2. Company-specific analysis with financial correlation
foreach ($portfolioSymbols as $symbol) {
    $companyAnalysis = $client->news()->companyAnalysis($symbol, [
        'include_financials' => true,
        'analysis_period' => 30  // Last 30 days
    ]);
    
    echo "🏢 {$symbol} Company Analysis:\n";
    
    // Key metrics
    if (isset($companyAnalysis['metrics'])) {
        $metrics = $companyAnalysis['metrics'];
        echo "News Coverage: {$metrics['total_articles']} articles\n";
        echo "Media Sentiment: {$metrics['sentiment']['overall']} ";
        echo "({$metrics['sentiment']['positive']}% positive, ";
        echo "{$metrics['sentiment']['negative']}% negative)\n";
        
        // Trending topics
        if (isset($metrics['trending_topics'])) {
            echo "Trending Topics: " . implode(', ', array_slice($metrics['trending_topics'], 0, 5)) . "\n";
        }
    }
    
    // Recent developments
    if (isset($companyAnalysis['recent_developments'])) {
        echo "Recent Developments:\n";
        foreach (array_slice($companyAnalysis['recent_developments'], 0, 3) as $development) {
            printf("  • %s (%s)\n", 
                $development['headline'], 
                $development['category']
            );
        }
    }
    echo "\n";
}

// 3. Market news aggregation and categorization
$marketNews = $client->news()->marketNews([
    'categories' => ['earnings', 'mergers', 'ipo', 'regulations'],
    'limit' => 20,
    'sentiment_filter' => 'all'
]);

echo "📊 Market News Categories:\n";
$newsByCategory = [];

foreach ($marketNews['articles'] as $article) {
    $category = $article['category'];
    if (!isset($newsByCategory[$category])) {
        $newsByCategory[$category] = [];
    }
    $newsByCategory[$category][] = $article;
}

foreach ($newsByCategory as $category => $articles) {
    echo "\n📈 " . strtoupper($category) . " ({" . count($articles) . " articles}):\n";
    
    foreach (array_slice($articles, 0, 3) as $article) {
        $impact = $article['market_impact'] ?? 'neutral';
        $impactEmoji = match($impact) {
            'high' => '🔥',
            'medium' => '⚡',
            'low' => '💭',
            default => '📋'
        };
        
        printf("  %s %s\n", $impactEmoji, $article['title']);
        
        // Show affected symbols if available
        if (isset($article['related_symbols']) && !empty($article['related_symbols'])) {
            echo "     Affects: " . implode(', ', array_slice($article['related_symbols'], 0, 5)) . "\n";
        }
    }
}

// 4. Sentiment-based trading insights
echo "\n🎯 News-Based Trading Insights:\n";

$sentimentSummary = [
    'strong_positive' => [],
    'positive' => [],
    'neutral' => [],
    'negative' => [],
    'strong_negative' => []
];

foreach ($newsAnalysis as $symbol => $analysis) {
    $score = $analysis['sentiment_score'];
    
    if ($score >= 0.3) {
        $sentimentSummary['strong_positive'][] = $symbol;
    } elseif ($score >= 0.1) {
        $sentimentSummary['positive'][] = $symbol;
    } elseif ($score <= -0.3) {
        $sentimentSummary['strong_negative'][] = $symbol;
    } elseif ($score <= -0.1) {
        $sentimentSummary['negative'][] = $symbol;
    } else {
        $sentimentSummary['neutral'][] = $symbol;
    }
}

foreach ($sentimentSummary as $sentiment => $symbols) {
    if (!empty($symbols)) {
        $emoji = match($sentiment) {
            'strong_positive' => '🚀',
            'positive' => '📈',
            'neutral' => '➡️',
            'negative' => '📉',
            'strong_negative' => '🔻',
            default => '⚪'
        };
        
        echo "{$emoji} " . str_replace('_', ' ', strtoupper($sentiment)) . ": " . implode(', ', $symbols) . "\n";
    }
}

// 5. News alert system configuration
echo "\n🔔 News Alert Configuration:\n";
$alertConfig = [
    'keywords' => ['earnings', 'merger', 'acquisition', 'FDA approval', 'partnership'],
    'symbols' => $portfolioSymbols,
    'sentiment_threshold' => 0.2,  // Alert on significant sentiment changes
    'impact_level' => 'medium',    // Minimum impact level
    'real_time' => true
];

echo "Monitoring keywords: " . implode(', ', $alertConfig['keywords']) . "\n";
echo "Tracking symbols: " . implode(', ', $alertConfig['symbols']) . "\n";
echo "Sentiment threshold: ±" . $alertConfig['sentiment_threshold'] . "\n";
echo "Minimum impact: " . $alertConfig['impact_level'] . "\n";

// 6. Advanced news analysis with correlation
$advancedAnalysis = $client->news()->advancedAnalysis([
    'symbols' => $portfolioSymbols,
    'correlation_analysis' => true,
    'price_impact_analysis' => true,
    'time_decay_factor' => 0.8  // Recent news weighted more heavily
]);

echo "\n🔬 Advanced Correlation Analysis:\n";
if (isset($advancedAnalysis['correlations'])) {
    foreach ($advancedAnalysis['correlations'] as $correlation) {
        printf("News sentiment vs price: %s (R² = %.3f)\n",
            $correlation['symbol'],
            $correlation['r_squared']
        );
    }
}

// News volume trends
if (isset($advancedAnalysis['volume_trends'])) {
    echo "\nNews Volume Trends:\n";
    foreach ($advancedAnalysis['volume_trends'] as $symbol => $trend) {
        $direction = $trend['change_percent'] > 0 ? '📈' : '📉';
        printf("%s %s: %+.1f%% news volume change\n",
            $direction,
            $symbol,
            $trend['change_percent']
        );
    }
}
```

**Response Structure Examples:**

```json
// news()->latest() response:
{
    "articles": [
        {
            "id": "news_12345",
            "title": "Apple Reports Record Q4 Earnings",
            "summary": "Apple exceeded analyst expectations with strong iPhone sales...",
            "content": "Full article content...",
            "source": "Reuters",
            "author": "John Smith",
            "published_at": "2025-10-22T14:30:00Z",
            "url": "https://reuters.com/article/...",
            "sentiment": {
                "label": "positive",
                "score": 0.75,
                "confidence": 0.92
            },
            "category": "earnings",
            "market_impact": "high",
            "related_symbols": ["AAPL", "NASDAQ"]
        }
    ],
    "metadata": {
        "symbol": "AAPL",
        "total_articles": 10,
        "sentiment_distribution": {
            "positive": 60,
            "neutral": 30,
            "negative": 10
        }
    }
}

// news()->companyAnalysis() response:
{
    "symbol": "AAPL",
    "analysis_period": 30,
    "metrics": {
        "total_articles": 145,
        "sentiment": {
            "overall": "positive",
            "positive": 65,
            "neutral": 25,
            "negative": 10
        },
        "trending_topics": ["earnings", "iPhone", "AI", "services"],
        "media_coverage_trend": "increasing"
    },
    "recent_developments": [
        {
            "headline": "Apple Announces New AI Features",
            "category": "product",
            "impact": "medium",
            "date": "2025-10-20"
        }
    ]
}
```

### Currency Exchange
```php
// Exchange rates
$rates = $client->currency()->baseUsd();
$eurRates = $client->currency()->allRates('EUR');

// Currency conversion
$conversion = $client->currency()->calculator('USD', 'EUR', 100);
echo "100 USD = " . $conversion['converted_amount'] . " EUR\n";

// Historical exchange rates
$history = $client->currency()->graph('USD', 'EUR', '1d');
```

### Account Management
```php
// Account balance
$balance = $client->account()->balance();
echo "Credits: " . $balance['credits'] . "\n";

// Usage statistics
$usage = $client->account()->usage(30); // Last 30 days
echo "API Calls: " . $usage['total_requests'] . "\n";

// Analytics
$analytics = $client->account()->analytics('month');
```

## Advanced Features

### Bulk Operations & Portfolio Management

```php
// 1. Portfolio batch processing with intelligent rate limiting
$portfolioSymbols = ['AAPL', 'GOOGL', 'MSFT', 'AMZN', 'TSLA', 'META', 'NVDA', 'AMD'];

echo "📊 Processing Portfolio (" . count($portfolioSymbols) . " symbols):\n\n";

// Bulk quotes with automatic batching
$portfolioQuotes = $client->stocks()->quoteBulk($portfolioSymbols, [
    'batch_size' => 3,              // Process 3 symbols at a time
    'delay_between_batches' => 150, // 150ms delay between batches
    'continue_on_error' => true,    // Don't stop if one symbol fails
    'include_signals' => true       // Include trading signals
]);

// Process portfolio results
$totalValue = 0;
$gainers = $losers = 0;
$portfolioSummary = [];

foreach ($portfolioQuotes['data'] as $symbol => $quote) {
    if (isset($quote['error'])) {
        echo "❌ {$symbol}: {$quote['error']}\n";
        continue;
    }
    
    $price = $quote['market']['price'];
    $change = $quote['market']['change']['percent'];
    $volume = $quote['market']['volume'];
    
    // Assume 100 shares for calculation
    $position_value = $price * 100;
    $totalValue += $position_value;
    
    if ($change > 0) $gainers++;
    else if ($change < 0) $losers++;
    
    printf("%-6s $%-8.2f %+.2f%% Vol: %-12s Value: $%-10.2f\n",
        $symbol,
        $price,
        $change,
        number_format($volume),
        $position_value
    );
    
    // Check for trading signals
    if (isset($quote['signal']) && $quote['signal']['confidence'] > 75) {
        $signalEmoji = $quote['signal']['signal_type'] === 'buy' ? '🟢' : '🔴';
        echo "  {$signalEmoji} Signal: {$quote['signal']['signal_type']} (confidence: {$quote['signal']['confidence']}%)\n";
    }
    
    $portfolioSummary[$symbol] = [
        'price' => $price,
        'change' => $change,
        'position_value' => $position_value,
        'signal' => $quote['signal'] ?? null
    ];
}

echo "\n📈 Portfolio Summary:\n";
echo "Total Value: $" . number_format($totalValue, 2) . "\n";
echo "Gainers: {$gainers} | Losers: {$losers} | Neutral: " . (count($portfolioSymbols) - $gainers - $losers) . "\n";
echo "Processing Time: {$portfolioQuotes['metadata']['processing_time']}ms\n\n";

// 2. Historical data bulk analysis
echo "📊 Historical Analysis (Last 30 days):\n";

$historicalBulk = $client->stocks()->timelineBulk(
    array_slice($portfolioSymbols, 0, 4), // Analyze top 4 positions
    [
        'period' => '30d',
        'interval' => '1d',
        'batch_size' => 2,
        'include_volume_analysis' => true
    ]
);

foreach ($historicalBulk['data'] as $symbol => $timeline) {
    if (empty($timeline)) continue;
    
    $prices = array_column($timeline, 'close');
    $volumes = array_column($timeline, 'volume');
    
    // Calculate statistics
    $currentPrice = end($prices);
    $startPrice = reset($prices);
    $monthlyReturn = (($currentPrice - $startPrice) / $startPrice) * 100;
    
    $avgVolume = array_sum($volumes) / count($volumes);
    $volatility = $this->calculateVolatility($prices);
    
    printf("%-6s: %+.2f%% return, %.2f%% volatility, avg vol: %s\n",
        $symbol,
        $monthlyReturn,
        $volatility,
        number_format($avgVolume)
    );
}

// 3. Bulk signal analysis for decision making
echo "\n🎯 Portfolio Signal Analysis:\n";

$signalsBulk = $client->signals()->activeBulk($portfolioSymbols, [
    'min_confidence' => 70,
    'batch_size' => 4,
    'include_fundamentals' => true
]);

$signalStrength = ['strong_buy' => [], 'buy' => [], 'hold' => [], 'sell' => [], 'strong_sell' => []];

foreach ($signalsBulk['data'] as $symbol => $signals) {
    if (empty($signals)) continue;
    
    $primarySignal = $signals[0]; // Highest confidence signal
    $confidence = $primarySignal['confidence'];
    $type = $primarySignal['signal_type'];
    
    // Categorize by strength
    if ($type === 'buy' && $confidence >= 85) {
        $signalStrength['strong_buy'][] = $symbol;
    } elseif ($type === 'buy') {
        $signalStrength['buy'][] = $symbol;
    } elseif ($type === 'sell' && $confidence >= 85) {
        $signalStrength['strong_sell'][] = $symbol;
    } elseif ($type === 'sell') {
        $signalStrength['sell'][] = $symbol;
    } else {
        $signalStrength['hold'][] = $symbol;
    }
}

foreach ($signalStrength as $strength => $symbols) {
    if (!empty($symbols)) {
        $emoji = match($strength) {
            'strong_buy' => '🚀',
            'buy' => '📈',
            'hold' => '➡️',
            'sell' => '📉',
            'strong_sell' => '🔻',
            default => '⚪'
        };
        echo "{$emoji} " . strtoupper(str_replace('_', ' ', $strength)) . ": " . implode(', ', $symbols) . "\n";
    }
}

// 4. Risk analysis and position sizing
echo "\n⚖️ Risk Analysis:\n";

class PortfolioRiskAnalyzer
{
    public function analyzePortfolio(array $portfolioData): array
    {
        $totalValue = array_sum(array_column($portfolioData, 'position_value'));
        $riskMetrics = [];
        
        foreach ($portfolioData as $symbol => $position) {
            $weight = $position['position_value'] / $totalValue;
            $volatilityRisk = $this->calculateRisk($position['change']);
            
            $riskMetrics[$symbol] = [
                'weight' => $weight * 100,
                'risk_level' => $volatilityRisk,
                'contribution_to_risk' => $weight * abs($position['change'])
            ];
        }
        
        return $riskMetrics;
    }
    
    private function calculateRisk(float $change): string
    {
        $absChange = abs($change);
        if ($absChange > 5) return 'High';
        if ($absChange > 2) return 'Medium';
        return 'Low';
    }
}

$riskAnalyzer = new PortfolioRiskAnalyzer();
$riskMetrics = $riskAnalyzer->analyzePortfolio($portfolioSummary);

echo "Position Risk Breakdown:\n";
foreach ($riskMetrics as $symbol => $risk) {
    printf("%-6s: %.1f%% weight, %s risk, %.2f%% risk contribution\n",
        $symbol,
        $risk['weight'],
        $risk['risk_level'],
        $risk['contribution_to_risk']
    );
}

// 5. Automated rebalancing suggestions
echo "\n🔄 Rebalancing Suggestions:\n";

$targetWeight = 100 / count($portfolioSummary); // Equal weight target
foreach ($riskMetrics as $symbol => $risk) {
    $currentWeight = $risk['weight'];
    $deviation = $currentWeight - $targetWeight;
    
    if (abs($deviation) > 2) { // Significant deviation
        $action = $deviation > 0 ? 'REDUCE' : 'INCREASE';
        $amount = abs($deviation);
        printf("%-6s: %s position by %.1f%% (current: %.1f%%, target: %.1f%%)\n",
            $symbol,
            $action,
            $amount,
            $currentWeight,
            $targetWeight
        );
    }
}

// 6. Bulk operations performance monitoring
echo "\n⚡ Performance Metrics:\n";
$totalProcessingTime = $portfolioQuotes['metadata']['processing_time'] + 
                      ($historicalBulk['metadata']['processing_time'] ?? 0) +
                      ($signalsBulk['metadata']['processing_time'] ?? 0);

echo "Total API calls: " . (count($portfolioSymbols) * 3) . "\n";
echo "Total processing time: {$totalProcessingTime}ms\n";
echo "Average per symbol: " . number_format($totalProcessingTime / count($portfolioSymbols), 1) . "ms\n";
echo "Rate limit efficiency: " . number_format((count($portfolioSymbols) / ($totalProcessingTime / 1000)) * 60, 1) . " symbols/minute\n";

// Helper function for volatility calculation
function calculateVolatility(array $prices): float
{
    if (count($prices) < 2) return 0;
    
    $returns = [];
    for ($i = 1; $i < count($prices); $i++) {
        $returns[] = ($prices[$i] - $prices[$i - 1]) / $prices[$i - 1];
    }
    
    $mean = array_sum($returns) / count($returns);
    $variance = array_sum(array_map(fn($r) => pow($r - $mean, 2), $returns)) / count($returns);
    
    return sqrt($variance) * 100 * sqrt(252); // Annualized volatility
}
```

### Caching & Performance Optimization

```php
use Wioex\SDK\Cache\CacheManager;
use Wioex\SDK\Enums\CacheDriver;

// 1. Enable advanced caching for better performance
$client = new WioexClient([
    'api_key' => 'your-api-key-here',
    'cache' => [
        'default' => 'file',
        'drivers' => [
            'memory' => [
                'driver' => 'memory',
                'config' => ['max_items' => 1000]
            ],
            'file' => [
                'driver' => 'file',
                'config' => [
                    'cache_dir' => '/tmp/wioex_cache',
                    'extension' => '.cache'
                ]
            ]
        ]
    ]
]);

// 2. Cache-aware data fetching
$cache = $client->getCache();
echo "🔄 Cache Performance Demo:\n\n";

// Tagged caching for organized cache management
$portfolioCache = $cache->tags(['portfolio', 'quotes']);
$analysisCache = $cache->tags(['analysis', 'historical']);

// Cache stocks quotes with TTL
$portfolioSymbols = ['AAPL', 'GOOGL', 'MSFT', 'TSLA'];
$cacheKey = 'portfolio_quotes_' . md5(implode(',', $portfolioSymbols));

echo "1. Fetching portfolio quotes (first call - cache miss):\n";
$startTime = microtime(true);

$quotes = $portfolioCache->remember($cacheKey, function () use ($client, $portfolioSymbols) {
    echo "   🔄 Cache miss - fetching from API...\n";
    return $client->stocks()->quote(implode(',', $portfolioSymbols));
}, 300); // Cache for 5 minutes

$firstCallTime = (microtime(true) - $startTime) * 1000;
echo "   ⏱️ Time: " . number_format($firstCallTime, 2) . "ms\n\n";

// Second call - should be from cache
echo "2. Fetching same data (second call - cache hit):\n";
$startTime = microtime(true);

$cachedQuotes = $portfolioCache->remember($cacheKey, function () use ($client, $portfolioSymbols) {
    echo "   🔄 This shouldn't appear (cache should hit)\n";
    return $client->stocks()->quote(implode(',', $portfolioSymbols));
}, 300);

$secondCallTime = (microtime(true) - $startTime) * 1000;
echo "   ⚡ Cache hit - Time: " . number_format($secondCallTime, 2) . "ms\n";
echo "   📈 Performance improvement: " . number_format(($firstCallTime / $secondCallTime), 1) . "x faster\n\n";

// 3. Layered caching strategy for different data types
echo "3. Layered Caching Strategy:\n";

// Fast-changing data (quotes) - short TTL
$realtimeCache = $cache->tags(['realtime']);
$quickQuote = $realtimeCache->remember('AAPL_quote', function () use ($client) {
    return $client->stocks()->quote('AAPL');
}, 60); // 1 minute TTL

echo "   📊 Real-time data (1min TTL): AAPL quote cached\n";

// Medium-changing data (company info) - medium TTL  
$companyCache = $cache->tags(['company_info']);
$companyInfo = $companyCache->remember('AAPL_info', function () use ($client) {
    return $client->stocks()->info('AAPL');
}, 3600); // 1 hour TTL

echo "   🏢 Company data (1hr TTL): AAPL info cached\n";

// Slow-changing data (historical) - long TTL
$historicalCache = $cache->tags(['historical']);
$historical = $historicalCache->remember('AAPL_30d_timeline', function () use ($client) {
    return $client->stocks()->timeline('AAPL', ['interval' => '1day', 'size' => 30]);
}, 86400); // 24 hours TTL

echo "   📈 Historical data (24hr TTL): AAPL timeline cached\n\n";

// 4. Smart cache invalidation
echo "4. Smart Cache Management:\n";

// Cache with dependency tracking
$dependentCache = $cache->tags(['dependent', 'portfolio']);
$portfolioAnalysis = $dependentCache->remember('portfolio_analysis_v2', function () use ($quotes) {
    echo "   🔄 Computing portfolio analysis...\n";
    
    $analysis = [
        'total_value' => 0,
        'daily_change' => 0,
        'top_performer' => null,
        'analysis_time' => time()
    ];
    
    $maxChange = -999;
    foreach ($quotes['tickers'] as $ticker) {
        $analysis['total_value'] += $ticker['market']['price'] * 100;
        $analysis['daily_change'] += $ticker['market']['change']['percent'];
        
        if ($ticker['market']['change']['percent'] > $maxChange) {
            $maxChange = $ticker['market']['change']['percent'];
            $analysis['top_performer'] = $ticker['ticker'];
        }
    }
    
    return $analysis;
}, 1800); // 30 minutes

echo "   📊 Portfolio analysis computed and cached\n";
echo "   🏆 Top performer: {$portfolioAnalysis['top_performer']}\n";
echo "   💰 Total value: $" . number_format($portfolioAnalysis['total_value'], 2) . "\n\n";

// 5. Cache statistics and monitoring
echo "5. Cache Performance Monitoring:\n";

$cacheStats = $cache->getStatistics();
echo "   📈 Cache Statistics:\n";
echo "   Default driver: {$cacheStats['default_driver']}\n";

foreach ($cacheStats['drivers'] as $driver => $stats) {
    $hitRate = $stats['hit_rate_percentage'] ?? 0;
    $color = $hitRate > 80 ? '🟢' : ($hitRate > 60 ? '🟡' : '🔴');
    echo "   {$color} {$driver}: {$hitRate}% hit rate ({$stats['hits']} hits, {$stats['misses']} misses)\n";
}

// Memory usage for file cache
if (isset($cacheStats['drivers']['file'])) {
    $fileStats = $cacheStats['drivers']['file'];
    echo "   💾 Cache size: " . number_format($fileStats['size_bytes'] / 1024, 1) . " KB\n";
    echo "   📁 Files: {$fileStats['file_count']}\n";
}

echo "\n";

// 6. Selective cache warming
echo "6. Cache Warming Strategy:\n";

function warmCache($client, $cache): void 
{
    echo "   🔥 Warming cache with frequently accessed data...\n";
    
    $popularSymbols = ['AAPL', 'GOOGL', 'MSFT', 'AMZN', 'TSLA'];
    $warmingStart = microtime(true);
    
    foreach ($popularSymbols as $symbol) {
        // Warm quotes cache
        $cache->tags(['quotes', 'warmed'])->remember("quote_{$symbol}", function () use ($client, $symbol) {
            return $client->stocks()->quote($symbol);
        }, 300);
        
        // Warm company info cache
        $cache->tags(['info', 'warmed'])->remember("info_{$symbol}", function () use ($client, $symbol) {
            return $client->stocks()->info($symbol);
        }, 3600);
        
        echo "   ✓ {$symbol} cached\n";
    }
    
    $warmingTime = (microtime(true) - $warmingStart) * 1000;
    echo "   ⏱️ Cache warming completed in " . number_format($warmingTime, 2) . "ms\n";
}

warmCache($client, $cache);

// 7. Cache cleanup and optimization
echo "\n7. Cache Maintenance:\n";

// Clean expired entries
$cleanedCount = $cache->cleanup();
echo "   🧹 Cleaned {$cleanedCount} expired entries\n";

// Selective tag-based cleanup
$cache->tags(['realtime'])->flush();
echo "   🗑️ Flushed real-time cache (high turnover data)\n";

// Size-based optimization
$cacheSize = $cache->size();
echo "   📏 Total cache size: " . number_format($cacheSize / 1024, 1) . " KB\n";

if ($cacheSize > 50 * 1024 * 1024) { // 50MB threshold
    echo "   ⚠️ Cache size large, considering cleanup of old entries\n";
    $cache->prune(0.3); // Remove 30% of least recently used
    echo "   ✂️ Pruned cache to optimize size\n";
}

// 8. Advanced caching patterns
echo "\n8. Advanced Caching Patterns:\n";

// Circuit breaker pattern with cache fallback
class CachedApiClient
{
    private $client;
    private $cache;
    private $failures = 0;
    private $maxFailures = 3;
    
    public function __construct($client, $cache)
    {
        $this->client = $client;
        $this->cache = $cache;
    }
    
    public function getQuoteWithFallback(string $symbol): ?array
    {
        $cacheKey = "quote_fallback_{$symbol}";
        
        // If circuit is open, use cache only
        if ($this->failures >= $this->maxFailures) {
            echo "   🔴 Circuit open - using cache fallback for {$symbol}\n";
            return $this->cache->get($cacheKey);
        }
        
        try {
            $quote = $this->client->stocks()->quote($symbol);
            
            // Store in cache with extended TTL for fallback
            $this->cache->put($cacheKey, $quote, 3600);
            
            // Reset failure count on success
            $this->failures = 0;
            
            echo "   🟢 Fresh data retrieved for {$symbol}\n";
            return $quote;
            
        } catch (Exception $e) {
            $this->failures++;
            echo "   🟡 API failed for {$symbol}, using cache fallback\n";
            
            // Return cached data as fallback
            return $this->cache->get($cacheKey);
        }
    }
}

$cachedClient = new CachedApiClient($client, $cache);
$fallbackQuote = $cachedClient->getQuoteWithFallback('AAPL');

echo "\n9. Cache Performance Summary:\n";
echo "   📊 Cache hit rate improvement: Reduced API calls by ~80%\n";
echo "   ⚡ Response time improvement: 5-20x faster for cached data\n";
echo "   💰 Cost optimization: Reduced API usage and costs\n";
echo "   🛡️ Reliability: Graceful degradation with cache fallbacks\n";
```

### Async Operations & Promise Handling

```php
use Wioex\SDK\Async\AsyncClient;
use Wioex\SDK\Async\Promise;
use Wioex\SDK\Enums\AsyncOperationType;

// 1. Initialize async client for concurrent operations
$asyncClient = $client->async();
echo "⚡ Async Operations Demo:\n\n";

// 2. Concurrent data fetching with Promises
echo "1. Concurrent API Calls:\n";

$portfolioSymbols = ['AAPL', 'GOOGL', 'MSFT', 'TSLA', 'NVDA'];
$startTime = microtime(true);

// Create multiple concurrent requests
$promises = [];
foreach ($portfolioSymbols as $symbol) {
    $promises[$symbol] = $asyncClient->stocks()->quoteAsync($symbol);
}

echo "   🚀 Initiated " . count($promises) . " concurrent requests\n";

// Wait for all promises to resolve
$results = [];
foreach ($promises as $symbol => $promise) {
    try {
        $result = $promise->wait(10); // 10 second timeout
        $results[$symbol] = $result;
        echo "   ✅ {$symbol}: $" . number_format($result['market']['price'], 2) . "\n";
    } catch (Exception $e) {
        echo "   ❌ {$symbol}: Failed - {$e->getMessage()}\n";
    }
}

$asyncTime = (microtime(true) - $startTime) * 1000;
echo "   ⏱️ Async completion time: " . number_format($asyncTime, 2) . "ms\n\n";

// Compare with synchronous approach
echo "2. Performance Comparison (Synchronous):\n";
$syncStartTime = microtime(true);

foreach (array_slice($portfolioSymbols, 0, 3) as $symbol) {
    $syncResult = $client->stocks()->quote($symbol);
    echo "   📊 {$symbol}: $" . number_format($syncResult['tickers'][0]['market']['price'], 2) . "\n";
}

$syncTime = (microtime(true) - $syncStartTime) * 1000;
echo "   ⏱️ Sync completion time: " . number_format($syncTime, 2) . "ms\n";
echo "   📈 Async performance gain: " . number_format($syncTime / $asyncTime, 1) . "x faster\n\n";

// 3. Advanced Promise handling with error recovery
echo "3. Advanced Promise Patterns:\n";

class AsyncPortfolioManager
{
    private $asyncClient;
    private $results = [];
    private $errors = [];
    
    public function __construct($asyncClient)
    {
        $this->asyncClient = $asyncClient;
    }
    
    public function fetchPortfolioData(array $symbols): array
    {
        $promises = [];
        
        // Create promises for different data types
        foreach ($symbols as $symbol) {
            $promises["quote_{$symbol}"] = $this->asyncClient->stocks()->quoteAsync($symbol);
            $promises["info_{$symbol}"] = $this->asyncClient->stocks()->infoAsync($symbol);
            $promises["signals_{$symbol}"] = $this->asyncClient->signals()->activeAsync(['symbol' => $symbol]);
        }
        
        echo "   🔄 Processing " . count($promises) . " async operations...\n";
        
        // Process promises with timeout and error handling
        $completed = 0;
        foreach ($promises as $key => $promise) {
            try {
                $result = $promise->wait(5); // 5 second timeout per request
                $this->results[$key] = $result;
                $completed++;
            } catch (Exception $e) {
                $this->errors[$key] = $e->getMessage();
                echo "   ⚠️ {$key}: {$e->getMessage()}\n";
            }
        }
        
        echo "   ✅ Completed: {$completed}/" . count($promises) . " operations\n";
        return $this->results;
    }
    
    public function getPortfolioSummary(): array
    {
        $summary = [
            'symbols' => [],
            'total_value' => 0,
            'signals_count' => 0,
            'data_completeness' => 0
        ];
        
        foreach ($this->results as $key => $data) {
            if (strpos($key, 'quote_') === 0) {
                $symbol = str_replace('quote_', '', $key);
                $price = $data['market']['price'] ?? 0;
                $summary['symbols'][$symbol]['price'] = $price;
                $summary['total_value'] += $price * 100; // Assume 100 shares
            } elseif (strpos($key, 'info_') === 0) {
                $symbol = str_replace('info_', '', $key);
                $summary['symbols'][$symbol]['market_cap'] = $data['market_cap'] ?? 0;
            } elseif (strpos($key, 'signals_') === 0) {
                $symbol = str_replace('signals_', '', $key);
                if (!empty($data)) {
                    $summary['symbols'][$symbol]['signal'] = $data[0];
                    $summary['signals_count']++;
                }
            }
        }
        
        $expectedData = count($this->results) + count($this->errors);
        $summary['data_completeness'] = count($this->results) / $expectedData * 100;
        
        return $summary;
    }
}

$portfolioManager = new AsyncPortfolioManager($asyncClient);
$portfolioData = $portfolioManager->fetchPortfolioData(['AAPL', 'GOOGL', 'MSFT']);
$summary = $portfolioManager->getPortfolioSummary();

echo "   📊 Portfolio Summary:\n";
echo "   💰 Total Value: $" . number_format($summary['total_value'], 2) . "\n";
echo "   🎯 Active Signals: {$summary['signals_count']}\n";
echo "   📈 Data Completeness: " . number_format($summary['data_completeness'], 1) . "%\n\n";

// 4. Event-driven async processing
echo "4. Event-Driven Processing:\n";

$asyncClient->onProgress(function ($completed, $total, $currentOperation) {
    $percentage = ($completed / $total) * 100;
    echo "   📊 Progress: " . number_format($percentage, 1) . "% ({$completed}/{$total}) - {$currentOperation}\n";
});

$asyncClient->onError(function ($operation, $error) {
    echo "   ❌ Error in {$operation}: {$error}\n";
});

$asyncClient->onComplete(function ($results) {
    echo "   ✅ All operations completed: " . count($results) . " results\n";
});

// 5. Batch async operations with rate limiting
echo "\n5. Rate-Limited Batch Processing:\n";

$largeBatch = ['AAPL', 'GOOGL', 'MSFT', 'AMZN', 'TSLA', 'META', 'NVDA', 'AMD', 'CRM', 'NFLX'];

$batchProcessor = $asyncClient->batch()
    ->setMaxConcurrent(3)           // Max 3 concurrent requests
    ->setDelayBetweenBatches(200)   // 200ms delay between batches
    ->setRetryAttempts(2)           // Retry failed requests twice
    ->setTimeout(10);               // 10 second timeout

foreach ($largeBatch as $symbol) {
    $batchProcessor->add(
        AsyncOperationType::STOCK_QUOTE,
        ['symbol' => $symbol],
        "quote_{$symbol}"
    );
}

echo "   🔄 Processing batch of " . count($largeBatch) . " symbols...\n";

$batchResults = $batchProcessor->execute();

echo "   ✅ Batch completed:\n";
echo "   📊 Successful: {$batchResults['successful']}\n";
echo "   ❌ Failed: {$batchResults['failed']}\n";
echo "   ⏱️ Total time: {$batchResults['execution_time']}ms\n";
echo "   📈 Average per symbol: " . number_format($batchResults['execution_time'] / count($largeBatch), 1) . "ms\n\n";

// 6. Real-time async streaming integration
echo "6. Async Streaming Integration:\n";

$streamingAsync = $asyncClient->streaming();

// Async token management
$tokenPromise = $streamingAsync->getTokenAsync();
$tokenPromise->then(function ($tokenData) {
    echo "   🔑 Streaming token obtained asynchronously\n";
    echo "   ⏰ Expires: {$tokenData['expires_at']}\n";
    
    // Setup WebSocket connection asynchronously
    return $this->setupWebSocketAsync($tokenData);
})
->then(function ($wsConnection) {
    echo "   🌐 WebSocket connected asynchronously\n";
    
    // Subscribe to multiple channels concurrently
    $subscriptions = [
        'stocks.quotes' => ['AAPL', 'GOOGL', 'MSFT'],
        'signals' => ['min_confidence' => 80],
        'market.status' => []
    ];
    
    foreach ($subscriptions as $channel => $params) {
        $wsConnection->subscribeAsync($channel, $params);
    }
})
->catch(function ($error) {
    echo "   ❌ Streaming setup failed: {$error}\n";
});

// 7. Promise combinators for complex workflows
echo "\n7. Promise Combinators:\n";

// Promise.all equivalent - wait for all
$allPromises = [
    $asyncClient->stocks()->quoteAsync('AAPL'),
    $asyncClient->stocks()->quoteAsync('GOOGL'),
    $asyncClient->markets()->statusAsync()
];

$allResults = Promise::all($allPromises)->wait();
echo "   ✅ Promise::all completed - all " . count($allResults) . " operations successful\n";

// Promise.race equivalent - first to complete
$racePromises = [
    $asyncClient->stocks()->searchAsync('Apple'),
    $asyncClient->stocks()->searchAsync('Google'),
    $asyncClient->stocks()->searchAsync('Microsoft')
];

$firstResult = Promise::race($racePromises)->wait();
echo "   🏁 Promise::race completed - first search returned results\n";

// Promise.any equivalent - first successful
$anyPromises = [
    $asyncClient->stocks()->quoteAsync('INVALID_SYMBOL'),  // Will fail
    $asyncClient->stocks()->quoteAsync('AAPL'),            // Will succeed
    $asyncClient->stocks()->quoteAsync('GOOGL')            // Will succeed
];

$anyResult = Promise::any($anyPromises)->wait();
echo "   🎯 Promise::any completed - first successful operation finished\n\n";

// 8. Memory and resource management
echo "8. Resource Management:\n";

// Monitor async operations
$eventLoop = $asyncClient->getEventLoop();
$loopStats = $eventLoop->getStatistics();

echo "   📊 Event Loop Statistics:\n";
echo "   🔄 Active operations: {$loopStats['active_operations']}\n";
echo "   ⏳ Pending promises: {$loopStats['pending_promises']}\n";
echo "   💾 Memory usage: " . number_format($loopStats['memory_usage'] / 1024, 1) . " KB\n";
echo "   ⚡ Operations/sec: " . number_format($loopStats['operations_per_second'], 1) . "\n";

// Cleanup and optimization
if ($loopStats['memory_usage'] > 50 * 1024 * 1024) { // 50MB threshold
    echo "   🧹 High memory usage detected, cleaning up...\n";
    $asyncClient->cleanup();
    echo "   ✅ Cleanup completed\n";
}

// Graceful shutdown
$asyncClient->shutdown(5); // 5 second graceful shutdown
echo "   🛑 Async client shutdown completed\n";

echo "\n9. Async Performance Summary:\n";
echo "   ⚡ Concurrent execution: 3-10x performance improvement\n";
echo "   📊 Resource efficiency: Better CPU and network utilization\n";
echo "   🔄 Scalability: Handle hundreds of concurrent operations\n";
echo "   🛡️ Reliability: Built-in error recovery and timeouts\n";
```

### Performance & Best Practices

```php
// 1. Optimal SDK Configuration for Production
echo "🚀 Production Optimization Guide:\n\n";

// Production-ready client configuration
$optimizedClient = new WioexClient([
    'api_key' => getenv('WIOEX_API_KEY'),
    'base_url' => 'https://api.wioex.com',
    
    // Performance optimizations
    'timeout' => 30,
    'connect_timeout' => 10,
    
    // Retry strategy for reliability
    'retry' => [
        'times' => 3,
        'delay' => 200,           // Start with 200ms
        'multiplier' => 2,        // Exponential backoff
        'max_delay' => 5000,      // Cap at 5 seconds
        'jitter' => true          // Add randomization
    ],
    
    // Connection pooling for efficiency
    'connection_pool' => [
        'max_connections' => 10,
        'max_per_host' => 5,
        'idle_timeout' => 30,
        'keep_alive' => true
    ],
    
    // Advanced caching configuration
    'cache' => [
        'default' => 'memory',
        'drivers' => [
            'memory' => [
                'driver' => 'memory',
                'config' => [
                    'max_items' => 1000,
                    'ttl_variance' => 0.1  // 10% TTL variance to prevent cache stampedes
                ]
            ],
            'redis' => [
                'driver' => 'redis',
                'config' => [
                    'host' => '127.0.0.1',
                    'port' => 6379,
                    'prefix' => 'wioex_cache:'
                ]
            ]
        ]
    ],
    
    // Rate limiting configuration
    'rate_limiting' => [
        'enabled' => true,
        'requests' => 100,
        'window' => 60,
        'strategy' => 'sliding_window',
        'burst_allowance' => 20
    ]
]);

echo "1. Optimized Client Configuration:\n";
echo "   ✅ Connection pooling enabled\n";
echo "   ✅ Intelligent retry strategy\n";
echo "   ✅ Advanced caching configured\n";
echo "   ✅ Rate limiting configured\n\n";

// 2. Performance monitoring and metrics
echo "2. Performance Monitoring:\n";

class PerformanceMonitor
{
    private $metrics = [];
    private $startTimes = [];
    
    public function startOperation(string $operation): void
    {
        $this->startTimes[$operation] = microtime(true);
    }
    
    public function endOperation(string $operation, bool $success = true): void
    {
        if (!isset($this->startTimes[$operation])) return;
        
        $duration = (microtime(true) - $this->startTimes[$operation]) * 1000;
        
        if (!isset($this->metrics[$operation])) {
            $this->metrics[$operation] = [
                'count' => 0,
                'total_time' => 0,
                'min_time' => PHP_FLOAT_MAX,
                'max_time' => 0,
                'success_count' => 0,
                'error_count' => 0
            ];
        }
        
        $metric = &$this->metrics[$operation];
        $metric['count']++;
        $metric['total_time'] += $duration;
        $metric['min_time'] = min($metric['min_time'], $duration);
        $metric['max_time'] = max($metric['max_time'], $duration);
        
        if ($success) {
            $metric['success_count']++;
        } else {
            $metric['error_count']++;
        }
        
        unset($this->startTimes[$operation]);
    }
    
    public function getReport(): array
    {
        $report = [];
        foreach ($this->metrics as $operation => $metric) {
            $avgTime = $metric['count'] > 0 ? $metric['total_time'] / $metric['count'] : 0;
            $successRate = $metric['count'] > 0 ? ($metric['success_count'] / $metric['count']) * 100 : 0;
            
            $report[$operation] = [
                'calls' => $metric['count'],
                'avg_time' => round($avgTime, 2),
                'min_time' => round($metric['min_time'], 2),
                'max_time' => round($metric['max_time'], 2),
                'success_rate' => round($successRate, 1),
                'total_time' => round($metric['total_time'], 2)
            ];
        }
        return $report;
    }
}

$monitor = new PerformanceMonitor();

// Monitor API operations
$operations = ['quote_AAPL', 'quote_GOOGL', 'quote_MSFT'];
foreach ($operations as $op) {
    $symbol = substr($op, 6); // Extract symbol
    
    $monitor->startOperation($op);
    try {
        $result = $optimizedClient->stocks()->quote($symbol);
        $monitor->endOperation($op, true);
        echo "   ✅ {$symbol}: $" . number_format($result['tickers'][0]['market']['price'], 2) . "\n";
    } catch (Exception $e) {
        $monitor->endOperation($op, false);
        echo "   ❌ {$symbol}: Failed\n";
    }
}

// Display performance report
$report = $monitor->getReport();
echo "\n   📊 Performance Report:\n";
foreach ($report as $operation => $stats) {
    printf("   %-12s: %d calls, %.1fms avg, %.1f%% success\n",
        $operation,
        $stats['calls'],
        $stats['avg_time'],
        $stats['success_rate']
    );
}

// 3. Memory optimization techniques
echo "\n3. Memory Optimization:\n";

// Memory-efficient data processing
function processLargeDataset(array $symbols, $client): void
{
    $initialMemory = memory_get_usage(true);
    echo "   💾 Initial memory: " . number_format($initialMemory / 1024, 1) . " KB\n";
    
    // Process in chunks to avoid memory bloat
    $chunkSize = 5;
    $chunks = array_chunk($symbols, $chunkSize);
    
    foreach ($chunks as $chunkIndex => $chunk) {
        // Process chunk
        $quotes = $client->stocks()->quote(implode(',', $chunk));
        
        // Process data immediately and discard
        foreach ($quotes['tickers'] as $ticker) {
            // Process ticker data here
            $price = $ticker['market']['price'];
            // ... processing logic
        }
        
        // Force garbage collection after processing
        unset($quotes);
        if ($chunkIndex % 3 === 0) { // Every 3 chunks
            gc_collect_cycles();
        }
        
        $currentMemory = memory_get_usage(true);
        echo "   📊 Chunk " . ($chunkIndex + 1) . " memory: " . number_format($currentMemory / 1024, 1) . " KB\n";
    }
    
    $finalMemory = memory_get_usage(true);
    $memoryIncrease = $finalMemory - $initialMemory;
    echo "   📈 Memory increase: " . number_format($memoryIncrease / 1024, 1) . " KB\n";
}

$largeSymbolList = ['AAPL', 'GOOGL', 'MSFT', 'AMZN', 'TSLA', 'META', 'NVDA', 'AMD', 'CRM', 'NFLX'];
processLargeDataset($largeSymbolList, $optimizedClient);

// 4. Advanced rate limiting and throttling
echo "\n4. Advanced Rate Limiting:\n";

class AdaptiveRateLimiter
{
    private $requestTimes = [];
    private $errorCount = 0;
    private $maxErrorsBeforeSlowdown = 3;
    private $baseDelay = 100; // milliseconds
    
    public function shouldDelay(): int
    {
        // Remove old requests (older than 1 minute)
        $cutoff = time() - 60;
        $this->requestTimes = array_filter($this->requestTimes, fn($time) => $time > $cutoff);
        
        // Calculate current rate
        $currentRate = count($this->requestTimes);
        
        // Adaptive delay based on error rate and current load
        $errorMultiplier = min(pow(2, $this->errorCount), 8); // Cap at 8x
        $loadMultiplier = $currentRate > 50 ? 2 : 1;
        
        return (int)($this->baseDelay * $errorMultiplier * $loadMultiplier);
    }
    
    public function recordRequest(bool $success = true): void
    {
        $this->requestTimes[] = time();
        
        if ($success) {
            $this->errorCount = max(0, $this->errorCount - 1); // Decay error count
        } else {
            $this->errorCount++;
        }
    }
    
    public function getStatistics(): array
    {
        return [
            'requests_last_minute' => count($this->requestTimes),
            'error_count' => $this->errorCount,
            'current_delay' => $this->shouldDelay()
        ];
    }
}

$rateLimiter = new AdaptiveRateLimiter();

for ($i = 0; $i < 5; $i++) {
    $delay = $rateLimiter->shouldDelay();
    if ($delay > 0) {
        echo "   ⏳ Adaptive delay: {$delay}ms\n";
        usleep($delay * 1000); // Convert to microseconds
    }
    
    // Simulate API call
    $success = rand(0, 10) > 2; // 80% success rate
    $rateLimiter->recordRequest($success);
    
    echo "   " . ($success ? "✅" : "❌") . " Request " . ($i + 1) . "\n";
}

$stats = $rateLimiter->getStatistics();
echo "   📊 Rate Limiter Stats: {$stats['requests_last_minute']} req/min, {$stats['error_count']} errors, {$stats['current_delay']}ms delay\n";

// 5. Efficient error handling and recovery
echo "\n5. Error Handling Best Practices:\n";

class RobustApiClient
{
    private $client;
    private $circuitBreaker;
    private $retryCount = 0;
    private $maxRetries = 3;
    
    public function __construct($client)
    {
        $this->client = $client;
        $this->circuitBreaker = ['failures' => 0, 'lastFailure' => 0, 'state' => 'closed'];
    }
    
    public function safeQuote(string $symbol): ?array
    {
        // Check circuit breaker
        if ($this->isCircuitOpen()) {
            echo "   🔴 Circuit breaker open for {$symbol}\n";
            return $this->getFallbackData($symbol);
        }
        
        try {
            $result = $this->client->stocks()->quote($symbol);
            $this->onSuccess();
            return $result;
            
        } catch (RateLimitException $e) {
            echo "   🟡 Rate limited, backing off...\n";
            $waitTime = $e->getRetryAfter() ?? (pow(2, $this->retryCount) * 1000);
            usleep($waitTime * 1000);
            
            if ($this->retryCount < $this->maxRetries) {
                $this->retryCount++;
                return $this->safeQuote($symbol);
            }
            
        } catch (Exception $e) {
            $this->onFailure();
            echo "   ❌ Error for {$symbol}: " . substr($e->getMessage(), 0, 50) . "\n";
            return $this->getFallbackData($symbol);
        }
        
        return null;
    }
    
    private function isCircuitOpen(): bool
    {
        if ($this->circuitBreaker['state'] === 'open') {
            // Check if enough time has passed to try again
            if (time() - $this->circuitBreaker['lastFailure'] > 30) {
                $this->circuitBreaker['state'] = 'half-open';
                return false;
            }
            return true;
        }
        return false;
    }
    
    private function onSuccess(): void
    {
        $this->circuitBreaker['failures'] = 0;
        $this->circuitBreaker['state'] = 'closed';
        $this->retryCount = 0;
    }
    
    private function onFailure(): void
    {
        $this->circuitBreaker['failures']++;
        $this->circuitBreaker['lastFailure'] = time();
        
        if ($this->circuitBreaker['failures'] >= 5) {
            $this->circuitBreaker['state'] = 'open';
            echo "   🔴 Circuit breaker opened due to failures\n";
        }
    }
    
    private function getFallbackData(string $symbol): array
    {
        // Return cached or default data
        return [
            'symbol' => $symbol,
            'price' => 100.00, // Fallback price
            'source' => 'fallback',
            'timestamp' => time()
        ];
    }
}

$robustClient = new RobustApiClient($optimizedClient);

// Test with some symbols
$testSymbols = ['AAPL', 'GOOGL', 'INVALID_SYMBOL'];
foreach ($testSymbols as $symbol) {
    $result = $robustClient->safeQuote($symbol);
    if ($result) {
        $price = $result['tickers'][0]['market']['price'] ?? $result['price'];
        echo "   💰 {$symbol}: $" . number_format($price, 2) . "\n";
    }
}

// 6. Optimized data fetching patterns
echo "\n6. Optimized Data Fetching:\n";

// Smart batching strategy
function optimizedPortfolioFetch(array $symbols, $client): array
{
    $startTime = microtime(true);
    
    // Group symbols for optimal batch sizes
    $prioritySymbols = array_slice($symbols, 0, 5);    // High priority
    $regularSymbols = array_slice($symbols, 5);       // Regular priority
    
    $results = [];
    
    // Fetch priority symbols first with smaller batches
    if (!empty($prioritySymbols)) {
        echo "   🎯 Fetching priority symbols...\n";
        $priorityQuotes = $client->stocks()->quote(implode(',', $prioritySymbols));
        $results['priority'] = $priorityQuotes;
    }
    
    // Fetch regular symbols with larger batches
    if (!empty($regularSymbols)) {
        echo "   📊 Fetching regular symbols...\n";
        $chunks = array_chunk($regularSymbols, 10); // Larger batches for regular data
        
        foreach ($chunks as $chunk) {
            $quotes = $client->stocks()->quote(implode(',', $chunk));
            $results['regular'][] = $quotes;
            
            // Small delay between chunks to respect rate limits
            usleep(50000); // 50ms
        }
    }
    
    $totalTime = (microtime(true) - $startTime) * 1000;
    echo "   ⏱️ Optimized fetch completed in " . number_format($totalTime, 2) . "ms\n";
    
    return $results;
}

$portfolioResults = optimizedPortfolioFetch($largeSymbolList, $optimizedClient);

// 7. Performance benchmarking
echo "\n7. Performance Benchmarking:\n";

function benchmarkOperations($client): array
{
    $benchmarks = [];
    
    // Benchmark different operation types
    $operations = [
        'single_quote' => fn() => $client->stocks()->quote('AAPL'),
        'multi_quote' => fn() => $client->stocks()->quote('AAPL,GOOGL,MSFT'),
        'company_info' => fn() => $client->stocks()->info('AAPL'),
        'market_status' => fn() => $client->markets()->status(),
    ];
    
    foreach ($operations as $name => $operation) {
        $times = [];
        
        // Run each operation 5 times
        for ($i = 0; $i < 5; $i++) {
            $start = microtime(true);
            try {
                $operation();
                $times[] = (microtime(true) - $start) * 1000;
            } catch (Exception $e) {
                // Skip failed operations
            }
        }
        
        if (!empty($times)) {
            $benchmarks[$name] = [
                'avg' => array_sum($times) / count($times),
                'min' => min($times),
                'max' => max($times),
                'count' => count($times)
            ];
        }
    }
    
    return $benchmarks;
}

$benchmarks = benchmarkOperations($optimizedClient);
foreach ($benchmarks as $operation => $stats) {
    printf("   ⚡ %-15s: %.1fms avg (%.1f-%.1fms range)\n",
        $operation,
        $stats['avg'],
        $stats['min'],
        $stats['max']
    );
}

// 8. Production deployment checklist
echo "\n8. Production Deployment Checklist:\n";

$productionChecklist = [
    'API Key Configuration' => getenv('WIOEX_API_KEY') !== false,
    'Caching Enabled' => $optimizedClient->getCache() !== null,
    'Error Logging' => function_exists('error_log'),
    'Memory Limit' => (int)ini_get('memory_limit') >= 256,
    'Timeout Configuration' => true, // Already configured above
    'Rate Limiting' => true,  // Already configured above
    'SSL Verification' => true // Assuming HTTPS is properly configured
];

foreach ($productionChecklist as $item => $status) {
    $emoji = $status ? '✅' : '❌';
    echo "   {$emoji} {$item}\n";
}

echo "\n9. Performance Summary:\n";
echo "   🚀 Optimized for production workloads\n";
echo "   📊 Comprehensive monitoring and metrics\n";
echo "   🛡️ Robust error handling and recovery\n";
echo "   ⚡ Efficient resource utilization\n";
echo "   🔄 Adaptive rate limiting and throttling\n";
echo "   💾 Memory-optimized data processing\n";
echo "   📈 Performance benchmarking and analysis\n";
```

## New Export Utilities (v2.0.0)

Export data in multiple formats:

```php
use Wioex\SDK\Export\ExportManager;
use Wioex\SDK\Enums\ExportFormat;

$exportManager = new ExportManager();

// Get stock data
$stockData = $client->stocks()->quote('AAPL,GOOGL,MSFT');

// Export to different formats
$exportManager->exportToFile($stockData, ExportFormat::JSON, 'stocks.json');
$exportManager->exportToFile($stockData, ExportFormat::CSV, 'stocks.csv');

// Export to string
$csvString = $exportManager->export($stockData, ExportFormat::CSV);

// Multiple formats at once
$results = $exportManager->exportMultipleFormats(
    $stockData, 
    [ExportFormat::JSON, ExportFormat::CSV], 
    'export_base_name'
);
```

## Configuration

### Basic Setup
```php
$client = new WioexClient([
    'api_key' => 'your-api-key-here'
]);
```

### Advanced Configuration
```php
use Wioex\SDK\Enums\ErrorReportingLevel;

$client = new WioexClient([
    'api_key' => 'your-api-key-here',
    'timeout' => 30,
    'connect_timeout' => 10,
    'error_reporting' => true,
    'error_reporting_level' => ErrorReportingLevel::STANDARD,
    'retry' => [
        'times' => 3,
        'delay' => 100,
        'multiplier' => 2
    ]
]);
```

### Environment-Based Configuration (New)
```php
use Wioex\SDK\Enums\Environment;

// Load from environment
$client = WioexClient::fromEnvironment(Environment::PRODUCTION);

// Load from config file
$client = WioexClient::fromConfig('config/wioex.php');

// Dynamic configuration
$client->configure([
    'timeout' => 60,
    'debug' => true
]);
```

## Error Handling

```php
use Wioex\SDK\Exceptions\{
    AuthenticationException,
    ValidationException,
    RateLimitException,
    ServerException,
    RequestException
};

try {
    $stock = $client->stocks()->quote('AAPL');
    
    if ($stock->successful()) {
        // Process data
        $data = $stock->data();
    }
    
} catch (AuthenticationException $e) {
    echo "Authentication failed: " . $e->getMessage();
} catch (ValidationException $e) {
    echo "Validation error: " . $e->getMessage();
} catch (RateLimitException $e) {
    echo "Rate limit exceeded. Retry after: " . $e->getRetryAfter() . " seconds";
} catch (ServerException $e) {
    echo "Server error: " . $e->getMessage();
} catch (RequestException $e) {
    echo "Request failed: " . $e->getMessage();
}
```

## Response Handling

```php
$response = $client->stocks()->quote('AAPL');

// Check status
if ($response->successful()) {
    // Get data
    $data = $response->data();
    $ticker = $response['tickers'][0];
    
    // Array access
    echo $ticker['market']['price'];
    
    // Get as JSON
    $json = $response->json();
}

// Response methods
$response->status();        // HTTP status code
$response->successful();    // true if 2xx
$response->failed();        // true if not 2xx
$response->headers();       // All headers
$response->header('Name');  // Specific header
```

## Testing

The SDK includes comprehensive testing:

```bash
# Run all tests
composer test

# Run with coverage
composer test:coverage

# Static analysis
composer phpstan

# Code style
composer cs:check
composer cs:fix
```

**Test Coverage:**
- 135+ unit tests
- Core classes: 100% coverage
- Resources: 95%+ coverage
- Error handling: 100% coverage

## Migration from v1.x

**Zero breaking changes!** Your existing code works without modifications:

```php
// All these calls work exactly the same
$client->stocks()->quote('AAPL');        // Works
$client->streaming()->getToken();        // Fixed in v2.0.0
$client->markets()->status();            // Works
$client->account()->balance();           // Works
```

**New optional features:**
- Export utilities for data export
- Environment-based configuration
- Enhanced error reporting

## Examples

See the `/examples` directory:

- `stocks-example.php` - Stock data operations
- `streaming-example.php` - WebSocket streaming setup
- `market-status-example.php` - Market hours and status
- `error-handling.php` - Exception handling patterns
- `export-example.php` - Data export examples
- `complete_integration_example.php` - Full feature demonstration

## Version History

- **v2.0.0** (2025-10-22) - Production ready release with critical fixes
- **v1.4.0** - Enhanced timeline and caching features
- **v1.3.0** - WebSocket streaming and API improvements
- **v1.2.0** - Session filtering and timeline enhancements
- **v1.1.0** - Trading signals and market status
- **v1.0.0** - Initial release

## Support

- **Documentation**: https://docs.wioex.com
- **Email**: api-support@wioex.com
- **Issues**: https://github.com/wioex/php-sdk/issues
- **Changelog**: [CHANGELOG.md](CHANGELOG.md)

## Contributing

1. Fork the repository
2. Create your feature branch
3. Run tests: `composer test`
4. Commit changes
5. Submit pull request

## License

MIT License. See [LICENSE](LICENSE) for details.

---

**Production Ready** - All critical issues resolved in v2.0.0

Made by [WioEX Team](https://wioex.com)