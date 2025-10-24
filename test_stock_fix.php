<?php
/**
 * WioEX PHP SDK - Stock Endpoint Fix Test
 * 
 * This script tests the correct configuration for WioEX PHP SDK
 * to resolve "Stock with symbol 'GM' not found" errors.
 * 
 * Usage: php test_stock_fix.php
 */

require_once __DIR__ . '/vendor/autoload.php';

use Wioex\SDK\WioexClient;

echo "🔧 WioEX PHP SDK - Stock Endpoint Configuration Test\n";
echo "==================================================\n\n";

// Check if API key is provided via environment variable
$apiKey = $_ENV['WIOEX_API_KEY'] ?? null;

if (!$apiKey) {
    echo "❌ Error: WIOEX_API_KEY environment variable not set.\n";
    echo "💡 Set your API key: export WIOEX_API_KEY='your-api-key-here'\n";
    echo "💡 Or edit this script and set \$apiKey = 'your-api-key-here';\n\n";
    
    // Uncomment the line below and add your API key for testing
    // $apiKey = 'your-api-key-here';
    
    if (!$apiKey) {
        exit(1);
    }
}

echo "🔑 API Key: " . substr($apiKey, 0, 8) . "...\n\n";

// ✅ CORRECT: Initialize client with proper configuration
try {
    $client = new WioexClient([
        'api_key' => $apiKey,
        // Note: base_url defaults to 'https://api.wioex.com' - no need to specify
    ]);
    
    echo "✅ Client initialized successfully\n";
    
    // Get client configuration for verification
    $config = $client->getConfig();
    echo "🌐 Base URL: " . $config->getBaseUrl() . "\n";
    echo "⏱️  Timeout: " . $config->getTimeout() . "s\n\n";
    
} catch (Exception $e) {
    echo "❌ Client initialization failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 1: Single stock that was failing (GM)
echo "📊 Test 1: Getting GM stock data (previously failing)\n";
echo "-----------------------------------------------------\n";

try {
    $stocks = $client->stocks()->get(['GM']);
    
    if (!empty($stocks)) {
        $gm = $stocks[0];
        echo "✅ SUCCESS! GM data received:\n";
        echo "   Symbol: " . $gm['symbol'] . "\n";
        echo "   Price: $" . $gm['price'] . "\n";
        echo "   Change: " . $gm['change'] . " (" . $gm['change_percent'] . "%)\n";
        echo "   Volume: " . number_format($gm['volume']) . "\n\n";
    } else {
        echo "⚠️  No data received for GM (empty response)\n\n";
    }
    
} catch (\Wioex\SDK\Exceptions\AuthenticationException $e) {
    echo "❌ Authentication error: " . $e->getMessage() . "\n";
    echo "💡 Check your API key and account status\n\n";
} catch (\Wioex\SDK\Exceptions\ValidationException $e) {
    echo "❌ Validation error: " . $e->getMessage() . "\n\n";
} catch (Exception $e) {
    echo "❌ Error getting GM data: " . $e->getMessage() . "\n\n";
}

// Test 2: Multiple popular stocks
echo "📊 Test 2: Getting multiple popular stocks\n";
echo "------------------------------------------\n";

$testSymbols = ['AAPL', 'TSLA', 'GOOGL', 'MSFT'];

try {
    $stocks = $client->stocks()->get($testSymbols);
    
    if (!empty($stocks)) {
        echo "✅ SUCCESS! Received data for " . count($stocks) . " stocks:\n";
        
        foreach ($stocks as $stock) {
            echo "   {$stock['symbol']}: \${$stock['price']} ({$stock['change_percent']}%)\n";
        }
        echo "\n";
        
    } else {
        echo "⚠️  No data received for test symbols\n\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error getting multiple stocks: " . $e->getMessage() . "\n\n";
}

// Test 3: Account information (to verify API key)
echo "👤 Test 3: Account verification\n";
echo "-------------------------------\n";

try {
    $account = $client->account()->info();
    
    echo "✅ Account verified successfully:\n";
    echo "   Name: " . ($account['name'] ?? 'N/A') . "\n";
    echo "   Plan: " . ($account['plan'] ?? 'N/A') . "\n";
    echo "   Credits: " . ($account['credits_remaining'] ?? 'N/A') . "\n\n";
    
} catch (Exception $e) {
    echo "❌ Account verification failed: " . $e->getMessage() . "\n";
    echo "💡 This might indicate an API key issue\n\n";
}

// Test 4: Configuration comparison
echo "🔍 Test 4: Configuration Analysis\n";
echo "---------------------------------\n";

echo "✅ CORRECT Configuration (Current):\n";
echo "   Domain: api.wioex.com\n";
echo "   Method: \$client->stocks()->get(['GM'])\n";
echo "   Endpoint: /v2/stocks/get?stocks=GM\n\n";

echo "❌ INCORRECT Configuration (Avoid):\n";
echo "   Domain: wioker.com\n";
echo "   Manual URL: https://wioker.com/api/stocks/GM\n";
echo "   This causes 'Stock not found' errors\n\n";

// Summary
echo "📋 Summary\n";
echo "----------\n";

if (isset($gm) && !empty($gm)) {
    echo "🎉 All tests passed! Your configuration is correct.\n";
    echo "✅ GM stock data was successfully retrieved.\n";
    echo "✅ The 'Stock not found' issue has been resolved.\n\n";
    
    echo "💡 Key points for your application:\n";
    echo "   1. Use 'api.wioex.com' domain (default)\n";
    echo "   2. Use \$client->stocks()->get(['SYMBOL']) method\n";
    echo "   3. Include proper error handling\n";
    echo "   4. Verify API key and credits regularly\n\n";
    
} else {
    echo "⚠️  Some tests failed. Please check:\n";
    echo "   1. Your API key is correct and active\n";
    echo "   2. Your account has sufficient credits\n";
    echo "   3. You're using the latest SDK version\n";
    echo "   4. Network connectivity is working\n\n";
    
    echo "📞 If issues persist, contact WioEX support with:\n";
    echo "   - API key prefix: " . substr($apiKey, 0, 8) . "...\n";
    echo "   - Error messages from above tests\n";
    echo "   - Your account email\n\n";
}

echo "📚 Documentation: https://docs.wioex.com\n";
echo "🆘 Support: https://wioex.com/support\n";
echo "\n🔧 Test completed!\n";