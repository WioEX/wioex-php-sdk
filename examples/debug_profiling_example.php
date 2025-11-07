<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Wioex\SDK\WioexClient;

// ====================================================================
// DEBUG MODE AND PERFORMANCE PROFILING EXAMPLE
// Demonstrates comprehensive debugging, performance monitoring,
// memory leak detection, and optimization recommendations
// ====================================================================

echo "🔍 WioEX SDK Debug Mode & Performance Profiling Demo\n";
echo "====================================================\n\n";

// Debug and profiling configuration
$debugConfig = [
    'api_key' => $_ENV['WIOEX_API_KEY'] ?? 'your-api-key-here',
    
    // Debug configuration
    'debug' => [
        'enabled' => true,
        'query_logging' => true,
        'performance_profiling' => true,
        'response_validation' => true,
        'include_trace' => false, // Set to true for detailed stack traces
        'max_log_entries' => 1000,
        'max_body_length' => 10000,
        'max_response_depth' => 5,
        'max_data_depth' => 3,
        'slow_operation_threshold' => 1.0, // 1 second
        'memory_intensive_threshold' => 5242880, // 5MB
        'memory_leak_threshold' => 1048576, // 1MB
        'persist_logs' => true,
        'max_cached_entries' => 100
    ],
    
    // Cache for debug data persistence
    'cache' => [
        'enabled' => true,
        'driver' => 'memory',
        'ttl' => [
            'debug_logs' => 3600
        ]
    ]
];

try {
    // Initialize client with debug mode enabled
    $client = new WioexClient($debugConfig);
    
    // Enable debug mode explicitly
    $client->withDebugMode(
        queryLogging: true,
        performanceProfiling: true,
        responseValidation: true
    );
    
    echo "🔧 Debug Configuration:\n";
    echo "Debug Enabled: " . ($client->isDebugEnabled() ? '✅ Yes' : '❌ No') . "\n";
    echo "Query Logging: ✅ Enabled\n";
    echo "Performance Profiling: ✅ Enabled\n";
    echo "Response Validation: ✅ Enabled\n\n";

    // ====================================================================
    // EXAMPLE 1: Basic Performance Profiling
    // ====================================================================
    echo "⏱️  Example 1: Basic Performance Profiling\n";
    echo "-----------------------------------------\n";
    
    // Take initial memory snapshot
    $client->takeMemorySnapshot('app_start', ['context' => 'application_initialization']);
    
    // Profile a simple operation
    $result = $client->profileOperation('data_processing', function() {
        // Simulate data processing
        $data = [];
        for ($i = 0; $i < 1000; $i++) {
            $data[] = [
                'id' => $i,
                'value' => random_int(1, 100),
                'timestamp' => time(),
                'hash' => md5("data_{$i}")
            ];
        }
        
        // Simulate some processing
        usleep(100000); // 100ms
        
        return count($data);
    }, ['operation_type' => 'data_generation']);
    
    echo "✅ Data processing completed\n";
    echo "🔢 Records processed: {$result}\n";
    
    // Take snapshot after processing
    $client->takeMemorySnapshot('after_processing', ['context' => 'after_data_processing']);
    
    // Compare memory usage
    $memoryComparison = $client->compareMemorySnapshots('app_start', 'after_processing');
    echo "💾 Memory Usage:\n";
    echo "   Growth: " . round($memoryComparison['memory_difference'] / 1024, 2) . " KB\n";
    echo "   Time: " . round($memoryComparison['time_elapsed'], 3) . " seconds\n";
    echo "   Leak Detected: " . ($memoryComparison['leak_detected'] ? '⚠️  Yes' : '✅ No') . "\n\n";

    // ====================================================================
    // EXAMPLE 2: Advanced Profiling with Checkpoints
    // ====================================================================
    echo "📍 Example 2: Advanced Profiling with Checkpoints\n";
    echo "-------------------------------------------------\n";
    
    $client->startProfiling('portfolio_analysis', ['user_id' => 'trader_123', 'portfolio_size' => 10]);
    
    // Checkpoint 1: Data fetching simulation
    $client->addCheckpoint('portfolio_analysis', 'data_fetch', ['symbols' => ['AAPL', 'GOOGL', 'MSFT']]);
    usleep(50000); // 50ms
    
    // Checkpoint 2: Calculations
    $client->addCheckpoint('portfolio_analysis', 'calculations', ['metrics' => 'risk_analysis']);
    
    // Simulate intensive calculations
    $portfolio = [];
    for ($i = 0; $i < 1000; $i++) {
        $portfolio[] = [
            'symbol' => 'STOCK_' . $i,
            'shares' => random_int(1, 1000),
            'price' => random_int(10, 500),
            'volatility' => mt_rand() / mt_getrandmax()
        ];
    }
    usleep(75000); // 75ms
    
    // Checkpoint 3: Report generation
    $client->addCheckpoint('portfolio_analysis', 'report_generation', ['format' => 'json']);
    usleep(25000); // 25ms
    
    // Stop profiling and get detailed results
    $profileResults = $client->stopProfiling('portfolio_analysis');
    
    echo "📊 Portfolio Analysis Profile:\n";
    echo "   Total Time: " . round($profileResults['execution_time'] * 1000, 2) . "ms\n";
    echo "   Memory Used: " . round($profileResults['memory_used'] / 1024, 2) . " KB\n";
    echo "   Checkpoints: " . count($profileResults['checkpoints']) . "\n";
    
    if (!empty($profileResults['checkpoints'])) {
        echo "   Checkpoint Details:\n";
        foreach ($profileResults['checkpoints'] as $checkpoint) {
            echo "     • {$checkpoint['name']}: " . round($checkpoint['elapsed_time'] * 1000, 2) . "ms\n";
        }
    }
    
    echo "   Performance Rating: " . strtoupper($profileResults['analysis']['performance_rating']) . "\n";
    echo "   Memory Efficiency: " . strtoupper($profileResults['analysis']['memory_efficiency']) . "\n\n";

    // ====================================================================
    // EXAMPLE 3: Query Logging and Response Validation
    // ====================================================================
    echo "📝 Example 3: Query Logging and API Call Simulation\n";
    echo "---------------------------------------------------\n";
    
    // Simulate multiple API calls with logging
    $apiCalls = [
        ['method' => 'GET', 'url' => 'https://api.wioex.com/v2/stocks/get', 'params' => ['ticker' => 'AAPL']],
        ['method' => 'GET', 'url' => 'https://api.wioex.com/v2/markets/status', 'params' => []],
        ['method' => 'GET', 'url' => 'https://api.wioex.com/v2/stocks/get', 'params' => ['ticker' => 'GOOGL']],
        ['method' => 'GET', 'url' => 'https://api.wioex.com/v2/streaming/token', 'params' => []]
    ];
    
    foreach ($apiCalls as $i => $call) {
        $startTime = microtime(true);
        
        // Simulate API response
        $response = [
            'success' => true,
            'data' => [
                'ticker' => $call['params']['ticker'] ?? 'MARKET',
                'price' => random_int(100, 500),
                'volume' => random_int(1000000, 50000000),
                'timestamp' => time()
            ],
            'request_id' => uniqid()
        ];
        
        $executionTime = microtime(true) - $startTime + (mt_rand() / mt_getrandmax() * 0.5); // Add some random delay
        
        // Log the query
        $client->logQuery(
            $call['method'],
            $call['url'],
            ['Content-Type' => 'application/json', 'User-Agent' => 'WioEX-SDK-Demo'],
            json_encode($call['params']),
            $response,
            $executionTime
        );
        
        echo "📡 API Call " . ($i + 1) . ": {$call['method']} " . basename($call['url']) . " (" . round($executionTime * 1000, 2) . "ms)\n";
        
        usleep(10000); // Small delay between calls
    }
    
    echo "\n📋 Query Log Summary:\n";
    $queryLog = $client->getQueryLog();
    echo "   Total Queries: " . count($queryLog) . "\n";
    
    if (!empty($queryLog)) {
        $avgTime = array_sum(array_column($queryLog, 'execution_time')) / count($queryLog);
        echo "   Average Response Time: " . round($avgTime * 1000, 2) . "ms\n";
        
        $slowQueries = $client->getQueryLog(['min_execution_time' => 0.1]);
        echo "   Slow Queries (>100ms): " . count($slowQueries) . "\n";
    }
    echo "\n";

    // ====================================================================
    // EXAMPLE 4: Memory Leak Detection
    // ====================================================================
    echo "🔍 Example 4: Memory Leak Detection\n";
    echo "----------------------------------\n";
    
    // Simulate operations that might cause memory leaks
    for ($round = 1; $round <= 5; $round++) {
        $client->takeMemorySnapshot("round_{$round}_start");
        
        // Simulate memory-intensive operation
        $data = [];
        for ($i = 0; $i < 500; $i++) {
            $data[] = str_repeat("A", 1024 * ($round * 2)); // Progressively more memory
        }
        
        // Intentionally don't unset $data to simulate potential leak
        usleep(50000);
        
        $client->takeMemorySnapshot("round_{$round}_end");
        echo "🔄 Round {$round} completed\n";
    }
    
    // Detect memory leaks
    $memoryLeaks = $client->detectMemoryLeaks();
    
    echo "\n🔍 Memory Leak Detection Results:\n";
    if (empty($memoryLeaks)) {
        echo "✅ No significant memory leaks detected\n";
    } else {
        echo "⚠️  Found " . count($memoryLeaks) . " potential memory leaks:\n";
        foreach ($memoryLeaks as $i => $leak) {
            echo "   Leak " . ($i + 1) . ":\n";
            echo "     Between: " . implode(' → ', $leak['between_snapshots']) . "\n";
            echo "     Growth: " . round($leak['memory_growth'] / 1024, 2) . " KB\n";
            echo "     Rate: " . round($leak['growth_rate'] / 1024, 2) . " KB/sec\n";
            echo "     Severity: " . strtoupper($leak['severity']) . "\n";
        }
    }
    echo "\n";

    // ====================================================================
    // EXAMPLE 5: Performance Analysis and Recommendations
    // ====================================================================
    echo "📈 Example 5: Performance Analysis and Recommendations\n";
    echo "-----------------------------------------------------\n";
    
    // Get comprehensive performance report
    $performanceReport = $client->getPerformanceReport();
    
    echo "🎯 Performance Summary:\n";
    if (isset($performanceReport['session'])) {
        $session = $performanceReport['session'];
        echo "   Session Duration: " . round($session['duration'], 2) . " seconds\n";
        echo "   Memory Used: " . round($session['memory_used'] / 1024, 2) . " KB\n";
        echo "   Peak Memory: " . round($session['peak_memory'] / 1024, 2) . " KB\n";
    }
    
    if (isset($performanceReport['analysis'])) {
        $analysis = $performanceReport['analysis'];
        echo "   Total Operations: {$analysis['total_operations']}\n";
        echo "   Average Time: " . round($analysis['average_execution_time'] * 1000, 2) . "ms\n";
        echo "   Median Time: " . round($analysis['median_execution_time'] * 1000, 2) . "ms\n";
        echo "   Max Time: " . round($analysis['max_execution_time'] * 1000, 2) . "ms\n";
    }
    
    // Get slow operations
    $slowOps = $client->getSlowOperations(5);
    echo "\n🐌 Slow Operations:\n";
    if (empty($slowOps)) {
        echo "   ✅ No slow operations detected\n";
    } else {
        foreach ($slowOps as $i => $op) {
            echo "   " . ($i + 1) . ". {$op['name']}: " . round($op['execution_time'] * 1000, 2) . "ms\n";
        }
    }
    
    // Get memory intensive operations
    $memoryOps = $client->getMemoryIntensiveOperations(5);
    echo "\n💾 Memory Intensive Operations:\n";
    if (empty($memoryOps)) {
        echo "   ✅ No memory intensive operations detected\n";
    } else {
        foreach ($memoryOps as $i => $op) {
            echo "   " . ($i + 1) . ". {$op['name']}: " . round($op['memory_used'] / 1024, 2) . " KB\n";
        }
    }
    
    // Get recommendations
    $recommendations = $client->getPerformanceRecommendations();
    echo "\n💡 Performance Recommendations:\n";
    if (empty($recommendations)) {
        echo "   ✅ No specific recommendations at this time\n";
    } else {
        foreach ($recommendations as $i => $rec) {
            $priority = strtoupper($rec['priority']);
            echo "   " . ($i + 1) . ". [{$priority}] {$rec['message']}\n";
            
            if (isset($rec['details']) && is_array($rec['details'])) {
                foreach ($rec['details'] as $detail) {
                    if (is_string($detail)) {
                        echo "      • {$detail}\n";
                    } elseif (is_array($detail) && isset($detail['name'])) {
                        echo "      • {$detail['name']}\n";
                    }
                }
            }
        }
    }
    echo "\n";

    // ====================================================================
    // EXAMPLE 6: Real-time Debug Information
    // ====================================================================
    echo "⚡ Example 6: Real-time Debug Information\n";
    echo "----------------------------------------\n";
    
    $realTimeInfo = $client->getRealTimeDebugInfo();
    
    echo "🔴 Live Debug Info:\n";
    echo "   Current Memory: " . round($realTimeInfo['memory_current'] / 1024, 2) . " KB\n";
    echo "   Peak Memory: " . round($realTimeInfo['memory_peak'] / 1024, 2) . " KB\n";
    echo "   Included Files: {$realTimeInfo['included_files_count']}\n";
    echo "   Declared Classes: {$realTimeInfo['declared_classes_count']}\n";
    
    if (!empty($realTimeInfo['recent_queries'])) {
        echo "   Recent Queries: " . count($realTimeInfo['recent_queries']) . "\n";
    }
    
    if (!empty($realTimeInfo['recent_errors'])) {
        echo "   Recent Errors: " . count($realTimeInfo['recent_errors']) . "\n";
    }
    echo "\n";

    // ====================================================================
    // EXAMPLE 7: Debug Data Export
    // ====================================================================
    echo "💾 Example 7: Debug Data Export\n";
    echo "------------------------------\n";
    
    // Export debug data in different formats
    echo "📤 Exporting debug data...\n";
    
    $jsonFile = $client->exportDebugData('json');
    echo "   JSON Export: {$jsonFile}\n";
    
    $csvFile = $client->exportDebugData('csv');
    echo "   CSV Export: {$csvFile}\n";
    
    $htmlFile = $client->exportDebugData('html');
    echo "   HTML Report: {$htmlFile}\n";
    echo "\n";

    // ====================================================================
    // EXAMPLE 8: Comprehensive Analysis
    // ====================================================================
    echo "🎯 Example 8: Comprehensive System Analysis\n";
    echo "-------------------------------------------\n";
    
    $comprehensiveAnalysis = $client->getComprehensiveAnalysis();
    
    echo "📊 System Health Overview:\n";
    
    // Debug summary
    if (isset($comprehensiveAnalysis['debug_summary'])) {
        $debugSummary = $comprehensiveAnalysis['debug_summary'];
        echo "   🔍 Debug Status:\n";
        echo "     Queries Logged: {$debugSummary['total_queries']}\n";
        echo "     Errors: {$debugSummary['total_errors']}\n";
        echo "     Validation Failures: {$debugSummary['total_validation_failures']}\n";
    }
    
    // Performance summary
    if (isset($comprehensiveAnalysis['performance_report']['analysis'])) {
        $perfAnalysis = $comprehensiveAnalysis['performance_report']['analysis'];
        echo "   ⚡ Performance Status:\n";
        echo "     Operations: {$perfAnalysis['total_operations']}\n";
        echo "     Avg Time: " . round($perfAnalysis['average_execution_time'] * 1000, 2) . "ms\n";
    }
    
    // Issues summary
    $issues = [
        'slow_operations' => count($comprehensiveAnalysis['slow_operations'] ?? []),
        'memory_intensive' => count($comprehensiveAnalysis['memory_intensive_operations'] ?? []),
        'memory_leaks' => count($comprehensiveAnalysis['memory_leaks'] ?? []),
        'recommendations' => count($comprehensiveAnalysis['recommendations'] ?? [])
    ];
    
    echo "   🎯 Issues Identified:\n";
    foreach ($issues as $type => $count) {
        $label = ucfirst(str_replace('_', ' ', $type));
        $status = $count > 0 ? "⚠️  {$count}" : "✅ 0";
        echo "     {$label}: {$status}\n";
    }
    echo "\n";

    // ====================================================================
    // FINAL SUMMARY
    // ====================================================================
    echo "🔍 Debug Mode & Performance Profiling Summary\n";
    echo "=============================================\n";
    echo "✅ Comprehensive query logging with sanitization and filtering\n";
    echo "✅ Advanced performance profiling with checkpoints and analysis\n";
    echo "✅ Memory leak detection with threshold-based alerts\n";
    echo "✅ Real-time performance monitoring and recommendations\n";
    echo "✅ Slow operation identification and optimization suggestions\n";
    echo "✅ Memory usage tracking and efficiency analysis\n";
    echo "✅ Multi-format debug data export (JSON, CSV, HTML)\n";
    echo "✅ Comprehensive system health analysis and reporting\n";
    echo "✅ Integration with caching for persistent debug data\n";
    echo "✅ Configurable thresholds and sensitivity levels\n";
    
    echo "\n🎯 Production Benefits:\n";
    echo "   • Identify performance bottlenecks before they impact users\n";
    echo "   • Monitor memory usage patterns to prevent leaks\n";
    echo "   • Track API call performance and response times\n";
    echo "   • Generate actionable optimization recommendations\n";
    echo "   • Export detailed reports for performance analysis\n";
    echo "   • Enable proactive performance management\n";
    echo "   • Support continuous performance improvement\n";

    // Clean up debug data
    echo "\n🧹 Cleaning up debug data...\n";
    $client->clearDebugData();
    echo "✅ Debug logs and profiling data cleared\n";

} catch (\Exception $e) {
    echo "❌ Debug/Profiling Demo Error: " . $e->getMessage() . "\n";
    echo "🔧 Troubleshooting:\n";
    echo "   • Verify debug configuration parameters are correct\n";
    echo "   • Check that sufficient memory is available for profiling\n";
    echo "   • Ensure cache is properly configured if using persistent logging\n";
    echo "   • Review threshold values for performance analysis\n";
}