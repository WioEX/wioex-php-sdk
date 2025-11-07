<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Wioex\SDK\WioexClient;

// ====================================================================
// ADVANCED CACHING EXAMPLE
// Demonstrates built-in caching capabilities of the WioEX PHP SDK
// ====================================================================

echo "🚀 WioEX SDK Advanced Caching Example\n";
echo "=====================================\n\n";

// Configuration with caching enabled
$config = [
    'api_key' => $_ENV['WIOEX_API_KEY'] ?? 'your-api-key-here',
    'cache' => [
        'enabled' => true,
        'driver' => 'auto', // Auto-detect best driver: Redis > Memcached > OPcache > Memory > File
        'ttl' => [
            'stream_token' => 1800,  // 30 min
            'market_data' => 60,     // 1 min  
            'static_data' => 3600,   // 1 hour
            'user_data' => 300,      // 5 min
            'news' => 1800,          // 30 min
            'signals' => 120,        // 2 min
            'account' => 600,        // 10 min
            'default' => 900         // 15 min fallback
        ],
        'prefix' => 'wioex_demo_',
        // Redis configuration (if available)
        'redis' => [
            'host' => '127.0.0.1',
            'port' => 6379,
            'persistent' => true,
            'compression' => true,
            'serialization' => 'igbinary'
        ],
        // Memcached configuration (if available)
        'memcached' => [
            'servers' => [['host' => '127.0.0.1', 'port' => 11211]],
            'persistent_id' => 'wioex_cache',
            'compression' => true
        ],
        // File cache fallback
        'file' => [
            'cache_dir' => '/tmp/wioex_cache'
        ]
    ]
];

try {
    // Create client with caching
    $client = new WioexClient($config);
    
    // Check cache status
    echo "📊 Cache Status:\n";
    echo "Cache Enabled: " . ($client->isCacheEnabled() ? '✅ Yes' : '❌ No') . "\n";
    
    if ($client->isCacheEnabled()) {
        $stats = $client->getCacheStatistics();
        echo "Default Driver: {$stats['default_driver']}\n";
        echo "Available Drivers: " . implode(', ', $stats['available_drivers']) . "\n\n";
    }

    // Get cache recommendations
    echo "💡 Cache Recommendations:\n";
    $recommendations = $client->getCacheRecommendations();
    foreach ($recommendations['recommendations'] as $driver => $info) {
        $status = $info['available'] ? '✅' : '❌';
        $recommended = $info['recommended'] ? '⭐' : '';
        echo "{$status} {$recommended} {$driver}: {$info['reason']}\n";
    }
    echo "\n";

    // ====================================================================
    // EXAMPLE 1: Basic caching with remember() pattern
    // ====================================================================
    echo "🔄 Example 1: Remember Pattern (Cache-Aside)\n";
    echo "--------------------------------------------\n";
    
    $start = microtime(true);
    
    // This will call API first time, cache the result
    $marketData = $client->remember('market_status', function() use ($client) {
        echo "📡 Making API call to get market status...\n";
        return $client->markets()->status();
    }, 'market_data');
    
    $firstCallTime = microtime(true) - $start;
    echo "✅ First call took: " . round($firstCallTime * 1000, 2) . " ms\n";
    
    // Second call should be much faster (from cache)
    $start = microtime(true);
    $cachedData = $client->remember('market_status', function() {
        echo "This shouldn't be called!\n";
        return null;
    }, 'market_data');
    
    $secondCallTime = microtime(true) - $start;
    echo "⚡ Cached call took: " . round($secondCallTime * 1000, 2) . " ms\n";
    echo "🚀 Speed improvement: " . round(($firstCallTime / $secondCallTime), 2) . "x faster\n\n";

    // ====================================================================
    // EXAMPLE 2: Manual cache operations
    // ====================================================================
    echo "🎯 Example 2: Manual Cache Operations\n";
    echo "-------------------------------------\n";
    
    // Set some test data
    $testData = [
        'timestamp' => time(),
        'message' => 'Hello from cache!',
        'performance' => 'excellent'
    ];
    
    $client->cacheSet('test_key', $testData, 'static_data');
    echo "✅ Data cached with key 'test_key'\n";
    
    // Retrieve cached data
    $retrieved = $client->cacheGet('test_key');
    echo "📥 Retrieved from cache: " . json_encode($retrieved) . "\n";
    
    // Check if key exists
    echo "🔍 Key exists: " . ($client->cacheHas('test_key') ? 'Yes' : 'No') . "\n";
    
    // Delete from cache
    $client->cacheDelete('test_key');
    echo "🗑️  Key deleted from cache\n";
    echo "🔍 Key exists after deletion: " . ($client->cacheHas('test_key') ? 'Yes' : 'No') . "\n\n";

    // ====================================================================
    // EXAMPLE 3: Namespaced caching
    // ====================================================================
    echo "📁 Example 3: Namespaced Caching\n";
    echo "--------------------------------\n";
    
    $userCache = $client->cacheNamespace('user:12345');
    $companyCache = $client->cacheNamespace('company:acme');
    
    if ($userCache && $companyCache) {
        $userCache->set('preferences', ['theme' => 'dark', 'lang' => 'en'], 300);
        $companyCache->set('preferences', ['logo' => 'blue', 'brand' => 'ACME'], 300);
        
        echo "✅ User preferences cached\n";
        echo "✅ Company preferences cached\n";
        
        $userPrefs = $userCache->get('preferences');
        $companyPrefs = $companyCache->get('preferences');
        
        echo "👤 User preferences: " . json_encode($userPrefs) . "\n";
        echo "🏢 Company preferences: " . json_encode($companyPrefs) . "\n\n";
    }

    // ====================================================================
    // EXAMPLE 4: Tagged caching (if supported)
    // ====================================================================
    echo "🏷️  Example 4: Tagged Caching\n";
    echo "-----------------------------\n";
    
    $taggedCache = $client->cacheWithTags(['stocks', 'real-time']);
    
    if ($taggedCache) {
        $taggedCache->set('AAPL_quote', ['price' => 150.00, 'change' => '+2.5%'], 60);
        $taggedCache->set('GOOGL_quote', ['price' => 2800.00, 'change' => '+1.2%'], 60);
        
        echo "✅ Tagged cache entries created\n";
        echo "📈 AAPL quote: " . json_encode($taggedCache->get('AAPL_quote')) . "\n";
        echo "📈 GOOGL quote: " . json_encode($taggedCache->get('GOOGL_quote')) . "\n\n";
    }

    // ====================================================================
    // EXAMPLE 5: Performance measurement
    // ====================================================================
    echo "⏱️  Example 5: Performance Measurement\n";
    echo "-------------------------------------\n";
    
    $iterations = 5;
    $totalApiTime = 0;
    $totalCacheTime = 0;
    
    // Test API calls (no cache)
    $client->disableCache();
    for ($i = 0; $i < $iterations; $i++) {
        $start = microtime(true);
        try {
            $client->markets()->status();
            $totalApiTime += microtime(true) - $start;
        } catch (Exception $e) {
            echo "⚠️  API call failed: " . $e->getMessage() . "\n";
            break;
        }
    }
    
    // Re-enable cache
    $client->enableCache($config['cache']);
    
    // Test cached calls
    for ($i = 0; $i < $iterations; $i++) {
        $start = microtime(true);
        $client->remember('performance_test', function() use ($client) {
            return $client->markets()->status();
        }, 'market_data');
        
        if ($i > 0) { // Skip first call (cache population)
            $totalCacheTime += microtime(true) - $start;
        }
    }
    
    $avgApiTime = $totalApiTime / $iterations;
    $avgCacheTime = $totalCacheTime / ($iterations - 1);
    
    echo "📊 Performance Results:\n";
    echo "   API Calls: " . round($avgApiTime * 1000, 2) . " ms average\n";
    echo "   Cache Hits: " . round($avgCacheTime * 1000, 2) . " ms average\n";
    echo "   🚀 Speed Improvement: " . round(($avgApiTime / $avgCacheTime), 2) . "x faster\n\n";

    // ====================================================================
    // EXAMPLE 6: Cache statistics and monitoring
    // ====================================================================
    echo "📈 Example 6: Cache Statistics\n";
    echo "-----------------------------\n";
    
    $stats = $client->getCacheStatistics();
    if (isset($stats['drivers'])) {
        foreach ($stats['drivers'] as $driverName => $driverStats) {
            echo "🔧 Driver: {$driverName}\n";
            echo "   Hits: {$driverStats['hits']}\n";
            echo "   Misses: {$driverStats['misses']}\n";
            echo "   Sets: {$driverStats['sets']}\n";
            echo "   Hit Ratio: " . ($driverStats['hit_ratio'] ?? 'N/A') . "%\n";
            echo "\n";
        }
    }

    // ====================================================================
    // EXAMPLE 7: Use case specific configuration
    // ====================================================================
    echo "⚙️  Example 7: Use Case Configuration\n";
    echo "------------------------------------\n";
    
    // Configure for API responses
    $client->configureCacheForUseCase('api_responses');
    echo "✅ Configured for API responses (Redis priority, moderate TTL)\n";
    
    // Configure for session storage
    $client->configureCacheForUseCase('session_storage');
    echo "✅ Configured for session storage (Redis priority, longer TTL)\n";
    
    // Configure for high frequency operations
    $client->configureCacheForUseCase('high_frequency');
    echo "✅ Configured for high frequency (Memory priority, short TTL)\n\n";

    // ====================================================================
    // EXAMPLE 8: Cache maintenance
    // ====================================================================
    echo "🧹 Example 8: Cache Maintenance\n";
    echo "------------------------------\n";
    
    // Flush expired entries
    $expiredCount = $client->flushExpiredCache();
    echo "🗑️  Flushed {$expiredCount} expired cache entries\n";
    
    // Clear all cache (use with caution in production!)
    echo "⚠️  Clearing all cache entries...\n";
    $client->cacheClear();
    echo "✅ Cache cleared\n\n";

    // ====================================================================
    // EXAMPLE 9: Real-world usage patterns
    // ====================================================================
    echo "🌍 Example 9: Real-world Usage Patterns\n";
    echo "---------------------------------------\n";
    
    // Pattern 1: Expensive data with fallback
    $stockInfo = $client->remember('stock_AAPL_info', function() use ($client) {
        try {
            echo "📡 Fetching AAPL stock info from API...\n";
            return $client->stocks()->info('AAPL');
        } catch (Exception $e) {
            echo "⚠️  API failed, using cached fallback data\n";
            return [
                'symbol' => 'AAPL',
                'error' => 'API unavailable',
                'cached_at' => time()
            ];
        }
    }, 'static_data');
    
    echo "📊 Stock info retrieved: " . ($stockInfo['symbol'] ?? 'Unknown') . "\n";
    
    // Pattern 2: Rate limiting bypass
    echo "\n🚦 Rate Limiting Bypass Example:\n";
    for ($i = 1; $i <= 3; $i++) {
        $start = microtime(true);
        $data = $client->remember("rate_limit_test_{$i}", function() use ($client) {
            // This would normally consume API credits/rate limits
            return ['request_id' => uniqid(), 'timestamp' => time()];
        }, 'market_data');
        
        $time = round((microtime(true) - $start) * 1000, 2);
        echo "   Request {$i}: {$time} ms (ID: {$data['request_id']})\n";
    }
    
    echo "\n✨ Cache implementation complete!\n";
    echo "🎯 Benefits achieved:\n";
    echo "   • Automatic rate limiting bypass\n";
    echo "   • Significant performance improvement\n";
    echo "   • Reduced API costs\n";
    echo "   • Multi-driver support with auto-detection\n";
    echo "   • Zero-config setup with smart defaults\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "📝 Make sure you have:\n";
    echo "   • Valid API key in WIOEX_API_KEY environment variable\n";
    echo "   • Redis/Memcached installed for optimal performance\n";
    echo "   • PHP extensions: redis, memcached (optional but recommended)\n";
}