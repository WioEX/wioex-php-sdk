<?php

/**
 * WioEX PHP SDK - Streaming Example (Updated for v2.0.0)
 * 
 * IMPORTANT: WebSocket streaming infrastructure is currently under development.
 * This example shows current capabilities and alternative approaches.
 */

require __DIR__ . '/../vendor/autoload.php';

use Wioex\SDK\WioexClient;
use Wioex\SDK\Exceptions\AuthenticationException;
use Wioex\SDK\Exceptions\ValidationException;

echo "=== WioEX PHP SDK - Streaming Example (v2.0.0) ===\n\n";

// Initialize client with your API key
$client = new WioexClient([
    'api_key' => 'your-api-key-here'
]);

echo "⚠️  STREAMING STATUS UPDATE ⚠️\n";
echo "WebSocket streaming infrastructure is under development.\n";
echo "Token generation works, but WebSocket connections are not yet operational.\n";
echo "Use the polling alternative below for real-time-like data access.\n\n";

try {
    // 1. WORKING: Get WebSocket authentication token
    echo "1. STREAMING TOKEN GENERATION (✅ Working)\n";
    echo str_repeat('-', 50) . "\n";
    
    $tokenResponse = $client->streaming()->getToken();

    if ($tokenResponse->successful()) {
        echo "✅ Token acquired successfully!\n\n";

        $data = $tokenResponse->data();
        echo "Token Response Data:\n";
        
        if (isset($data['token'])) {
            $maskedToken = substr($data['token'], 0, 10) . '...' . substr($data['token'], -10);
            echo "  Authentication Token: " . $maskedToken . "\n";
        }
        
        if (isset($data['expires_at'])) {
            echo "  Expires At: " . date('Y-m-d H:i:s', $data['expires_at']) . "\n";
        }
        
        if (isset($data['expires_in'])) {
            echo "  Valid For: " . $data['expires_in'] . " seconds\n";
        }
        
        if (isset($data['token_type'])) {
            echo "  Token Type: " . $data['token_type'] . "\n";
        }

        // Check for WebSocket URL (currently not included)
        if (isset($data['websocket_url'])) {
            echo "  WebSocket URL: " . $data['websocket_url'] . "\n";
        } else {
            echo "  ⚠️  WebSocket URL: Not provided (under development)\n";
        }

    } else {
        echo "❌ Failed to get streaming token\n";
        echo "Status: " . $tokenResponse->status() . "\n";
        echo "Error: " . $tokenResponse->json() . "\n";
        exit(1);
    }

} catch (AuthenticationException $e) {
    echo "❌ Authentication failed: " . $e->getMessage() . "\n";
    echo "   Check your API key and streaming permissions\n";
    exit(1);
} catch (ValidationException $e) {
    echo "❌ Validation error: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "❌ Unexpected error: " . $e->getMessage() . "\n";
    exit(1);
}

// 2. ALTERNATIVE: High-frequency polling for real-time data
echo "\n\n2. REAL-TIME ALTERNATIVE: POLLING APPROACH (✅ Working)\n";
echo str_repeat('-', 50) . "\n";

echo "Since WebSocket streaming is under development, use this polling approach:\n\n";

class RealTimeDataPoller {
    private $client;
    private $symbols;
    private $interval;
    private $running = false;
    
    public function __construct(WioexClient $client, array $symbols, int $intervalSeconds = 2) {
        $this->client = $client;
        $this->symbols = $symbols;
        $this->interval = $intervalSeconds;
    }
    
    public function start(int $maxUpdates = 10) {
        $this->running = true;
        $updateCount = 0;
        
        echo "Starting real-time polling for: " . implode(', ', $this->symbols) . "\n";
        echo "Update interval: {$this->interval} seconds\n";
        echo "Max updates: {$maxUpdates}\n\n";
        
        while ($this->running && $updateCount < $maxUpdates) {
            try {
                $quotes = $this->client->stocks()->quote(implode(',', $this->symbols));
                
                if ($quotes->successful()) {
                    $this->displayUpdates($quotes['tickers']);
                    $updateCount++;
                } else {
                    echo "❌ Failed to get quotes: " . $quotes->status() . "\n";
                }
                
                if ($updateCount < $maxUpdates) {
                    sleep($this->interval);
                }
                
            } catch (Exception $e) {
                echo "❌ Error: " . $e->getMessage() . "\n";
                sleep($this->interval * 2); // Backoff on error
            }
        }
        
        echo "\nPolling completed after {$updateCount} updates.\n";
    }
    
    private function displayUpdates(array $tickers) {
        $timestamp = date('H:i:s');
        echo "[{$timestamp}] Market Updates:\n";
        
        foreach ($tickers as $ticker) {
            $price = $ticker['market']['price'];
            $change = $ticker['market']['change']['value'];
            $changePercent = $ticker['market']['change']['percent'];
            
            $indicator = $change >= 0 ? '📈' : '📉';
            $sign = $change >= 0 ? '+' : '';
            
            echo "  {$indicator} {$ticker['ticker']}: \${$price} ({$sign}\${$change}, {$sign}{$changePercent}%)\n";
        }
        echo "\n";
    }
    
    public function stop() {
        $this->running = false;
    }
}

// Demo the polling approach
echo "Demo: Real-time polling for AAPL, TSLA, MSFT\n";
echo str_repeat('-', 40) . "\n";

$poller = new RealTimeDataPoller($client, ['AAPL', 'TSLA', 'MSFT'], 3);
$poller->start(5); // Run for 5 updates

// 3. FUTURE: WebSocket connection (when available)
echo "\n3. FUTURE WEBSOCKET IMPLEMENTATION (🚧 Under Development)\n";
echo str_repeat('-', 50) . "\n";

echo "When WebSocket streaming becomes available, usage will be:\n\n";

echo "```php\n";
echo "// Future implementation (not yet working)\n";
echo "\$tokenResponse = \$client->streaming()->getToken();\n";
echo "\$token = \$tokenResponse['token'];\n";
echo "\$wsUrl = \$tokenResponse['websocket_url']; // Not yet included\n";
echo "\n";
echo "// WebSocket connection (pseudo-code)\n";
echo "\$ws = new \\ReactSocket\\SocketIo\\Client(\$wsUrl);\n";
echo "\n";
echo "\$ws->on('connect', function() use (\$ws, \$token) {\n";
echo "    // Authenticate\n";
echo "    \$ws->emit('auth', ['token' => \$token]);\n";
echo "    \n";
echo "    // Subscribe to symbols\n";
echo "    \$ws->emit('subscribe', [\n";
echo "        'symbols' => ['AAPL', 'TSLA', 'MSFT'],\n";
echo "        'data_types' => ['quotes', 'trades']\n";
echo "    ]);\n";
echo "});\n";
echo "\n";
echo "\$ws->on('quote', function(\$data) {\n";
echo "    echo \"Real-time quote: {\$data['symbol']} = \${\$data['price']}\\n\";\n";
echo "});\n";
echo "\n";
echo "\$ws->on('trade', function(\$data) {\n";
echo "    echo \"Trade: {\$data['symbol']} - {\$data['size']} @ \${\$data['price']}\\n\";\n";
echo "});\n";
echo "\n";
echo "\$ws->connect();\n";
echo "```\n\n";

echo "=== Summary ===\n";
echo "✅ Token generation: Fully functional\n";
echo "🚧 WebSocket connections: Under development\n";
echo "✅ Polling alternative: Recommended for now\n";
echo "📢 Stay updated: Monitor for streaming service announcements\n\n";

echo "=== Example completed ===\n";
