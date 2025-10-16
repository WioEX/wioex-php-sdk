<?php

/**
 * WioEX PHP SDK - Advanced Timeline Example
 *
 * This example demonstrates the new timeline features including:
 * - Session-based filtering (regular, pre-market, after-hours, extended)
 * - Date-based filtering (started_date parameter)
 * - Different intervals (1min, 1day)
 * - Convenience methods for common use cases
 */

require __DIR__ . '/../vendor/autoload.php';

use Wioex\SDK\WioexClient;

$client = new WioexClient([
    'api_key' => 'your-api-key-here'
]);

echo "=== WioEX PHP SDK - Advanced Timeline Features ===\n\n";

// 1. Basic timeline usage
echo "1. BASIC TIMELINE DATA\n";
echo str_repeat('-', 50) . "\n";

$timeline = $client->stocks()->timeline('TSLA', [
    'interval' => '1day',
    'size' => 5,
    'orderBy' => 'DESC'
]);

echo "TSLA - Last 5 trading days:\n";
foreach ($timeline['timeline'] as $point) {
    echo sprintf(
        "  %s: Open $%-8s High $%-8s Low $%-8s Close $%-8s Vol: %s\n",
        substr($point['datetime'], 0, 10), // Extract date part
        number_format($point['open'], 2),
        number_format($point['high'], 2),
        number_format($point['low'], 2),
        number_format($point['close'], 2),
        number_format($point['volume'])
    );
}
echo "\n";

// 2. Session-filtered timeline (1-minute intervals)
echo "2. SESSION-FILTERED TIMELINE (1-minute data)\n";
echo str_repeat('-', 50) . "\n";

echo "Regular Trading Hours (9:30 AM - 4:00 PM EST):\n";
$regularHours = $client->stocks()->intradayTimeline('TSLA', [
    'size' => 3,
    'orderBy' => 'DESC'
]);

foreach ($regularHours['timeline'] as $point) {
    echo sprintf(
        "  %s: $%-8s (Vol: %s)\n",
        $point['datetime'],
        number_format($point['close'], 2),
        number_format($point['volume'])
    );
}

echo "\nPre-Market Hours (4:00 AM - 9:30 AM EST):\n";
$preMarket = $client->stocks()->timelineBySession('TSLA', 'pre_market', [
    'size' => 3,
    'orderBy' => 'DESC'
]);

foreach ($preMarket['timeline'] as $point) {
    echo sprintf(
        "  %s: $%-8s (Vol: %s)\n",
        $point['datetime'],
        number_format($point['close'], 2),
        number_format($point['volume'])
    );
}

echo "\nExtended Hours (Pre + Regular + After Hours):\n";
$extendedHours = $client->stocks()->extendedHoursTimeline('TSLA', [
    'size' => 5,
    'orderBy' => 'DESC'
]);

foreach ($extendedHours['timeline'] as $point) {
    echo sprintf(
        "  %s: $%-8s (Vol: %s)\n",
        $point['datetime'],
        number_format($point['close'], 2),
        number_format($point['volume'])
    );
}
echo "\n";

// 3. Date-filtered timeline
echo "3. DATE-FILTERED TIMELINE\n";
echo str_repeat('-', 50) . "\n";

echo "Data from October 15, 2025 onwards:\n";
$fromDate = $client->stocks()->timelineFromDate('TSLA', '2025-10-15', [
    'interval' => '1day',
    'size' => 3,
    'orderBy' => 'ASC'
]);

foreach ($fromDate['timeline'] as $point) {
    echo sprintf(
        "  %s: Open $%-8s Close $%-8s Change: %+.2f%%\n",
        substr($point['datetime'], 0, 10),
        number_format($point['open'], 2),
        number_format($point['close'], 2),
        (($point['close'] - $point['open']) / $point['open']) * 100
    );
}
echo "\n";

// 4. Manual parameter usage
echo "4. MANUAL PARAMETER USAGE\n";
echo str_repeat('-', 50) . "\n";

echo "Custom timeline with all parameters:\n";
$custom = $client->stocks()->timeline('AAPL', [
    'interval' => '1min',
    'session' => 'regular',
    'orderBy' => 'DESC',
    'size' => 5,
    'started_date' => '2025-10-15'
]);

echo "AAPL 1-minute data (regular hours only, from Oct 15):\n";
foreach ($custom['timeline'] as $point) {
    echo sprintf(
        "  %s: $%-8s\n",
        $point['datetime'],
        number_format($point['close'], 2)
    );
}
echo "\n";

// 5. Data source information
echo "5. DATA SOURCE INFORMATION\n";
echo str_repeat('-', 50) . "\n";

$meta = $client->stocks()->timeline('TSLA', ['size' => 1]);
echo "Timeline Metadata:\n";
echo "  Data Source: {$meta['wioex']['data_source']}\n";
echo "  Symbol: {$meta['wioex']['symbol']}\n";
echo "  Interval: {$meta['wioex']['interval']}\n";
echo "  Exchange: {$meta['wioex']['exchange']}\n";
echo "  Timezone: {$meta['wioex']['exchange_timezone']}\n";
echo "  Last Cache: {$meta['wioex']['cache']['last_cache']}\n";

echo "\nRequest Parameters:\n";
echo "  Valid Tickers: " . implode(', ', $meta['requests']['tickers']['valid']) . "\n";
echo "  Interval: {$meta['requests']['interval']}\n";
echo "  Order: {$meta['requests']['order']}\n";
echo "  Size: {$meta['requests']['size']}\n";
echo "  Session: {$meta['requests']['session']}\n";

echo "\n=== Advanced Timeline Example completed ===\n";

/*
 * TRADING SESSION REFERENCE:
 * 
 * - regular: 9:30 AM - 4:00 PM EST (Standard market hours)
 * - pre_market: 4:00 AM - 9:30 AM EST (Early trading)
 * - after_hours: 4:00 PM - 8:00 PM EST (Extended trading)
 * - extended: 4:00 AM - 8:00 PM EST (All extended hours combined)
 * - all: Full 24-hour data (default)
 * 
 * Note: Session filtering only applies to 1-minute interval data.
 * Daily data ignores the session parameter.
 */