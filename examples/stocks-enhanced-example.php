<?php

/**
 * WioEX PHP SDK - Enhanced Stocks Example
 *
 * This example demonstrates the new unified response format and enhanced stock features.
 * Shows both backward compatibility and new capabilities.
 */

require __DIR__ . '/../vendor/autoload.php';

use Wioex\SDK\WioexClient;

$client = new WioexClient([
    'api_key' => 'a1b2c3d4-e5f6-7890-abcd-ef1234567890' // Test API key
]);

echo "=== WioEX PHP SDK - Enhanced Stocks Example ===\n\n";

// ===================================
// 1. UNIFIED FORMAT FEATURES
// ===================================
echo "1. UNIFIED FORMAT FEATURES\n";
echo str_repeat('-', 50) . "\n";

$basicQuote = $client->stocks()->quote('SOUN');

// Access unified metadata
echo "Response Metadata:\n";
echo "  Request ID: " . $basicQuote->getRequestId() . "\n";
echo "  Response Time: " . $basicQuote->getResponseTime() . "ms\n";
echo "  Data Provider: " . $basicQuote->getDataProvider() . "\n";
echo "  Credits Consumed: " . $basicQuote->getCredits()['consumed'] . "\n";
echo "\n";

// Access instruments data
echo "Instruments Data:\n";
foreach ($basicQuote->getInstruments() as $instrument) {
    echo "  Symbol: {$instrument['symbol']}\n";
    echo "  Name: {$instrument['name']}\n";
    echo "  Price: \${$instrument['price']['current']}\n";
    echo "  Change: {$instrument['change']['amount']} ({$instrument['change']['percent']}%)\n";
    echo "  Market Status: {$instrument['market_status']['session']}\n";
    echo "  Real-time: " . ($instrument['market_status']['real_time'] ? 'Yes' : 'No') . "\n";
}
echo "\n";

// ===================================
// 2. ENHANCED DETAILED MODE
// ===================================
echo "2. ENHANCED DETAILED MODE\n";
echo str_repeat('-', 50) . "\n";

$detailedQuote = $client->stocks()->quoteDetailed('SOUN');

echo "SOUN Enhanced Data:\n";
foreach ($detailedQuote->getInstruments() as $stock) {
    echo "  Symbol: {$stock['symbol']}\n";
    echo "  Name: {$stock['name']}\n";
    echo "  Current Price: \${$stock['price']['current']}\n";
    
    // Enhanced price data
    if (isset($stock['price']['fifty_two_week_high'])) {
        echo "  52-Week Range: \${$stock['price']['fifty_two_week_low']} - \${$stock['price']['fifty_two_week_high']}\n";
    }
    
    // Pre-market data
    if (isset($stock['pre_market']) && $stock['pre_market']['price'] > 0) {
        echo "  Pre-market: \${$stock['pre_market']['price']} ({$stock['pre_market']['change_percent']}%)\n";
    }
    
    // Post-market data  
    if (isset($stock['post_market']) && $stock['post_market']['price'] > 0) {
        echo "  Post-market: \${$stock['post_market']['price']} ({$stock['post_market']['change_percent']}%)\n";
    }
    
    // Market cap
    if (isset($stock['market_cap']['value'])) {
        echo "  Market Cap: $" . number_format($stock['market_cap']['value'] / 1000000000, 2) . "B\n";
    }
    
    // Company info
    if (isset($stock['company_info']['logo_url'])) {
        echo "  Logo URL: {$stock['company_info']['logo_url']}\n";
    }
}

echo "\n";
echo "Detailed Mode Status: " . ($detailedQuote->isDetailedMode() ? 'Enabled' : 'Disabled') . "\n";
echo "\n";

// ===================================
// 3. PERFORMANCE COMPARISON
// ===================================
echo "3. PERFORMANCE COMPARISON\n";
echo str_repeat('-', 50) . "\n";

$symbols = 'SOUN,NVDA,AAPL,TSLA';

// Basic mode
$basicStart = microtime(true);
$basicResponse = $client->stocks()->quote($symbols);
$basicTime = (microtime(true) - $basicStart) * 1000;

// Detailed mode
$detailedStart = microtime(true);
$detailedResponse = $client->stocks()->quote($symbols, ['detailed' => true]);
$detailedTime = (microtime(true) - $detailedStart) * 1000;

echo "Performance Results:\n";
echo "  Basic Mode:\n";
echo "    Response Time: " . round($basicTime, 2) . "ms (local)\n";
echo "    Server Time: " . $basicResponse->getResponseTime() . "ms\n";
echo "    Instruments: " . count($basicResponse->getInstruments()) . "\n";
echo "    Data Provider: " . $basicResponse->getDataProvider() . "\n";

echo "  Detailed Mode:\n";
echo "    Response Time: " . round($detailedTime, 2) . "ms (local)\n";
echo "    Server Time: " . $detailedResponse->getResponseTime() . "ms\n";
echo "    Instruments: " . count($detailedResponse->getInstruments()) . "\n";
echo "    Data Provider: " . $detailedResponse->getDataProvider() . "\n";

echo "\n";

// ===================================
// 4. ERROR HANDLING
// ===================================
echo "4. ERROR HANDLING\n";
echo str_repeat('-', 50) . "\n";

try {
    $invalidResponse = $client->stocks()->quote('INVALID123');
    
    if ($invalidResponse->failed()) {
        echo "Error Response:\n";
        echo "  Status: " . $invalidResponse->status() . "\n";
        echo "  Successful: " . ($invalidResponse->successful() ? 'Yes' : 'No') . "\n";
        
        $errorData = $invalidResponse->data();
        if (isset($errorData['error'])) {
            echo "  Error Code: " . $errorData['error']['error_code'] . "\n";
            echo "  Error Message: " . $errorData['error']['message'] . "\n";
        }
    }
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}

echo "\n";

// ===================================
// 5. RESPONSE SUMMARY
// ===================================
echo "5. RESPONSE SUMMARY\n";
echo str_repeat('-', 50) . "\n";

$summary = $detailedResponse->getSummary();
echo "Response Summary:\n";
foreach ($summary as $key => $value) {
    echo "  " . ucfirst(str_replace('_', ' ', $key)) . ": " . 
         (is_bool($value) ? ($value ? 'Yes' : 'No') : $value) . "\n";
}

echo "\n";

// ===================================
// 6. CONVENIENCE METHODS
// ===================================
echo "6. CONVENIENCE METHODS\n";
echo str_repeat('-', 50) . "\n";

echo "WioEX Metadata:\n";
$wioexMeta = $detailedResponse->getWioexMetadata();
echo "  API Version: {$wioexMeta['api_version']}\n";
echo "  Brand: {$wioexMeta['brand']}\n";

echo "\nData Quality:\n";
$dataQuality = $detailedResponse->getDataQuality();
echo "  Freshness: {$dataQuality['data_freshness']}\n";
echo "  Accuracy: {$dataQuality['data_accuracy']}\n";

echo "\nCache Info:\n";
$cache = $detailedResponse->getCache();
echo "  Status: {$cache['status']}\n";
echo "  TTL: {$cache['ttl_seconds']} seconds\n";

echo "\n=== Enhanced Example Completed ===\n";
echo "\n💡 Key Features Demonstrated:\n";
echo "✅ Unified response format\n";
echo "✅ Enhanced detailed mode with rich data\n";
echo "✅ Professional metadata access\n";
echo "✅ Performance monitoring\n";
echo "✅ Error handling\n";
echo "✅ Convenience methods for common operations\n";