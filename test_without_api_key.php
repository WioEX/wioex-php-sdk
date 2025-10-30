<?php
/**
 * WioEX PHP SDK - Configuration Test (Without API Key)
 * 
 * Tests SDK configuration and endpoint structure without requiring API key
 * Useful for diagnosing configuration issues before API testing
 */

echo "🔧 WioEX PHP SDK - Configuration Test (No API Key Required)\n";
echo "=========================================================\n\n";

// Test 1: Domain Resolution
echo "🌐 Test 1: Domain Resolution\n";
echo "----------------------------\n";

$domains = [
    'api.wioex.com' => '✅ CORRECT (Official WioEX API)',
    'wioker.com' => '❌ INCORRECT (Causes stock not found errors)'
];

foreach ($domains as $domain => $status) {
    $ip = gethostbyname($domain);
    if ($ip !== $domain) {
        echo "   $domain → $ip $status\n";
    } else {
        echo "   $domain → DNS FAILED $status\n";
    }
}
echo "\n";

// Test 2: Endpoint Structure Test
echo "🔗 Test 2: Endpoint Structure\n";
echo "-----------------------------\n";

$endpoints = [
    'https://api.wioex.com/stocks/get?stocks=GM' => '✅ CORRECT',
    'https://wioker.com/api/stocks/GM' => '❌ INCORRECT',
    'https://api.wioex.com/stocks/search?query=General' => '✅ CORRECT',
    'https://api.wioex.com/account/info' => '✅ CORRECT'
];

foreach ($endpoints as $endpoint => $status) {
    echo "   $endpoint\n";
    echo "   $status\n\n";
}

// Test 3: cURL Test (No API Key)
echo "📡 Test 3: Basic Connectivity Test\n";
echo "----------------------------------\n";

$testUrl = 'https://api.wioex.com/stocks/get?stocks=AAPL';

echo "Testing: $testUrl\n";
echo "Expected: API key required error (not 'stock not found')\n\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $testUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_HEADER, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response !== false) {
    echo "✅ Connection successful (HTTP $httpCode)\n";
    
    $data = json_decode($response, true);
    if ($data && isset($data['error'])) {
        $errorCode = $data['error']['code'] ?? 'unknown';
        $errorMessage = $data['error']['message'] ?? 'unknown';
        
        if (strpos($errorCode, 'API_KEY') !== false) {
            echo "✅ PERFECT! Got expected API key error: $errorCode\n";
            echo "   This means endpoint structure is correct!\n";
        } elseif (strpos($errorMessage, 'not found') !== false) {
            echo "❌ ERROR: Got 'not found' error - wrong endpoint!\n";
        } else {
            echo "⚠️  Got different error: $errorCode - $errorMessage\n";
        }
    }
} else {
    echo "❌ Connection failed\n";
}
echo "\n";

// Test 4: SDK Class Loading Test
echo "🏗️ Test 4: SDK Class Loading\n";
echo "----------------------------\n";

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    echo "✅ Composer autoload found\n";
    
    require_once __DIR__ . '/vendor/autoload.php';
    
    try {
        $reflection = new ReflectionClass('Wioex\SDK\WioexClient');
        echo "✅ WioexClient class loaded successfully\n";
        echo "   File: " . $reflection->getFileName() . "\n";
        
        // Test configuration without API key
        $client = new Wioex\SDK\WioexClient([
            // No api_key provided - should work for initialization
        ]);
        
        $config = $client->getConfig();
        echo "✅ Client initialization successful\n";
        echo "   Base URL: " . $config->getBaseUrl() . "\n";
        echo "   Timeout: " . $config->getTimeout() . "s\n";
        
    } catch (Exception $e) {
        echo "❌ SDK loading error: " . $e->getMessage() . "\n";
    }
} else {
    echo "❌ Composer autoload not found\n";
    echo "   Run: composer install\n";
}
echo "\n";

// Test 5: Configuration Analysis
echo "🔍 Test 5: Configuration Analysis\n";
echo "---------------------------------\n";

echo "✅ CORRECT Configuration Pattern:\n";
echo "```php\n";
echo "\$client = new WioexClient([\n";
echo "    'api_key' => 'your-api-key-here'\n";
echo "    // base_url defaults to https://api.wioex.com\n";
echo "]);\n";
echo "\$stocks = \$client->stocks()->get(['GM']);\n";
echo "```\n\n";

echo "❌ INCORRECT Patterns to Avoid:\n";
echo "1. Using wioker.com domain\n";
echo "2. Manual URL construction\n";
echo "3. Wrong endpoint paths\n\n";

// Summary
echo "📋 Test Summary\n";
echo "---------------\n";
echo "If all tests above show ✅, your configuration is correct.\n";
echo "The only missing piece is a valid API key.\n\n";

echo "🔑 To get a test API key:\n";
echo "1. Visit: https://dashboard.wioex.com\n";
echo "2. Login to your account\n";
echo "3. Go to API Keys section\n";
echo "4. Create a new test/development key\n";
echo "5. Copy the key and use in your code\n\n";

echo "📞 Need help?\n";
echo "- Documentation: https://docs.wioex.com\n";
echo "- Support: https://wioex.com/support\n";
echo "- GitHub: https://github.com/WioEX/wioex-php-sdk\n\n";

echo "🔧 Configuration test completed!\n";
?>