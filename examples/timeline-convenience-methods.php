<?php

/**
 * WioEX PHP SDK - Timeline Convenience Methods (v1.4.0)
 *
 * This example demonstrates the new convenience methods for common timeline use cases.
 * These methods provide optimized intervals and simplified usage for different trading styles.
 */

require __DIR__ . '/../vendor/autoload.php';

use Wioex\SDK\WioexClient;

$client = new WioexClient([
    'api_key' => 'your-api-key-here'
]);

echo "=== WioEX PHP SDK - Timeline Convenience Methods ===\n\n";

$symbol = 'AAPL';
echo "Getting timeline data for {$symbol} using convenience methods:\n\n";

// 1. Five-Minute Analysis (Day Trading)
echo "1. FIVE-MINUTE ANALYSIS (Day Trading)\n";
echo str_repeat('-', 50) . "\n";
echo "Perfect for: Day trading, scalping, intraday analysis\n";

$fiveMin = $client->stocks()->timelineFiveMinute($symbol, ['size' => 10]);
echo "Last 10 five-minute intervals:\n";
$timelineData = $fiveMin->getCoreData();
foreach (array_slice($timelineData['timeline'], -5) as $point) {
    $change = (($point['close'] - $point['open']) / $point['open']) * 100;
    echo sprintf(
        "  %s: \$%-7s (%+.2f%%)\n",
        date('H:i', strtotime($point['datetime'])),
        number_format($point['close'], 2),
        $change
    );
}
echo "\n";

// 2. Hourly Timeline (Swing Trading)
echo "2. HOURLY TIMELINE (Swing Trading)\n";
echo str_repeat('-', 50) . "\n";
echo "Perfect for: Swing trading, trend analysis, position sizing\n";

$hourly = $client->stocks()->timelineHourly($symbol, ['size' => 24]); // Last 24 hours
echo "Last 5 hourly intervals:\n";
$hourlyData = $hourly->getCoreData();
foreach (array_slice($hourlyData['timeline'], -5) as $point) {
    $change = (($point['close'] - $point['open']) / $point['open']) * 100;
    echo sprintf(
        "  %s: \$%-7s (%+.2f%%)\n",
        date('M j H:i', strtotime($point['datetime'])),
        number_format($point['close'], 2),
        $change
    );
}
echo "\n";

// 3. Weekly Trends (Medium-term Analysis)
echo "3. WEEKLY TRENDS (Medium-term Analysis)\n";
echo str_repeat('-', 50) . "\n";
echo "Perfect for: Medium-term trends, quarterly analysis, earnings cycles\n";

$weekly = $client->stocks()->timelineWeekly($symbol, ['size' => 12]); // Last 12 weeks
echo "Last 5 weekly intervals:\n";
$weeklyData = $weekly->getCoreData();
foreach (array_slice($weeklyData['timeline'], -5) as $point) {
    $change = (($point['close'] - $point['open']) / $point['open']) * 100;
    echo sprintf(
        "  Week of %s: \$%-7s (%+.2f%%)\n",
        date('M j', strtotime($point['datetime'])),
        number_format($point['close'], 2),
        $change
    );
}
echo "\n";

// 4. Monthly Overview (Long-term Trends)
echo "4. MONTHLY OVERVIEW (Long-term Trends)\n";
echo str_repeat('-', 50) . "\n";
echo "Perfect for: Long-term investing, annual performance, portfolio rebalancing\n";

$monthly = $client->stocks()->timelineMonthly($symbol, ['size' => 12]); // Last 12 months
echo "Last 6 monthly intervals:\n";
foreach (array_slice($monthly['data']['timeline'], -6) as $point) {
    $change = (($point['close'] - $point['open']) / $point['open']) * 100;
    echo sprintf(
        "  %s: \$%-7s (%+.2f%%)\n",
        date('M Y', strtotime($point['datetime'])),
        number_format($point['close'], 2),
        $change
    );
}
echo "\n";

// 5. One-Year Optimized (Annual Performance)
echo "5. ONE-YEAR OPTIMIZED (Annual Performance)\n";
echo str_repeat('-', 50) . "\n";
echo "Perfect for: Annual reviews, year-over-year comparison, fundamental analysis\n";

$oneYear = $client->stocks()->timelineOneYear($symbol, ['size' => 52]); // Optimal for 1 year
echo "Showing quarterly data from one-year timeline:\n";
$quarterly = array_chunk($oneYear['data']['timeline'], 13); // ~13 weeks per quarter
foreach ($quarterly as $q => $quarter) {
    if (!empty($quarter)) {
        $start = reset($quarter);
        $end = end($quarter);
        $change = (($end['close'] - $start['open']) / $start['open']) * 100;
        echo sprintf(
            "  Q%d: \$%-7s to \$%-7s (%+.2f%%)\n",
            $q + 1,
            number_format($start['open'], 2),
            number_format($end['close'], 2),
            $change
        );
    }
}
echo "\n";

// 6. Maximum Historical Data (Complete History)
echo "6. MAXIMUM HISTORICAL DATA (Complete History)\n";
echo str_repeat('-', 50) . "\n";
echo "Perfect for: Complete stock history, IPO analysis, long-term backtesting\n";

$maxData = $client->stocks()->timelineMax($symbol, ['size' => 60]); // Last 60 monthly points
echo "Historical performance summary:\n";
$firstPoint = reset($maxData['data']['timeline']);
$lastPoint = end($maxData['data']['timeline']);
$totalChange = (($lastPoint['close'] - $firstPoint['open']) / $firstPoint['open']) * 100;
$timespan = (strtotime($lastPoint['datetime']) - strtotime($firstPoint['datetime'])) / (365 * 24 * 3600);

echo sprintf(
    "  From: %s (\$%s)\n",
    date('M Y', strtotime($firstPoint['datetime'])),
    number_format($firstPoint['open'], 2)
);
echo sprintf(
    "  To: %s (\$%s)\n",
    date('M Y', strtotime($lastPoint['datetime'])),
    number_format($lastPoint['close'], 2)
);
echo sprintf(
    "  Total Change: %+.2f%% over %.1f years\n",
    $totalChange,
    $timespan
);
echo sprintf(
    "  Annualized Return: %+.2f%%\n",
    ($totalChange / $timespan)
);
echo "\n";

// 7. Cache Performance Comparison
echo "7. CACHE PERFORMANCE COMPARISON\n";
echo str_repeat('-', 50) . "\n";

$methods = [
    'timelineFiveMinute' => '5-minute intervals',
    'timelineHourly' => '1-hour intervals',
    'timelineWeekly' => '1-week intervals',
    'timelineMonthly' => '1-month intervals',
    'timelineOneYear' => '1-year optimized',
    'timelineMax' => 'Maximum history'
];

echo "Cache TTL for each convenience method:\n";
foreach ($methods as $method => $description) {
    $response = $client->stocks()->$method($symbol, ['size' => 1]);
    $ttl = $response['metadata']['cache']['ttl_seconds'];
    $status = $response['metadata']['cache']['status'];

    if ($ttl < 3600) {
        $readable = round($ttl / 60) . " minutes";
    } elseif ($ttl < 86400) {
        $readable = round($ttl / 3600) . " hours";
    } else {
        $readable = round($ttl / 86400) . " days";
    }

    echo sprintf(
        "  %-20s: %s (%s)\n",
        $method,
        $readable,
        $status
    );
}
echo "\n";

// 8. Trading Strategy Examples
echo "8. TRADING STRATEGY EXAMPLES\n";
echo str_repeat('-', 50) . "\n";

echo "A. Scalping Strategy (5-minute data):\n";
$scalping = $client->stocks()->timelineFiveMinute($symbol, ['size' => 20]);
$recent = array_slice($scalping['data']['timeline'], -5);
$volatility = 0;
for ($i = 1; $i < count($recent); $i++) {
    $volatility += abs($recent[$i]['close'] - $recent[$i - 1]['close']);
}
$avgVolatility = $volatility / (count($recent) - 1);
echo "  Average 5-min volatility: \$" . number_format($avgVolatility, 3) . "\n";
echo "  Scalping opportunities: " . ($avgVolatility > 0.5 ? "HIGH" : "LOW") . "\n\n";

echo "B. Swing Trading Strategy (hourly data):\n";
$swingData = $client->stocks()->timelineHourly($symbol, ['size' => 48]); // 2 days
$hourlyPrices = array_column($swingData['data']['timeline'], 'close');
$sma20 = array_sum(array_slice($hourlyPrices, -20)) / 20;
$currentPrice = end($hourlyPrices);
echo "  Current Price: \$" . number_format($currentPrice, 2) . "\n";
echo "  20-hour SMA: \$" . number_format($sma20, 2) . "\n";
echo "  Trend: " . ($currentPrice > $sma20 ? "BULLISH" : "BEARISH") . "\n\n";

echo "C. Long-term Investment (monthly data):\n";
$longTerm = $client->stocks()->timelineMonthly($symbol, ['size' => 24]); // 2 years
$monthlyPrices = array_column($longTerm['data']['timeline'], 'close');
$firstPrice = reset($monthlyPrices);
$lastPrice = end($monthlyPrices);
$twoYearReturn = (($lastPrice - $firstPrice) / $firstPrice) * 100;
echo "  2-Year Return: " . number_format($twoYearReturn, 2) . "%\n";
echo "  Investment Grade: " . ($twoYearReturn > 15 ? "STRONG" : ($twoYearReturn > 5 ? "MODERATE" : "WEAK")) . "\n\n";

echo "=== Timeline Convenience Methods Example Completed ===\n";

/*
 * CONVENIENCE METHODS QUICK REFERENCE:
 *
 * timelineFiveMinute($symbol, $options)
 * - Best for: Day trading, scalping, real-time analysis
 * - Cache: 5 minutes
 * - Data: High frequency, detailed intraday movements
 *
 * timelineHourly($symbol, $options)
 * - Best for: Swing trading, trend analysis
 * - Cache: 1 hour
 * - Data: Medium frequency, hourly price movements
 *
 * timelineWeekly($symbol, $options)
 * - Best for: Medium-term trends, quarterly analysis
 * - Cache: 2 hours
 * - Data: Weekly summaries, trend identification
 *
 * timelineMonthly($symbol, $options)
 * - Best for: Long-term investing, annual reviews
 * - Cache: 4 hours
 * - Data: Monthly summaries, long-term trends
 *
 * timelineOneYear($symbol, $options)
 * - Best for: Annual performance, fundamental analysis
 * - Cache: 8 hours
 * - Data: Optimized for 1-year timeframe
 *
 * timelineMax($symbol, $options)
 * - Best for: Complete history, IPO analysis, backtesting
 * - Cache: 48 hours
 * - Data: Maximum available historical data
 */
