<?php

/**
 * WioEX PHP SDK - Stocks Example
 *
 * This example demonstrates comprehensive stock operations.
 */

require __DIR__ . '/../vendor/autoload.php';

use Wioex\SDK\WioexClient;
use Wioex\SDK\Enums\TimelineInterval;
use Wioex\SDK\Enums\SortOrder;
use Wioex\SDK\Enums\ScreenType;
use Wioex\SDK\Enums\MarketIndex;

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

$stocks = $client->stocks()->quote('AAPL,GOOGL,MSFT,TSLA');
echo "Current prices:\n";
foreach ($stocks->getInstruments() as $stock) {
    echo sprintf(
        "  %-6s $%-8s %+.2f%%\n",
        $stock['symbol'],
        number_format($stock['price']['current'], 2),
        $stock['change']['percent']
    );
}
echo "\n";

// 3. Get detailed stock info
echo "3. DETAILED STOCK INFO\n";
echo str_repeat('-', 50) . "\n";

$info = $client->stocks()->info('AAPL');
$infoData = $info->getCoreData();
echo "Apple Inc. (AAPL):\n";
echo "  Company: {$infoData['company_name']}\n";
echo "  Sector: {$infoData['sector']}\n";
echo "  Market Cap: $" . number_format($infoData['market_cap']) . "\n";
echo "  P/E Ratio: {$infoData['pe_ratio']}\n";
echo "  52W High: $" . number_format($infoData['week_52_high'], 2) . "\n";
echo "  52W Low: $" . number_format($infoData['week_52_low'], 2) . "\n";
echo "\n";

// 4. Get historical data
echo "4. HISTORICAL PRICE DATA\n";
echo str_repeat('-', 50) . "\n";

$timeline = $client->stocks()->timeline('AAPL', [
    'interval' => TimelineInterval::ONE_MINUTE,  // Using ENUM for type safety
    'size' => 10,
    'orderBy' => SortOrder::DESCENDING  // Using ENUM instead of 'DESC'
]);

// Note: ENUMs provide better IDE support and prevent typos
// Alternative: Use convenient method $client->stocks()->timelineMinute('AAPL', ['size' => 10])

echo "AAPL - Last 10 data points:\n";
$timelineData = $timeline->getCoreData();
foreach ($timelineData['timeline'] as $point) {
    echo sprintf(
        "  %s: $%-8s (Vol: %s)\n",
        $point['datetime'],
        number_format($point['close'], 2),
        number_format($point['volume'])
    );
}
echo "\n";

// 5. Get financials
echo "5. FINANCIAL DATA\n";
echo str_repeat('-', 50) . "\n";

$financials = $client->stocks()->financials('AAPL', 'USD');
$financialData = $financials->getCoreData();
echo "AAPL Financial Metrics:\n";
echo "  Revenue: $" . number_format($financialData['revenue']) . "\n";
echo "  Net Income: $" . number_format($financialData['net_income']) . "\n";
echo "  EPS: $" . $financialData['eps'] . "\n";
echo "  Debt to Equity: " . $financialData['debt_to_equity'] . "\n";
echo "\n";

// 6. Get market heatmap
echo "6. MARKET HEATMAP\n";
echo str_repeat('-', 50) . "\n";

$heatmap = $client->stocks()->heatmap('nasdaq100');
$heatmapData = $heatmap->getCoreData();
echo "NASDAQ 100 Top Movers:\n";
foreach (array_slice($heatmapData['data'], 0, 5) as $stock) {
    $indicator = $stock['change_percent'] > 0 ? '📈' : '📉';
    echo sprintf("  %s %-6s %+.2f%%\n", $indicator, $stock['symbol'], $stock['change_percent']);
}
echo "\n";

// 7. Stock screening (Enhanced with new runtime features)
echo "7. STOCK SCREENING (Enhanced)\n";
echo str_repeat('-', 50) . "\n";

// Traditional method
echo "Most Active Stocks:\n";
$active = $client->screens()->active(5);
$activeData = $active->getCoreData();
foreach ($activeData['data'] as $stock) {
    echo sprintf("  %-6s Volume: %s\n", $stock['symbol'], number_format($stock['volume']));
}

// Enhanced method with new parameters
echo "\nMost Active S&P 500 (Enhanced):\n";
$activeEnhanced = $client->screens()->active(
    limit: 5,
    sortOrder: SortOrder::DESCENDING,
    market: MarketIndex::SP500
);
$activeEnhancedData = $activeEnhanced->getCoreData();
foreach ($activeEnhancedData['data'] as $stock) {
    echo sprintf("  %-6s Volume: %s\n", $stock['symbol'], number_format($stock['volume']));
}

// Unified screen method demonstration
echo "\nTop Gainers (Unified Method):\n";
$gainersUnified = $client->screens()->screen(ScreenType::GAINERS, ['limit' => 5]);
$gainersData = $gainersUnified->getCoreData();
foreach ($gainersData['data'] as $stock) {
    echo sprintf("  %-6s +%.2f%%\n", $stock['symbol'], $stock['change_percent']);
}

echo "\nTop Losers (Enhanced):\n";
$losers = $client->screens()->losers(
    limit: 5,
    sortOrder: SortOrder::DESCENDING
);
$losersData = $losers->getCoreData();
foreach ($losersData['data'] as $stock) {
    echo sprintf("  %-6s %.2f%%\n", $stock['symbol'], $stock['change_percent']);
}

// Market sentiment analysis
echo "\nMarket Sentiment Analysis:\n";
$sentiment = $client->screens()->marketSentiment(MarketIndex::SP500, 30);
$sentimentData = $sentiment->getCoreData();
echo sprintf("  Sentiment: %s\n", $sentimentData['data']['sentiment']);
echo sprintf(
    "  Bullish: %.1f%% | Bearish: %.1f%%\n",
    $sentimentData['data']['metrics']['bullish_ratio'],
    $sentimentData['data']['metrics']['bearish_ratio']
);

echo "\n\n💡 Runtime Features Demonstrated:\n";
echo "✅ Enhanced method parameters (limit, sortOrder, market)\n";
echo "✅ Unified screen() method with ENUMs\n";
echo "✅ Smart market sentiment analysis\n";
echo "✅ Unified response format with getCoreData()\n";
echo "\n=== Example completed ===\n";
