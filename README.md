# WioEX PHP SDK

Official PHP SDK for **WioEX Financial Data API** - Enterprise-grade client library for accessing stocks, trading signals, news, currency, and financial market data.

[![PHP Version](https://img.shields.io/packagist/php-v/wioex/php-sdk.svg)](https://packagist.org/packages/wioex/php-sdk)
[![Latest Version](https://img.shields.io/packagist/v/wioex/php-sdk.svg)](https://packagist.org/packages/wioex/php-sdk)
[![License](https://img.shields.io/packagist/l/wioex/php-sdk.svg)](https://packagist.org/packages/wioex/php-sdk)
[![Tests](https://img.shields.io/badge/tests-135%2B%20passing-brightgreen.svg)](https://github.com/wioex/php-sdk)

## 🚀 Version 2.0.0 - Production Ready

**All critical issues resolved!** This major release fixes all runtime errors and provides enterprise-grade reliability.

### Critical Fixes ✅
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

// Initialize client
$client = new WioexClient([
    'api_key' => 'your-api-key-here'
]);

// All resource methods now work correctly ✅
$stocks = $client->stocks();
$streaming = $client->streaming();  // Fixed: No longer throws fatal error
$screens = $client->screens();
$signals = $client->signals();
$markets = $client->markets();
$news = $client->news();
$currency = $client->currency();
$account = $client->account();

// Get stock data
$response = $client->stocks()->quote('AAPL');
if ($response->successful()) {
    $ticker = $response['tickers'][0];
    echo "AAPL Price: $" . $ticker['market']['price'] . "\n";
    
    // Auto-included trading signals
    if (isset($ticker['signal'])) {
        echo "Signal: " . $ticker['signal']['signal_type'] . "\n";
    }
}

// Market status (works with or without API key)
$status = $client->markets()->status();
$nyse = $status['markets']['nyse'];
echo "NYSE is " . ($nyse['is_open'] ? 'open' : 'closed') . "\n";

// Account info
$balance = $client->account()->balance();
echo "Credits: " . $balance['credits'] . "\n";
```

## Core Features

### Stock Data
```php
// Search stocks
$results = $client->stocks()->search('Apple');

// Get quotes (single or multiple)
$quote = $client->stocks()->quote('AAPL');
$quotes = $client->stocks()->quote('AAPL,GOOGL,MSFT');

// Historical data with multiple intervals
$timeline = $client->stocks()->timeline('AAPL', [
    'interval' => '1day',  // 1min, 5min, 1hour, 1day, 1week, 1month
    'size' => 100
]);

// Company information
$info = $client->stocks()->info('AAPL');

// Price changes across timeframes
$changes = $client->stocks()->priceChanges('AAPL');
```

### Market Screens
```php
// Market movers
$gainers = $client->screens()->gainers();
$losers = $client->screens()->losers();
$active = $client->screens()->active();

// Pre/post market
$preGainers = $client->screens()->preMarketGainers();
$postLosers = $client->screens()->postMarketLosers();

// IPOs
$upcomingIpos = $client->screens()->ipos('upcoming');
```

### Trading Signals
```php
// Active signals
$signals = $client->signals()->active();

// Filter by symbol and confidence
$appleSignals = $client->signals()->active([
    'symbol' => 'AAPL',
    'min_confidence' => 80
]);

// Signal history
$history = $client->signals()->history(['days' => 7]);
```

### Real-time Streaming
```php
// Get WebSocket token for real-time data
$tokenResponse = $client->streaming()->getToken();

if ($tokenResponse->successful()) {
    $token = $tokenResponse->data('token');
    $wsUrl = $tokenResponse->data('websocket_url');
    
    // Use in your WebSocket client
    echo "Token: {$token}\n";
    echo "WebSocket URL: {$wsUrl}\n";
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
// Latest news
$news = $client->news()->latest('TSLA');
foreach ($news['articles'] as $article) {
    echo $article['title'] . "\n";
}

// Company analysis
$analysis = $client->news()->companyAnalysis('AAPL');
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
$client->stocks()->quote('AAPL');        // ✅ Works
$client->streaming()->getToken();        // ✅ Fixed in v2.0.0
$client->markets()->status();            // ✅ Works
$client->account()->balance();           // ✅ Works
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

**🎉 Production Ready** - All critical issues resolved in v2.0.0

Made with ❤️ by [WioEX Team](https://wioex.com)