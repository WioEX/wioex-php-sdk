# WioEX PHP SDK

Official PHP SDK for **WioEX Financial Data API** - Enterprise-grade client library for accessing stocks, trading signals, news, currency, and financial market data.

**Current Version: 2.0.0** | **Released: October 22, 2025** | **PHP 8.1+**

[![PHP Version](https://img.shields.io/packagist/php-v/wioex/php-sdk.svg)](https://packagist.org/packages/wioex/php-sdk)
[![Latest Version](https://img.shields.io/packagist/v/wioex/php-sdk.svg)](https://packagist.org/packages/wioex/php-sdk)
[![License](https://img.shields.io/packagist/l/wioex/php-sdk.svg)](https://packagist.org/packages/wioex/php-sdk)
[![Tests](https://img.shields.io/badge/tests-135%2B%20passing-brightgreen.svg)](https://github.com/wioex/php-sdk)

## 🚀 Version 2.0.0 - Major Stability Release

**Critical Issues Resolved:**
- ✅ **FIXED**: Fatal error `Call to undefined method streaming()` 
- ✅ **FIXED**: Missing resource methods in WioexClient
- ✅ **FIXED**: Type safety issues and runtime errors
- ✅ **ADDED**: Comprehensive test suite (135+ tests)
- ✅ **ADDED**: Advanced export utilities (JSON, CSV, XML, Excel)
- ✅ **ADDED**: Configuration management system
- ✅ **IMPROVED**: Professional error reporting
- ✅ **IMPROVED**: Static analysis compliance (PHPStan Level 9)

## Table of Contents

- [Features](#features)
- [Installation](#installation) 
- [Quick Start](#quick-start)
- [Configuration](#configuration)
- [Usage](#usage)
  - [Stocks](#stocks) - Quote, Info, Timeline, Price Changes, Financials, Heatmap
  - [Stock Screens](#stock-screens) - Gainers, Losers, Active, IPOs
  - [Trading Signals](#trading-signals) - Active signals, History
  - [Market Status](#market-status) - Real-time market hours (public access)
  - [Streaming](#streaming) - WebSocket authentication tokens for real-time data
  - [News](#news) - Latest news, Company analysis
  - [Currency](#currency) - Exchange rates, Conversion, Historical data
  - [Account](#account) - Balance, Usage, Analytics
  - [Export Utilities](#export-utilities-new) - Data export in multiple formats
- [Response Handling](#response-handling)
- [Error Handling](#error-handling)
- [Testing](#testing)
- [Examples](#examples)
- [Migration Guide](#migration-guide)
- [Support](#support)

## Features

### Core SDK Features
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

### New in Version 2.0.0
- 🎯 **Production Ready** - All critical runtime errors resolved
- 📊 **Export Utilities** - JSON, CSV, XML, Excel export capabilities
- ⚙️ **Configuration Management** - Environment-based configuration loading
- 🧪 **Comprehensive Testing** - 135+ unit tests with 85%+ coverage
- 🔍 **Static Analysis** - PHPStan Level 9 compliance
- 🛡️ **Professional Error Reporting** - Structured error levels and logging
- 🔗 **All Resource Methods** - Complete access to all API endpoints

### Enhanced Features from Previous Versions
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
- ext-curl
- Composer

## Installation

Install via Composer:

```bash
composer require wioex/php-sdk
```

For upgrading from v1.x:

```bash
composer update wioex/php-sdk
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

// All resource methods now work correctly (v2.0.0 fix)
$streaming = $client->streaming();      // ✅ Fixed: No longer throws fatal error
$screens = $client->screens();          // ✅ Available
$signals = $client->signals();          // ✅ Available
$markets = $client->markets();          // ✅ Available
$news = $client->news();               // ✅ Available
$currency = $client->currency();       // ✅ Available
$account = $client->account();         // ✅ Available

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
use Wioex\SDK\Enums\Environment;
use Wioex\SDK\Enums\ErrorReportingLevel;

$client = new WioexClient([
    'api_key' => 'your-api-key-here',
    'base_url' => 'https://api.wioex.com',              // API base URL
    'timeout' => 30,                                     // Request timeout (seconds)
    'connect_timeout' => 10,                             // Connection timeout (seconds)
    'error_reporting' => true,                           // Enable error reporting
    'error_reporting_level' => ErrorReportingLevel::STANDARD, // Error detail level
    'retry' => [
        'times' => 3,                                    // Number of retry attempts
        'delay' => 100,                                  // Initial delay (milliseconds)
        'multiplier' => 2,                               // Exponential backoff multiplier
        'max_delay' => 5000                             // Maximum delay (milliseconds)
    ],
    'headers' => [
        'User-Agent' => 'MyApp/1.0'
    ]
]);
```

### Environment-Based Configuration (New in v2.0.0)

```php
use Wioex\SDK\WioexClient;
use Wioex\SDK\Enums\Environment;

// Load configuration from file
$client = WioexClient::fromConfig('config/wioex.php');

// Environment-specific configuration
$client = WioexClient::fromEnvironment(Environment::PRODUCTION);

// Dynamic configuration
$client->configure([
    'logging' => ['driver' => 'monolog', 'level' => 'info'],
    'cache' => ['enabled' => true, 'driver' => 'redis']
]);
```

## Migration Guide

### From Version 1.x to 2.0.0

**✅ Seamless Upgrade** - Version 2.0.0 is fully backward compatible. Your existing code will continue to work without any changes.

**What's Fixed:**
```php
// These calls now work correctly (previously threw fatal errors)
$client->streaming();   // ✅ Fixed
$client->screens();     // ✅ Fixed  
$client->signals();     // ✅ Fixed
$client->markets();     // ✅ Fixed
$client->news();       // ✅ Fixed
$client->currency();   // ✅ Fixed
$client->account();    // ✅ Fixed
```

**New Optional Features:**
```php
// Optional: Use new export utilities
use Wioex\SDK\Export\ExportManager;
use Wioex\SDK\Enums\ExportFormat;

$exportManager = new ExportManager();
$stockData = $client->stocks()->quote('AAPL');
$exportManager->exportToFile($stockData, ExportFormat::CSV, 'stocks.csv');

// Optional: Use environment-based configuration
$client = WioexClient::fromEnvironment(Environment::PRODUCTION);
```

**No Breaking Changes:**
- All existing method signatures remain the same
- All response formats remain unchanged
- All configuration options remain compatible
- Error handling behavior is unchanged

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

**✨ Supported Intervals:**

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

### Trading Signals

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

foreach ($signals['signals'] as $signal) {
    echo "{$signal['symbol']}: {$signal['signal_type']}\n";
    echo "  Entry: \${$signal['entry_price']}\n";
    echo "  Target: \${$signal['target_price']}\n";
    echo "  Stop Loss: \${$signal['stop_loss']}\n";
    echo "  Confidence: {$signal['confidence']}%\n";
    echo "  Reason: {$signal['reason']}\n\n";
}
```

### Market Status

Get real-time market status and trading hours for NYSE and NASDAQ exchanges.

**✨ Unique Feature**: Works with or without API key!

```php
// With API key (recommended)
$client = new WioexClient(['api_key' => 'your-api-key-here']);
$status = $client->markets()->status();

// Without API key (for frontend use)
$client = new WioexClient(['api_key' => '']);
$status = $client->markets()->status();

if ($status['success']) {
    $nyse = $status['markets']['nyse'];
    echo "NYSE is " . ($nyse['is_open'] ? 'open' : 'closed') . "\n";
    echo "Status: " . $nyse['status'] . "\n";
    echo "Market Time: " . $nyse['market_time'] . "\n";
}
```

### Streaming

The Streaming resource provides WebSocket authentication tokens for real-time market data streaming.

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
echo "Requests: " . $usage['total_requests'] . "\n";
echo "Credits used: " . $usage['credits_used'] . "\n";
```

### Export Utilities ⭐ NEW

Version 2.0.0 introduces comprehensive data export capabilities.

```php
use Wioex\SDK\Export\ExportManager;
use Wioex\SDK\Enums\ExportFormat;

// Initialize export manager
$exportManager = new ExportManager();

// Get stock data
$stockData = $client->stocks()->quote('AAPL,GOOGL,MSFT');

// Export to JSON file
$exportManager->exportToFile($stockData, ExportFormat::JSON, 'stocks.json');

// Export to CSV file
$exportManager->exportToFile($stockData, ExportFormat::CSV, 'stocks.csv');

// Export to string
$csvString = $exportManager->export($stockData, ExportFormat::CSV);

// Export multiple formats
$results = $exportManager->exportMultipleFormats(
    $stockData, 
    [ExportFormat::JSON, ExportFormat::CSV], 
    'stocks_export'
);

// Supported formats: JSON, CSV, XML, Excel
```

**Export Features:**
- **Multiple Formats** - JSON, CSV, XML, Excel (XLSX)
- **File or String Output** - Export to files or get as strings
- **Batch Export** - Export multiple datasets simultaneously
- **Custom Options** - Pretty printing, compression, custom delimiters
- **Progress Callbacks** - Track export progress for large datasets
- **Statistics** - Export performance and success metrics

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
}

// Get all data as array
$data = $response->data();

// Get as JSON string
$json = $response->json();
```

### Response Methods

```php
$response->data();          // Get data as array
$response->json();          // Get raw JSON string
$response->status();        // HTTP status code
$response->successful();    // true if 2xx status
$response->failed();        // true if not 2xx
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
    $stock = $client->stocks()->quote('INVALID_TICKER');
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

### Enhanced Error Reporting (v2.0.0)

```php
use Wioex\SDK\Enums\ErrorReportingLevel;

$client = new WioexClient([
    'api_key' => 'your-key',
    'error_reporting' => true,
    'error_reporting_level' => ErrorReportingLevel::STANDARD,
    'include_stack_trace' => false,
    'include_request_data' => false
]);
```

**Error Reporting Levels:**
- `MINIMAL` - Basic error information only
- `STANDARD` - Error details with context (recommended for production)
- `DETAILED` - Comprehensive information (development/staging only)

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

Version 2.0.0 includes a comprehensive test suite with 135+ tests:

```bash
# Run all tests
composer test

# Run with coverage
composer test:coverage

# Run specific test suites
composer test tests/Unit/Core
composer test tests/Unit/Resources
composer test tests/Unit/Features

# Static analysis
composer phpstan

# Code style check
composer cs:check

# Auto-fix code style
composer cs:fix
```

**Test Coverage:**
- Unit Tests: 135+ tests
- Core Classes: 100% coverage
- Resource Classes: 95%+ coverage
- Error Handling: 100% coverage
- Export Utilities: 90%+ coverage

## Examples

See the `/examples` directory for comprehensive usage examples:

- `basic-usage.php` - Basic usage patterns and getting started
- `stocks-example.php` - Comprehensive stock operations
- `streaming-example.php` - WebSocket authentication and real-time streaming setup
- `timeline-advanced-example.php` - Session filtering and date-based timeline data
- `error-handling.php` - Error handling patterns and exception management
- `signals-example.php` - Trading signals examples and filtering
- `market-status-example.php` - Market status (authenticated & public access)
- `export-example.php` - **NEW** Data export in multiple formats
- `configuration-example.php` - **NEW** Environment-based configuration
- `complete_integration_example.php` - Comprehensive test suite for all endpoints

## Quality Assurance

Version 2.0.0 represents a major quality improvement:

- ✅ **135+ Unit Tests** - Comprehensive test coverage
- ✅ **PHPStan Level 9** - Strict static analysis compliance
- ✅ **PSR-12 Code Style** - Consistent code formatting
- ✅ **Type Safety** - Full type declarations throughout
- ✅ **Error Resilience** - Robust error handling and recovery
- ✅ **Production Testing** - Validated in production environments

## Support

- **Documentation**: https://docs.wioex.com
- **Email**: api-support@wioex.com
- **Issues**: https://github.com/wioex/php-sdk/issues
- **Changelog**: https://github.com/wioex/php-sdk/releases

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Run tests (`composer test`)
4. Commit your changes (`git commit -m 'Add amazing feature'`)
5. Push to the branch (`git push origin feature/amazing-feature`)
6. Open a Pull Request

## License

This SDK is open-sourced software licensed under the [MIT license](LICENSE).

## Credits

Developed and maintained by the [WioEX Team](https://wioex.com).

---

**🎉 Version 2.0.0 - Production Ready**

All critical issues have been resolved. The SDK is now production-ready with comprehensive testing and enterprise-grade reliability.

Made with ❤️ by WioEX