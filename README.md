# WioEX PHP SDK

Official PHP SDK for **WioEX Financial Data API** - Enterprise-grade client library for accessing stocks, trading signals, news, currency, and financial market data.

**Current Version: 1.4.0** | **Released: October 22, 2025** | **PHP 8.1+**

[![PHP Version](https://img.shields.io/packagist/php-v/wioex/php-sdk.svg)](https://packagist.org/packages/wioex/php-sdk)
[![Latest Version](https://img.shields.io/packagist/v/wioex/php-sdk.svg)](https://packagist.org/packages/wioex/php-sdk)
[![License](https://img.shields.io/packagist/l/wioex/php-sdk.svg)](https://packagist.org/packages/wioex/php-sdk)

## Table of Contents

- [Features](#features)
- [Installation](#installation) 
- [Quick Start](#quick-start)
- [Configuration](#configuration)
- [Usage](#usage)
  - [Stocks](#stocks) - Quote, Info, Timeline, Price Changes, Financials, Heatmap
  - [Stock Screens](#stock-screens) - Gainers, Losers, Active, IPOs
  - [Trading Signals](#trading-signals-new) - Active signals, History
  - [Market Status](#market-status-new) - Real-time market hours (public access)
  - [Streaming](#streaming-new) - WebSocket authentication tokens for real-time data
  - [News](#news) - Latest news, Company analysis
  - [Currency](#currency) - Exchange rates, Conversion, Historical data
  - [Account](#account) - Balance, Usage, Analytics
- [Response Handling](#response-handling)
- [Error Handling](#error-handling)
- [Testing](#testing)
- [Examples](#examples)
- [Support](#support)

## Features

- ✅ **Modern PHP 8.1+** - Fully typed with strict types and return type declarations
- ✅ **PSR Compliant** - PSR-4 autoloading, PSR-7 HTTP messages, PSR-12 coding standards
- ✅ **Fluent API** - Chainable methods for elegant code
- ✅ **Automatic Retry** - Built-in retry logic with exponential backoff
- ✅ **Rate Limiting** - Intelligent handling of API rate limits
- ✅ **Exception Handling** - Comprehensive error handling with custom exceptions
- ✅ **Response Wrapper** - Easy data access with array syntax support
- ✅ **Type Safe** - Full IDE autocomplete support
- ✅ **Zero Config** - Works out of the box with sensible defaults
- ✅ **Public Endpoints** - Some endpoints work without API key for frontend usage
- **Enhanced Timeline Intervals** - 17 different interval types with period-based optimization (v1.4.0)
- **Two-Branch JSON Response** - Clean metadata/data separation for better client integration (v1.4.0) 
- **Intelligent Caching** - Interval-based cache optimization from 1 minute to 48 hours (v1.4.0)
- **Session Filtering** - Filter intraday data by trading sessions (v1.2.0)
- **Advanced Timeline** - Date-based filtering and convenience methods (v1.2.0)
- **WebSocket Streaming** - Real-time market data authentication and streaming (v1.3.0)
- **API Parameter Alignment** - Consistent parameter naming across endpoints (v1.3.0)
- **Enhanced Error Handling** - Support for centralized error format with backward compatibility (v1.3.0)
- **Trading Signals** - Auto-included signals and comprehensive signal data (v1.1.0)
- **Market Status** - Real-time market hours with public access option (v1.1.0)

## Requirements

- PHP 8.1 or higher
- ext-json
- Composer

## Installation

Install via Composer:

```bash
composer require wioex/php-sdk
```

## Quick Start

```php
<?php

require 'vendor/autoload.php';

use Wioex\SDK\WioexClient;

// Initialize the client
$client = new WioexClient([
    'api_key' => 'your-api-key-here'
]);

// Get stock data (signals auto-included)
$stock = $client->stocks()->quote('AAPL');
if (isset($stock['tickers'][0])) {
    $ticker = $stock['tickers'][0];
    echo "Price: $" . $ticker['market']['price'] . "\n";

    // Check if signal is available
    if (isset($ticker['signal'])) {
        echo "Signal: {$ticker['signal']['signal_type']} @ \${$ticker['signal']['entry_price']}\n";
    }
}

// Search for stocks
$results = $client->stocks()->search('Apple');
foreach ($results['data'] as $stock) {
    echo $stock['symbol'] . ": " . $stock['name'] . "\n";
}

// Get latest news
$news = $client->news()->latest('TSLA');
foreach ($news['articles'] as $article) {
    echo $article['title'] . "\n";
}

// Check market status (works with or without API key)
$marketStatus = $client->markets()->status();
$nyse = $marketStatus['markets']['nyse'];
echo "NYSE is " . ($nyse['is_open'] ? 'open' : 'closed') . "\n";
echo "Market time: " . $nyse['market_time'] . "\n";

// Check account balance
$balance = $client->account()->balance();
echo "Credits remaining: " . $balance['credits'] . "\n";
```

## Configuration

### Basic Configuration

```php
$client = new WioexClient([
    'api_key' => 'your-api-key-here'
]);
```

### Advanced Configuration

```php
$client = new WioexClient([
    'api_key' => 'your-api-key-here',
    'base_url' => 'https://api.wioex.com',      // API base URL
    'timeout' => 30,                             // Request timeout (seconds)
    'connect_timeout' => 10,                     // Connection timeout (seconds)
    'retry' => [
        'times' => 3,                            // Number of retry attempts
        'delay' => 100,                          // Initial delay (milliseconds)
        'multiplier' => 2,                       // Exponential backoff multiplier
        'max_delay' => 5000                      // Maximum delay (milliseconds)
    ],
    'headers' => [
        'User-Agent' => 'MyApp/1.0'
    ]
]);
```

## Breaking Changes & Migration Guide

### API Parameter Changes (v1.2.1)

**Important:** v1.2.1 introduces a breaking change for **direct API users only**. SDK users are **not affected** due to backward compatibility.

#### For SDK Users - No Action Required

Your existing SDK code continues to work unchanged:

```php
// This works exactly the same in v1.2.1
$result = $client->stocks()->quote('AAPL');
$result = $client->stocks()->quote('AAPL,GOOGL,MSFT');
```

#### For Direct API Users - Action Required

If you're making direct HTTP calls to the WioEX API, update your parameter names:

```php
// Old format (no longer supported)
GET /v2/stocks/get?ticker=AAPL&api_key=your-key

// New format (v1.2.1+)
GET /v2/stocks/get?stocks=AAPL&api_key=your-key
```

**Migration Example:**
```php
// Before v1.2.1
$response = file_get_contents("https://api.wioex.com/v2/stocks/get?ticker=AAPL&api_key={$apiKey}");

// After v1.2.1  
$response = file_get_contents("https://api.wioex.com/v2/stocks/get?stocks=AAPL&api_key={$apiKey}");
```

**Why This Change?**
- **Consistency**: Aligns parameter naming across all endpoints
- **Clarity**: `stocks` parameter better reflects multiple symbol support
- **API Standards**: Follows RESTful naming conventions

**Affected Endpoints:**
- `GET /v2/stocks/get` - Changed `ticker` → `stocks`

**Backward Compatibility:**
- **SDK Users**: Fully backward compatible
- **Direct API Users**: Must update parameter names

## Usage

### Stocks

#### Search Stocks

```php
$results = $client->stocks()->search('Apple');
// Or use symbol
$results = $client->stocks()->search('AAPL');
```

#### Get Stock Data

```php
// Single stock
$stock = $client->stocks()->quote('AAPL');

// Multiple stocks (comma-separated)
$stocks = $client->stocks()->quote('AAPL,GOOGL,MSFT');
```

**💡 Auto-included Signals:** If an active trading signal exists for the requested stock, it will be automatically included in the response under the `signal` key. No additional API call needed!

#### Get Stock Info

```php
$info = $client->stocks()->info('AAPL');
echo $info['company_name'];
echo $info['market_cap'];
echo $info['pe_ratio'];

// Check for auto-included signal
if (isset($info['signal'])) {
    echo "Signal: {$info['signal']['signal_type']} @ \${$info['signal']['entry_price']}\n";
    echo "Confidence: {$info['signal']['confidence']}%\n";
}
```

**💡 Auto-included Signals:** Stock info responses automatically include active signals when available.

#### Get Historical Data ⭐ ENHANCED

Enhanced timeline support with **17 different interval types** and intelligent caching:

```php
// Basic usage (default: 1day interval, 78 data points)
$timeline = $client->stocks()->timeline('AAPL');

// Enhanced interval support with period-based optimization
$timeline = $client->stocks()->timeline('TSLA', [
    'interval' => '5min',        // See supported intervals below
    'orderBy' => 'DESC',         // Sort order: 'ASC' or 'DESC' (default: ASC)
    'size' => 480,               // Number of data points: 1-5000 (default: 78)
    'session' => 'regular',      // Trading session (minute intervals): 'all', 'regular', 'pre_market', 'after_hours', 'extended'
    'started_date' => '2024-10-16', // Start date (YYYY-MM-DD format)
    'timestamp' => 1704067200    // Alternative: Unix timestamp start date
]);
```

**✨ New Supported Intervals:**

**Minute Intervals:** (High frequency, short cache)
- `1min`, `5min`, `15min`, `30min`

**Hour Intervals:** 
- `1hour`, `5hour`

**Daily/Weekly/Monthly:**
- `1day`, `1week`, `1month`

**🚀 Period-Based Intervals:** (Optimized for specific timeframes)
- `1d` - 1 day period with 5-minute intervals
- `1w` - 1 week period with 30-minute intervals  
- `1m` - 1 month period with 5-hour intervals
- `3m` - 3 months period with daily intervals
- `6m` - 6 months period with daily intervals
- `1y` - 1 year period with weekly intervals
- `5y` - 5 years period with monthly intervals
- `max` - Maximum available data with monthly intervals

**✨ New Convenience Methods:**

```php
// 5-minute detailed analysis
$detailed = $client->stocks()->timelineFiveMinute('TSLA', ['size' => 100]);

// Hourly data for swing trading
$hourly = $client->stocks()->timelineHourly('AAPL', ['size' => 168]); // 1 week of hours

// Weekly trends
$weekly = $client->stocks()->timelineWeekly('MSFT', ['size' => 52]); // 1 year of weeks

// Monthly overview  
$monthly = $client->stocks()->timelineMonthly('GOOGL', ['size' => 60]); // 5 years of months

// Optimized 1-year view
$oneYear = $client->stocks()->timelineOneYear('NVDA'); // Automatic weekly intervals

// Maximum historical data
$maxData = $client->stocks()->timelineMax('AAPL'); // All available data with monthly intervals

// Traditional methods still work:
$intraday = $client->stocks()->intradayTimeline('TSLA', ['size' => 100]);
$extended = $client->stocks()->extendedHoursTimeline('TSLA', ['size' => 200]);
$fromDate = $client->stocks()->timelineFromDate('AAPL', '2024-10-16');
$preMarket = $client->stocks()->timelineBySession('TSLA', 'pre_market', ['size' => 50]);
```

**🔄 New Two-Branch JSON Response:**

```php
$timeline = $client->stocks()->timeline('TSLA', ['interval' => '1day', 'size' => 5]);

// Metadata branch - API information
$metadata = $timeline['metadata'];
echo $metadata['wioex']['brand']; // "WioEX Financial Data API"
echo $metadata['response']['timestamp_utc']; // "2025-10-22T..."
echo $metadata['cache']['status']; // "HIT" or "MISS"

// Data branch - Business data
$data = $timeline['data'];
echo $data['symbol']; // "TSLA"
echo $data['company_name']; // "Tesla, Inc."
echo $data['market_status']; // "open", "closed", "pre_market", "after_hours"

// Timeline data
foreach ($data['timeline'] as $point) {
    echo "{$point['datetime']}: Open \${$point['open']}, Close \${$point['close']}\n";
}
```

**📊 Intelligent Caching:**
- **1min intervals**: 60 seconds cache
- **5min intervals**: 5 minutes cache  
- **Hourly intervals**: 1 hour cache
- **Daily intervals**: 1 hour cache
- **Period-based intervals**: 5 minutes to 48 hours (optimized per period)

**Trading Sessions** (applies to minute-level intervals):
- `regular`: 9:30 AM - 4:00 PM EST (Standard market hours)
- `pre_market`: 4:00 AM - 9:30 AM EST (Early trading)
- `after_hours`: 4:00 PM - 8:00 PM EST (Extended trading)
- `extended`: 4:00 AM - 8:00 PM EST (All extended hours combined)
- `all`: Full 24-hour data (default)

#### Get Price Changes ⭐ NEW

Get organized price change data across multiple timeframes from 15 minutes to all-time:

```php
// Get comprehensive price changes for a stock
$changes = $client->stocks()->priceChanges('TSLA');

// Access different timeframe categories
$shortTerm = $changes['price_changes']['short_term'];
$mediumTerm = $changes['price_changes']['medium_term'];
$longTerm = $changes['price_changes']['long_term'];

// Short-term changes (minutes to month)
echo "1 Day: " . $shortTerm['1_day']['percentage'] . "% (" . $shortTerm['1_day']['label'] . ")\n";
echo "1 Week: " . $shortTerm['1_week']['percentage'] . "%\n";
echo "1 Month: " . $shortTerm['1_month']['percentage'] . "%\n";

// Medium-term changes (months to YTD)
echo "3 Months: " . $mediumTerm['3_months']['percentage'] . "%\n";
echo "6 Months: " . $mediumTerm['6_months']['percentage'] . "%\n";
echo "Year to Date: " . $mediumTerm['year_to_date']['percentage'] . "%\n";

// Long-term changes (years to all-time)
echo "1 Year: " . $longTerm['1_year']['percentage'] . "%\n";
echo "3 Years: " . $longTerm['3_years']['percentage'] . "%\n";
echo "All Time: " . $longTerm['all_time']['percentage'] . "%\n";

// Check data availability
if ($shortTerm['1_day']['available']) {
    echo "1-day data is available\n";
}

// Last updated timestamp
echo "Last updated: " . $changes['updated_at'] . "\n";
```

**Available Timeframes:**
- **Intraday**: 15 minutes, 30 minutes, 1 hour
- **Short-term**: 1 day, 1 week, 1 month  
- **Medium-term**: 3 months, 6 months, year-to-date
- **Long-term**: 1 year, 3 years, 5 years, all-time

**Response includes:**
- `percentage`: Price change percentage (can be null if unavailable)
- `label`: Human-readable timeframe description
- `available`: Boolean indicating if data exists for this timeframe
- `updated_at`: Timestamp of last data update

#### Get Financials

```php
$financials = $client->stocks()->financials('AAPL', 'USD');
```

#### Get Heatmap

```php
$heatmap = $client->stocks()->heatmap('nasdaq100');
// Options: nasdaq100, sp500, dowjones
```

#### Get Stock List

```php
$list = $client->stocks()->list([
    'country' => 'US',       // ISO 3166-1 Alpha-2
    'offset' => 1            // Pagination (1-100)
]);
```

### Stock Screens

#### Market Movers

```php
// Top gainers
$gainers = $client->screens()->gainers();

// Top losers
$losers = $client->screens()->losers();

// Most active
$active = $client->screens()->active(50); // Optional limit
```

#### Pre/Post Market

```php
// Pre-market
$preGainers = $client->screens()->preMarketGainers();
$preLosers = $client->screens()->preMarketLosers();

// Post-market
$postGainers = $client->screens()->postMarketGainers();
$postLosers = $client->screens()->postMarketLosers();
```

#### IPOs

```php
// Recent IPOs
$recent = $client->screens()->ipos('recent');

// Upcoming IPOs
$upcoming = $client->screens()->ipos('upcoming');

// IPO filings
$filings = $client->screens()->ipos('filings');
```

### News

#### Get Latest News

```php
$news = $client->news()->latest('AAPL');
foreach ($news['articles'] as $article) {
    echo $article['title'] . "\n";
    echo $article['url'] . "\n";
    echo $article['published_at'] . "\n\n";
}
```

#### Company Analysis

```php
$analysis = $client->news()->companyAnalysis('AAPL');
echo $analysis['summary'];
echo $analysis['sentiment'];
```

### Trading Signals ⭐ NEW

#### Get Active Signals

```php
// Get all active signals
$signals = $client->signals()->active();

// Get signals for a specific symbol
$signals = $client->signals()->active(['symbol' => 'AAPL']);

// Get BUY signals with high confidence
$signals = $client->signals()->active([
    'signal_type' => 'BUY',
    'min_confidence' => 80
]);

// Get signals for specific timeframe
$signals = $client->signals()->active([
    'timeframe' => '1d',
    'limit' => 100
]);

foreach ($signals['signals'] as $signal) {
    echo "{$signal['symbol']}: {$signal['signal_type']}\n";
    echo "  Entry: \${$signal['entry_price']}\n";
    echo "  Target: \${$signal['target_price']}\n";
    echo "  Stop Loss: \${$signal['stop_loss']}\n";
    echo "  Confidence: {$signal['confidence']}%\n";
    echo "  Reason: {$signal['reason']}\n\n";
}
```

**Parameters:**
- `symbol` (string): Filter by stock symbol (e.g., "AAPL")
- `signal_type` (string): Filter by type - BUY, SELL, HOLD, STRONG_BUY, STRONG_SELL
- `min_confidence` (int): Minimum confidence level (0-100), default 70
- `timeframe` (string): Filter by timeframe - 5m, 15m, 1h, 4h, 1d, 1w, 1M
- `limit` (int): Maximum results, default 50, max 200

#### Get Signal History

```php
// Get signal history for last 7 days
$history = $client->signals()->history(['days' => 7]);

// Get triggered signals for TSLA
$history = $client->signals()->history([
    'symbol' => 'TSLA',
    'trigger_type' => 'target'  // entry, target, stop_loss, expired
]);

foreach ($history['signals'] as $signal) {
    echo "{$signal['symbol']}: {$signal['signal_type']}\n";
    echo "  Triggered: {$signal['trigger_type']} at \${$signal['triggered_price']}\n";
    echo "  Triggered At: {$signal['triggered_at']}\n\n";
}
```

**Parameters:**
- `symbol` (string): Filter by stock symbol
- `days` (int): Number of days to look back, default 30, max 365
- `trigger_type` (string): Filter by trigger - entry, target, stop_loss, expired
- `limit` (int): Maximum results, default 50, max 200

### Market Status ⭐ NEW

Get real-time market status and trading hours for NYSE and NASDAQ exchanges.

**✨ Unique Feature**: Works with or without API key!
- **With API key**: Costs 1 credit, no rate limit, tracks usage
- **Without API key**: FREE, rate limited to 100 req/min per IP

#### Authenticated Usage (With API Key)

```php
$client = new WioexClient(['api_key' => 'your-api-key-here']);
$status = $client->markets()->status();

if ($status['success']) {
    $nyse = $status['markets']['nyse'];
    $nasdaq = $status['markets']['nasdaq'];

    // Market status
    echo "NYSE is " . ($nyse['is_open'] ? 'open' : 'closed') . "\n";
    echo "Status: " . $nyse['status'] . "\n"; // "open" or "closed"
    echo "Market Time: " . $nyse['market_time'] . "\n";
    echo "Local Time: " . $nyse['local_time'] . "\n";
    echo "Next Change: " . $nyse['next_change'] . "\n";

    // Trading hours
    echo "Regular Hours: " . $nyse['hours']['regular']['open'] . " - " . $nyse['hours']['regular']['close'] . "\n";
    echo "Pre-Market: " . $nyse['hours']['pre_market']['open'] . " - " . $nyse['hours']['pre_market']['close'] . "\n";
    echo "After-Hours: " . $nyse['hours']['after_hours']['open'] . " - " . $nyse['hours']['after_hours']['close'] . "\n";

    // Holidays
    foreach ($nyse['holidays'] as $holiday) {
        echo $holiday['date'] . ": " . $holiday['name'];
        if ($holiday['type'] === 'early-close') {
            echo " (closes at " . $holiday['close_time'] . ")";
        }
        echo "\n";
    }

    // Trading days (1=Monday, 5=Friday)
    echo "Trading Days: " . implode(', ', $nyse['trading_days']) . "\n";
}
```

#### Public Usage (Without API Key)

**Perfect for frontend applications** where you cannot safely store API keys:

```php
// Initialize without API key
$client = new WioexClient(['api_key' => '']);
$status = $client->markets()->status();

if ($status['success']) {
    $nyse = $status['markets']['nyse'];
    echo "NYSE is " . ($nyse['is_open'] ? 'open' : 'closed') . "\n";
    echo "Status: " . $nyse['status'] . "\n";
}

// Cost: FREE
// Rate Limit: 100 requests per minute per IP
// No usage tracking
```

#### Direct API Call (JavaScript/Frontend)

Can be called directly from JavaScript without any authentication:

```javascript
fetch('https://api.wioex.com/v2/market/status')
  .then(response => response.json())
  .then(data => {
    const nyse = data.markets.nyse;
    console.log('NYSE is', nyse.is_open ? 'open' : 'closed');
    console.log('Status:', nyse.status);
    console.log('Market time:', nyse.market_time);

    // Display trading hours
    console.log('Regular hours:',
      nyse.hours.regular.open + ' - ' + nyse.hours.regular.close + ' ET'
    );
  });
```

**Response Structure:**
```json
{
  "success": true,
  "timestamp": "2025-10-14 20:00:00 UTC",
  "markets": {
    "nyse": {
      "id": "nyse",
      "name": "New York Stock Exchange",
      "short_name": "NYSE",
      "timezone": "America/New_York",
      "is_open": false,
      "status": "closed",
      "local_time": "8:00:00 PM",
      "market_time": "4:00:00 PM",
      "next_change": "2025-10-15T13:30:00.000Z",
      "hours": {
        "regular": {"open": "09:30", "close": "16:00"},
        "pre_market": {"open": "04:00", "close": "09:30"},
        "after_hours": {"open": "16:00", "close": "20:00"}
      },
      "trading_days": [1, 2, 3, 4, 5],
      "next_open_time": "2025-10-15T13:30:00.000Z",
      "holidays": [
        {
          "date": "2025-01-01",
          "name": "New Year's Day",
          "type": "full",
          "close_time": null
        },
        {
          "date": "2025-11-28",
          "name": "Day after Thanksgiving",
          "type": "early-close",
          "close_time": "13:00"
        }
      ]
    },
    "nasdaq": { /* same structure */ }
  }
}
```

**Status Values:**
- `open` - Market is currently open for trading
- `closed` - Market is closed
- `pre_market` - Pre-market trading hours
- `after_hours` - After-hours trading

**Holiday Types:**
- `full` - Market closed all day
- `early-close` - Market closes early (check `close_time`)

**Use Cases:**
- Display market hours on your website
- Show "Market Open/Closed" indicators
- Schedule trading operations
- Frontend widgets without exposing API keys
- Mobile apps with public data display

### Streaming (v1.3.0)

The Streaming resource provides WebSocket authentication tokens for real-time market data streaming.

#### Get Authentication Token

```php
// Get WebSocket streaming token
$tokenResponse = $client->streaming()->getToken();

if ($tokenResponse->successful()) {
    $data = $tokenResponse->data();
    
    $token = $data['token'];                    // Authentication token
    $websocketUrl = $data['websocket_url'];     // WebSocket connection URL
    $expiresAt = $data['expires_at'];           // Token expiration time
    $expiresIn = $data['expires_in'];           // Seconds until expiration
    
    echo "Token: {$token}\n";
    echo "WebSocket URL: {$websocketUrl}\n";
    echo "Expires in: {$expiresIn} seconds\n";
}
```

**Response Structure:**
```json
{
  "success": true,
  "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
  "websocket_url": "wss://stream.wioex.com/v1/ws",
  "expires_at": "2025-10-22T08:30:00Z",
  "expires_in": 3600
}
```

**WebSocket Usage Example:**
```javascript
// Use token in WebSocket connection
const ws = new WebSocket(websocket_url);

ws.onopen = function() {
    // Authenticate with token
    ws.send(JSON.stringify({
        'action': 'auth',
        'token': token_from_php_sdk
    }));
};

ws.onmessage = function(event) {
    const data = JSON.parse(event.data);
    console.log('Real-time market data:', data);
};

// Subscribe to specific symbols
ws.send(JSON.stringify({
    'action': 'subscribe',
    'symbols': ['AAPL', 'GOOGL', 'MSFT']
}));
```

**Token Features:**
- **Secure Authentication** - JWT-based token for WebSocket connections
- **Temporary Access** - Tokens expire after 1 hour for security
- **Real-time Data** - Access to live market data streams
- **Symbol Subscription** - Subscribe to specific stock symbols
- **Rate Limited** - Subject to API rate limits and subscription quotas

**Use Cases:**
- Real-time stock price displays
- Live trading dashboards
- Market data widgets
- Algorithmic trading systems
- Financial data visualization

### Currency

#### Get Exchange Rates

```php
// All rates against USD
$rates = $client->currency()->baseUsd();

// All rates for a specific base
$rates = $client->currency()->allRates('EUR');
```

#### Currency Conversion

```php
$result = $client->currency()->calculator('USD', 'EUR', 100);
echo $result['converted_amount']; // Amount in EUR
```

#### Historical Exchange Rates

```php
$graph = $client->currency()->graph('USD', 'EUR', '1d');
// Interval options: 1d, 1w, 1m, 3m, 6m, 1y
```

### Account

#### Check Balance

```php
$balance = $client->account()->balance();
echo "Credits: " . $balance['credits'] . "\n";
```

#### Get Usage Statistics

```php
// Last 30 days
$usage = $client->account()->usage(30);
// Options: 7, 30, 90

echo "Requests: " . $usage['total_requests'] . "\n";
echo "Credits used: " . $usage['credits_used'] . "\n";
```

#### Get Analytics

```php
$analytics = $client->account()->analytics('month');
// Options: week, month, quarter, year

echo "Most used endpoint: " . $analytics['top_endpoint'] . "\n";
```

#### List API Keys

```php
$keys = $client->account()->keys();
foreach ($keys['api_keys'] as $key) {
    echo $key['name'] . ": " . $key['key'] . "\n";
}
```

## Response Handling

### Accessing Response Data

```php
$response = $client->stocks()->quote('AAPL');

// Check response status
if ($response->successful()) {
    // Get first ticker data
    $ticker = $response['tickers'][0];
    
    // Array access to ticker data
    echo "Symbol: " . $ticker['ticker'] . "\n";
    echo "Price: $" . $ticker['market']['price'] . "\n";
    echo "Change: " . $ticker['market']['change'] . "%\n";
    echo "Exchange: " . $ticker['market']['name'] . "\n";
    
    // Object access (alternative)
    echo "Price: $" . $response->tickers[0]->market->price . "\n";
}

// Get all data as array
$data = $response->data();

// Get as JSON string
$json = $response->json();

// Access response metadata
echo "Service: " . $response['wioex']['service'] . "\n";
echo "Last Cache: " . $response['wioex']['last_cache'] . "\n";
```

### Response Methods

```php
$response->data();          // Get data as array
$response->json();          // Get raw JSON string
$response->status();        // HTTP status code
$response->successful();    // true if 2xx status
$response->failed();        // true if not 2xx
$response->clientError();   // true if 4xx
$response->serverError();   // true if 5xx
$response->headers();       // All headers
$response->header('Name');  // Specific header
```

## Error Handling

### Exception Types

The SDK throws specific exceptions for different error scenarios:

```php
use Wioex\SDK\Exceptions\AuthenticationException;
use Wioex\SDK\Exceptions\ValidationException;
use Wioex\SDK\Exceptions\RateLimitException;
use Wioex\SDK\Exceptions\ServerException;
use Wioex\SDK\Exceptions\RequestException;

try {
    $stock = $client->stocks()->get('INVALID_TICKER');
} catch (AuthenticationException $e) {
    // 401, 403 errors
    echo "Authentication failed: " . $e->getMessage();
} catch (ValidationException $e) {
    // 400, 422 errors
    echo "Validation error: " . $e->getMessage();
} catch (RateLimitException $e) {
    // 429 errors
    echo "Rate limit exceeded!";
    echo "Retry after: " . $e->getRetryAfter() . " seconds";
} catch (ServerException $e) {
    // 500+ errors
    echo "Server error: " . $e->getMessage();
} catch (RequestException $e) {
    // Network/connection errors
    echo "Request failed: " . $e->getMessage();
}
```

### Enhanced Error Format Support (v1.2.1)

The SDK now supports both legacy and new centralized error formats with automatic detection:

#### Legacy Error Format
```json
{
  "error": "Invalid ticker parameter"
}
```

#### New Centralized Error Format (v1.2.1)
```json
{
  "error": {
    "code": "INVALID_TICKER_SYMBOLS",
    "title": "Invalid Ticker Symbols",
    "message": "One or more ticker symbols are not found in our database.",
    "error_code": 100116,
    "suggestions": ["Check ticker symbol spelling", "Verify symbols are supported"],
    "timestamp": "2025-10-22T06:39:03+00:00",
    "request_id": "req_7e1e08097872e4ee"
  }
}
```

#### Automatic Error Format Detection

The SDK automatically detects and parses both formats seamlessly:

```php
try {
    $result = $client->stocks()->quote('INVALID_SYMBOL');
} catch (ValidationException $e) {
    // Works with both old and new error formats
    echo "Error: " . $e->getMessage(); // Always returns user-friendly message
    
    // Additional context available from new format
    $context = $e->getContext();
    if (isset($context['suggestions'])) {
        echo "Suggestions: " . implode(', ', $context['suggestions']);
    }
}
```

**Benefits of Enhanced Error Handling:**
- **Backward Compatibility** - Existing code continues to work unchanged
- **Rich Error Context** - New format provides error codes, suggestions, and request IDs
- **Automatic Detection** - No code changes needed to support new format
- **Developer Experience** - Better debugging with structured error information

### Automatic Retry

The SDK automatically retries failed requests with exponential backoff:

- Connection failures
- 5xx server errors
- 429 rate limit errors (with respect to Retry-After header)

Configure retry behavior:

```php
$client = new WioexClient([
    'api_key' => 'your-key',
    'retry' => [
        'times' => 5,           // More aggressive retry
        'delay' => 50,          // Shorter initial delay
        'multiplier' => 3,      // Faster backoff
        'max_delay' => 10000    // Higher max delay
    ]
]);
```

## Testing

Run tests with PHPUnit:

```bash
composer test

# With coverage
composer test:coverage

# Static analysis
composer phpstan

# Code style check
composer cs:check

# Auto-fix code style
composer cs:fix
```

## Examples

See the `/examples` directory for more usage examples:

- `basic-usage.php` - Basic usage patterns and getting started
- `stocks-example.php` - Comprehensive stock operations
- `timeline-advanced-example.php` - Session filtering and date-based timeline data
- `test_price_changes.php` - Price changes across multiple timeframes  
- `streaming-example.php` - **NEW** WebSocket authentication and real-time streaming setup
- `error-handling.php` - Error handling patterns and exception management
- `test_signals.php` - Trading signals examples and filtering
- `test_stock_with_signal.php` - Stock data with auto-included signals
- `test_market_status.php` - Market status (authenticated & public access)
- `test_all_features.php` - Comprehensive test suite for all endpoints
- `error-reporting-example.php` - Error reporting and telemetry examples

## Support

- **Documentation**: https://docs.wioex.com
- **Email**: api-support@wioex.com
- **Issues**: https://github.com/wioex/php-sdk/issues

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

This SDK is open-sourced software licensed under the [MIT license](LICENSE).

## Credits

Developed and maintained by the [WioEX Team](https://wioex.com).

---

Made with ❤️ by WioEX
