<?php

/**
 * Test Signals API
 *
 * This example demonstrates how to use the Signals API to get active trading signals
 * and signal history.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Wioex\SDK\WioexClient;

// Initialize client
$client = new WioexClient([
    'api_key' => '65c8a165-f368-41d5-ab9b-b50cef65d5e1'
]);

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "  WioEX Trading Signals API Test\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// Test 1: Get all active signals
echo "📊 Test 1: Get all active signals\n";
echo "─────────────────────────────────────────────\n";
try {
    $response = $client->signals()->active();
    $data = $response->data();

    echo "Success: " . ($data['success'] ? 'YES' : 'NO') . "\n";
    echo "Count: " . $data['count'] . "\n\n";

    if ($data['count'] > 0) {
        foreach ($data['signals'] as $signal) {
            echo "  • {$signal['symbol']} ({$signal['signal_type']})\n";
            echo "    Entry: \${$signal['entry_price']}\n";
            echo "    Target: \$" . ($signal['target_price'] ?? 'N/A') . "\n";
            echo "    Stop Loss: \$" . ($signal['stop_loss'] ?? 'N/A') . "\n";
            echo "    Confidence: {$signal['confidence']}%\n";
            echo "    Timeframe: {$signal['timeframe']}\n\n";
        }
    }
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n\n";
}

// Test 2: Get signals for SOUN
echo "📊 Test 2: Get signals for SOUN\n";
echo "─────────────────────────────────────────────\n";
try {
    $response = $client->signals()->active(['symbol' => 'SOUN']);
    $data = $response->data();

    echo "Success: " . ($data['success'] ? 'YES' : 'NO') . "\n";
    echo "Count: " . $data['count'] . "\n\n";

    if ($data['count'] > 0) {
        foreach ($data['signals'] as $signal) {
            echo "  🔔 {$signal['symbol']} - {$signal['signal_type']} Signal\n";
            echo "     Entry Price: \${$signal['entry_price']}\n";
            echo "     Target Price: \$" . ($signal['target_price'] ?? 'N/A') . "\n";
            echo "     Stop Loss: \$" . ($signal['stop_loss'] ?? 'N/A') . "\n";
            echo "     Confidence: {$signal['confidence']}%\n";
            echo "     Timeframe: {$signal['timeframe']}\n";
            echo "     Source: {$signal['source']}\n";
            echo "     Reason: {$signal['reason']}\n";
            echo "     Created: {$signal['created_at']}\n\n";
        }
    }
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n\n";
}

// Test 3: Get BUY signals with high confidence
echo "📊 Test 3: Get BUY signals (confidence >= 80)\n";
echo "─────────────────────────────────────────────\n";
try {
    $response = $client->signals()->active([
        'signal_type' => 'BUY',
        'min_confidence' => 80
    ]);
    $data = $response->data();

    echo "Success: " . ($data['success'] ? 'YES' : 'NO') . "\n";
    echo "Count: " . $data['count'] . "\n\n";

    if ($data['count'] > 0) {
        foreach ($data['signals'] as $signal) {
            echo "  • {$signal['symbol']}: \${$signal['entry_price']} (Confidence: {$signal['confidence']}%)\n";
        }
        echo "\n";
    }
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n\n";
}

// Test 4: Get signal history
echo "📊 Test 4: Get signal history (last 30 days)\n";
echo "─────────────────────────────────────────────\n";
try {
    $response = $client->signals()->history(['days' => 30]);
    $data = $response->data();

    echo "Success: " . ($data['success'] ? 'YES' : 'NO') . "\n";
    echo "Count: " . $data['count'] . "\n\n";

    if ($data['count'] > 0) {
        foreach (array_slice($data['signals'], 0, 5) as $signal) {
            echo "  • {$signal['symbol']} ({$signal['signal_type']})\n";
            echo "    Trigger Type: {$signal['trigger_type']}\n";
            echo "    Triggered Price: \$" . ($signal['triggered_price'] ?? 'N/A') . "\n";
            echo "    Triggered At: " . ($signal['triggered_at'] ?? 'N/A') . "\n\n";
        }
    } else {
        echo "  No signal history found.\n\n";
    }
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n\n";
}

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "  Tests completed!\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
