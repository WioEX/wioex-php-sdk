<?php

/**
 * WioEX PHP SDK - Enhanced Stocks Example
 *
 * This example demonstrates the new unified ResponseTemplate format 
 * and enhanced Yahoo Finance integration with detailed market data.
 */

require __DIR__ . '/../vendor/autoload.php';

use Wioex\SDK\WioexClient;

$client = new WioexClient([
    'api_key' => 'your-api-key-here'
]);

echo "=== WioEX PHP SDK - Enhanced Stocks with Unified Format ===\n\n";

// 1. Basic Stock Quote (TipRanks data)
echo "1. BASIC STOCK QUOTE (Standard Mode)\n";
echo str_repeat('-', 60) . "\n";

$basicQuote = $client->stocks()->quote('SOUN');
$instruments = $basicQuote->getInstruments();
$metadata = $basicQuote->getWioexMetadata();

echo "SOUN - Basic Quote (Provider: {$metadata['brand']}):\n";
if (!empty($instruments)) {
    $stock = $instruments[0];
    echo sprintf("  Symbol: %s (%s)\n", $stock['symbol'], $stock['name']);
    echo sprintf("  Price: $%s\n", number_format($stock['price']['current'], 2));
    echo sprintf("  Change: %+.2f (%+.2f%%)\n", 
        $stock['change']['amount'], 
        $stock['change']['percent']
    );
    echo sprintf("  Volume: %s\n", number_format($stock['volume']['current']));
    echo sprintf("  Market Status: %s\n", $stock['market_status']['session']);
}
echo "\n";

// 2. Enhanced Detailed Quote (Yahoo Finance data)
echo "2. ENHANCED DETAILED QUOTE (Detailed Mode)\n";
echo str_repeat('-', 60) . "\n";

$detailedQuote = $client->stocks()->quoteDetailed('SOUN');
$detailedInstruments = $detailedQuote->getInstruments();
$performance = $detailedQuote->getPerformance();
$dataQuality = $detailedQuote->getDataQuality();

echo "SOUN - Enhanced Quote (Provider: {$dataQuality['data_accuracy']}):\n";
if (!empty($detailedInstruments)) {
    $stock = $detailedInstruments[0];
    
    // Basic price info
    echo sprintf("  Symbol: %s (%s)\n", $stock['symbol'], $stock['name']);
    echo sprintf("  Current Price: $%s\n", number_format($stock['price']['current'], 2));
    echo sprintf("  Change: %+.2f (%+.2f%%)\n", 
        $stock['change']['amount'], 
        $stock['change']['percent']
    );
    
    // Enhanced 52-week data
    if (isset($stock['price']['fifty_two_week_high'])) {
        echo sprintf("  52W High: $%s | 52W Low: $%s\n", 
            number_format($stock['price']['fifty_two_week_high'], 2),
            number_format($stock['price']['fifty_two_week_low'], 2)
        );
    }
    
    // Market cap
    if (isset($stock['market_cap']['value'])) {
        $marketCapB = $stock['market_cap']['value'] / 1000000000;
        echo sprintf("  Market Cap: $%.2fB\n", $marketCapB);
    }
    
    // Pre/Post market data
    if (isset($stock['pre_market']) && $stock['pre_market']['price'] > 0) {
        echo sprintf("  Pre-Market: $%s (%+.2f%%)\n", 
            number_format($stock['pre_market']['price'], 2),
            $stock['pre_market']['change_percent']
        );
    }
    
    if (isset($stock['post_market']) && $stock['post_market']['price'] > 0) {
        echo sprintf("  Post-Market: $%s (%+.2f%%)\n", 
            number_format($stock['post_market']['price'], 2),
            $stock['post_market']['change_percent']
        );
    }
    
    // Company info enhancements
    if (isset($stock['company_info']['logo_url'])) {
        echo sprintf("  Logo: %s\n", $stock['company_info']['logo_url']);
    }
    
    echo sprintf("  Data Source: %s\n", $stock['data_source']);
    echo sprintf("  Response Time: %.1fms\n", $performance['total_time_ms']);
}
echo "\n";

// 3. Multiple Enhanced Stocks
echo "3. MULTIPLE ENHANCED STOCKS\n";
echo str_repeat('-', 60) . "\n";

$multipleStocks = $client->stocks()->quoteDetailed('SOUN,NVDA,AAPL');
$multipleInstruments = $multipleStocks->getInstruments();
$credits = $multipleStocks->getCredits();

echo "Portfolio View (Credits Used: {$credits['consumed']}):\n";
foreach ($multipleInstruments as $stock) {
    $marketCapB = ($stock['market_cap']['value'] ?? 0) / 1000000000;
    echo sprintf("  %-6s $%-8s %+6.2f%% | MCap: $%.1fB\n",
        $stock['symbol'],
        number_format($stock['price']['current'], 2),
        $stock['change']['percent'],
        $marketCapB
    );
}
echo "\n";

// 4. Response Metadata Analysis
echo "4. UNIFIED RESPONSE METADATA\n";
echo str_repeat('-', 60) . "\n";

$cache = $detailedQuote->getCache();
$requestInfo = $detailedQuote->getRequestId();

echo "Response Analysis:\n";
echo sprintf("  Request ID: %s\n", $requestInfo);
echo sprintf("  Cache Status: %s\n", $cache['status']);
echo sprintf("  Data Freshness: %s\n", $dataQuality['data_freshness']);
echo sprintf("  Market Timezone: %s\n", $dataQuality['market_timezone']);
echo sprintf("  Last Market Close: %s\n", $dataQuality['last_market_close_utc']);
echo sprintf("  Next Market Open: %s\n", $dataQuality['next_market_open_utc']);
echo "\n";

// 5. Validation Example
echo "5. RESPONSE VALIDATION\n";
echo str_repeat('-', 60) . "\n";

try {
    $validation = $detailedQuote->validateEnhancedStockQuote();
    if ($validation->isValid()) {
        echo "✅ Enhanced stock quote validation: PASSED\n";
        echo sprintf("   Validation Rules: %d\n", count($validation->getMetadata()['rules_applied'] ?? []));
    } else {
        echo "❌ Enhanced stock quote validation: FAILED\n";
        foreach ($validation->getErrors() as $error) {
            echo sprintf("   Error: %s\n", $error['message']);
        }
    }
} catch (Exception $e) {
    echo "⚠️  Validation error: " . $e->getMessage() . "\n";
}
echo "\n";

// 6. Advanced Features Demo
echo "6. ADVANCED FEATURES\n";
echo str_repeat('-', 60) . "\n";

// Check if detailed mode was used
$isDetailed = $detailedQuote->isDetailedMode();
echo sprintf("Detailed Mode Active: %s\n", $isDetailed ? 'Yes' : 'No');

// Response transformation example
$simplifiedData = $detailedQuote->pluck('symbol');
echo "Symbols in Response: " . implode(', ', $simplifiedData) . "\n";

// Get only price data
$priceData = $detailedQuote->only(['data']);
echo sprintf("Data Keys Available: %s\n", implode(', ', array_keys($priceData)));

echo "\n=== Example completed ===\n";
echo "\n💡 New Features Demonstrated:\n";
echo "✅ Unified ResponseTemplate format\n";
echo "✅ Enhanced Yahoo Finance integration\n";
echo "✅ Pre/post market data\n";
echo "✅ 52-week high/low ranges\n";
echo "✅ Market capitalization data\n";
echo "✅ Company logos and enhanced info\n";
echo "✅ Professional metadata access\n";
echo "✅ Response validation\n";
echo "✅ Helper methods (getCoreData, getInstruments)\n";
echo "✅ Performance and cache metrics\n";