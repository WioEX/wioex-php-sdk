<?php

declare(strict_types=1);

/**
 * Test Stream Token Fix in PHP SDK v2.6.0
 */

require __DIR__ . '/vendor/autoload.php';

use Wioex\SDK\WioexClient;
use Wioex\SDK\Version;

echo "=== WioEX PHP SDK v2.6.0 - Stream Token Fix Test ===\n\n";

// Display version info
echo "SDK Version: " . Version::current() . "\n";
echo "Release: " . Version::CODENAME . "\n";
echo "Date: " . Version::RELEASE_DATE . "\n\n";

// Test with demo API key
$client = new WioexClient([
    'api_key' => 'd8541dc2-13c6-45c1-9419-512f1240039f',
    'base_url' => 'https://api.wioex.com'  // Production API for testing
]);

try {
    echo "🧪 Testing Production Token Generation...\n";
    echo str_repeat('-', 50) . "\n";
    
    $response = $client->streaming()->getToken();
    
    if ($response->successful()) {
        echo "✅ SUCCESS: Production token generated!\n\n";
        
        $data = $response->data();
        
        // Verify token format
        if (isset($data['token'])) {
            $token = $data['token'];
            echo "🔍 Token Analysis:\n";
            echo "  Format: " . (str_starts_with($token, 'eyJ') ? 'JWT ✅' : 'Unknown ❌') . "\n";
            echo "  Prefix: " . substr($token, 0, 10) . "...\n";
            echo "  Length: " . strlen($token) . " characters\n";
            
            // Check if it's NOT a demo token
            if (str_starts_with($token, 'demo_')) {
                echo "  ❌ ERROR: Still receiving demo token!\n";
            } else {
                echo "  ✅ SUCCESS: Production token confirmed!\n";
            }
        }
        
        // Check token type
        if (isset($data['type'])) {
            echo "  Type: " . $data['type'] . " " . ($data['type'] === 'stream_production' ? '✅' : '❌') . "\n";
        }
        
        // Check WebSocket URL
        if (isset($data['websocket_url'])) {
            echo "  WebSocket URL: " . $data['websocket_url'] . "\n";
        }
        
        // Check expiration
        if (isset($data['expires_at'])) {
            echo "  Expires: " . date('Y-m-d H:i:s', $data['expires_at']) . "\n";
        }
        
        echo "\n📋 Full Response:\n";
        echo json_encode($data, JSON_PRETTY_PRINT) . "\n";
        
    } else {
        echo "❌ ERROR: Token generation failed!\n";
        echo "Status Code: " . $response->status() . "\n";
        echo "Error: " . json_encode($response->data(), JSON_PRETTY_PRINT) . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ EXCEPTION: " . $e->getMessage() . "\n";
    echo "Class: " . get_class($e) . "\n";
}

echo "\n" . str_repeat('=', 60) . "\n";
echo "Test completed. Expected result: JWT production token\n";
echo "If you see 'demo_' prefix, the fix didn't work properly.\n";