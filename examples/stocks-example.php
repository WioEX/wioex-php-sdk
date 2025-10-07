<?php

/**
 * WioEX PHP SDK - Stocks Example
 *
 * This example demonstrates comprehensive stock operations.
 */

require __DIR__ . '/../vendor/autoload.php';

use Wioex\SDK\WioexClient;

$client = new WioexClient([
    'api_key' => 'your-api-key-here'
]);

echo "=== WioEX PHP SDK - Stocks Example ===\n\n";

// 1. Search stocks
echo "1. SEARCH STOCKS\n";
echo str_repeat('-', 50) . "\n";

$searchResults = $client->stocks()->search('tech');
echo "Search results for 'tech':\n";
foreach (array_slice($searchResults['data'], 0, 5) as $stock) {
    echo sprintf("  %-6s %-30s %s\n", $stock['symbol'], $stock['name'], $stock['exchange']);
}
echo "\n";

// 2. Get multiple stocks
echo "2. GET MULTIPLE STOCKS\n";
echo str_repeat('-', 50) . "\n";

$stocks = $client->stocks()->get('AAPL,GOOGL,MSFT,TSLA');
echo "Current prices:\n";
foreach ($stocks['data'] as $stock) {
    echo sprintf("  %-6s $%-8s %+.2f%%\n",
        $stock['symbol'],
        number_format($stock['price'], 2),
        $stock['change_percent']
    );
}
echo "\n";

// 3. Get detailed stock info
echo "3. DETAILED STOCK INFO\n";
echo str_repeat('-', 50) . "\n";

$info = $client->stocks()->info('AAPL');
echo "Apple Inc. (AAPL):\n";
echo "  Company: {$info['company_name']}\n";
echo "  Sector: {$info['sector']}\n";
echo "  Market Cap: $" . number_format($info['market_cap']) . "\n";
echo "  P/E Ratio: {$info['pe_ratio']}\n";
echo "  52W High: $" . number_format($info['week_52_high'], 2) . "\n";
echo "  52W Low: $" . number_format($info['week_52_low'], 2) . "\n";
echo "\n";

// 4. Get historical data
echo "4. HISTORICAL PRICE DATA\n";
echo str_repeat('-', 50) . "\n";

$timeline = $client->stocks()->timeline('AAPL', [
    'interval' => '1m',
    'size' => 10,
    'orderBy' => 'DESC'
]);

echo "AAPL - Last 10 trading days:\n";
foreach ($timeline['data'] as $point) {
    echo sprintf("  %s: $%-8s (Vol: %s)\n",
        $point['date'],
        number_format($point['close'], 2),
        number_format($point['volume'])
    );
}
echo "\n";

// 5. Get financials
echo "5. FINANCIAL DATA\n";
echo str_repeat('-', 50) . "\n";

$financials = $client->stocks()->financials('AAPL', 'USD');
echo "AAPL Financial Metrics:\n";
echo "  Revenue: $" . number_format($financials['revenue']) . "\n";
echo "  Net Income: $" . number_format($financials['net_income']) . "\n";
echo "  EPS: $" . $financials['eps'] . "\n";
echo "  Debt to Equity: " . $financials['debt_to_equity'] . "\n";
echo "\n";

// 6. Get market heatmap
echo "6. MARKET HEATMAP\n";
echo str_repeat('-', 50) . "\n";

$heatmap = $client->stocks()->heatmap('nasdaq100');
echo "NASDAQ 100 Top Movers:\n";
foreach (array_slice($heatmap['data'], 0, 5) as $stock) {
    $indicator = $stock['change_percent'] > 0 ? '📈' : '📉';
    echo sprintf("  %s %-6s %+.2f%%\n", $indicator, $stock['symbol'], $stock['change_percent']);
}
echo "\n";

// 7. Stock screening
echo "7. STOCK SCREENING\n";
echo str_repeat('-', 50) . "\n";

echo "Most Active Stocks:\n";
$active = $client->screens()->active(5);
foreach ($active['data'] as $stock) {
    echo sprintf("  %-6s Volume: %s\n", $stock['symbol'], number_format($stock['volume']));
}

echo "\nTop Gainers:\n";
$gainers = $client->screens()->gainers();
foreach (array_slice($gainers['data'], 0, 5) as $stock) {
    echo sprintf("  %-6s +%.2f%%\n", $stock['symbol'], $stock['change_percent']);
}

echo "\nTop Losers:\n";
$losers = $client->screens()->losers();
foreach (array_slice($losers['data'], 0, 5) as $stock) {
    echo sprintf("  %-6s %.2f%%\n", $stock['symbol'], $stock['change_percent']);
}

echo "\n=== Example completed ===\n";
