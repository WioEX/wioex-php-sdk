<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Wioex\SDK\Client;
use Wioex\SDK\Config;
use Wioex\SDK\Cache\CacheManager;
use Wioex\SDK\Cache\Drivers\FileDriver;
use Wioex\SDK\Cache\Drivers\MemoryDriver;

/**
 * Advanced Caching Example
 * 
 * This example demonstrates the comprehensive caching system including
 * multiple drivers, tagging, prefixing, and advanced cache operations.
 */

// Initialize the WioEX SDK with advanced caching configuration
$config = Config::create([
    'api_key' => 'your_api_key_here',
    'base_url' => 'https://api.wioex.com',
    'cache' => [
        'default' => 'file',
        'drivers' => [
            'memory' => [
                'driver' => 'memory',
                'config' => []
            ],
            'file' => [
                'driver' => 'file',
                'config' => [
                    'cache_dir' => __DIR__ . '/cache',
                    'extension' => '.cache',
                    'dir_permissions' => 0755,
                    'file_permissions' => 0644,
                ]
            ]
        ]
    ]
]);

$client = new Client($config);

echo "=== WioEX Advanced Caching Example ===\n\n";

try {
    // 1. Basic Cache Operations
    echo "1. Basic Cache Operations:\n";
    
    $cache = $client->getCache();
    
    // Store and retrieve data
    echo "   a) Basic set/get operations...\n";
    $cache->set('user_preferences', ['theme' => 'dark', 'language' => 'en'], 3600);
    $preferences = $cache->get('user_preferences');
    echo "   ✓ Stored and retrieved user preferences\n";
    echo "   📝 Theme: {$preferences['theme']}, Language: {$preferences['language']}\n\n";

    // Check existence and TTL
    echo "   b) Existence and TTL checks...\n";
    $exists = $cache->has('user_preferences');
    $ttl = $cache->getTtl('user_preferences');
    echo "   ✓ Key exists: " . ($exists ? 'Yes' : 'No') . "\n";
    echo "   ⏰ TTL remaining: {$ttl} seconds\n\n";

    // 2. Multiple Cache Drivers
    echo "2. Multiple Cache Drivers:\n";
    
    // Access different drivers
    $fileCache = $cache->driver('file');
    $memoryCache = $cache->driver('memory');
    
    echo "   a) File cache operations...\n";
    $fileCache->set('persistent_data', ['stocks' => ['AAPL', 'GOOGL']], 7200);
    echo "   ✓ Data stored in file cache\n";
    
    echo "   b) Memory cache operations...\n";
    $memoryCache->set('session_data', ['user_id' => 123, 'login_time' => time()], 1800);
    echo "   ✓ Data stored in memory cache\n";
    
    // Driver information
    $fileInfo = $fileCache->getDriverInfo();
    $memoryInfo = $memoryCache->getDriverInfo();
    
    echo "   📊 File driver: {$fileInfo['description']} (persistent: " . 
         ($fileInfo['persistent'] ? 'Yes' : 'No') . ")\n";
    echo "   📊 Memory driver: {$memoryInfo['description']} (persistent: " . 
         ($memoryInfo['persistent'] ? 'Yes' : 'No') . ")\n\n";

    // 3. Tagged Cache Operations
    echo "3. Tagged Cache Operations:\n";
    
    // Create tagged cache instances
    $stocksCache = $cache->tags(['stocks', 'market_data']);
    $userCache = $cache->tags(['user', 'preferences']);
    
    echo "   a) Storing tagged data...\n";
    $stocksCache->set('AAPL_quote', ['price' => 150.00, 'change' => 2.5], 300);
    $stocksCache->set('GOOGL_quote', ['price' => 2800.00, 'change' => -15.2], 300);
    $userCache->set('user_123_settings', ['notifications' => true], 3600);
    
    echo "   ✓ Stored quotes with 'stocks' and 'market_data' tags\n";
    echo "   ✓ Stored user settings with 'user' and 'preferences' tags\n";
    
    // Retrieve tagged data
    $aaplQuote = $stocksCache->get('AAPL_quote');
    echo "   📈 AAPL: \${$aaplQuote['price']} ({$aaplQuote['change']})\n";
    
    // Flush specific tags
    echo "   b) Flushing tagged data...\n";
    $stocksCache->flushTag('stocks');
    echo "   ✓ Flushed all 'stocks' tagged data\n";
    
    $aaplAfterFlush = $stocksCache->get('AAPL_quote');
    echo "   📉 AAPL after flush: " . ($aaplAfterFlush ? 'Still exists' : 'Deleted') . "\n\n";

    // 4. Prefixed Cache Operations
    echo "4. Prefixed Cache Operations:\n";
    
    // Create prefixed cache instances
    $apiCache = $cache->prefix('api_responses');
    $analyticsCache = $cache->prefix('analytics');
    
    echo "   a) Storing prefixed data...\n";
    $apiCache->set('stocks_list', ['AAPL', 'GOOGL', 'MSFT'], 1800);
    $apiCache->set('forex_rates', ['EUR' => 1.1, 'GBP' => 1.3], 3600);
    $analyticsCache->set('page_views', 1250, 86400);
    $analyticsCache->set('unique_visitors', 890, 86400);
    
    echo "   ✓ Stored API responses with 'api_responses' prefix\n";
    echo "   ✓ Stored analytics data with 'analytics' prefix\n";
    
    // Namespace operations
    $stocksNamespace = $apiCache->namespace('stocks');
    $stocksNamespace->set('trending', ['TSLA', 'AMD', 'NVDA'], 900);
    echo "   ✓ Stored trending stocks in nested namespace\n";
    
    // Get prefixed keys
    $apiKeys = $apiCache->getKeys();
    echo "   🔑 API cache keys: " . implode(', ', $apiKeys) . "\n\n";

    // 5. Bulk Operations
    echo "5. Bulk Operations:\n";
    
    echo "   a) Multiple set operations...\n";
    $bulkData = [
        'stock_AAPL' => ['price' => 150, 'volume' => 1000000],
        'stock_GOOGL' => ['price' => 2800, 'volume' => 500000],
        'stock_MSFT' => ['price' => 300, 'volume' => 800000],
    ];
    
    $cache->setMultiple($bulkData, 600);
    echo "   ✓ Stored " . count($bulkData) . " stock records\n";
    
    echo "   b) Multiple get operations...\n";
    $keys = array_keys($bulkData);
    $retrieved = $cache->getMultiple($keys);
    echo "   ✓ Retrieved " . count($retrieved) . " records\n";
    
    foreach ($retrieved as $key => $data) {
        if ($data) {
            $symbol = str_replace('stock_', '', $key);
            echo "   📊 {$symbol}: \${$data['price']} (Vol: " . number_format($data['volume']) . ")\n";
        }
    }
    echo "\n";

    // 6. Advanced Operations
    echo "6. Advanced Operations:\n";
    
    echo "   a) Increment/Decrement operations...\n";
    $cache->set('page_views', 100);
    $newViews = $cache->increment('page_views', 5);
    echo "   ✓ Page views incremented to: {$newViews}\n";
    
    $cache->set('stock_price', 150.50);
    $newPrice = $cache->decrement('stock_price', 2.25);
    echo "   ✓ Stock price decremented to: {$newPrice}\n";
    
    echo "   b) Remember operations...\n";
    $expensiveData = $cache->remember('expensive_calculation', function() {
        // Simulate expensive operation
        usleep(100000); // 100ms delay
        return ['result' => mt_rand(1000, 9999), 'calculated_at' => time()];
    }, 1800);
    
    echo "   ✓ Expensive calculation result: {$expensiveData['result']}\n";
    echo "   ⏰ Calculated at: " . date('H:i:s', $expensiveData['calculated_at']) . "\n";
    
    // Second call should be from cache
    $start = microtime(true);
    $cachedData = $cache->remember('expensive_calculation', function() {
        return ['result' => 'should not execute'];
    }, 1800);
    $duration = (microtime(true) - $start) * 1000;
    
    echo "   ⚡ Second call duration: " . number_format($duration, 2) . "ms (from cache)\n\n";

    // 7. Cache Statistics and Health
    echo "7. Cache Statistics and Health:\n";
    
    $stats = $cache->getStatistics();
    
    echo "   a) Overall statistics...\n";
    echo "   📊 Default driver: {$stats['default_driver']}\n";
    echo "   🔧 Available drivers: " . implode(', ', $stats['available_drivers']) . "\n";
    
    foreach ($stats['drivers'] as $driverName => $driverStats) {
        echo "   \n   {$driverName} driver statistics:\n";
        echo "     • Total requests: {$driverStats['total_requests']}\n";
        echo "     • Hit rate: {$driverStats['hit_rate_percentage']}%\n";
        echo "     • Hits: {$driverStats['hits']}\n";
        echo "     • Misses: {$driverStats['misses']}\n";
        
        if (isset($driverStats['item_count'])) {
            echo "     • Items cached: {$driverStats['item_count']}\n";
        }
        
        if (isset($driverStats['disk_usage_bytes'])) {
            echo "     • Disk usage: " . number_format($driverStats['disk_usage_bytes']) . " bytes\n";
        }
    }
    
    echo "\n   b) Health check...\n";
    $healthCheck = $cache->getAllDriversHealth();
    
    foreach ($healthCheck as $driverName => $health) {
        $status = $health['healthy'] ? '✅ Healthy' : '❌ Unhealthy';
        echo "   {$driverName} driver: {$status}\n";
    }
    echo "\n";

    // 8. Cache Maintenance
    echo "8. Cache Maintenance:\n";
    
    echo "   a) Flushing expired items...\n";
    $expiredCount = $cache->flushExpiredAllDrivers();
    foreach ($expiredCount as $driver => $count) {
        echo "   🧹 {$driver}: Removed {$count} expired items\n";
    }
    
    echo "   b) Cache sizes...\n";
    foreach ($stats['drivers'] as $driverName => $driverStats) {
        if (isset($driverStats['item_count'])) {
            echo "   📦 {$driverName}: {$driverStats['item_count']} items\n";
        }
    }
    echo "\n";

    // 9. Real-world Integration Example
    echo "9. Real-world Integration Example:\n\n";
    
    echo "```php\n";
    echo "class StockService {\n";
    echo "    private \$client;\n";
    echo "    private \$cache;\n";
    echo "    \n";
    echo "    public function __construct(\$client) {\n";
    echo "        \$this->client = \$client;\n";
    echo "        \$this->cache = \$client->getCache()->tags(['stocks']);\n";
    echo "    }\n";
    echo "    \n";
    echo "    public function getQuote(string \$symbol): array {\n";
    echo "        return \$this->cache->remember(\"quote_{\$symbol}\", function() use (\$symbol) {\n";
    echo "            return \$this->client->stocks()->quote(\$symbol)->data();\n";
    echo "        }, 300); // 5 minutes\n";
    echo "    }\n";
    echo "    \n";
    echo "    public function getHistoricalData(string \$symbol, string \$period): array {\n";
    echo "        \$key = \"historical_{\$symbol}_{\$period}\";\n";
    echo "        \n";
    echo "        return \$this->cache->remember(\$key, function() use (\$symbol, \$period) {\n";
    echo "            return \$this->client->stocks()->timeline(\$symbol, [\n";
    echo "                'period' => \$period\n";
    echo "            ])->data();\n";
    echo "        }, 3600); // 1 hour\n";
    echo "    }\n";
    echo "    \n";
    echo "    public function invalidateStock(string \$symbol): void {\n";
    echo "        \$this->cache->forget(\"quote_{\$symbol}\");\n";
    echo "        \$this->cache->forgetMany([\n";
    echo "            \"historical_{\$symbol}_1d\",\n";
    echo "            \"historical_{\$symbol}_1w\",\n";
    echo "            \"historical_{\$symbol}_1m\",\n";
    echo "        ]);\n";
    echo "    }\n";
    echo "}\n";
    echo "```\n\n";

    // 10. Performance Comparison
    echo "10. Performance Comparison:\n";
    
    $iterations = 1000;
    $testKey = 'performance_test';
    $testData = array_fill(0, 100, mt_rand());
    
    // Memory driver test
    $memoryCache->clear();
    $start = microtime(true);
    for ($i = 0; $i < $iterations; $i++) {
        $memoryCache->set($testKey . $i, $testData);
    }
    $memoryWriteTime = (microtime(true) - $start) * 1000;
    
    $start = microtime(true);
    for ($i = 0; $i < $iterations; $i++) {
        $memoryCache->get($testKey . $i);
    }
    $memoryReadTime = (microtime(true) - $start) * 1000;
    
    // File driver test
    $fileCache->clear();
    $start = microtime(true);
    for ($i = 0; $i < $iterations; $i++) {
        $fileCache->set($testKey . $i, $testData);
    }
    $fileWriteTime = (microtime(true) - $start) * 1000;
    
    $start = microtime(true);
    for ($i = 0; $i < $iterations; $i++) {
        $fileCache->get($testKey . $i);
    }
    $fileReadTime = (microtime(true) - $start) * 1000;
    
    echo "   Performance test ({$iterations} operations):\n";
    echo "   \n";
    echo "   Memory Driver:\n";
    echo "     • Write: " . number_format($memoryWriteTime, 2) . "ms\n";
    echo "     • Read:  " . number_format($memoryReadTime, 2) . "ms\n";
    echo "   \n";
    echo "   File Driver:\n";
    echo "     • Write: " . number_format($fileWriteTime, 2) . "ms\n";
    echo "     • Read:  " . number_format($fileReadTime, 2) . "ms\n\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    
    if (strpos($e->getMessage(), 'permission') !== false) {
        echo "💡 Check file permissions for cache directory\n";
    } elseif (strpos($e->getMessage(), 'disk space') !== false) {
        echo "💡 Check available disk space\n";
    }
}

echo "\n=== Example completed ===\n";
echo "\n💡 Caching Best Practices:\n";
echo "- Choose appropriate TTL values based on data volatility\n";
echo "- Use tags to group related cache entries for easy invalidation\n";
echo "- Implement cache warming strategies for critical data\n";
echo "- Monitor cache hit rates and adjust strategies accordingly\n";
echo "- Use remember() patterns to simplify cache-or-compute logic\n";
echo "- Consider memory vs persistence trade-offs when choosing drivers\n";
echo "- Implement proper cache invalidation strategies\n";
echo "- Use prefixes/namespaces to avoid key collisions\n";