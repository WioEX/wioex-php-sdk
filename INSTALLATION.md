# WioEX PHP SDK - Installation & Quick Start Guide

## 📦 Package Overview

**Package Name:** `wioex/php-sdk`
**Version:** 1.0.0
**PHP Version:** 8.1+
**Total Files:** 25
**Total Lines:** ~1,533 lines of PHP code

---

## 📁 Directory Structure

```
wioex-php-sdk/
├── composer.json              # Package metadata & dependencies
├── README.md                  # Full documentation
├── LICENSE                    # MIT License
├── .gitignore                # Git ignore rules
├── phpunit.xml.dist          # PHPUnit configuration
├── INSTALLATION.md           # This file
│
├── src/                      # Source code
│   ├── WioexClient.php       # Main SDK client (168 lines)
│   ├── Config.php            # Configuration class (107 lines)
│   │
│   ├── Http/                 # HTTP layer
│   │   ├── Client.php        # Guzzle wrapper (157 lines)
│   │   ├── Response.php      # Response wrapper (111 lines)
│   │   └── RetryHandler.php  # Retry logic (79 lines)
│   │
│   ├── Exceptions/           # Custom exceptions
│   │   ├── WioexException.php           # Base exception
│   │   ├── AuthenticationException.php  # 401/403 errors
│   │   ├── ValidationException.php      # 400/422 errors
│   │   ├── RateLimitException.php       # 429 errors
│   │   ├── ServerException.php          # 500+ errors
│   │   └── RequestException.php         # Network errors
│   │
│   └── Resources/            # API resource classes
│       ├── Resource.php      # Base resource (35 lines)
│       ├── Stocks.php        # Stocks API (86 lines)
│       ├── Screens.php       # Screens API (104 lines)
│       ├── News.php          # News API (28 lines)
│       ├── Currency.php      # Currency API (44 lines)
│       └── Account.php       # Account API (53 lines)
│
└── examples/                 # Usage examples
    ├── basic-usage.php       # Basic usage patterns
    ├── stocks-example.php    # Comprehensive stock operations
    └── error-handling.php    # Error handling patterns
```

---

## 🚀 Installation Methods

### Method 1: Via Composer (Recommended - When Published)

Once the package is published to Packagist:

```bash
composer require wioex/php-sdk
```

### Method 2: Local Installation (For Development)

If you're developing or testing locally:

1. **Copy the SDK to your project:**
```bash
cp -r /var/www/clients/client7/web64/_subdomains/app/wioex-php-sdk /path/to/your/project/
```

2. **Add to your project's composer.json:**
```json
{
    "require": {
        "wioex/php-sdk": "*"
    },
    "repositories": [
        {
            "type": "path",
            "url": "./wioex-php-sdk"
        }
    ]
}
```

3. **Install dependencies:**
```bash
composer install
```

### Method 3: Direct Inclusion

Include the SDK directly without Composer:

```php
<?php
require_once '/path/to/wioex-php-sdk/vendor/autoload.php';
// OR use PSR-4 autoloading manually
```

---

## 🔧 Setup

### 1. Install Dependencies

Navigate to the SDK directory and install Guzzle:

```bash
cd wioex-php-sdk
composer install
```

This will install:
- `guzzlehttp/guzzle` (^7.8) - HTTP client
- PHPUnit and dev tools (optional)

### 2. Get Your API Key

1. Sign up at https://wioex.com
2. Navigate to your dashboard
3. Generate an API key
4. Copy the key for use in your code

### 3. Initialize the Client

```php
<?php

require 'vendor/autoload.php';

use Wioex\SDK\WioexClient;

$client = new WioexClient([
    'api_key' => 'your-api-key-here'
]);

// You're ready to make API calls!
$stock = $client->stocks()->get('AAPL');
echo "AAPL Price: $" . $stock['price'];
```

---

## 📝 Quick Start Examples

### Example 1: Basic Stock Data

```php
$client = new WioexClient(['api_key' => 'your-key']);

// Get single stock
$aapl = $client->stocks()->get('AAPL');
echo "{$aapl['symbol']}: \${$aapl['price']}";

// Get multiple stocks
$stocks = $client->stocks()->get('AAPL,GOOGL,MSFT');
foreach ($stocks['data'] as $stock) {
    echo "{$stock['symbol']}: \${$stock['price']}\n";
}
```

### Example 2: Market Movers

```php
// Top gainers
$gainers = $client->screens()->gainers();
foreach ($gainers['data'] as $stock) {
    echo "{$stock['symbol']}: +{$stock['change_percent']}%\n";
}

// Most active
$active = $client->screens()->active(10);
```

### Example 3: News & Analysis

```php
// Latest news
$news = $client->news()->latest('TSLA');
foreach ($news['articles'] as $article) {
    echo $article['title'] . "\n";
}

// Company analysis
$analysis = $client->news()->companyAnalysis('AAPL');
echo $analysis['summary'];
```

### Example 4: Error Handling

```php
use Wioex\SDK\Exceptions\ValidationException;
use Wioex\SDK\Exceptions\RateLimitException;

try {
    $stock = $client->stocks()->get('INVALID');
} catch (ValidationException $e) {
    echo "Invalid ticker: " . $e->getMessage();
} catch (RateLimitException $e) {
    echo "Rate limited. Retry after: " . $e->getRetryAfter() . "s";
}
```

---

## 🧪 Testing the Installation

Run the basic usage example:

```bash
cd wioex-php-sdk
php examples/basic-usage.php
```

Expected output:
```
=== WioEX PHP SDK - Basic Usage Example ===

1. Getting stock data for AAPL...
Symbol: AAPL
Price: $175.43
Change: +1.25%

2. Searching for Apple stocks...
Found 5 results
  - AAPL: Apple Inc.
  - AAPL.US: Apple Inc. (US)
  ...
```

---

## 📚 Next Steps

1. **Read the full documentation:** [README.md](README.md)
2. **Explore examples:** Check the `/examples` directory
3. **API reference:** https://docs.wioex.com
4. **Get support:** api-support@wioex.com

---

## 🐛 Troubleshooting

### Issue: "Class not found"

**Solution:** Run `composer install` to install dependencies.

```bash
cd wioex-php-sdk
composer install
```

### Issue: "Invalid API key"

**Solution:** Check that your API key is correct and active.

```php
$client = new WioexClient([
    'api_key' => 'your-actual-api-key-not-placeholder'
]);
```

### Issue: "Connection timeout"

**Solution:** Increase timeout settings.

```php
$client = new WioexClient([
    'api_key' => 'your-key',
    'timeout' => 60,  // Increase to 60 seconds
    'connect_timeout' => 20
]);
```

### Issue: "Rate limit exceeded"

**Solution:** The SDK automatically retries. You can also implement custom retry logic:

```php
$client = new WioexClient([
    'api_key' => 'your-key',
    'retry' => [
        'times' => 5,      // More retries
        'delay' => 100,    // Initial delay
        'multiplier' => 2
    ]
]);
```

---

## 📦 Publishing to Packagist (For Maintainers)

To publish this package to Packagist:

1. Create a GitHub repository
2. Push the code
3. Register on https://packagist.org
4. Submit the GitHub URL
5. Enable auto-update webhook

---

## 🎉 You're Ready!

Start building amazing financial applications with WioEX API!

```php
$client = new WioexClient(['api_key' => 'your-key']);
$data = $client->stocks()->get('AAPL');
// Build something awesome! 🚀
```

---

**Questions?** Contact us at api-support@wioex.com
