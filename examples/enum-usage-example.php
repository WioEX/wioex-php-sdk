<?php

/**
 * WioEX PHP SDK - ENUM Usage Examples (v1.4.0)
 *
 * This example demonstrates the new ENUM functionality for type-safe API usage.
 * ENUMs provide better IDE support, prevent typos, and make code more maintainable.
 */

require __DIR__ . '/../vendor/autoload.php';

use Wioex\SDK\WioexClient;

// Import all ENUM classes
use Wioex\SDK\Enums\TimelineInterval;
use Wioex\SDK\Enums\SortOrder;
use Wioex\SDK\Enums\TradingSession;
use Wioex\SDK\Enums\SignalType;
use Wioex\SDK\Enums\TriggerType;
use Wioex\SDK\Enums\MarketIndex;
use Wioex\SDK\Enums\IpoType;
use Wioex\SDK\Enums\UsagePeriod;
use Wioex\SDK\Enums\AnalyticsPeriod;
use Wioex\SDK\Enums\CurrencyCode;
use Wioex\SDK\Enums\CurrencyInterval;

$client = new WioexClient([
    'api_key' => 'your-api-key-here'
]);

echo "=== WioEX PHP SDK - ENUM Usage Examples ===\n\n";

// 1. Timeline ENUMs - Most comprehensive usage
echo "1. TIMELINE OPERATIONS WITH ENUMs\n";
echo str_repeat('-', 50) . "\n";

// Using TimelineInterval ENUM for different trading strategies
echo "Day Trading (5-minute intervals):\n";
$dayTrading = $client->stocks()->timeline('AAPL', [
    'interval' => TimelineInterval::FIVE_MINUTES,
    'orderBy' => SortOrder::DESCENDING,
    'session' => TradingSession::REGULAR,
    'size' => 50
]);
echo "  Interval: " . TimelineInterval::FIVE_MINUTES->getDescription() . "\n";
echo "  Strategy: " . TimelineInterval::FIVE_MINUTES->getTradingStrategy() . "\n";
echo "  Data points: " . count($dayTrading['data']['timeline']) . "\n\n";

// Using convenience methods with ENUMs
echo "Swing Trading (hourly data):\n";
$swingTrading = $client->stocks()->timelineHourly('TSLA', ['size' => 168]); // 1 week
echo "  Timeframe: " . TimelineInterval::ONE_HOUR->getDescription() . "\n";
echo "  Cache TTL: " . TimelineInterval::ONE_HOUR->getCacheTTL() . " seconds\n\n";

// Period-based optimization
echo "Long-term Analysis (1-year optimized):\n";
$longTerm = $client->stocks()->timelineOneYear('MSFT');
echo "  Period: " . TimelineInterval::PERIOD_1Y->getDescription() . "\n";
echo "  Cache TTL: " . TimelineInterval::PERIOD_1Y->getCacheTTL() . " seconds\n\n";

// 2. Trading Session Filtering
echo "2. TRADING SESSION FILTERING\n";
echo str_repeat('-', 50) . "\n";

foreach (TradingSession::cases() as $session) {
    echo "Session: {$session->value}\n";
    echo "  Description: " . $session->getDescription() . "\n";
    echo "  Duration: " . $session->getDurationHours() . " hours\n";
    echo "  Use case: " . $session->getUseCase() . "\n\n";
}

// Get pre-market data
$preMarket = $client->stocks()->timelineBySession('AAPL', TradingSession::PRE_MARKET, ['size' => 20]);
echo "Pre-market data points: " . count($preMarket['data']['timeline']) . "\n\n";

// 3. Signal Operations with ENUMs
echo "3. TRADING SIGNALS WITH ENUMs\n";
echo str_repeat('-', 50) . "\n";

// Get strong buy signals
$strongBuySignals = $client->signals()->active([
    'signal_type' => SignalType::STRONG_BUY,
    'min_confidence' => 85
]);
echo "Strong Buy Signals:\n";
echo "  Type: " . SignalType::STRONG_BUY->getDescription() . "\n";
echo "  Direction: " . SignalType::STRONG_BUY->getDirection() . "\n";
echo "  Strength: " . SignalType::STRONG_BUY->getStrength() . "/5\n";
echo "  Action: " . SignalType::STRONG_BUY->getRecommendedAction() . "\n\n";

// Get signal history by trigger type
$profitableSignals = $client->signals()->history([
    'trigger_type' => TriggerType::TARGET,
    'days' => 30
]);
echo "Profitable Signal Outcomes:\n";
echo "  Trigger: " . TriggerType::TARGET->getDescription() . "\n";
echo "  Outcome: " . TriggerType::TARGET->getOutcome() . "\n";
echo "  Resolution: " . TriggerType::TARGET->getTypicalResolutionTime() . "\n\n";

// 4. Market Index Operations
echo "4. MARKET INDEX OPERATIONS\n";
echo str_repeat('-', 50) . "\n";

foreach (MarketIndex::cases() as $index) {
    echo "Index: {$index->getName()}\n";
    echo "  Companies: " . $index->getCompanyCount() . "\n";
    echo "  Focus: " . $index->getMarketCapFocus() . "\n";
    echo "  Weighting: " . $index->getWeightingMethod() . "\n";
    echo "  ETF: " . $index->getCommonETF() . "\n\n";
}

// Get NASDAQ-100 heatmap
$heatmap = $client->stocks()->heatmap(MarketIndex::NASDAQ_100);
echo "NASDAQ-100 Heatmap retrieved\n";
echo "Volatility: " . MarketIndex::NASDAQ_100->getVolatilityCharacteristics() . "\n\n";

// 5. IPO Screening
echo "5. IPO SCREENING WITH ENUMs\n";
echo str_repeat('-', 50) . "\n";

foreach (IpoType::cases() as $ipoType) {
    echo "IPO Type: {$ipoType->value}\n";
    echo "  Description: " . $ipoType->getDescription() . "\n";
    echo "  Timeframe: " . $ipoType->getTimeframe() . "\n";
    echo "  Investment Opportunity: " . $ipoType->getInvestmentOpportunity() . "\n";
    echo "  Risk Level: " . $ipoType->getRiskLevel() . "\n\n";
}

// Get recent IPOs
$recentIpos = $client->screens()->ipos(IpoType::RECENT);
echo "Recent IPOs retrieved\n\n";

// 6. Account Analytics with Period ENUMs
echo "6. ACCOUNT ANALYTICS WITH PERIOD ENUMs\n";
echo str_repeat('-', 50) . "\n";

// Usage periods
foreach (UsagePeriod::cases() as $period) {
    echo "Usage Period: " . $period->getDescription() . "\n";
    echo "  Type: " . $period->getPeriodType() . "\n";
    echo "  Use Case: " . $period->getUseCase() . "\n";
    echo "  Data Volume: " . $period->getExpectedDataVolume() . "\n\n";
}

// Get weekly usage
$weeklyUsage = $client->account()->usage(UsagePeriod::SEVEN_DAYS);
echo "Weekly usage data retrieved\n\n";

// Analytics periods
foreach (AnalyticsPeriod::cases() as $period) {
    echo "Analytics Period: " . $period->getDescription() . "\n";
    echo "  Duration: " . $period->getDurationDays() . " days\n";
    echo "  Analysis Depth: " . $period->getAnalysisDepth() . "\n";
    echo "  Business Use: " . $period->getBusinessUseCase() . "\n\n";
}

// Get quarterly analytics
$quarterlyAnalytics = $client->account()->analytics(AnalyticsPeriod::QUARTER);
echo "Quarterly analytics retrieved\n\n";

// 7. Currency Operations
echo "7. CURRENCY OPERATIONS WITH ENUMs\n";
echo str_repeat('-', 50) . "\n";

// Major currencies
echo "Major Currencies:\n";
foreach (CurrencyCode::getMajorCurrencies() as $currency) {
    echo "  {$currency->value}: " . $currency->getName() . " ({$currency->getSymbol()})\n";
    echo "    Region: " . $currency->getCountryRegion() . "\n";
    echo "    Volatility: " . $currency->getVolatilityProfile() . "\n";
}
echo "\n";

// Currency conversion with ENUMs
$conversion = $client->currency()->calculator(
    CurrencyCode::USD,
    CurrencyCode::EUR,
    1000.0
);
echo "USD to EUR conversion completed\n\n";

// Currency chart with interval
$currencyChart = $client->currency()->graph(
    CurrencyCode::EUR,
    CurrencyCode::GBP,
    CurrencyInterval::THREE_MONTHS
);
echo "EUR/GBP 3-month chart:\n";
echo "  Interval: " . CurrencyInterval::THREE_MONTHS->getDescription() . "\n";
echo "  Use Case: " . CurrencyInterval::THREE_MONTHS->getUseCase() . "\n";
echo "  Trading Style: " . CurrencyInterval::THREE_MONTHS->getTradingStyleRecommendation() . "\n\n";

// 8. ENUM Helper Methods Demonstration
echo "8. ENUM HELPER METHODS\n";
echo str_repeat('-', 50) . "\n";

// TimelineInterval grouping
echo "Minute-level intervals:\n";
foreach (TimelineInterval::getMinuteIntervals() as $interval) {
    echo "  {$interval->value}: " . $interval->getDescription() . "\n";
}
echo "\n";

echo "Period-based intervals:\n";
foreach (TimelineInterval::getPeriodBasedIntervals() as $interval) {
    echo "  {$interval->value}: " . $interval->getDescription() . "\n";
}
echo "\n";

// SignalType grouping
echo "Bullish signals:\n";
foreach (SignalType::getBullishSignals() as $signal) {
    echo "  {$signal->value}: " . $signal->getDescription() . "\n";
}
echo "\n";

echo "Strong signals (high confidence):\n";
foreach (SignalType::getStrongSignals() as $signal) {
    echo "  {$signal->value}: " . $signal->getDescription() . "\n";
}
echo "\n";

// Currency grouping
echo "Asian currencies:\n";
foreach (CurrencyCode::getAsianCurrencies() as $currency) {
    echo "  {$currency->value}: " . $currency->getName() . "\n";
}
echo "\n";

// 9. Backward Compatibility Demonstration
echo "9. BACKWARD COMPATIBILITY\n";
echo str_repeat('-', 50) . "\n";

echo "ENUM usage (recommended):\n";
$enumTimeline = $client->stocks()->timeline('AAPL', [
    'interval' => TimelineInterval::FIVE_MINUTES,
    'orderBy' => SortOrder::DESCENDING,
    'session' => TradingSession::REGULAR
]);
echo "  Using ENUMs - Type safe, IDE autocomplete\n\n";

echo "String usage (backward compatible):\n";
$stringTimeline = $client->stocks()->timeline('AAPL', [
    'interval' => '5min',
    'orderBy' => 'DESC',
    'session' => 'regular'
]);
echo "  Using strings - Still works, no breaking changes\n\n";

// 10. Error Prevention Examples
echo "10. ERROR PREVENTION WITH ENUMs\n";
echo str_repeat('-', 50) . "\n";

echo "Valid ENUM usage:\n";
try {
    $validSignal = SignalType::BUY;
    echo "  Signal strength: " . $validSignal->getStrength() . "/5\n";
    echo "  ✅ Success - Valid ENUM value\n\n";
} catch (Exception $e) {
    echo "  ❌ Error: " . $e->getMessage() . "\n\n";
}

echo "Invalid string conversion:\n";
try {
    $invalidSignal = SignalType::fromString('INVALID_SIGNAL');
    echo "  ✅ This should not execute\n\n";
} catch (Exception $e) {
    echo "  ❌ Error prevented: " . $e->getMessage() . "\n";
    echo "  💡 ENUMs prevent invalid values at runtime\n\n";
}

echo "=== ENUM Usage Examples Completed ===\n";
echo "\n";
echo "Benefits of using ENUMs:\n";
echo "✅ Type safety - No invalid values\n";
echo "✅ IDE autocomplete - Better developer experience\n";
echo "✅ Self-documenting - Built-in descriptions and helpers\n";
echo "✅ Refactoring safe - IDE can track usage\n";
echo "✅ Backward compatible - Existing code still works\n";
echo "✅ Runtime validation - Catch errors early\n";
echo "✅ Performance - No string comparisons\n";

/*
 * ENUM USAGE BEST PRACTICES:
 *
 * 1. USE ENUMs for new code:
 *    $client->stocks()->timeline('AAPL', [
 *        'interval' => TimelineInterval::FIVE_MINUTES
 *    ]);
 *
 * 2. LEVERAGE helper methods:
 *    foreach (TimelineInterval::getMinuteIntervals() as $interval) {
 *        // Process minute-level intervals
 *    }
 *
 * 3. VALIDATE user input:
 *    try {
 *        $signal = SignalType::fromString($userInput);
 *    } catch (InvalidArgumentException $e) {
 *        // Handle invalid input
 *    }
 *
 * 4. EXPLORE ENUM methods:
 *    $interval = TimelineInterval::FIVE_MINUTES;
 *    echo $interval->getDescription();
 *    echo $interval->getTradingStrategy();
 *    echo $interval->getCacheTTL();
 *
 * 5. COMBINE ENUMs for complex filtering:
 *    $signals = $client->signals()->active([
 *        'signal_type' => SignalType::STRONG_BUY
 *    ]);
 */
