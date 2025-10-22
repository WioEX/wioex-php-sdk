<?php

/**
 * WioEX PHP SDK - Streaming Example
 *
 * This example demonstrates how to get a WebSocket authentication token
 * for real-time streaming of market data.
 */

require __DIR__ . '/../vendor/autoload.php';

use Wioex\SDK\WioexClient;
use Wioex\SDK\Exceptions\AuthenticationException;
use Wioex\SDK\Exceptions\ValidationException;

echo "=== WioEX PHP SDK - Streaming Example ===\n\n";

// Initialize client with your API key
$client = new WioexClient([
    'api_key' => 'your-api-key-here'
]);

try {
    // Get WebSocket authentication token
    echo "1. Getting WebSocket streaming token...\n";
    $tokenResponse = $client->streaming()->getToken();

    if ($tokenResponse->successful()) {
        echo "✅ Token acquired successfully!\n\n";

        // Extract token and connection details
        $data = $tokenResponse->data();

        if (isset($data['token'])) {
            echo "Authentication Token: " . $data['token'] . "\n";
        }

        if (isset($data['websocket_url'])) {
            echo "WebSocket URL: " . $data['websocket_url'] . "\n";
        }

        if (isset($data['expires_at'])) {
            echo "Token Expires: " . $data['expires_at'] . "\n";
        }

        if (isset($data['expires_in'])) {
            echo "Valid for: " . $data['expires_in'] . " seconds\n";
        }

        echo "\n";
        echo "💡 Use this token to authenticate your WebSocket connection:\n";
        echo "   1. Connect to the WebSocket URL\n";
        echo "   2. Send authentication message with the token\n";
        echo "   3. Subscribe to real-time data streams\n";
    } else {
        echo "❌ Failed to get streaming token\n";
        echo "Status: " . $tokenResponse->status() . "\n";
        echo "Response: " . $tokenResponse->json() . "\n";
    }
} catch (AuthenticationException $e) {
    echo "❌ Authentication failed: " . $e->getMessage() . "\n";
    echo "   Check your API key and make sure it has streaming permissions\n";
} catch (ValidationException $e) {
    echo "❌ Validation error: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "❌ Unexpected error: " . $e->getMessage() . "\n";
}

echo "\n";

// Example WebSocket usage (pseudo-code)
echo "=== Example WebSocket Usage ===\n";
echo "```javascript\n";
echo "// After getting the token from PHP SDK\n";
echo "const ws = new WebSocket(websocket_url);\n";
echo "\n";
echo "ws.onopen = function() {\n";
echo "    // Authenticate with the token\n";
echo "    ws.send(JSON.stringify({\n";
echo "        'action': 'auth',\n";
echo "        'token': token_from_php_sdk\n";
echo "    }));\n";
echo "};\n";
echo "\n";
echo "ws.onmessage = function(event) {\n";
echo "    const data = JSON.parse(event.data);\n";
echo "    console.log('Real-time data:', data);\n";
echo "};\n";
echo "```\n";

echo "\n=== Example completed ===\n";
