# WioEX PHP SDK - Stock Endpoint Configuration Fix

## Issue Description

Customers are receiving "Stock with symbol 'GM' not found" errors when using the WioEX PHP SDK. This is caused by incorrect API endpoint configuration.

## Root Cause

The issue occurs when customers use:
1. **Wrong domain**: `wioker.com` instead of `api.wioex.com`
2. **Wrong endpoint structure**: `/api/stocks/SYMBOL` instead of `/v2/stocks/get?stocks=SYMBOL`

## Solution

### ✅ Correct Configuration

```php
<?php
require_once 'vendor/autoload.php';

use Wioex\SDK\WioexClient;

// ✅ CORRECT: Use api.wioex.com domain (default)
$client = new WioexClient([
    'api_key' => 'your-wioex-api-key',
    // 'base_url' => 'https://api.wioex.com', // This is the default, no need to specify
]);

// ✅ CORRECT: Use the stocks() resource with proper method
try {
    $stocks = $client->stocks()->get(['GM', 'AAPL', 'TSLA']);
    
    foreach ($stocks as $stock) {
        echo "Symbol: {$stock['symbol']}\n";
        echo "Price: {$stock['price']}\n";
        echo "Change: {$stock['change']}\n\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
```

### ❌ Incorrect Configurations

```php
// ❌ WRONG: Using wioker.com domain
$client = new WioexClient([
    'api_key' => 'your-api-key',
    'base_url' => 'https://wioker.com'  // WRONG DOMAIN
]);

// ❌ WRONG: Manually constructing endpoint URLs
$url = "https://wioker.com/api/stocks/GM"; // WRONG DOMAIN AND STRUCTURE
```

## Quick Test

Test your configuration with this simple script:

```php
<?php
require_once 'vendor/autoload.php';

use Wioex\SDK\WioexClient;

$client = new WioexClient([
    'api_key' => 'your-wioex-api-key'
]);

// Test with a popular stock
try {
    $stocks = $client->stocks()->get(['AAPL']);
    echo "✅ Success! AAPL data received.\n";
    print_r($stocks);
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    
    // Check if it's an authentication error
    if (strpos($e->getMessage(), 'API_KEY') !== false) {
        echo "💡 Tip: Verify your API key is correct and active.\n";
    }
}
```

## Multiple Stocks

For multiple stocks in a single request:

```php
// ✅ CORRECT: Get multiple stocks efficiently
$stocks = $client->stocks()->get(['GM', 'AAPL', 'TSLA', 'GOOGL', 'MSFT']);

foreach ($stocks as $stock) {
    echo "{$stock['symbol']}: ${$stock['price']} ({$stock['change_percent']}%)\n";
}
```

## Error Handling

```php
try {
    $stocks = $client->stocks()->get(['GM']);
    
    if (empty($stocks)) {
        echo "No data received for GM\n";
    } else {
        echo "GM Price: $" . $stocks[0]['price'] . "\n";
    }
    
} catch (\Wioex\SDK\Exceptions\AuthenticationException $e) {
    echo "Authentication error: Check your API key\n";
} catch (\Wioex\SDK\Exceptions\ValidationException $e) {
    echo "Validation error: " . $e->getMessage() . "\n";
} catch (\Wioex\SDK\Exceptions\RequestException $e) {
    echo "Request error: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "General error: " . $e->getMessage() . "\n";
}
```

## API Endpoint Reference

| Method | Correct Endpoint | SDK Usage |
|--------|-----------------|-----------|
| Single Stock | `/v2/stocks/get?stocks=AAPL` | `$client->stocks()->get(['AAPL'])` |
| Multiple Stocks | `/v2/stocks/get?stocks=AAPL,TSLA,GM` | `$client->stocks()->get(['AAPL', 'TSLA', 'GM'])` |
| Stock Search | `/v2/stocks/search?query=Apple` | `$client->stocks()->search('Apple')` |
| Stock Info | `/v2/stocks/info?symbol=AAPL` | `$client->stocks()->info('AAPL')` |

## Configuration Verification

To verify your configuration is correct:

```php
// Check current configuration
$config = $client->getConfig();
echo "Base URL: " . $config->getBaseUrl() . "\n";
echo "API Key: " . (empty($config->getApiKey()) ? 'Not set' : 'Set') . "\n";

// Test connection
try {
    $account = $client->account()->info();
    echo "✅ Connection successful!\n";
    echo "Account: " . $account['name'] . "\n";
} catch (Exception $e) {
    echo "❌ Connection failed: " . $e->getMessage() . "\n";
}
```

## Support

If you continue to experience issues after following these steps:

1. **Verify API Key**: Ensure your API key is active in your WioEX dashboard
2. **Check Credits**: Confirm you have sufficient API credits
3. **Review Documentation**: https://docs.wioex.com
4. **Contact Support**: Include your API key prefix and error details

## Summary

- ✅ Use `api.wioex.com` (default domain)
- ✅ Use `$client->stocks()->get(['SYMBOL'])` method
- ✅ Include proper error handling
- ❌ Don't use `wioker.com` domain
- ❌ Don't manually construct endpoint URLs