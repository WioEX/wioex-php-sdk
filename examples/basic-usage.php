<?php

/**
 * WioEX PHP SDK - Basic Usage Example
 *
 * This example demonstrates the basic usage patterns of the WioEX PHP SDK.
 */

require __DIR__ . '/../vendor/autoload.php';

use Wioex\SDK\WioexClient;

// Initialize client with your API key
$client = new WioexClient([
    'api_key' => 'your-api-key-here'
]);

echo "=== WioEX PHP SDK - Basic Usage Example ===\n\n";

// 1. Get stock data
echo "1. Getting stock data for AAPL...\n";
$stock = $client->stocks()->quote('AAPL');

if ($stock->successful()) {
    $ticker = $stock['tickers'][0];
    echo "Symbol: {$ticker['ticker']}\n";
    echo "Price: $" . $ticker['market']['price'] . "\n";
    echo "Change: " . $ticker['market']['change']['percent'] . "%\n";
} else {
    echo "Failed to get stock data\n";
}

echo "\n";

// 2. Search for stocks
echo "2. Searching for Apple stocks...\n";
$results = $client->stocks()->search('Apple');

if ($results->successful()) {
    echo "Found " . count($results['data']) . " results\n";
    foreach (array_slice($results['data'], 0, 3) as $item) {
        echo "  - {$item['symbol']}: {$item['name']}\n";
    }
}

echo "\n";

// 3. Get market movers
echo "3. Getting top gainers...\n";
$gainers = $client->screens()->gainers();

if ($gainers->successful()) {
    echo "Top 3 gainers:\n";
    foreach (array_slice($gainers['data'], 0, 3) as $gainer) {
        echo "  - {$gainer['symbol']}: +{$gainer['change_percent']}%\n";
    }
}

echo "\n";

// 4. Get news
echo "4. Getting latest news for TSLA...\n";
$news = $client->news()->latest('TSLA');

if ($news->successful()) {
    echo "Latest headlines:\n";
    foreach (array_slice($news['articles'], 0, 3) as $article) {
        echo "  - {$article['title']}\n";
    }
}

echo "\n";

// 5. Check account balance
echo "5. Checking account balance...\n";
$balance = $client->account()->balance();

if ($balance->successful()) {
    echo "Credits remaining: " . $balance['credits'] . "\n";
}

echo "\n";

// 6. Get currency exchange rates
echo "6. Getting USD to EUR exchange rate...\n";
$rates = $client->currency()->baseUsd();

if ($rates->successful()) {
    echo "1 USD = " . $rates['rates']['EUR'] . " EUR\n";
}

echo "\n=== Example completed ===\n";
