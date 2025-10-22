<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Wioex\SDK\Config;
use Wioex\SDK\Async\AsyncClient;
use Wioex\SDK\Async\EventLoop;
use Wioex\SDK\Async\Promise;
use Wioex\SDK\Enums\AsyncOperationType;

/**
 * Promise-based Async Support Example
 * 
 * This example demonstrates the Promise-based async functionality
 * including concurrent requests, batch processing, and event loop management.
 */

echo "=== WioEX Promise-based Async Support Example ===\n\n";

// Initialize the async client
$config = Config::create([
    'api_key' => 'your_api_key_here',
    'base_url' => 'https://api.wioex.com',
    'timeout' => 30,
]);

$eventLoop = new EventLoop();
$asyncClient = new AsyncClient($config, $eventLoop);

echo "1. Basic Promise Operations:\n";

try {
    // Create a simple promise
    $basicPromise = Promise::resolve('Hello, World!');
    $result = $asyncClient->wait($basicPromise);
    echo "   ✓ Basic promise resolved: {$result}\n";
    
    // Promise chaining
    $chainedPromise = Promise::resolve(10)
        ->then(fn($value) => $value * 2)
        ->then(fn($value) => $value + 5)
        ->then(fn($value) => "Final result: {$value}");
    
    $chainResult = $asyncClient->wait($chainedPromise);
    echo "   ✓ Chained promise: {$chainResult}\n\n";
    
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n\n";
}

echo "2. Async HTTP Requests:\n";

try {
    // Single async request
    echo "   a) Single async request...\n";
    $quotePromise = $asyncClient->getAsync('/v2/stocks/quote', ['symbol' => 'AAPL']);
    
    $quotePromise->then(
        function($response) {
            $data = $response->data();
            echo "      ✓ AAPL Quote: \${$data['price']} ({$data['change_percent']}%)\n";
        },
        function($error) {
            echo "      ❌ Request failed: " . $error->getMessage() . "\n";
        }
    );
    
    // Wait for completion
    $asyncClient->wait($quotePromise);
    
    // Multiple concurrent requests
    echo "   b) Concurrent requests...\n";
    $symbols = ['AAPL', 'GOOGL', 'MSFT'];
    $promises = [];
    
    foreach ($symbols as $symbol) {
        $promises[$symbol] = $asyncClient->getAsync('/v2/stocks/quote', ['symbol' => $symbol]);
    }
    
    $allResults = Promise::allSettled($promises);
    $results = $asyncClient->wait($allResults);
    
    foreach ($results as $symbol => $result) {
        if ($result['status'] === 'fulfilled') {
            $data = $result['value']->data();
            echo "      ✓ {$symbol}: \${$data['price']}\n";
        } else {
            echo "      ❌ {$symbol}: Failed\n";
        }
    }
    echo "\n";
    
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n\n";
}

echo "3. Bulk Operations with Promises:\n";

try {
    $bulkRequests = [
        ['method' => 'GET', 'path' => '/v2/stocks/quote', 'options' => ['query' => ['symbol' => 'AAPL']]],
        ['method' => 'GET', 'path' => '/v2/stocks/quote', 'options' => ['query' => ['symbol' => 'GOOGL']]],
        ['method' => 'GET', 'path' => '/v2/stocks/quote', 'options' => ['query' => ['symbol' => 'MSFT']]],
    ];
    
    $bulkPromise = $asyncClient->bulkAsync($bulkRequests);
    $bulkResults = $asyncClient->wait($bulkPromise);
    
    echo "   ✓ Bulk operation completed\n";
    echo "   📊 Successful requests: " . count(array_filter($bulkResults, fn($r) => $r['status'] === 'fulfilled')) . "\n";
    echo "   📊 Failed requests: " . count(array_filter($bulkResults, fn($r) => $r['status'] === 'rejected')) . "\n\n";
    
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n\n";
}

echo "4. Batch Processing with Concurrency Control:\n";

try {
    $batchRequests = [];
    $symbols = ['AAPL', 'GOOGL', 'MSFT', 'AMZN', 'TSLA', 'META'];
    
    foreach ($symbols as $symbol) {
        $batchRequests[] = [
            'method' => 'GET',
            'path' => '/v2/stocks/quote',
            'options' => ['query' => ['symbol' => $symbol]]
        ];
    }
    
    $batchPromise = $asyncClient->batchAsync($batchRequests, 3); // Concurrency of 3
    $batchResults = $asyncClient->wait($batchPromise);
    
    echo "   ✓ Batch processing completed\n";
    echo "   📊 Total requests: {$batchResults['total']}\n";
    echo "   📊 Completed: {$batchResults['completed']}\n";
    echo "   📊 Successful: " . count($batchResults['results']) . "\n";
    echo "   📊 Failed: " . count($batchResults['errors']) . "\n\n";
    
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n\n";
}

echo "5. Timeout and Retry Operations:\n";

try {
    // Timeout example
    echo "   a) Timeout operation...\n";
    $slowPromise = $asyncClient->delayAsync(2000); // 2 second delay
    $timeoutPromise = $asyncClient->timeoutAsync($slowPromise, 1000); // 1 second timeout
    
    try {
        $asyncClient->wait($timeoutPromise);
        echo "      ✓ Operation completed within timeout\n";
    } catch (Exception $e) {
        echo "      ⏰ Operation timed out (expected)\n";
    }
    
    // Retry example
    echo "   b) Retry operation...\n";
    $attempts = 0;
    $retryOperation = function() use (&$attempts) {
        $attempts++;
        if ($attempts < 3) {
            throw new Exception("Attempt {$attempts} failed");
        }
        return Promise::resolve("Success on attempt {$attempts}");
    };
    
    $retryPromise = $asyncClient->retryAsync($retryOperation, 5, 100);
    $retryResult = $asyncClient->wait($retryPromise);
    echo "      ✓ Retry result: {$retryResult}\n\n";
    
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n\n";
}

echo "6. Event Loop Management:\n";

$eventLoop = $asyncClient->getEventLoop();
$stats = $eventLoop->getStatistics();

echo "   📊 Event Loop Statistics:\n";
echo "     • State: " . $stats['state']['value'] . "\n";
echo "     • Total ticks: " . $stats['total_ticks'] . "\n";
echo "     • Total operations: " . $stats['total_operations'] . "\n";
echo "     • Pending timers: " . $stats['pending_timers'] . "\n";
echo "     • Pending callbacks: " . $stats['pending_callbacks'] . "\n";
echo "     • Pending operations: " . $stats['pending_operations'] . "\n";
echo "     • Average tick time: " . number_format($stats['avg_tick_time'] * 1000, 2) . "ms\n";

$healthMetrics = $eventLoop->getHealthMetrics();
echo "   🏥 Health Status: " . ($healthMetrics['is_healthy'] ? 'Healthy' : 'Unhealthy') . "\n";
echo "   ⚡ Performance:\n";
echo "     • Avg tick time: " . number_format($healthMetrics['performance']['avg_tick_time_ms'], 2) . "ms\n";
echo "     • Max tick time: " . number_format($healthMetrics['performance']['max_tick_time_ms'], 2) . "ms\n";
echo "     • Ticks per second: " . number_format($healthMetrics['performance']['ticks_per_second'], 1) . "\n\n";

echo "7. Advanced Promise Patterns:\n";

try {
    // Promise.all - wait for all to complete
    echo "   a) Promise.all pattern...\n";
    $allPromises = [
        Promise::resolve('First'),
        Promise::resolve('Second'),
        Promise::resolve('Third'),
    ];
    
    $allResult = Promise::all($allPromises);
    $allValues = $asyncClient->wait($allResult);
    echo "      ✓ All values: " . implode(', ', $allValues) . "\n";
    
    // Promise.race - first to complete wins
    echo "   b) Promise.race pattern...\n";
    $racePromises = [
        $asyncClient->delayAsync(100)->then(fn() => 'Fast'),
        $asyncClient->delayAsync(200)->then(fn() => 'Slow'),
    ];
    
    $raceResult = Promise::race($racePromises);
    $winner = $asyncClient->wait($raceResult);
    echo "      ✓ Race winner: {$winner}\n";
    
    // Promise.any - first successful result
    echo "   c) Promise.any pattern...\n";
    $anyPromises = [
        Promise::reject('Error 1'),
        Promise::reject('Error 2'),
        Promise::resolve('Success!'),
    ];
    
    $anyResult = Promise::any($anyPromises);
    $anyValue = $asyncClient->wait($anyResult);
    echo "      ✓ Any result: {$anyValue}\n\n";
    
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n\n";
}

echo "8. Real-world Example - Portfolio Monitoring:\n";

try {
    class AsyncPortfolioMonitor {
        private AsyncClient $client;
        
        public function __construct(AsyncClient $client) {
            $this->client = $client;
        }
        
        public function monitorPortfolio(array $symbols): Promise {
            $promises = [];
            
            foreach ($symbols as $symbol) {
                $promises[$symbol] = $this->client->getAsync('/v2/stocks/quote', ['symbol' => $symbol])
                    ->then(function($response) use ($symbol) {
                        $data = $response->data();
                        return [
                            'symbol' => $symbol,
                            'price' => $data['price'] ?? 0,
                            'change' => $data['change_percent'] ?? 0,
                            'status' => 'success'
                        ];
                    })
                    ->catch(function($error) use ($symbol) {
                        return [
                            'symbol' => $symbol,
                            'error' => $error->getMessage(),
                            'status' => 'error'
                        ];
                    });
            }
            
            return Promise::allSettled($promises);
        }
    }
    
    $monitor = new AsyncPortfolioMonitor($asyncClient);
    $portfolioSymbols = ['AAPL', 'GOOGL', 'MSFT'];
    
    $monitoringPromise = $monitor->monitorPortfolio($portfolioSymbols);
    $portfolioResults = $asyncClient->wait($monitoringPromise);
    
    echo "   📊 Portfolio Monitoring Results:\n";
    foreach ($portfolioResults as $symbol => $result) {
        if ($result['status'] === 'fulfilled') {
            $data = $result['value'];
            if ($data['status'] === 'success') {
                echo "      ✓ {$data['symbol']}: \${$data['price']} ({$data['change']}%)\n";
            } else {
                echo "      ❌ {$data['symbol']}: {$data['error']}\n";
            }
        }
    }
    echo "\n";
    
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n\n";
}

echo "9. Performance and Best Practices:\n";

echo "   💡 Async Best Practices:\n";
echo "   • Use Promise.all() for concurrent operations\n";
echo "   • Implement proper error handling with .catch()\n";
echo "   • Set appropriate timeouts for operations\n";
echo "   • Use batch processing for large datasets\n";
echo "   • Monitor event loop health for performance\n";
echo "   • Clean up timers and callbacks when done\n";
echo "   • Use retry logic for resilient operations\n\n";

echo "   📊 Current Performance Metrics:\n";
$finalStats = $asyncClient->getEventLoop()->getStatistics();
echo "   • Total operations processed: " . $finalStats['total_operations'] . "\n";
echo "   • Pending requests: " . $asyncClient->getPendingRequestCount() . "\n";
echo "   • Event loop uptime: " . number_format($finalStats['uptime_seconds'], 2) . "s\n\n";

// Cleanup
$asyncClient->cancelAllPendingRequests();
$eventLoop->reset();

echo "=== Async Example Completed ===\n";
echo "\n🎉 Promise-based async support features demonstrated:\n";
echo "• Basic Promise operations and chaining\n";
echo "• Async HTTP requests with concurrency\n";
echo "• Bulk and batch operations\n";
echo "• Timeout and retry mechanisms\n";
echo "• Event loop management and monitoring\n";
echo "• Advanced Promise patterns (all, race, any)\n";
echo "• Real-world portfolio monitoring example\n";
echo "• Performance optimization and best practices\n";
echo "\n💡 Ready for production async operations!\n";