# WioEX PHP SDK

Official PHP SDK for **WioEX Financial Data API** - Enterprise-grade client library for accessing stocks, trading signals, news, currency, and financial market data.

**Current Version: 1.2.0** | **Released: October 16, 2025** | **PHP 8.1+**

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
- ⭐ **Session Filtering** - Filter intraday data by trading sessions (v1.2.0)
- ⭐ **Advanced Timeline** - Date-based filtering and convenience methods (v1.2.0)
- ⭐ **Trading Signals** - Auto-included signals and comprehensive signal data (v1.1.0)
- ⭐ **Market Status** - Real-time market hours with public access option (v1.1.0)

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

#### Get Historical Data

```php
// Basic usage (default: 1day interval, 78 data points)
$timeline = $client->stocks()->timeline('AAPL');

// With all options
$timeline = $client->stocks()->timeline('AAPL', [
    'interval' => '1min',        // Options: '1min' or '1day'
    'orderBy' => 'DESC',         // Sort order: 'ASC' or 'DESC' (default: ASC)
    'size' => 480,               // Number of data points: 1-5000 (default: 78)
    'session' => 'regular',      // Trading session (1min only): 'all', 'regular', 'pre_market', 'after_hours', 'extended'
    'started_date' => '2024-10-16', // Start date (YYYY-MM-DD format)
    'timestamp' => 1704067200    // Alternative: Unix timestamp start date
]);

// Convenience methods for common use cases:

// Get regular trading hours only (9:30 AM - 4:00 PM EST)
$intraday = $client->stocks()->intradayTimeline('TSLA', ['size' => 100]);

// Get extended hours data (pre-market + regular + after-hours)
$extended = $client->stocks()->extendedHoursTimeline('TSLA', ['size' => 200]);

// Get data from specific date onwards
$fromDate = $client->stocks()->timelineFromDate('AAPL', '2024-10-16', [
    'interval' => '1day',
    'size' => 30
]);

// Session-specific data (1-minute intervals only)
$preMarket = $client->stocks()->timelineBySession('TSLA', 'pre_market', ['size' => 50]);
$afterHours = $client->stocks()->timelineBySession('TSLA', 'after_hours', ['size' => 50]);
```

**Trading Sessions** (applies to 1-minute intervals only):
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
- ✅ Display market hours on your website
- ✅ Show "Market Open/Closed" indicators
- ✅ Schedule trading operations
- ✅ Frontend widgets without exposing API keys
- ✅ Mobile apps with public data display

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
$stock = $client->stocks()->get('AAPL');

// Array access
echo $stock['symbol'];
echo $stock['price'];

// Object access
echo $stock->symbol;
echo $stock->price;

// Get all data as array
$data = $stock->data();

// Get as JSON string
$json = $stock->json();

// Check response status
if ($stock->successful()) {
    echo "Success!";
}
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
- `timeline-advanced-example.php` - ⭐ **NEW** Session filtering and date-based timeline data
- `test_price_changes.php` - ⭐ **NEW** Price changes across multiple timeframes  
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
