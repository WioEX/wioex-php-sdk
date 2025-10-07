# WioEX PHP SDK

Official PHP SDK for **WioEX Financial Data API** - Enterprise-grade client library for accessing stocks, news, currency, and financial market data.

[![PHP Version](https://img.shields.io/packagist/php-v/wioex/php-sdk.svg)](https://packagist.org/packages/wioex/php-sdk)
[![Latest Version](https://img.shields.io/packagist/v/wioex/php-sdk.svg)](https://packagist.org/packages/wioex/php-sdk)
[![License](https://img.shields.io/packagist/l/wioex/php-sdk.svg)](https://packagist.org/packages/wioex/php-sdk)

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

// Get stock data
$stock = $client->stocks()->get('AAPL');
echo "Price: $" . $stock['price'] . "\n";

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
$stock = $client->stocks()->get('AAPL');

// Multiple stocks (comma-separated)
$stocks = $client->stocks()->get('AAPL,GOOGL,MSFT');
```

#### Get Stock Info

```php
$info = $client->stocks()->info('AAPL');
echo $info['company_name'];
echo $info['market_cap'];
echo $info['pe_ratio'];
```

#### Get Historical Data

```php
// Basic usage (default: 1day interval, 78 data points)
$timeline = $client->stocks()->timeline('AAPL');

// With options
$timeline = $client->stocks()->timeline('AAPL', [
    'interval' => '1min',    // Options: '1min' or '1day'
    'orderBy' => 'DESC',     // Sort order: 'ASC' or 'DESC' (default: ASC)
    'size' => 480,           // Number of data points: 1-5000 (default: 78)
    'timestamp' => 1704067200 // Optional: Unix timestamp start date
]);

// Examples:
// Last 8 hours (minute data): size=480, interval=1min
// Last 3 months (daily data): size=90, interval=1day
// Last 1 year (daily data): size=365, interval=1day
```

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

- `basic-usage.php` - Basic usage patterns
- `stocks-example.php` - Comprehensive stock operations
- `error-handling.php` - Error handling patterns

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
