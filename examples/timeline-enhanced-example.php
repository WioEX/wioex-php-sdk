<?php

/**
 * WioEX PHP SDK - Enhanced Timeline Features (v1.4.0)
 *
 * This example demonstrates the new enhanced timeline features including:
 * - 17 different interval types with period-based optimization
 * - Two-branch JSON response structure (metadata/data)
 * - New convenience methods for common intervals
 * - Intelligent caching based on interval frequency
 * - Period-based intervals for optimal data retrieval
 */

require __DIR__ . '/../vendor/autoload.php';

use Wioex\SDK\WioexClient;

$client = new WioexClient([
    'api_key' => 'your-api-key-here'
]);

echo "=== WioEX PHP SDK - Enhanced Timeline Features (v1.4.0) ===\n\n";

// 1. Two-Branch JSON Response Structure
echo "1. TWO-BRANCH JSON RESPONSE STRUCTURE\n";
echo str_repeat('-', 60) . "\n";

$timeline = $client->stocks()->timeline('TSLA', [
    'interval' => '1day',
    'size' => 3
]);

echo "Metadata Branch (API Information):\n";
$metadata = $timeline['metadata'];
echo "  WioEX Brand: {$metadata['wioex']['brand']}\n";
echo "  Service: {$metadata['wioex']['service']}\n";
echo "  Version: {$metadata['wioex']['version']}\n";
echo "  Timestamp (UTC): {$metadata['response']['timestamp_utc']}\n";
echo "  Cache Status: {$metadata['cache']['status']}\n";
echo "  Cache TTL: {$metadata['cache']['ttl_seconds']} seconds\n";
echo "  Credit Cost: {$metadata['usage']['credit_cost']}\n\n";

echo "Data Branch (Business Data):\n";
$data = $timeline['data'];
echo "  Symbol: {$data['symbol']}\n";
echo "  Company: {$data['company_name']}\n";
echo "  Currency: {$data['currency']}\n";
echo "  Exchange: {$data['exchange']}\n";
echo "  Market Status: {$data['market_status']}\n";
echo "  Timeline Points: " . count($data['timeline']) . "\n\n";

// 2. All 17 Supported Intervals
echo "2. ALL 17 SUPPORTED INTERVALS\n";
echo str_repeat('-', 60) . "\n";

$intervals = [
    // Minute intervals (high frequency)
    '1min' => 'Every minute (60s cache)',
    '5min' => 'Every 5 minutes (5min cache)',
    '15min' => 'Every 15 minutes (15min cache)',
    '30min' => 'Every 30 minutes (30min cache)',

    // Hour intervals
    '1hour' => 'Every hour (1hr cache)',
    '5hour' => 'Every 5 hours (1hr cache)',

    // Daily/Weekly/Monthly
    '1day' => 'Daily intervals (1hr cache)',
    '1week' => 'Weekly intervals (2hr cache)',
    '1month' => 'Monthly intervals (4hr cache)',

    // Period-based intervals (optimized for timeframes)
    '1d' => '1 day period with 5min intervals (5min cache)',
    '1w' => '1 week period with 30min intervals (30min cache)',
    '1m' => '1 month period with 5hr intervals (1hr cache)',
    '3m' => '3 months period with daily intervals (2hr cache)',
    '6m' => '6 months period with daily intervals (4hr cache)',
    '1y' => '1 year period with weekly intervals (8hr cache)',
    '5y' => '5 years period with monthly intervals (24hr cache)',
    'max' => 'Maximum data with monthly intervals (48hr cache)'
];

echo "Available Intervals with Intelligent Caching:\n";
foreach ($intervals as $interval => $description) {
    echo "  {$interval}: {$description}\n";
}
echo "\n";

// 3. New Convenience Methods
echo "3. NEW CONVENIENCE METHODS\n";
echo str_repeat('-', 60) . "\n";

echo "Testing new convenience methods with AAPL:\n\n";

// 5-minute detailed analysis
echo "5-Minute Detailed Analysis:\n";
$fiveMin = $client->stocks()->timelineFiveMinute('AAPL', ['size' => 3]);
foreach ($fiveMin['data']['timeline'] as $point) {
    echo "  {$point['datetime']}: \${$point['close']} (5min intervals)\n";
}
echo "\n";

// Hourly data for swing trading
echo "Hourly Data for Swing Trading:\n";
$hourly = $client->stocks()->timelineHourly('AAPL', ['size' => 3]);
foreach ($hourly['data']['timeline'] as $point) {
    echo "  {$point['datetime']}: \${$point['close']} (1hr intervals)\n";
}
echo "\n";

// Weekly trends
echo "Weekly Trends:\n";
$weekly = $client->stocks()->timelineWeekly('AAPL', ['size' => 3]);
foreach ($weekly['data']['timeline'] as $point) {
    echo "  {$point['datetime']}: \${$point['close']} (1week intervals)\n";
}
echo "\n";

// Monthly overview
echo "Monthly Overview:\n";
$monthly = $client->stocks()->timelineMonthly('AAPL', ['size' => 3]);
foreach ($monthly['data']['timeline'] as $point) {
    echo "  {$point['datetime']}: \${$point['close']} (1month intervals)\n";
}
echo "\n";

// Optimized 1-year view
echo "Optimized 1-Year View (automatically uses weekly intervals):\n";
$oneYear = $client->stocks()->timelineOneYear('AAPL', ['size' => 3]);
foreach ($oneYear['data']['timeline'] as $point) {
    echo "  {$point['datetime']}: \${$point['close']} (optimized for 1y period)\n";
}
echo "\n";

// Maximum historical data
echo "Maximum Historical Data (automatically uses monthly intervals):\n";
$maxData = $client->stocks()->timelineMax('AAPL', ['size' => 3]);
foreach ($maxData['data']['timeline'] as $point) {
    echo "  {$point['datetime']}: \${$point['close']} (optimized for max period)\n";
}
echo "\n";

// 4. Period-Based Optimization Demonstration
echo "4. PERIOD-BASED OPTIMIZATION\n";
echo str_repeat('-', 60) . "\n";

echo "Demonstrating period-based interval optimization:\n\n";

// 1-day period uses 5-minute intervals for detail
echo "1-Day Period (uses 5min intervals for intraday detail):\n";
$oneDay = $client->stocks()->timeline('MSFT', [
    'interval' => '1d',
    'size' => 3
]);
echo "  Interval used: 1d (5min data points)\n";
echo "  Cache TTL: {$oneDay['metadata']['cache']['ttl_seconds']} seconds\n";
echo "  Data points: " . count($oneDay['data']['timeline']) . "\n\n";

// 1-year period uses weekly intervals for efficiency
echo "1-Year Period (uses weekly intervals for efficiency):\n";
$oneYearOptimized = $client->stocks()->timeline('MSFT', [
    'interval' => '1y',
    'size' => 3
]);
echo "  Interval used: 1y (weekly data points)\n";
echo "  Cache TTL: {$oneYearOptimized['metadata']['cache']['ttl_seconds']} seconds\n";
echo "  Data points: " . count($oneYearOptimized['data']['timeline']) . "\n\n";

// 5. Intelligent Caching Analysis
echo "5. INTELLIGENT CACHING ANALYSIS\n";
echo str_repeat('-', 60) . "\n";

$cachingExamples = [
    '1min' => $client->stocks()->timeline('GOOGL', ['interval' => '1min', 'size' => 1]),
    '1hour' => $client->stocks()->timeline('GOOGL', ['interval' => '1hour', 'size' => 1]),
    '1day' => $client->stocks()->timeline('GOOGL', ['interval' => '1day', 'size' => 1]),
    '1y' => $client->stocks()->timeline('GOOGL', ['interval' => '1y', 'size' => 1]),
];

echo "Caching Strategy by Interval Frequency:\n";
foreach ($cachingExamples as $interval => $response) {
    $ttl = $response['metadata']['cache']['ttl_seconds'];
    $minutes = round($ttl / 60);
    $hours = round($ttl / 3600);

    if ($ttl < 3600) {
        $readable = $minutes . " minutes";
    } else {
        $readable = $hours . " hours";
    }

    echo "  {$interval}: {$ttl}s ({$readable}) - " .
         ($ttl <= 300 ? "High frequency" :
          ($ttl <= 3600 ? "Medium frequency" : "Low frequency")) . "\n";
}
echo "\n";

// 6. Advanced Usage with All Parameters
echo "6. ADVANCED USAGE WITH ALL PARAMETERS\n";
echo str_repeat('-', 60) . "\n";

echo "Using all available parameters for maximum customization:\n";
$advanced = $client->stocks()->timeline('NVDA', [
    'interval' => '5min',           // 5-minute intervals
    'orderBy' => 'DESC',            // Latest first
    'size' => 5,                    // 5 data points
    'session' => 'regular',         // Regular trading hours only
    'started_date' => '2024-10-16'  // From specific date
]);

echo "NVDA 5-minute data (regular hours, from Oct 16, latest first):\n";
echo "Metadata:\n";
echo "  Cache Status: {$advanced['metadata']['cache']['status']}\n";
echo "  Provider Used: {$advanced['data']['provider_used']}\n";
echo "  Market Status: {$advanced['data']['market_status']}\n\n";

echo "Timeline Data:\n";
foreach ($advanced['data']['timeline'] as $point) {
    echo "  {$point['datetime']}: Open \${$point['open']}, Close \${$point['close']}\n";
}
echo "\n";

// 7. Error Handling for Enhanced Features
echo "7. ERROR HANDLING FOR ENHANCED FEATURES\n";
echo str_repeat('-', 60) . "\n";

try {
    // This will demonstrate error handling
    $invalidInterval = $client->stocks()->timeline('AAPL', [
        'interval' => 'invalid_interval',
        'size' => 1
    ]);
} catch (\Exception $e) {
    echo "Error handling example:\n";
    echo "  Error Type: " . get_class($e) . "\n";
    echo "  Message: {$e->getMessage()}\n\n";
}

// 8. Performance Comparison
echo "8. PERFORMANCE COMPARISON\n";
echo str_repeat('-', 60) . "\n";

echo "Comparing response times for different intervals:\n";

$performanceTests = ['1min', '1hour', '1day', '1y'];
foreach ($performanceTests as $interval) {
    $start = microtime(true);
    $response = $client->stocks()->timeline('AMZN', [
        'interval' => $interval,
        'size' => 1
    ]);
    $end = microtime(true);

    $responseTime = round(($end - $start) * 1000, 2);
    $cacheStatus = $response['metadata']['cache']['status'];

    echo "  {$interval}: {$responseTime}ms (Cache: {$cacheStatus})\n";
}

echo "\n=== Enhanced Timeline Features Example Completed ===\n";

/*
 * ENHANCED TIMELINE FEATURES SUMMARY (v1.4.0):
 *
 * NEW INTERVAL TYPES:
 * - Minute: 1min, 5min, 15min, 30min (high frequency, short cache)
 * - Hour: 1hour, 5hour (medium frequency)
 * - Daily/Weekly/Monthly: 1day, 1week, 1month (low frequency)
 * - Period-based: 1d, 1w, 1m, 3m, 6m, 1y, 5y, max (optimized intervals)
 *
 * NEW CONVENIENCE METHODS:
 * - timelineFiveMinute() - 5-minute detailed analysis
 * - timelineHourly() - Hourly data for swing trading
 * - timelineWeekly() - Weekly trends
 * - timelineMonthly() - Monthly overview
 * - timelineOneYear() - Optimized 1-year view
 * - timelineMax() - Maximum historical data
 *
 * TWO-BRANCH JSON RESPONSE:
 * - metadata: API information, caching, usage, timestamps
 * - data: Business data, symbol info, timeline points
 *
 * INTELLIGENT CACHING:
 * - 1min: 60s cache (real-time needs)
 * - 5min-30min: 5-30min cache (short-term analysis)
 * - 1hour+: 1-48hr cache (historical analysis)
 * - Period-based: Optimized per timeframe
 *
 * PERIOD-BASED OPTIMIZATION:
 * - Automatically selects best interval for requested period
 * - Balances detail vs. efficiency
 * - Reduces API calls while maintaining data quality
 */
