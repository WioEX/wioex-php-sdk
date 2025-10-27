<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Wioex\SDK\Client;
use Wioex\SDK\Config;

/**
 * WebSocket Integration Example
 *
 * This example demonstrates how to use the enhanced WebSocket functionality
 * including token management, connection status monitoring, and real-time streaming.
 */

// Initialize the WioEX SDK
$config = Config::create([
    'api_key' => 'your_api_key_here',
    'base_url' => 'https://api.wioex.com',
    'timeout' => 30,
    'cache' => [
        'default' => 'file',
        'file' => [
            'cache_dir' => __DIR__ . '/cache',
        ]
    ]
]);

$client = new Client($config);

echo "=== WioEX WebSocket Integration Example ===\n\n";

try {
    // 1. Get streaming token with enhanced features
    echo "1. Getting streaming token...\n";
    $tokenResponse = $client->streaming()->getStreamingToken();
    echo "✓ Token obtained: " . substr($tokenResponse['token'], 0, 20) . "...\n";
    echo "✓ Expires at: " . $tokenResponse['expires_at'] . "\n\n";

    // 2. Validate token
    echo "2. Validating token...\n";
    $isValid = $client->streaming()->validateToken();
    echo $isValid ? "✓ Token is valid\n" : "✗ Token is invalid\n";

    // Get token expiry information
    $expiry = $client->streaming()->getTokenExpiry();
    echo "✓ Token expires in: " . $expiry['expires_in'] . " seconds\n";
    echo "✓ Expires at: " . $expiry['expires_at'] . "\n\n";

    // 3. Get WebSocket connection URL
    echo "3. Getting WebSocket URL...\n";
    $websocketUrl = $client->streaming()->getWebSocketUrl();
    echo "✓ WebSocket URL: " . $websocketUrl . "\n\n";

    // 4. Get available channels
    echo "4. Available streaming channels:\n";
    $channels = $client->streaming()->getAvailableChannels();
    foreach ($channels as $category => $channelList) {
        echo "  {$category}:\n";
        foreach ($channelList as $channel) {
            echo "    - {$channel['name']}: {$channel['description']}\n";
        }
    }
    echo "\n";

    // 5. Check connection status
    echo "5. Checking connection status...\n";
    $status = $client->streaming()->getConnectionStatus();
    echo "✓ Status: " . $status['status'] . "\n";
    echo "✓ Connected: " . ($status['connected'] ? 'Yes' : 'No') . "\n";
    echo "✓ Last ping: " . ($status['last_ping'] ?? 'Never') . "\n\n";

    // 6. Ping WebSocket connection
    echo "6. Testing WebSocket connection...\n";
    $pingResult = $client->streaming()->ping();
    echo "✓ Ping successful: " . ($pingResult['success'] ? 'Yes' : 'No') . "\n";
    echo "✓ Response time: " . $pingResult['response_time'] . "ms\n\n";

    // 7. Get usage statistics
    echo "7. Usage statistics:\n";
    $stats = $client->streaming()->getUsageStats();
    echo "✓ Total connections: " . $stats['total_connections'] . "\n";
    echo "✓ Messages sent: " . $stats['messages_sent'] . "\n";
    echo "✓ Messages received: " . $stats['messages_received'] . "\n";
    echo "✓ Connection uptime: " . $stats['uptime_seconds'] . " seconds\n";
    echo "✓ Data transferred: " . number_format($stats['bytes_transferred']) . " bytes\n\n";

    // 8. Example WebSocket client implementation
    echo "8. Example WebSocket client setup:\n";
    echo "```javascript\n";
    echo "const ws = new WebSocket('{$websocketUrl}');\n\n";
    echo "ws.onopen = function(event) {\n";
    echo "    console.log('Connected to WioEX WebSocket');\n";
    echo "    \n";
    echo "    // Authenticate with token\n";
    echo "    ws.send(JSON.stringify({\n";
    echo "        action: 'authenticate',\n";
    echo "        token: 'your_token_here'\n";
    echo "    }));\n";
    echo "    \n";
    echo "    // Subscribe to real-time stock quotes\n";
    echo "    ws.send(JSON.stringify({\n";
    echo "        action: 'subscribe',\n";
    echo "        symbols: ['AAPL', 'GOOGL', 'MSFT']\n";
    echo "    }));\n";
    echo "};\n\n";
    echo "ws.onmessage = function(event) {\n";
    echo "    const data = JSON.parse(event.data);\n";
    echo "    \n";
    echo "    // WioEX provides unified format regardless of data source\n";
    echo "    if (data.type === 'ticker') {\n";
    echo "        console.log('Stock Update:', {\n";
    echo "            symbol: data.symbol,\n";
    echo "            price: data.price,\n";
    echo "            change: data.change,\n";
    echo "            volume: data.volume\n";
    echo "        });\n";
    echo "    }\n";
    echo "};\n\n";
    echo "ws.onclose = function(event) {\n";
    echo "    console.log('WebSocket connection closed');\n";
    echo "};\n\n";
    echo "ws.onerror = function(error) {\n";
    echo "    console.error('WebSocket error:', error);\n";
    echo "};\n";
    echo "```\n\n";

    // 9. Refresh token example
    echo "9. Token refresh example:\n";
    $refreshed = $client->streaming()->refreshToken();
    echo "✓ Token refreshed successfully\n";
    echo "✓ New token: " . substr($refreshed['token'], 0, 20) . "...\n";
    echo "✓ New expiry: " . $refreshed['expires_at'] . "\n\n";

    // 10. Advanced usage with error handling
    echo "10. Advanced usage patterns:\n";
    echo "\n";
    echo "// Automatic token refresh\n";
    echo "class WebSocketManager {\n";
    echo "    private \$client;\n";
    echo "    private \$ws;\n";
    echo "    \n";
    echo "    public function __construct(\$client) {\n";
    echo "        \$this->client = \$client;\n";
    echo "    }\n";
    echo "    \n";
    echo "    public function connect() {\n";
    echo "        // Check if token needs refresh\n";
    echo "        \$expiry = \$this->client->streaming()->getTokenExpiry();\n";
    echo "        if (\$expiry['expires_in'] < 300) { // Refresh if < 5 minutes\n";
    echo "            \$this->client->streaming()->refreshToken();\n";
    echo "        }\n";
    echo "        \n";
    echo "        \$url = \$this->client->streaming()->getWebSocketUrl();\n";
    echo "        // Connect to WebSocket...\n";
    echo "    }\n";
    echo "    \n";
    echo "    public function subscribeToStocks(array \$symbols) {\n";
    echo "        // Subscribe to real-time stock data\n";
    echo "    }\n";
    echo "}\n\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";

    if ($e instanceof \Wioex\SDK\Exceptions\AuthenticationException) {
        echo "💡 Check your API key configuration\n";
    } elseif ($e instanceof \Wioex\SDK\Exceptions\RateLimitException) {
        echo "💡 Rate limit exceeded, please wait before retrying\n";
    }
}

echo "\n=== Example completed ===\n";
echo "\n💡 Tips:\n";
echo "- Store tokens securely and refresh them before expiry\n";
echo "- Monitor connection status and implement reconnection logic\n";
echo "- Use ping() to verify connection health\n";
echo "- Subscribe only to channels you need to minimize bandwidth\n";
echo "- Implement proper error handling for production use\n";
