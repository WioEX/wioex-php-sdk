<?php

/**
 * Test Stock Data with Integrated Signals
 *
 * This example demonstrates how stock endpoints automatically include signal data
 * when available for the requested ticker.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Wioex\SDK\WioexClient;

// Initialize client
$client = new WioexClient([
    'api_key' => 'your-api-key-here'
]);

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "  Stock Data with Integrated Signals\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// Test 1: Get stock quote with signal (SOUN has an active signal)
echo "📊 Test 1: Get stock quote for SOUN (has signal)\n";
echo "─────────────────────────────────────────────\n";
try {
    $response = $client->stocks()->quote('SOUN');
    $data = $response->data();

    if (isset($data['tickers']) && count($data['tickers']) > 0) {
        $stock = $data['tickers'][0];

        echo "Ticker: {$stock['ticker']}\n";
        echo "Price: \${$stock['market']['price']}\n";
        echo "Change: {$stock['market']['change']['percent']}%\n\n";

        // Check if signal is included
        if (isset($stock['signal'])) {
            echo "🔔 SIGNAL DETECTED:\n";
            echo "   Type: {$stock['signal']['signal_type']}\n";
            echo "   Entry Price: \${$stock['signal']['entry_price']}\n";
            echo "   Target Price: \$" . ($stock['signal']['target_price'] ?? 'N/A') . "\n";
            echo "   Stop Loss: \$" . ($stock['signal']['stop_loss'] ?? 'N/A') . "\n";
            echo "   Confidence: {$stock['signal']['confidence']}%\n";
            echo "   Timeframe: {$stock['signal']['timeframe']}\n";
            echo "   Reason: {$stock['signal']['reason']}\n\n";
        } else {
            echo "ℹ️  No active signal for this stock\n\n";
        }
    }
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n\n";
}

// Test 2: Get stock info with signal
echo "📊 Test 2: Get detailed stock info for SOUN\n";
echo "─────────────────────────────────────────────\n";
try {
    $response = $client->stocks()->info('SOUN');
    $data = $response->data();

    echo "Company: " . ($data['info']['companyName'] ?? 'N/A') . "\n";
    echo "Sector: " . ($data['info']['sector'] ?? 'N/A') . "\n";
    echo "Market Cap: " . ($data['info']['marketCap'] ?? 'N/A') . "\n\n";

    // Check if signal is included
    if (isset($data['signal'])) {
        echo "🔔 TRADING SIGNAL:\n";
        echo "   Type: {$data['signal']['signal_type']}\n";
        echo "   Entry: \${$data['signal']['entry_price']}\n";
        echo "   Target: \$" . ($data['signal']['target_price'] ?? 'N/A') . "\n";
        echo "   Stop Loss: \$" . ($data['signal']['stop_loss'] ?? 'N/A') . "\n";
        echo "   Confidence: {$data['signal']['confidence']}%\n";
        echo "   Source: {$data['signal']['source']}\n";
        echo "   Created: {$data['signal']['created_at']}\n\n";
    } else {
        echo "ℹ️  No active signal for this stock\n\n";
    }
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n\n";
}

// Test 3: Get stock quote for a stock without signal (AAPL)
echo "📊 Test 3: Get stock quote for AAPL (no signal)\n";
echo "─────────────────────────────────────────────\n";
try {
    $response = $client->stocks()->quote('AAPL');
    $data = $response->data();

    if (isset($data['tickers']) && count($data['tickers']) > 0) {
        $stock = $data['tickers'][0];

        echo "Ticker: {$stock['ticker']}\n";
        echo "Price: \${$stock['market']['price']}\n";
        echo "Change: {$stock['market']['change']['percent']}%\n";

        if (isset($stock['signal'])) {
            echo "\n🔔 Signal: {$stock['signal']['signal_type']} @ \${$stock['signal']['entry_price']}\n\n";
        } else {
            echo "\nℹ️  No active signal\n\n";
        }
    }
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n\n";
}

// Test 4: Multiple stocks - some with signals, some without
echo "📊 Test 4: Get multiple stocks (SOUN, AAPL, TSLA)\n";
echo "─────────────────────────────────────────────\n";
try {
    $response = $client->stocks()->quote('SOUN,AAPL,TSLA');
    $data = $response->data();

    if (isset($data['tickers'])) {
        foreach ($data['tickers'] as $stock) {
            echo "• {$stock['ticker']}: \${$stock['market']['price']}";
            if (isset($stock['signal'])) {
                echo " [SIGNAL: {$stock['signal']['signal_type']}]";
            }
            echo "\n";
        }
        echo "\n";
    }
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n\n";
}

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "  Tests completed!\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "\n";
echo "💡 Key Points:\n";
echo "   • Signals are automatically included in stock data\n";
echo "   • No extra API call needed\n";
echo "   • Signal info only appears if an active signal exists\n";
echo "   • Works with both quote() and info() methods\n";
echo "   • Supports multiple stocks in a single request\n";
