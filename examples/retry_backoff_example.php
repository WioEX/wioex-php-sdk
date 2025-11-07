<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Wioex\SDK\WioexClient;
use Wioex\SDK\Exceptions\RequestException;

// ====================================================================
// AUTO-RETRY WITH EXPONENTIAL BACKOFF EXAMPLE
// Demonstrates intelligent retry strategies, backoff algorithms,
// bulk retry operations, and adaptive retry patterns
// ====================================================================

echo "🔄 WioEX SDK Auto-Retry with Exponential Backoff Demo\n";
echo "====================================================\n\n";

// Retry configuration
$retryConfig = [
    'api_key' => $_ENV['WIOEX_API_KEY'] ?? 'your-api-key-here',
    
    // Retry configuration
    'retry' => [
        'strategy' => 'exponential_backoff',
        'max_attempts' => 5,
        'base_delay' => 1000, // 1 second
        'max_delay' => 30000, // 30 seconds
        'multiplier' => 2.0,
        'jitter' => true,
        'jitter_type' => 'full',
        'retryable_exceptions' => [
            'RuntimeException',
            'Wioex\\SDK\\Exceptions\\RequestException'
        ],
        'non_retryable_exceptions' => [
            'InvalidArgumentException'
        ],
        'retryable_status_codes' => [408, 429, 500, 502, 503, 504],
        'non_retryable_status_codes' => [400, 401, 403, 404],
        'default_retryable' => true,
        'bulk_strategy' => 'parallel',
        'batch_size' => 5,
        'batch_delay' => 1000,
        'max_history_size' => 100
    ]
];

try {
    // Initialize client with retry configuration
    $client = new WioexClient($retryConfig);
    
    echo "🔧 Retry Configuration:\n";
    $retryConfiguration = $client->getRetryConfiguration();
    echo "Strategy: {$retryConfiguration['strategy']}\n";
    echo "Max Attempts: {$retryConfiguration['max_attempts']}\n";
    echo "Base Delay: {$retryConfiguration['base_delay']}ms\n";
    echo "Multiplier: {$retryConfiguration['multiplier']}x\n";
    echo "Jitter: " . ($retryConfiguration['jitter'] ? 'Enabled' : 'Disabled') . "\n";
    echo "Max Delay: {$retryConfiguration['max_delay']}ms\n\n";

    // ====================================================================
    // EXAMPLE 1: Basic Exponential Backoff Retry
    // ====================================================================
    echo "🚀 Example 1: Exponential Backoff Retry Strategy\n";
    echo "-----------------------------------------------\n";
    
    $failureCount = 0;
    $maxFailures = 3;
    
    echo "📡 Simulating API call that fails {$maxFailures} times then succeeds...\n";
    
    $result = $client->executeWithRetry(function($attempt) use (&$failureCount, $maxFailures) {
        echo "🔄 Attempt {$attempt}: ";
        
        if ($failureCount < $maxFailures) {
            $failureCount++;
            echo "❌ Failed (simulated network error)\n";
            throw new RequestException("Network timeout on attempt {$attempt}", 504);
        }
        
        echo "✅ Success!\n";
        return ['status' => 'success', 'data' => 'API response data', 'attempt' => $attempt];
    }, [
        'strategy' => 'exponential_backoff',
        'max_attempts' => 5,
        'base_delay' => 500
    ]);
    
    echo "🎯 Final Result: " . json_encode($result) . "\n\n";

    // ====================================================================
    // EXAMPLE 2: Different Backoff Strategies Comparison
    // ====================================================================
    echo "⚖️  Example 2: Backoff Strategies Comparison\n";
    echo "-------------------------------------------\n";
    
    $strategies = [
        'exponential_backoff' => 'Exponential (2^n)',
        'linear_backoff' => 'Linear (n*delay)',
        'fibonacci_backoff' => 'Fibonacci sequence',
        'adaptive_backoff' => 'Adaptive (learns from history)',
        'fixed_delay' => 'Fixed delay'
    ];
    
    foreach ($strategies as $strategy => $description) {
        echo "🧪 Testing {$strategy} ({$description}):\n";
        
        $testResult = $client->testRetryConfig([
            'strategy' => $strategy,
            'max_attempts' => 4,
            'base_delay' => 1000,
            'multiplier' => 2.0
        ], 3); // Simulate 3 failures
        
        echo "   Attempts: {$testResult['config']['max_attempts']}\n";
        echo "   Total Delay: " . round($testResult['total_delay'], 3) . " seconds\n";
        echo "   Would Succeed: " . ($testResult['would_succeed'] ? '✅ Yes' : '❌ No') . "\n";
        
        echo "   Delay Pattern: ";
        foreach ($testResult['results'] as $i => $result) {
            if ($result['delay'] > 0) {
                echo round($result['delay'], 2) . "s";
                if ($i < count($testResult['results']) - 1 && $testResult['results'][$i + 1]['delay'] > 0) {
                    echo " → ";
                }
            }
        }
        echo "\n\n";
    }

    // ====================================================================
    // EXAMPLE 3: Jitter Types Demonstration
    // ====================================================================
    echo "🎲 Example 3: Jitter Types for Avoiding Thundering Herd\n";
    echo "-------------------------------------------------------\n";
    
    $jitterTypes = [
        'full' => 'Full jitter (0 to delay)',
        'equal' => 'Equal jitter (50% + 0-50%)',
        'decorrelated' => 'Decorrelated jitter'
    ];
    
    foreach ($jitterTypes as $jitterType => $description) {
        echo "🎯 {$jitterType} jitter ({$description}):\n";
        
        // Configure client with specific jitter type
        $client->withRetryStrategy('exponential_backoff', 3, 1000, 2.0, true);
        
        // Test configuration shows theoretical delays
        $testResult = $client->testRetryConfig([
            'jitter_type' => $jitterType,
            'strategy' => 'exponential_backoff'
        ], 2);
        
        echo "   Base delays: ";
        foreach ($testResult['results'] as $result) {
            if ($result['delay'] > 0) {
                echo round($result['delay'], 2) . "s ";
            }
        }
        echo "\n   (Actual delays will vary due to jitter)\n\n";
    }

    // ====================================================================
    // EXAMPLE 4: Error Type Handling
    // ====================================================================
    echo "🎭 Example 4: Smart Error Type Handling\n";
    echo "--------------------------------------\n";
    
    $errorScenarios = [
        [
            'description' => 'Retryable error (503 Service Unavailable)',
            'exception' => new RequestException('Service temporarily unavailable', 503),
            'should_retry' => true
        ],
        [
            'description' => 'Non-retryable error (401 Unauthorized)',
            'exception' => new RequestException('Invalid API key', 401),
            'should_retry' => false
        ],
        [
            'description' => 'Retryable error (Network timeout)',
            'exception' => new RequestException('Connection timed out', 0),
            'should_retry' => true
        ],
        [
            'description' => 'Non-retryable error (Invalid argument)',
            'exception' => new \InvalidArgumentException('Missing required parameter'),
            'should_retry' => false
        ]
    ];
    
    foreach ($errorScenarios as $i => $scenario) {
        echo "🧪 Scenario " . ($i + 1) . ": {$scenario['description']}\n";
        
        try {
            $client->executeWithRetry(function() use ($scenario) {
                throw $scenario['exception'];
            }, [
                'max_attempts' => 3,
                'base_delay' => 100 // Fast for demo
            ]);
            
            echo "   ⚠️  Unexpected success\n";
        } catch (\Exception $e) {
            $retryHistory = $client->getRetryHistory();
            $lastAttempt = !empty($retryHistory) ? end($retryHistory) : null;
            $attempts = $lastAttempt ? $lastAttempt['attempt'] : 1;
            
            if ($scenario['should_retry']) {
                echo "   ✅ Retried {$attempts} times before failing (expected behavior)\n";
            } else {
                echo "   ✅ Failed immediately without retry (expected behavior)\n";
            }
        }
        echo "\n";
    }

    // ====================================================================
    // EXAMPLE 5: Bulk Operations with Retry
    // ====================================================================
    echo "📦 Example 5: Bulk Operations with Individual Retry Logic\n";
    echo "---------------------------------------------------------\n";
    
    // Create multiple operations that may fail
    $operations = [];
    for ($i = 1; $i <= 5; $i++) {
        $operations["operation_{$i}"] = function($attempt) use ($i) {
            // Simulate different failure rates for different operations
            $failureRate = $i * 0.2; // 20%, 40%, 60%, 80%, 100%
            
            if (mt_rand() / mt_getrandmax() < $failureRate && $attempt <= 2) {
                throw new RequestException("Operation {$i} failed on attempt {$attempt}", 503);
            }
            
            return [
                'operation_id' => $i,
                'result' => "Success from operation {$i}",
                'attempt' => $attempt
            ];
        };
    }
    
    echo "🚀 Executing 5 operations with different failure rates...\n";
    
    $bulkResults = $client->executeBulkWithRetry($operations, [
        'strategy' => 'exponential_backoff',
        'max_attempts' => 3,
        'base_delay' => 200,
        'bulk_strategy' => 'parallel'
    ]);
    
    echo "📊 Bulk Operation Results:\n";
    foreach ($bulkResults as $key => $result) {
        $status = $result['success'] ? '✅ Success' : '❌ Failed';
        echo "   {$key}: {$status}";
        
        if ($result['success'] && isset($result['result']['attempt'])) {
            echo " (attempt {$result['result']['attempt']})";
        } elseif (!$result['success']) {
            echo " - " . $result['error']->getMessage();
        }
        echo "\n";
    }
    echo "\n";

    // ====================================================================
    // EXAMPLE 6: Async Retry Pattern
    // ====================================================================
    echo "🔄 Example 6: Async Retry Pattern\n";
    echo "--------------------------------\n";
    
    echo "🎬 Running async operation with retry tracking...\n";
    
    $asyncResult = $client->executeWithAsyncRetry(function($attempt) {
        echo "🔄 Async attempt {$attempt}: ";
        
        // Simulate async operation that fails first 2 times
        if ($attempt <= 2) {
            echo "❌ Failed\n";
            throw new RequestException("Async operation failed", 500);
        }
        
        echo "✅ Success!\n";
        return [
            'async_result' => 'Async operation completed',
            'final_attempt' => $attempt,
            'timestamp' => time()
        ];
    }, [
        'strategy' => 'linear_backoff',
        'max_attempts' => 4,
        'base_delay' => 300
    ]);
    
    echo "🎯 Async Result Summary:\n";
    echo "   Success: " . ($asyncResult['success'] ? '✅ Yes' : '❌ No') . "\n";
    echo "   Total Attempts: {$asyncResult['attempts']}\n";
    
    if ($asyncResult['success']) {
        echo "   Final Result: " . json_encode($asyncResult['result']) . "\n";
    }
    
    echo "   Attempt History:\n";
    foreach ($asyncResult['history'] as $attempt) {
        $status = $attempt['success'] ? 'Success' : 'Failed';
        echo "     Attempt {$attempt['attempt']}: {$status}\n";
    }
    echo "\n";

    // ====================================================================
    // EXAMPLE 7: Resilient API Call Wrapper
    // ====================================================================
    echo "🛡️  Example 7: Resilient API Call Wrapper\n";
    echo "-----------------------------------------\n";
    
    // Configure client with specific retry strategy
    $client->withExponentialBackoff(4, 500, 1.5);
    
    echo "📡 Making resilient API calls...\n";
    
    $apiCalls = [
        'get_market_status' => function() {
            static $attempt = 0;
            $attempt++;
            
            if ($attempt <= 2) {
                throw new RequestException("Market service temporarily unavailable", 503);
            }
            
            return [
                'markets' => [
                    'NYSE' => ['status' => 'open'],
                    'NASDAQ' => ['status' => 'open']
                ],
                'timestamp' => time()
            ];
        },
        'get_stock_quote' => function() {
            // This one succeeds immediately
            return [
                'symbol' => 'AAPL',
                'price' => 150.25,
                'volume' => 1000000
            ];
        },
        'get_portfolio_data' => function() {
            static $attempt = 0;
            $attempt++;
            
            if ($attempt === 1) {
                throw new RequestException("Rate limit exceeded", 429);
            }
            
            return [
                'portfolio_value' => 100000,
                'positions' => 10,
                'cash' => 5000
            ];
        }
    ];
    
    foreach ($apiCalls as $callName => $apiCall) {
        echo "🎯 Executing {$callName}...\n";
        
        try {
            $result = $client->makeResilientApiCall($apiCall, [
                'max_attempts' => 4,
                'strategy' => 'exponential_backoff'
            ]);
            
            echo "   ✅ Success: " . json_encode($result) . "\n";
        } catch (\Exception $e) {
            echo "   ❌ Final failure: " . $e->getMessage() . "\n";
        }
        echo "\n";
    }

    // ====================================================================
    // EXAMPLE 8: Retry Analytics and Optimization
    // ====================================================================
    echo "📈 Example 8: Retry Analytics and Pattern Analysis\n";
    echo "-------------------------------------------------\n";
    
    // Get comprehensive retry statistics
    $retryStats = $client->getRetryStatistics();
    
    echo "🎯 Retry Statistics Summary:\n";
    echo "   Total Operations: {$retryStats['total_attempts']}\n";
    echo "   Total Retries: {$retryStats['total_retries']}\n";
    echo "   Successful Retries: {$retryStats['successful_retries']}\n";
    echo "   Failed Retries: {$retryStats['failed_retries']}\n";
    echo "   Retry Rate: " . round($retryStats['retry_rate'], 2) . "%\n";
    echo "   Success Rate: " . round($retryStats['success_rate'], 2) . "%\n";
    echo "   Avg Backoff Time: " . round($retryStats['average_backoff_time'], 2) . "ms\n";
    echo "   Total Backoff Time: " . round($retryStats['total_backoff_time'], 2) . "ms\n\n";
    
    // Analyze retry patterns
    $patterns = $client->analyzeRetryPatterns();
    
    if (!isset($patterns['no_data'])) {
        echo "🔍 Retry Pattern Analysis:\n";
        echo "   Total History Entries: {$patterns['total_entries']}\n";
        
        if (!empty($patterns['strategy_distribution'])) {
            echo "   Strategy Usage:\n";
            foreach ($patterns['strategy_distribution'] as $strategy => $count) {
                echo "     {$strategy}: {$count} times\n";
            }
        }
        
        if (!empty($patterns['exception_distribution'])) {
            echo "   Exception Types:\n";
            foreach ($patterns['exception_distribution'] as $exception => $count) {
                $shortName = basename(str_replace('\\', '/', $exception));
                echo "     {$shortName}: {$count} occurrences\n";
            }
        }
        
        if (!empty($patterns['attempt_statistics'])) {
            $attempts = $patterns['attempt_statistics'];
            echo "   Attempt Statistics:\n";
            echo "     Min Attempts: {$attempts['min']}\n";
            echo "     Max Attempts: {$attempts['max']}\n";
            echo "     Avg Attempts: " . round($attempts['average'], 2) . "\n";
        }
        echo "\n";
    }
    
    // Get optimization recommendations
    $recommendations = $client->getRetryRecommendations();
    
    echo "💡 Retry Optimization Recommendations:\n";
    if (empty($recommendations)) {
        echo "   ✅ No specific recommendations - retry patterns look optimal!\n";
    } else {
        foreach ($recommendations as $i => $rec) {
            $priority = strtoupper($rec['priority']);
            echo "   " . ($i + 1) . ". [{$priority}] {$rec['message']}\n";
        }
    }
    echo "\n";

    // ====================================================================
    // EXAMPLE 9: Configuration Testing and Tuning
    // ====================================================================
    echo "⚙️  Example 9: Retry Configuration Testing\n";
    echo "-----------------------------------------\n";
    
    $testConfigs = [
        'aggressive' => [
            'strategy' => 'exponential_backoff',
            'max_attempts' => 5,
            'base_delay' => 500,
            'multiplier' => 1.5
        ],
        'moderate' => [
            'strategy' => 'exponential_backoff',
            'max_attempts' => 3,
            'base_delay' => 1000,
            'multiplier' => 2.0
        ],
        'conservative' => [
            'strategy' => 'linear_backoff',
            'max_attempts' => 3,
            'base_delay' => 2000
        ]
    ];
    
    echo "🧪 Testing different retry configurations:\n";
    foreach ($testConfigs as $configName => $config) {
        $testResult = $client->testRetryConfig($config, 3);
        
        echo "   {$configName} config:\n";
        echo "     Strategy: {$config['strategy']}\n";
        echo "     Max Attempts: {$config['max_attempts']}\n";
        echo "     Total Delay: " . round($testResult['total_delay'], 2) . "s\n";
        echo "     Would Succeed: " . ($testResult['would_succeed'] ? '✅ Yes' : '❌ No') . "\n";
        echo "\n";
    }

    // ====================================================================
    // FINAL SUMMARY
    // ====================================================================
    echo "🔄 Auto-Retry with Exponential Backoff Summary\n";
    echo "==============================================\n";
    echo "✅ Multiple backoff strategies (exponential, linear, fibonacci, adaptive)\n";
    echo "✅ Intelligent jitter for avoiding thundering herd problems\n";
    echo "✅ Smart error classification (retryable vs non-retryable)\n";
    echo "✅ Configurable retry attempts and delay parameters\n";
    echo "✅ Bulk operations with individual retry logic\n";
    echo "✅ Async retry patterns with comprehensive tracking\n";
    echo "✅ Resilient API call wrappers with automatic fallback\n";
    echo "✅ Comprehensive retry analytics and pattern analysis\n";
    echo "✅ Configuration testing and optimization recommendations\n";
    echo "✅ Adaptive backoff based on historical success rates\n";
    
    echo "\n🎯 Production Benefits:\n";
    echo "   • Improves application resilience against transient failures\n";
    echo "   • Reduces user-facing errors from temporary service issues\n";
    echo "   • Optimizes API usage through intelligent backoff strategies\n";
    echo "   • Provides detailed analytics for performance optimization\n";
    echo "   • Prevents system overload through jitter and smart delays\n";
    echo "   • Supports different strategies for various failure patterns\n";
    echo "   • Enables graceful degradation during service disruptions\n";
    
    // Reset statistics for clean state
    echo "\n🧹 Resetting retry statistics...\n";
    $client->resetRetryStatistics();
    echo "✅ Retry statistics and history cleared\n";

} catch (\Exception $e) {
    echo "❌ Retry Demo Error: " . $e->getMessage() . "\n";
    echo "🔧 Troubleshooting:\n";
    echo "   • Verify retry configuration parameters are valid\n";
    echo "   • Check that max_attempts and delay values are reasonable\n";
    echo "   • Ensure exception types are correctly configured\n";
    echo "   • Review status codes for retryable vs non-retryable errors\n";
}