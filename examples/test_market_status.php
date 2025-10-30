<?php

/**
 * Market Status Example
 *
 * Demonstrates unified market status endpoint that supports both
 * authenticated (with API key) and public (without API key) access
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Wioex\SDK\WioexClient;

echo "\n";
echo "╔══════════════════════════════════════════════════════╗\n";
echo "║         WioEX Market Status - Unified Endpoint       ║\n";
echo "╚══════════════════════════════════════════════════════╝\n";
echo "\n";

// ============================================
// Method 1: Public Access (No API Key)
// ============================================
echo "🌐 Method 1: Public Access (No API Key Required)\n";
echo str_repeat('─', 54) . "\n";

$publicClient = new WioexClient(['api_key' => '']);
$status = $publicClient->markets()->status();

if ($status->successful()) {
    $data = $status->data();
    $nyse = $data['markets']['nyse'];

    echo "✅ Success!\n";
    echo "  NYSE Status: " . $nyse['status'] . "\n";
    echo "  Is Open: " . ($nyse['is_open'] ? 'Yes' : 'No') . "\n";
    echo "  Market Time: " . $nyse['market_time'] . "\n";
    echo "  Regular Hours: " . $nyse['hours']['regular']['open'] . " - " . $nyse['hours']['regular']['close'] . " ET\n";
    echo "  Cost: FREE (no credit)\n";
    echo "  Rate Limit: 100 requests/minute per IP\n";
} else {
    echo "❌ Failed to fetch market status\n";
}

echo "\n";

// ============================================
// Method 2: Authenticated Access (With API Key)
// ============================================
echo "🔐 Method 2: Authenticated Access (With API Key)\n";
echo str_repeat('─', 54) . "\n";

$client = new WioexClient(['api_key' => 'your-api-key-here']);
$status = $client->markets()->status();

if ($status->successful()) {
    $data = $status->data();
    $nasdaq = $data['markets']['nasdaq'];

    echo "✅ Success!\n";
    echo "  NASDAQ Status: " . $nasdaq['status'] . "\n";
    echo "  Is Open: " . ($nasdaq['is_open'] ? 'Yes' : 'No') . "\n";
    echo "  Market Time: " . $nasdaq['market_time'] . "\n";
    echo "  Next Change: " . $nasdaq['next_change'] . "\n";
    echo "  Cost: 1 credit\n";
    echo "  Rate Limit: Based on API plan\n";
} else {
    echo "❌ Failed to fetch market status\n";
}

echo "\n";

// ============================================
// Key Features
// ============================================
echo "✨ Key Features:\n";
echo str_repeat('─', 54) . "\n";
echo "  • Same endpoint (/v2/market/status) for both modes\n";
echo "  • Automatically detects if API key is provided\n";
echo "  • With API key: Uses credit, no rate limit, tracking\n";
echo "  • Without API key: Free, 100/min rate limit, no tracking\n";
echo "\n";

// ============================================
// Usage Recommendation
// ============================================
echo "💡 Usage Recommendations:\n";
echo str_repeat('─', 54) . "\n";
echo "  • Use WITHOUT API key for frontend/client-side apps\n";
echo "  • Use WITHOUT API key to avoid exposing credentials\n";
echo "  • Use WITH API key for backend applications\n";
echo "  • Use WITH API key when you need usage tracking\n";
echo "\n";

// ============================================
// Frontend JavaScript Example
// ============================================
echo "🌐 Direct Frontend Access (JavaScript):\n";
echo str_repeat('─', 54) . "\n";
echo <<<'JS'
// No API key needed for public access
fetch('https://api.wioex.com/market/status')
  .then(response => response.json())
  .then(data => {
    const nyse = data.markets.nyse;
    console.log('NYSE is', nyse.is_open ? 'open' : 'closed');
    console.log('Status:', nyse.status);
    console.log('Market Time:', nyse.market_time);
  });

JS;
echo "\n";
