<?php

/**
 * WioEX PHP SDK - Advanced Screens Runtime Features Example
 *
 * This example demonstrates the new runtime capabilities of the Screens resource,
 * including unified screening, session-based filtering, and smart analysis features.
 */

require __DIR__ . '/../vendor/autoload.php';

use Wioex\SDK\WioexClient;
use Wioex\SDK\Enums\ScreenType;
use Wioex\SDK\Enums\SortOrder;
use Wioex\SDK\Enums\TradingSession;
use Wioex\SDK\Enums\MarketIndex;

$client = new WioexClient([
    'api_key' => 'your-api-key-here'
]);

echo "=== WioEX Advanced Screens Runtime Features ===\n\n";

// =================================================================
// 1. UNIFIED SCREEN METHOD DEMONSTRATIONS
// =================================================================

echo "1. UNIFIED SCREEN METHOD USAGE\n";
echo str_repeat('=', 60) . "\n";

// Basic unified screening
echo "📊 Basic Unified Screening:\n";
$basicGainers = $client->screens()->screen(ScreenType::GAINERS, ['limit' => 10]);
echo "  Screen Type: " . ScreenType::GAINERS->getDescription() . "\n";
echo "  Category: " . ScreenType::GAINERS->getCategory() . "\n";
echo "  Use Case: " . ScreenType::GAINERS->getUseCase() . "\n";
echo "  Results: " . count($basicGainers['data']) . " stocks\n\n";

// Advanced unified screening with multiple parameters
echo "🔧 Advanced Unified Screening:\n";
$advancedActive = $client->screens()->screen(ScreenType::ACTIVE, [
    'limit' => 25,
    'sortOrder' => SortOrder::DESCENDING,
    'session' => TradingSession::REGULAR,
    'market' => MarketIndex::NASDAQ_100
]);
echo "  Screen: " . ScreenType::ACTIVE->getDescription() . "\n";
echo "  Session: " . TradingSession::REGULAR->getDescription() . "\n";
echo "  Market: " . MarketIndex::NASDAQ_100->getName() . "\n";
echo "  Sort: " . SortOrder::DESCENDING->getDescription() . "\n";
echo "  Results: " . count($advancedActive['data']) . " stocks\n\n";

// Session-specific screening
echo "🌅 Pre-Market Screening:\n";
$preMarketGainers = $client->screens()->screen(ScreenType::PRE_GAINERS, [
    'limit' => 15,
    'sortOrder' => SortOrder::DESCENDING
]);
echo "  Screen: " . ScreenType::PRE_GAINERS->getDescription() . "\n";
echo "  Session: " . ScreenType::PRE_GAINERS->getPrimarySession()->getDescription() . "\n";
echo "  Risk Level: " . ScreenType::PRE_GAINERS->getRiskLevel() . "\n";
echo "  Investor Type: " . ScreenType::PRE_GAINERS->getInvestorType() . "\n";
echo "  Results: " . count($preMarketGainers['data']) . " stocks\n\n";

// =================================================================
// 2. ENHANCED EXISTING METHODS
// =================================================================

echo "2. ENHANCED EXISTING METHODS\n";
echo str_repeat('=', 60) . "\n";

// Enhanced active method with new parameters
echo "📈 Enhanced Active Stocks:\n";
$enhancedActive = $client->screens()->active(
    limit: 30,
    sortOrder: SortOrder::DESCENDING,
    market: MarketIndex::SP500
);
echo "  Market: S&P 500\n";
echo "  Limit: 30 stocks\n";
echo "  Sort: Descending by volume\n";
echo "  Results: " . count($enhancedActive['data']) . " stocks\n\n";

// Enhanced gainers with parameters
echo "🚀 Enhanced Gainers:\n";
$enhancedGainers = $client->screens()->gainers(
    limit: 20,
    sortOrder: SortOrder::DESCENDING,
    market: MarketIndex::NASDAQ_100
);
echo "  Market: NASDAQ-100\n";
echo "  Limit: 20 stocks\n";
echo "  Results: " . count($enhancedGainers['data']) . " stocks\n\n";

// Enhanced pre-market with parameters
echo "🌅 Enhanced Pre-Market Gainers:\n";
$enhancedPreGainers = $client->screens()->preMarketGainers(
    limit: 15,
    sortOrder: SortOrder::DESCENDING
);
echo "  Session: Pre-market (4:00-9:30 AM EST)\n";
echo "  Limit: 15 stocks\n";
echo "  Results: " . count($enhancedPreGainers['data']) . " stocks\n\n";

// =================================================================
// 3. SESSION-BASED CONVENIENCE METHODS
// =================================================================

echo "3. SESSION-BASED CONVENIENCE METHODS\n";
echo str_repeat('=', 60) . "\n";

// Pre-market screens
echo "🌅 Pre-Market Screens:\n";
$preMarketScreens = $client->screens()->preMarketScreens(
    ScreenType::PRE_GAINERS,
    ['limit' => 10]
);
echo "  Screen Type: " . ScreenType::PRE_GAINERS->value . "\n";
echo "  Session: Pre-market\n";
echo "  Refresh: " . ScreenType::PRE_GAINERS->getRefreshFrequency() . "\n";
echo "  Results: " . count($preMarketScreens['data']) . " stocks\n\n";

// Post-market screens
echo "🌇 Post-Market Screens:\n";
$postMarketScreens = $client->screens()->postMarketScreens(
    ScreenType::POST_GAINERS,
    ['limit' => 10]
);
echo "  Screen Type: " . ScreenType::POST_GAINERS->value . "\n";
echo "  Session: After-hours\n";
echo "  Refresh: " . ScreenType::POST_GAINERS->getRefreshFrequency() . "\n";
echo "  Results: " . count($postMarketScreens['data']) . " stocks\n\n";

// Regular hours screens
echo "🕘 Regular Hours Screens:\n";
$regularScreens = $client->screens()->regularHoursScreens(
    ScreenType::ACTIVE,
    ['limit' => 15]
);
echo "  Screen Type: " . ScreenType::ACTIVE->value . "\n";
echo "  Session: Regular market hours\n";
echo "  Use Case: " . ScreenType::ACTIVE->getUseCase() . "\n";
echo "  Results: " . count($regularScreens['data']) . " stocks\n\n";

// Screens by session
echo "📊 Screens by Session:\n";
$sessionScreens = $client->screens()->screensBySession(
    TradingSession::EXTENDED,
    ScreenType::GAINERS,
    ['limit' => 12]
);
echo "  Session: " . TradingSession::EXTENDED->getDescription() . "\n";
echo "  Duration: " . TradingSession::EXTENDED->getDurationHours() . " hours\n";
echo "  Screen Type: " . ScreenType::GAINERS->value . "\n";
echo "  Results: " . count($sessionScreens['data']) . " stocks\n\n";

// =================================================================
// 4. SMART FILTERING METHODS
// =================================================================

echo "4. SMART FILTERING METHODS\n";
echo str_repeat('=', 60) . "\n";

// Top movers analysis
echo "🎯 Top Movers Analysis:\n";
$topMovers = $client->screens()->topMovers(
    limit: 15,
    order: SortOrder::DESCENDING,
    market: MarketIndex::SP500
);
echo "  Market: " . MarketIndex::SP500->getName() . "\n";
echo "  Analysis Type: Combined gainers and losers\n";
echo "  Gainers: " . $topMovers['data']['metadata']['total_gainers'] . "\n";
echo "  Losers: " . $topMovers['data']['metadata']['total_losers'] . "\n";
echo "  Timestamp: " . $topMovers['data']['metadata']['timestamp'] . "\n\n";

// Market sentiment analysis
echo "💭 Market Sentiment Analysis:\n";
$sentiment = $client->screens()->marketSentiment(
    MarketIndex::NASDAQ_100,
    sampleSize: 50
);
$sentimentData = $sentiment['data'];
echo "  Market: " . $sentimentData['market_index'] . "\n";
echo "  Sentiment: " . $sentimentData['sentiment'] . "\n";
echo "  Bullish Ratio: " . $sentimentData['metrics']['bullish_ratio'] . "%\n";
echo "  Bearish Ratio: " . $sentimentData['metrics']['bearish_ratio'] . "%\n";
echo "  Sample Size: " . $sentimentData['sample_size'] . " stocks\n";
echo "  Gainer Count: " . $sentimentData['metrics']['gainer_count'] . "\n";
echo "  Loser Count: " . $sentimentData['metrics']['loser_count'] . "\n\n";

// Volatility screens
echo "⚡ Volatility Screens:\n";
$volatility = $client->screens()->volatilityScreens([
    'limit' => 20,
    'minVolumeChange' => 200, // 200% above average
    'minPriceChange' => 8.0   // 8% price change
]);
$volData = $volatility['data'];
echo "  Scan Type: " . $volData['metadata']['scan_type'] . "\n";
echo "  Min Volume Change: " . $volData['filters']['min_volume_change_percent'] . "%\n";
echo "  Min Price Change: " . $volData['filters']['min_price_change_percent'] . "%\n";
echo "  High Volume Stocks: " . count($volData['high_volume_stocks']) . "\n";
echo "  Volatile Gainers: " . count($volData['volatile_gainers']) . "\n";
echo "  Volatile Losers: " . count($volData['volatile_losers']) . "\n\n";

// Day trading candidates
echo "📈 Day Trading Candidates:\n";
$dayTrading = $client->screens()->dayTradingCandidates(25);
echo "  Screen Type: " . ScreenType::ACTIVE->getDescription() . "\n";
echo "  Session: Regular market hours\n";
echo "  Target: " . ScreenType::ACTIVE->getInvestorType() . "\n";
echo "  Risk Level: " . ScreenType::ACTIVE->getRiskLevel() . "\n";
echo "  Results: " . count($dayTrading['data']) . " candidates\n\n";

// Earnings reaction screens
echo "📊 Earnings Reaction Screens:\n";
$earningsReaction = $client->screens()->earningsReactionScreens(
    TradingSession::PRE_MARKET,
    limit: 12
);
echo "  Session: Pre-market (earnings reactions)\n";
echo "  Focus: Early earnings impact detection\n";
echo "  Results: " . count($earningsReaction['data']) . " stocks\n\n";

// =================================================================
// 5. ENUM HELPER METHODS DEMONSTRATION
// =================================================================

echo "5. ENUM HELPER METHODS\n";
echo str_repeat('=', 60) . "\n";

// Screen type categorization
echo "📋 Screen Type Categories:\n";
echo "Performance-based screens:\n";
foreach (ScreenType::getPerformanceScreens() as $screen) {
    echo "  • {$screen->value}: " . $screen->getDescription() . "\n";
}
echo "\n";

echo "Extended hours screens:\n";
foreach (ScreenType::getExtendedHoursScreens() as $screen) {
    echo "  • {$screen->value}: " . $screen->getDescription() . "\n";
}
echo "\n";

echo "Bullish sentiment screens:\n";
foreach (ScreenType::getBullishScreens() as $screen) {
    echo "  • {$screen->value}: " . $screen->getDescription() . "\n";
}
echo "\n";

// Session compatibility
echo "🕐 Session Compatibility Analysis:\n";
$screenType = ScreenType::GAINERS;
echo "Screen: {$screenType->value}\n";
echo "Supported sessions:\n";
foreach ($screenType->getSupportedSessions() as $session) {
    echo "  • {$session->value}: " . $session->getDescription() . "\n";
}
echo "Primary session: " . $screenType->getPrimarySession()->getDescription() . "\n";
echo "Performance-based: " . ($screenType->isPerformanceBased() ? 'Yes' : 'No') . "\n";
echo "Session-specific: " . ($screenType->isSessionSpecific() ? 'Yes' : 'No') . "\n\n";

// =================================================================
// 6. BACKWARD COMPATIBILITY DEMONSTRATION
// =================================================================

echo "6. BACKWARD COMPATIBILITY\n";
echo str_repeat('=', 60) . "\n";

echo "🔄 Backward Compatibility Test:\n";

// Old way (still works)
echo "Old API style (still supported):\n";
$oldActive = $client->screens()->active(10);
$oldGainers = $client->screens()->gainers();
$oldLosers = $client->screens()->losers();
echo "  ✅ active(10) - " . count($oldActive['data']) . " results\n";
echo "  ✅ gainers() - " . count($oldGainers['data']) . " results\n";
echo "  ✅ losers() - " . count($oldLosers['data']) . " results\n\n";

// New way (enhanced features)
echo "New API style (enhanced features):\n";
$newActive = $client->screens()->active(
    limit: 10,
    sortOrder: SortOrder::DESCENDING,
    market: MarketIndex::NASDAQ_100
);
$newGainers = $client->screens()->screen(ScreenType::GAINERS, ['limit' => 10]);
echo "  ✅ active() with named parameters - " . count($newActive['data']) . " results\n";
echo "  ✅ screen(ScreenType::GAINERS) - " . count($newGainers['data']) . " results\n\n";

// String vs ENUM compatibility
echo "String vs ENUM compatibility:\n";
try {
    $stringScreen = $client->screens()->screen('gainers', ['limit' => 5]);
    $enumScreen = $client->screens()->screen(ScreenType::GAINERS, ['limit' => 5]);
    echo "  ✅ String parameter: " . count($stringScreen['data']) . " results\n";
    echo "  ✅ ENUM parameter: " . count($enumScreen['data']) . " results\n";
} catch (Exception $e) {
    echo "  ❌ Error: " . $e->getMessage() . "\n";
}
echo "\n";

// =================================================================
// 7. ERROR HANDLING AND VALIDATION
// =================================================================

echo "7. ERROR HANDLING AND VALIDATION\n";
echo str_repeat('=', 60) . "\n";

echo "🛡️ Runtime Validation Examples:\n";

// Session validation
echo "Session compatibility validation:\n";
try {
    // This should work
    $validScreen = $client->screens()->screen(ScreenType::PRE_GAINERS, [
        'session' => TradingSession::PRE_MARKET
    ]);
    echo "  ✅ Valid session combination\n";
} catch (Exception $e) {
    echo "  ❌ Error: " . $e->getMessage() . "\n";
}

try {
    // This should fail (incompatible session)
    $invalidScreen = $client->screens()->screen(ScreenType::PRE_GAINERS, [
        'session' => TradingSession::AFTER_HOURS
    ]);
    echo "  ❌ This should not execute\n";
} catch (Exception $e) {
    echo "  ✅ Caught invalid session: " . $e->getMessage() . "\n";
}

// Invalid ENUM validation
echo "ENUM validation:\n";
try {
    $invalidEnum = ScreenType::fromString('invalid_screen');
    echo "  ❌ This should not execute\n";
} catch (Exception $e) {
    echo "  ✅ Caught invalid ENUM: " . $e->getMessage() . "\n";
}

echo "\n=== Advanced Screens Runtime Features Demo Completed ===\n\n";

echo "🎯 Key Benefits of Runtime Features:\n";
echo "✅ Type Safety - ENUMs prevent invalid values\n";
echo "✅ IDE Support - Full autocomplete and IntelliSense\n";
echo "✅ Runtime Flexibility - Dynamic parameter building\n";
echo "✅ Session Awareness - Smart session validation\n";
echo "✅ Backward Compatibility - Existing code still works\n";
echo "✅ Smart Analysis - Built-in market sentiment and volatility\n";
echo "✅ Performance Optimization - Recommended limits per screen type\n";
echo "✅ Error Prevention - Runtime validation and helpful messages\n\n";

echo "📚 Advanced Usage Patterns:\n";
echo "• Unified screening with screen() method\n";
echo "• Session-based filtering for pre/post market analysis\n";
echo "• Smart filtering for day trading and volatility screening\n";
echo "• Market sentiment analysis with automated calculations\n";
echo "• ENUM helper methods for categorization and discovery\n";
echo "• Runtime parameter validation with helpful error messages\n";

?>