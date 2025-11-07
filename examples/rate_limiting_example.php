<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Wioex\SDK\WioexClient;
use Wioex\SDK\Exceptions\RateLimitException;

// ====================================================================
// LOCAL RATE LIMITING WITH BURST PROTECTION EXAMPLE
// Demonstrates intelligent rate limiting, burst protection,
// fair queuing, and adaptive throttling
// ====================================================================

echo "⚡ WioEX SDK Local Rate Limiting & Burst Protection Demo\n";
echo "=======================================================\n\n";

// Rate limiting configuration
$rateLimitConfig = [
    'api_key' => $_ENV['WIOEX_API_KEY'] ?? 'your-api-key-here',
    
    // Advanced rate limiting settings
    'rate_limiting' => [
        'enabled' => true,
        'fair_queuing' => true,
        'priority_processing' => true,
        'bucket_ttl' => 3600,
        'bucket_max_age' => 7200,
        
        // Default limits
        'default' => [
            'max_requests' => 100,
            'refill_rate' => 10,
            'refill_period' => 60,
            'burst_multiplier' => 2.0
        ],
        
        // Category-specific limits
        'categories' => [
            'quote_requests' => [
                'max_requests' => 1000,
                'refill_rate' => 100,
                'refill_period' => 60,
                'burst_multiplier' => 2.0,
                'priority' => 'high'
            ],
            'market_data' => [
                'max_requests' => 500,
                'refill_rate' => 50,
                'refill_period' => 60,
                'burst_multiplier' => 1.5,
                'priority' => 'medium'
            ],
            'historical_data' => [
                'max_requests' => 100,
                'refill_rate' => 10,
                'refill_period' => 60,
                'burst_multiplier' => 1.2,
                'priority' => 'low'
            ],
            'streaming' => [
                'max_requests' => 50,
                'refill_rate' => 5,
                'refill_period' => 60,
                'burst_multiplier' => 1.0,
                'priority' => 'high'
            ]
        ]
    ],
    
    // Cache for persistent rate limiting
    'cache' => [
        'enabled' => true,
        'driver' => 'memory',
        'ttl' => [
            'rate_limit_buckets' => 3600
        ]
    ]
];

try {
    // Initialize client with rate limiting
    $client = new WioexClient($rateLimitConfig);
    
    // Enable intelligent rate limiting
    $client->withIntelligentRateLimiting();
    
    echo "🔧 Rate Limiting Configuration:\n";
    $metrics = $client->getRateLimitingMetrics();
    echo "✅ Rate Limiting Enabled: Yes\n";
    echo "📊 Categories Configured: " . count($rateLimitConfig['rate_limiting']['categories']) . "\n";
    echo "🎯 Fair Queuing: " . ($rateLimitConfig['rate_limiting']['fair_queuing'] ? 'Enabled' : 'Disabled') . "\n";
    echo "⚡ Burst Protection: Enabled with adaptive multipliers\n\n";

    // ====================================================================
    // EXAMPLE 1: Basic Rate Limiting with Token Bucket
    // ====================================================================
    echo "🪣 Example 1: Token Bucket Rate Limiting\n";
    echo "---------------------------------------\n";
    
    $userId = 'user_12345';
    
    // Check initial rate limit status
    $status = $client->getRateLimitStatus($userId);
    echo "👤 User: {$userId}\n";
    echo "🎫 Initial Tokens Available:\n";
    
    foreach ($status['categories'] as $category => $categoryStatus) {
        echo "   {$category}: {$categoryStatus['current_tokens']}/{$categoryStatus['max_tokens']} ";
        echo "(burst: {$categoryStatus['max_burst_tokens']})\n";
    }
    echo "\n";
    
    // Simulate multiple requests
    echo "📡 Simulating requests...\n";
    $requestCount = 0;
    $allowedCount = 0;
    
    for ($i = 0; $i < 15; $i++) {
        $requestCount++;
        
        if ($client->isRequestAllowed($userId, ['quote_requests'])) {
            $allowedCount++;
            echo "✅ Request {$i + 1}: Allowed\n";
            
            // Consume the token
            try {
                $client->processRateLimitedRequest($userId, ['quote_requests']);
            } catch (RateLimitException $e) {
                echo "⚠️  Request {$i + 1}: {$e->getMessage()}\n";
            }
        } else {
            echo "❌ Request {$i + 1}: Rate limited\n";
        }
        
        usleep(100000); // 100ms delay between requests
    }
    
    echo "\n📊 Results: {$allowedCount}/{$requestCount} requests allowed\n";
    
    // Check final status
    $finalStatus = $client->getRateLimitStatus($userId);
    foreach ($finalStatus['categories'] as $category => $categoryStatus) {
        echo "🎫 Final {$category} tokens: {$categoryStatus['current_tokens']}/{$categoryStatus['max_tokens']}\n";
        echo "📈 Usage: {$categoryStatus['usage_percentage']}%\n";
    }
    echo "\n";

    // ====================================================================
    // EXAMPLE 2: Burst Protection Demonstration
    // ====================================================================
    echo "💥 Example 2: Burst Protection in Action\n";
    echo "---------------------------------------\n";
    
    $burstUser = 'burst_user_67890';
    
    // Simulate burst of requests
    echo "🚀 Simulating burst of 25 rapid requests...\n";
    $burstResults = [];
    
    for ($i = 0; $i < 25; $i++) {
        $allowed = $client->isRequestAllowed($burstUser, ['market_data']);
        $burstResults[] = $allowed;
        
        if ($allowed) {
            try {
                $client->processRateLimitedRequest($burstUser, ['market_data']);
            } catch (RateLimitException $e) {
                // Handle rate limit
            }
        }
        
        // Very rapid requests
        usleep(10000); // 10ms between requests
    }
    
    $allowedInBurst = array_sum($burstResults);
    echo "✅ Allowed during burst: {$allowedInBurst}/25\n";
    
    // Check burst protection status
    $burstProtection = $client->applyBurstProtection($burstUser, ['market_data']);
    echo "🛡️  Burst Protection Status:\n";
    echo "   Active: " . ($burstProtection['burst_active'] ? 'Yes' : 'No') . "\n";
    echo "   Protection Level: " . strtoupper($burstProtection['protection_level']) . "\n";
    
    if (!empty($burstProtection['recommendations'])) {
        echo "💡 Recommendations:\n";
        foreach ($burstProtection['recommendations'] as $recommendation) {
            echo "   • {$recommendation}\n";
        }
    }
    echo "\n";

    // ====================================================================
    // EXAMPLE 3: Fair Queuing Algorithm
    // ====================================================================
    echo "⚖️  Example 3: Fair Queuing Algorithm\n";
    echo "-----------------------------------\n";
    
    // Create requests from multiple users
    $requests = [
        ['identifier' => 'user_A', 'category' => 'quote_requests', 'data' => 'AAPL'],
        ['identifier' => 'user_B', 'category' => 'market_data', 'data' => 'market_status'],
        ['identifier' => 'user_A', 'category' => 'quote_requests', 'data' => 'GOOGL'],
        ['identifier' => 'user_C', 'category' => 'historical_data', 'data' => 'MSFT_1y'],
        ['identifier' => 'user_B', 'category' => 'market_data', 'data' => 'indices'],
        ['identifier' => 'user_A', 'category' => 'quote_requests', 'data' => 'TSLA'],
        ['identifier' => 'user_C', 'category' => 'historical_data', 'data' => 'SPY_6m'],
        ['identifier' => 'user_B', 'category' => 'streaming', 'data' => 'real_time']
    ];
    
    echo "📥 Original request order:\n";
    foreach ($requests as $i => $request) {
        echo "   {$i + 1}. {$request['identifier']} - {$request['category']} - {$request['data']}\n";
    }
    
    // Apply fair queuing
    $fairQueue = $client->applyFairQueuing($requests);
    
    echo "\n⚖️  Fair queued order:\n";
    foreach ($fairQueue as $i => $request) {
        echo "   {$i + 1}. {$request['identifier']} - {$request['category']} - {$request['data']}\n";
    }
    echo "\n";

    // ====================================================================
    // EXAMPLE 4: Priority-Based Processing
    // ====================================================================
    echo "🎯 Example 4: Priority-Based Request Processing\n";
    echo "----------------------------------------------\n";
    
    // Process requests with priority
    $priorityRequests = [
        ['identifier' => 'trader_1', 'category' => 'streaming', 'data' => 'real_time'],
        ['identifier' => 'analyst_1', 'category' => 'historical_data', 'data' => 'analysis'],
        ['identifier' => 'trader_2', 'category' => 'quote_requests', 'data' => 'portfolio'],
        ['identifier' => 'researcher_1', 'category' => 'market_data', 'data' => 'research']
    ];
    
    echo "📋 Processing requests by priority...\n";
    $processedRequests = $client->processPriorityRequests($priorityRequests);
    
    foreach ($processedRequests as $i => $result) {
        $status = $result['processed'] ? '✅ Processed' : '❌ Rate Limited';
        $category = $result['category'];
        $identifier = $result['identifier'];
        echo "   {$i + 1}. {$identifier} - {$category}: {$status}\n";
        
        if (isset($result['error'])) {
            echo "      Error: {$result['error']}\n";
        }
    }
    echo "\n";

    // ====================================================================
    // EXAMPLE 5: Adaptive Rate Limiting
    // ====================================================================
    echo "🔄 Example 5: Adaptive Rate Limiting\n";
    echo "-----------------------------------\n";
    
    $adaptiveUser = 'adaptive_user';
    
    // Get adaptive recommendations
    $adaptation = $client->getAdaptiveRateLimiting($adaptiveUser, 'market_data');
    
    echo "🧠 Adaptive Rate Limiting Analysis:\n";
    echo "   Original Limit: {$adaptation['original_limit']} requests\n";
    echo "   Adapted Limit: {$adaptation['adapted_limit']} requests\n";
    echo "   System Load: " . round($adaptation['system_load'] * 100, 2) . "%\n";
    echo "   Adaptation Factor: {$adaptation['adaptation_factor']}x\n";
    echo "   Reason: " . str_replace('_', ' ', $adaptation['reason']) . "\n\n";

    // ====================================================================
    // EXAMPLE 6: Custom Rate Limiting Configuration
    // ====================================================================
    echo "⚙️  Example 6: Custom Rate Limiting Setup\n";
    echo "----------------------------------------\n";
    
    // Configure custom limits for specific operations
    $client->withCustomRateLimit('premium_api', 2000, 200, 60, 3.0);
    $client->withCustomRateLimit('webhook_calls', 50, 5, 60, 1.0);
    
    echo "✅ Custom rate limits configured:\n";
    echo "   premium_api: 2000 requests/minute (3x burst)\n";
    echo "   webhook_calls: 50 requests/minute (no burst)\n";
    
    // Test custom limits
    $premiumUser = 'premium_user';
    $premiumStatus = $client->getRateLimitStatus($premiumUser);
    
    if (isset($premiumStatus['categories']['premium_api'])) {
        $premium = $premiumStatus['categories']['premium_api'];
        echo "🎫 Premium API tokens: {$premium['current_tokens']}/{$premium['max_tokens']}\n";
    }
    echo "\n";

    // ====================================================================
    // EXAMPLE 7: Rate Limiting Analytics and Monitoring
    // ====================================================================
    echo "📈 Example 7: Rate Limiting Analytics\n";
    echo "------------------------------------\n";
    
    // Get comprehensive metrics
    $globalMetrics = $client->getRateLimitingMetrics();
    
    echo "🌐 Global Rate Limiting Metrics:\n";
    echo "   Total Requests: {$globalMetrics['total_requests']}\n";
    echo "   Total Blocked: {$globalMetrics['total_blocked']}\n";
    echo "   Success Rate: {$globalMetrics['global_success_rate']}%\n";
    echo "   Block Rate: {$globalMetrics['global_block_rate']}%\n";
    
    if (isset($globalMetrics['limiters'])) {
        echo "\n📊 Per-Category Metrics:\n";
        foreach ($globalMetrics['limiters'] as $category => $metrics) {
            echo "   {$category}:\n";
            echo "     Allowed: {$metrics['allowed_requests']}\n";
            echo "     Blocked: {$metrics['blocked_requests']}\n";
            echo "     Burst: {$metrics['burst_requests']}\n";
            echo "     Success Rate: {$metrics['allowed_rate']}%\n";
        }
    }
    echo "\n";

    // ====================================================================
    // EXAMPLE 8: System Maintenance and Cleanup
    // ====================================================================
    echo "🧹 Example 8: System Maintenance\n";
    echo "-------------------------------\n";
    
    // Perform cleanup of expired data
    $cleanupResults = $client->cleanupRateLimiting();
    
    echo "🗑️  Cleanup Results:\n";
    echo "   Buckets Cleaned: {$cleanupResults['total_buckets_cleaned']}\n";
    echo "   Categories Affected: {$cleanupResults['categories_cleaned']}\n";
    echo "   Memory Freed: " . round($cleanupResults['memory_freed'] / 1024, 2) . " KB\n\n";

    // ====================================================================
    // FINAL SUMMARY
    // ====================================================================
    echo "⚡ Local Rate Limiting Features Summary\n";
    echo "======================================\n";
    echo "✅ Token bucket algorithm with configurable refill rates\n";
    echo "✅ Burst protection with adaptive multipliers\n";
    echo "✅ Fair queuing algorithm for equitable resource distribution\n";
    echo "✅ Priority-based request processing for different user tiers\n";
    echo "✅ Adaptive rate limiting based on system load\n";
    echo "✅ Category-specific limits for different API operations\n";
    echo "✅ Real-time metrics and monitoring\n";
    echo "✅ Automatic cleanup of expired rate limiting data\n";
    echo "✅ Custom rate limiting configuration per operation type\n";
    echo "✅ Persistent state management with cache integration\n";
    
    echo "\n🎯 Production Benefits:\n";
    echo "   • Prevents API abuse and ensures fair resource allocation\n";
    echo "   • Handles traffic bursts without complete service denial\n";
    echo "   • Maintains service quality during high-load periods\n";
    echo "   • Provides detailed analytics for capacity planning\n";
    echo "   • Supports different service tiers and user priorities\n";
    echo "   • Enables graceful degradation under system stress\n";
    echo "   • Reduces infrastructure costs through intelligent throttling\n";

} catch (\Exception $e) {
    echo "❌ Rate Limiting Demo Error: " . $e->getMessage() . "\n";
    echo "🔧 Troubleshooting:\n";
    echo "   • Verify rate limiting configuration parameters\n";
    echo "   • Check cache connectivity if using persistent storage\n";
    echo "   • Ensure sufficient system resources for token bucket operations\n";
    echo "   • Review category-specific rate limiting settings\n";
}