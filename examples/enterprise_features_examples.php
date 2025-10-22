<?php

/**
 * WioEX PHP SDK v2.0.0 - Enterprise Features Examples
 *
 * Comprehensive examples for automatic error reporting/telemetry and 
 * bulk operations for high-volume trading applications.
 *
 * Features demonstrated:
 * - Automatic error reporting with privacy controls
 * - Telemetry configuration and usage
 * - Bulk operations for portfolio management
 * - Performance optimization techniques
 * - Enterprise-grade error handling
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Wioex\SDK\Client;
use Wioex\SDK\Config;
use Wioex\SDK\Enums\TimelineInterval;
use Wioex\SDK\Enums\SortOrder;
use Wioex\SDK\Exceptions\BulkOperationException;
use Wioex\SDK\Exceptions\ValidationException;

// =============================================================================
// TELEMETRY & ERROR REPORTING EXAMPLES
// =============================================================================

echo "=== WioEX SDK v2.0.0 Enterprise Features Examples ===\n\n";

/**
 * Example 1: Basic Telemetry Configuration
 * 
 * Configure automatic error reporting and telemetry for different environments
 */
function example1_basic_telemetry_setup()
{
    echo "Example 1: Basic Telemetry Configuration\n";
    echo "----------------------------------------\n";
    
    // Production environment - minimal privacy, essential errors only
    $productionConfig = [
        'api_key' => 'your-api-key-here',
        'telemetry' => [
            'enabled' => true,
            'privacy_mode' => 'production',
            'sampling_rate' => 0.1, // 10% of requests
            'error_reporting' => [
                'enabled' => true,
                'level' => 'minimal',
                'include_stack_trace' => false,
                'include_request_data' => false,
                'include_response_data' => false,
            ],
            'performance_tracking' => [
                'enabled' => true,
                'track_response_times' => true,
                'track_error_rates' => true,
            ],
            'usage_analytics' => [
                'enabled' => true,
                'track_endpoint_usage' => true,
                'track_parameter_patterns' => false,
            ]
        ]
    ];
    
    // Development environment - detailed privacy, comprehensive tracking
    $developmentConfig = [
        'api_key' => 'your-dev-api-key-here',
        'telemetry' => [
            'enabled' => true,
            'privacy_mode' => 'development',
            'sampling_rate' => 1.0, // 100% of requests
            'error_reporting' => [
                'enabled' => true,
                'level' => 'detailed',
                'include_stack_trace' => true,
                'include_request_data' => true,
                'include_response_data' => true,
            ],
            'performance_tracking' => [
                'enabled' => true,
                'track_response_times' => true,
                'track_error_rates' => true,
                'track_memory_usage' => true,
            ],
            'usage_analytics' => [
                'enabled' => true,
                'track_endpoint_usage' => true,
                'track_parameter_patterns' => true,
            ]
        ]
    ];
    
    $client = new Client($productionConfig);
    echo "✓ Production client configured with minimal telemetry\n";
    
    $devClient = new Client($developmentConfig);
    echo "✓ Development client configured with detailed telemetry\n";
    
    echo "\n";
    return [$client, $devClient];
}

/**
 * Example 2: Advanced Error Reporting with Privacy Controls
 * 
 * Demonstrate different privacy levels and async reporting
 */
function example2_advanced_error_reporting($client)
{
    echo "Example 2: Advanced Error Reporting\n";
    echo "-----------------------------------\n";
    
    try {
        // Configure error reporter with enhanced settings
        $errorReporter = $client->getErrorReporter();
        
        // Configure batch reporting for better performance
        $errorReporter->configureBatchReporting(
            batchSize: 5,        // Send errors in batches of 5
            batchTimeout: 30.0   // Auto-flush after 30 seconds
        );
        
        // Configure rate limiting to prevent spam
        $errorReporter->configureRateLimit(maxReportsPerMinute: 10);
        
        echo "✓ Error reporter configured with batch reporting and rate limiting\n";
        
        // Simulate different types of errors for demonstration
        $errors = [
            new \RuntimeException('API rate limit exceeded'),
            new \InvalidArgumentException('Invalid symbol format: ABC123!'),
            new \Exception('Network timeout during request'),
        ];
        
        foreach ($errors as $error) {
            // Queue error for batch reporting (non-blocking)
            $queued = $errorReporter->queueError($error, [
                'category' => 'stocks',
                'endpoint' => '/v2/stocks/get',
                'user_agent' => 'SDK-Example/1.0'
            ]);
            
            if ($queued) {
                echo "✓ Error queued for batch reporting: " . $error->getMessage() . "\n";
            }
        }
        
        // Get reporting statistics
        $stats = $errorReporter->getReportingStats();
        echo "\nReporting Statistics:\n";
        echo "- Queue size: {$stats['queue_size']} errors\n";
        echo "- Rate limit: {$stats['rate_limit_status']['reports_remaining']} reports remaining\n";
        echo "- Batch config: {$stats['batch_config']['batch_size']} errors per batch\n";
        
        // Manually flush queue for demonstration
        $flushed = $errorReporter->flushErrorQueue();
        echo $flushed ? "✓ Error queue flushed successfully\n" : "✗ Failed to flush error queue\n";
        
    } catch (\Exception $e) {
        echo "✗ Error reporting example failed: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
}

/**
 * Example 3: Performance Monitoring and Usage Analytics
 * 
 * Demonstrate automatic performance tracking and usage analytics
 */
function example3_performance_monitoring($client)
{
    echo "Example 3: Performance Monitoring\n";
    echo "---------------------------------\n";
    
    try {
        // Get telemetry manager for manual tracking
        $telemetry = $client->getTelemetryManager();
        
        if (!$telemetry) {
            echo "✗ Telemetry not available (check configuration)\n";
            return;
        }
        
        // Manual performance tracking example
        $startTime = microtime(true);
        
        // Simulate API call
        usleep(250000); // 250ms simulated delay
        
        $endTime = microtime(true);
        $responseTime = ($endTime - $startTime) * 1000; // Convert to milliseconds
        
        // Track performance manually
        $telemetry->trackPerformance('/v2/stocks/get', $responseTime, 200, [
            'cache_hit' => false,
            'retry_count' => 0,
            'symbols_requested' => 5
        ]);
        
        echo "✓ Performance tracked: {$responseTime}ms response time\n";
        
        // Track usage pattern
        $telemetry->trackUsage('/v2/stocks/get', [
            'symbols' => ['AAPL', 'TSLA', 'GOOGL'],
            'currency' => 'USD'
        ]);
        
        echo "✓ Usage tracked: 3 symbols requested\n";
        
        // Track environment information (one-time)
        $telemetry->trackEnvironment();
        echo "✓ Environment information collected\n";
        
        // Send telemetry batch
        $sent = $telemetry->sendTelemetryBatch();
        echo $sent ? "✓ Telemetry batch sent successfully\n" : "ℹ Telemetry batch queued for later\n";
        
    } catch (\Exception $e) {
        echo "✗ Performance monitoring example failed: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
}

// =============================================================================
// BULK OPERATIONS EXAMPLES
// =============================================================================

/**
 * Example 4: Basic Bulk Quote Operations
 * 
 * Demonstrate high-performance bulk quote retrieval for portfolios
 */
function example4_bulk_quotes($client)
{
    echo "Example 4: High-Performance Bulk Quotes\n";
    echo "---------------------------------------\n";
    
    try {
        // Large portfolio of stocks (simulation)
        $portfolioSymbols = [
            // Tech sector
            'AAPL', 'MSFT', 'GOOGL', 'AMZN', 'TSLA', 'META', 'NVDA', 'NFLX',
            // Financial sector  
            'JPM', 'BAC', 'WFC', 'GS', 'MS', 'C', 'USB', 'PNC',
            // Healthcare sector
            'JNJ', 'PFE', 'UNH', 'MRK', 'ABT', 'TMO', 'DHR', 'BMY',
            // Energy sector
            'XOM', 'CVX', 'COP', 'EOG', 'SLB', 'PSX', 'VLO', 'MPC',
            // Consumer sector
            'WMT', 'PG', 'KO', 'PEP', 'MCD', 'NKE', 'SBUX', 'TGT'
        ];
        
        echo "Portfolio size: " . count($portfolioSymbols) . " stocks\n";
        
        // Performance comparison demonstration
        echo "\n--- Performance Comparison for QUOTES ---\n";
        echo "Note: Performance improvement applies to QUOTES only.\n";
        echo "Timeline/Info/Financials use individual API calls (1 symbol per request).\n\n";
        
        // Method 1: Individual calls (simulated timing)
        $individualStartTime = microtime(true);
        echo "Individual quote calls would take: ~" . (count($portfolioSymbols) * 0.2) . " seconds\n";
        
        // Method 2: Bulk quote operation with chunking
        $bulkStartTime = microtime(true);
        
        $bulkQuotes = $client->stocks()->quoteBulk($portfolioSymbols, [
            'chunk_size' => 30,              // 30 symbols per chunk (API limit)
            'chunk_delay' => 0.1,            // 100ms between chunks
            'fail_on_partial_errors' => false // Continue on errors
        ]);
        
        $bulkEndTime = microtime(true);
        $bulkTime = $bulkEndTime - $bulkStartTime;
        
        if ($bulkQuotes->successful()) {
            $data = $bulkQuotes->data();
            $successCount = $data['bulk_operation']['success_count'] ?? 0;
            $totalRequested = $data['bulk_operation']['total_requested'] ?? 0;
            $chunksProcessed = $data['bulk_operation']['chunks_processed'] ?? 0;
            
            echo "✓ Bulk operation completed in " . round($bulkTime, 2) . " seconds\n";
            echo "✓ Successfully retrieved {$successCount}/{$totalRequested} quotes\n";
            echo "✓ Processed in {$chunksProcessed} chunks\n";
            echo "✓ Performance improvement: ~" . round((1 - $bulkTime / (count($portfolioSymbols) * 0.2)) * 100, 1) . "%\n";
            
            // Display sample results
            if (isset($data['tickers']) && count($data['tickers']) > 0) {
                echo "\nSample quotes:\n";
                foreach (array_slice($data['tickers'], 0, 3) as $ticker) {
                    $symbol = $ticker['ticker'] ?? 'N/A';
                    $price = $ticker['market']['price'] ?? 'N/A';
                    $change = $ticker['market']['change']['percent'] ?? 'N/A';
                    echo "- {$symbol}: \${$price} ({$change}%)\n";
                }
            }
        } else {
            echo "✗ Bulk quotes failed\n";
        }
        
    } catch (BulkOperationException $e) {
        echo "⚠ Partial bulk operation failure:\n";
        echo "- Message: " . $e->getMessage() . "\n";
        echo "- Successful responses: " . count($e->getSuccessfulResponses()) . "\n";
        echo "- Errors: " . count($e->getErrors()) . "\n";
        echo "- Failure rate: " . round($e->getFailureRate(), 1) . "%\n";
        
        if ($e->hasPartialSuccess()) {
            echo "✓ Partial success - some quotes retrieved\n";
        }
    } catch (\Exception $e) {
        echo "✗ Bulk quotes example failed: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
}

/**
 * Example 5: Bulk Timeline Data for Portfolio Analysis
 * 
 * Demonstrate retrieving historical data for multiple stocks efficiently
 */
function example5_bulk_timeline($client)
{
    echo "Example 5: Bulk Timeline Data for Portfolio Analysis\n";
    echo "----------------------------------------------------\n";
    
    try {
        // Selection of stocks for timeline analysis
        $analysisSymbols = ['AAPL', 'TSLA', 'GOOGL', 'MSFT', 'NVDA'];
        
        echo "Analyzing " . count($analysisSymbols) . " stocks for timeline data\n";
        
        // Get 30 days of daily data for technical analysis
        $timelines = $client->stocks()->timelineBulk(
            $analysisSymbols,
            TimelineInterval::ONE_DAY,
            [
                'size' => 30,                    // 30 data points
                'orderBy' => SortOrder::DESCENDING, // Latest first
                'chunk_size' => 25,              // Smaller chunks for timeline
                'chunk_delay' => 0.2             // Slower for timeline data
            ]
        );
        
        if ($timelines->successful()) {
            $data = $timelines->data();
            $successCount = $data['bulk_operation']['success_count'] ?? 0;
            $processingTime = $data['bulk_operation']['processing_time_ms'] ?? 0;
            
            echo "✓ Timeline data retrieved for {$successCount} symbols\n";
            echo "✓ Processing time: " . round($processingTime / 1000, 2) . " seconds\n";
            
            // Display sample timeline statistics
            if (isset($data['data']) && count($data['data']) > 0) {
                echo "\nTimeline Statistics:\n";
                foreach (array_slice($data['data'], 0, 3) as $timelineData) {
                    if (isset($timelineData['symbol']) && isset($timelineData['data'])) {
                        $symbol = $timelineData['symbol'];
                        $points = count($timelineData['data']);
                        $firstPoint = $timelineData['data'][0] ?? null;
                        $lastPoint = $timelineData['data'][$points - 1] ?? null;
                        
                        if ($firstPoint && $lastPoint) {
                            $priceChange = (($firstPoint['close'] - $lastPoint['close']) / $lastPoint['close']) * 100;
                            echo "- {$symbol}: {$points} data points, " . round($priceChange, 2) . "% change\n";
                        }
                    }
                }
            }
        } else {
            echo "✗ Bulk timeline retrieval failed\n";
        }
        
    } catch (\Exception $e) {
        echo "✗ Bulk timeline example failed: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
}

/**
 * Example 6: Advanced Bulk Operations with Mixed Data
 * 
 * Demonstrate retrieving different types of data for comprehensive analysis
 */
function example6_mixed_bulk_operations($client)
{
    echo "Example 6: Mixed Bulk Operations for Comprehensive Analysis\n";
    echo "----------------------------------------------------------\n";
    
    try {
        // Portfolio analysis requires multiple data types
        $analysisSymbols = ['AAPL', 'MSFT', 'GOOGL'];
        
        echo "Performing comprehensive analysis for " . count($analysisSymbols) . " stocks\n";
        
        // Step 1: Get real-time quotes
        echo "\n1. Retrieving real-time quotes...\n";
        $quotes = $client->stocks()->quoteBulk($analysisSymbols);
        
        if ($quotes->successful()) {
            $quoteData = $quotes->data();
            echo "✓ Retrieved quotes for " . count($quoteData['tickers'] ?? []) . " symbols\n";
        }
        
        // Step 2: Get company information
        echo "\n2. Retrieving company information...\n";
        $companyInfo = $client->stocks()->infoBulk($analysisSymbols);
        
        if ($companyInfo->successful()) {
            $infoData = $companyInfo->data();
            echo "✓ Retrieved company info for " . count($infoData['data'] ?? []) . " symbols\n";
        }
        
        // Step 3: Get financial data
        echo "\n3. Retrieving financial data...\n";
        $financials = $client->stocks()->financialsBulk($analysisSymbols, 'USD');
        
        if ($financials->successful()) {
            $financialData = $financials->data();
            echo "✓ Retrieved financial data for " . count($financialData['data'] ?? []) . " symbols\n";
        }
        
        // Combine results for comprehensive portfolio view
        echo "\n--- Portfolio Analysis Summary ---\n";
        foreach ($analysisSymbols as $symbol) {
            echo "Symbol: {$symbol}\n";
            
            // Find quote data
            $quote = null;
            if (isset($quoteData['tickers'])) {
                foreach ($quoteData['tickers'] as $ticker) {
                    if (($ticker['ticker'] ?? '') === $symbol) {
                        $quote = $ticker;
                        break;
                    }
                }
            }
            
            if ($quote) {
                $price = $quote['market']['price'] ?? 'N/A';
                $change = $quote['market']['change']['percent'] ?? 'N/A';
                echo "- Current Price: \${$price} ({$change}%)\n";
            }
            
            // Find company info
            $info = $infoData['data'][$symbol] ?? null;
            if ($info) {
                $marketCap = $info['market_cap'] ?? 'N/A';
                $peRatio = $info['pe_ratio'] ?? 'N/A';
                echo "- Market Cap: \${$marketCap}, P/E: {$peRatio}\n";
            }
            
            echo "\n";
        }
        
    } catch (\Exception $e) {
        echo "✗ Mixed bulk operations example failed: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
}

/**
 * Example 7: Production-Ready Portfolio Management System
 * 
 * Real-world example of a high-performance portfolio management system
 */
function example7_portfolio_management_system($client)
{
    echo "Example 7: Production Portfolio Management System\n";
    echo "------------------------------------------------\n";
    
    try {
        // Simulate a large institutional portfolio
        $institutionalPortfolio = [
            'large_cap' => ['AAPL', 'MSFT', 'GOOGL', 'AMZN', 'TSLA', 'META', 'NVDA', 'BRK.A', 'JNJ', 'V'],
            'mid_cap' => ['AMD', 'PYPL', 'ADBE', 'CRM', 'INTC', 'CSCO', 'PEP', 'TMO', 'COST', 'AVGO'],
            'small_cap' => ['ETSY', 'ROKU', 'SNAP', 'TWLO', 'OKTA', 'DDOG', 'CRWD', 'ZM', 'PTON', 'PLTR'],
            'international' => ['ASML', 'TSM', 'NVO', 'NESN', 'RHHBY', 'SAP', 'TM', 'SONY', 'UL', 'BABA']
        ];
        
        $allSymbols = array_merge(...array_values($institutionalPortfolio));
        $totalSymbols = count($allSymbols);
        
        echo "Managing institutional portfolio: {$totalSymbols} stocks across 4 categories\n";
        
        // Configure for high-performance processing
        $startTime = microtime(true);
        
        // Get quotes for entire portfolio with optimized settings
        $portfolioQuotes = $client->stocks()->quoteBulk($allSymbols, [
            'chunk_size' => 50,              // Larger chunks for efficiency
            'chunk_delay' => 0.05,           // Faster processing
            'fail_on_partial_errors' => false // Resilient to individual failures
        ]);
        
        $endTime = microtime(true);
        $totalTime = $endTime - $startTime;
        
        if ($portfolioQuotes->successful()) {
            $data = $portfolioQuotes->data();
            $operations = $data['bulk_operation'] ?? [];
            
            echo "\n--- Portfolio Processing Results ---\n";
            echo "✓ Processing time: " . round($totalTime, 2) . " seconds\n";
            echo "✓ Success rate: " . round($operations['success_rate'] ?? 0, 1) . "%\n";
            echo "✓ Chunks processed: " . ($operations['chunks_processed'] ?? 0) . "\n";
            echo "✓ Total requests made: " . ($operations['chunks_processed'] ?? 0) . " (vs {$totalSymbols} individual)\n";
            echo "✓ Performance gain: ~" . round((1 - $totalTime / ($totalSymbols * 0.2)) * 100, 1) . "% faster\n";
            
            // Calculate portfolio metrics by category
            echo "\n--- Portfolio Category Performance ---\n";
            $tickers = $data['tickers'] ?? [];
            
            foreach ($institutionalPortfolio as $category => $symbols) {
                $categoryQuotes = array_filter($tickers, function($ticker) use ($symbols) {
                    return in_array($ticker['ticker'] ?? '', $symbols);
                });
                
                if (count($categoryQuotes) > 0) {
                    $avgChange = array_sum(array_column(
                        array_column($categoryQuotes, 'market'), 
                        'change'
                    )) / count($categoryQuotes);
                    
                    $successRate = (count($categoryQuotes) / count($symbols)) * 100;
                    
                    echo "- " . ucfirst(str_replace('_', ' ', $category)) . 
                         ": {$avgChange}% avg change, {$successRate}% success rate\n";
                }
            }
            
            // Risk assessment simulation
            echo "\n--- Risk Assessment ---\n";
            $highVolatility = array_filter($tickers, function($ticker) {
                return abs($ticker['market']['change']['percent'] ?? 0) > 5;
            });
            
            echo "- High volatility stocks (>5% change): " . count($highVolatility) . "\n";
            echo "- Portfolio diversification: " . count($institutionalPortfolio) . " categories\n";
            echo "- Total portfolio value tracked: {$totalSymbols} positions\n";
            
        } else {
            echo "✗ Portfolio processing failed\n";
        }
        
    } catch (BulkOperationException $e) {
        echo "\n⚠ Partial portfolio processing:\n";
        echo "- Processed: " . count($e->getSuccessfulResponses()) . " chunks\n";
        echo "- Failed: " . count($e->getErrors()) . " chunks\n";
        echo "- Summary: " . $e->getSummary() . "\n";
        
        // In production, you would handle partial failures gracefully
        if ($e->hasPartialSuccess()) {
            echo "✓ Partial portfolio data available for analysis\n";
        }
    } catch (\Exception $e) {
        echo "✗ Portfolio management example failed: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
}

// =============================================================================
// CONFIGURATION AND BEST PRACTICES
// =============================================================================

/**
 * Example 8: Best Practices and Configuration Guidelines
 */
function example8_best_practices()
{
    echo "Example 8: Best Practices and Configuration Guidelines\n";
    echo "------------------------------------------------------\n";
    
    echo "🔧 Telemetry Configuration Best Practices:\n\n";
    
    echo "Production Environment:\n";
    echo "- Privacy mode: 'production' (minimal data collection)\n";
    echo "- Sampling rate: 0.1 - 0.3 (10-30% of requests)\n";
    echo "- Error reporting: 'minimal' level, no sensitive data\n";
    echo "- Batch reporting: enabled for performance\n";
    echo "- Rate limiting: 5-10 reports per minute\n\n";
    
    echo "Development Environment:\n";
    echo "- Privacy mode: 'development' (detailed data collection)\n";
    echo "- Sampling rate: 1.0 (100% of requests)\n";
    echo "- Error reporting: 'detailed' level with full context\n";
    echo "- Include stack traces and request/response data\n";
    echo "- Higher rate limits for debugging\n\n";
    
    echo "🚀 Bulk Operations Best Practices:\n\n";
    
    echo "Performance Optimization:\n";
    echo "- Use appropriate chunk sizes (30 for quotes, 1 for timeline/info/financials)\n";
    echo "- Configure delays between chunks (0.1s for quotes, 0.2s for others)\n";
    echo "- Enable partial failure handling for resilience\n";
    echo "- Monitor bulk operation statistics\n";
    echo "- Remember: Only quotes get true bulk processing, others are automated individual calls\n\n";
    
    echo "Scalability Guidelines:\n";
    echo "- Maximum 1000 symbols per bulk operation\n";
    echo "- Use async reporting for non-blocking error collection\n";
    echo "- Implement circuit breakers for external dependencies\n";
    echo "- Cache results when appropriate\n\n";
    
    echo "Security Considerations:\n";
    echo "- Never log API keys or sensitive financial data\n";
    echo "- Use appropriate privacy levels for telemetry\n";
    echo "- Implement proper access controls for bulk operations\n";
    echo "- Regular security audits of telemetry data\n\n";
    
    echo "📊 Performance Metrics:\n\n";
    
    echo "Expected Performance Improvements:\n";
    echo "- Individual API calls: ~200ms per request\n";
    echo "- Bulk QUOTES (30 symbols): ~93% faster (500 stocks: ~17 requests vs 500)\n";
    echo "- Bulk TIMELINE/INFO/FINANCIALS: Same as individual (1 symbol per request)\n";
    echo "- Credit consumption: QUOTES save ~93% credits, others same as individual\n";
    echo "- Network requests: QUOTES reduce by ~93%, others unchanged\n";
    echo "- Mixed operations: Performance varies by operation type\n\n";
}

// =============================================================================
// MAIN EXECUTION
// =============================================================================

function main()
{
    echo "Starting WioEX SDK Enterprise Features Examples...\n\n";
    
    try {
        // Example 1: Basic telemetry setup
        [$client, $devClient] = example1_basic_telemetry_setup();
        
        // Example 2: Advanced error reporting
        example2_advanced_error_reporting($devClient);
        
        // Example 3: Performance monitoring
        example3_performance_monitoring($devClient);
        
        // Example 4: Basic bulk quotes
        example4_bulk_quotes($client);
        
        // Example 5: Bulk timeline data
        example5_bulk_timeline($client);
        
        // Example 6: Mixed bulk operations
        example6_mixed_bulk_operations($client);
        
        // Example 7: Production portfolio management
        example7_portfolio_management_system($client);
        
        // Example 8: Best practices
        example8_best_practices();
        
        echo "✅ All examples completed successfully!\n";
        echo "\nFor more information, visit: https://docs.wioex.com/sdk/php/enterprise-features\n";
        
    } catch (\Exception $e) {
        echo "❌ Examples failed with error: " . $e->getMessage() . "\n";
        echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    }
}

// Run examples only if this file is executed directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    main();
}