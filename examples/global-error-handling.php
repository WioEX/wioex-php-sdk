<?php
/**
 * WioEX PHP SDK - Global Error Handling Example
 * 
 * This example demonstrates how to properly handle various server errors,
 * connection issues, and rate limits when using the WioEX SDK with 
 * automatic retry mechanisms and global error policies.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Wioex\SDK\WioexClient;
use Wioex\SDK\Exceptions\ServerException;
use Wioex\SDK\Exceptions\RateLimitException;

// Initialize client with enhanced retry configuration for global error handling
$client = new WioexClient([
    'api_key' => 'your-api-key-here',
    
    // Enhanced retry configuration - global error handling
    'enhanced_retry' => [
        'enabled' => true,
        'attempts' => 5,                        // Retry up to 5 times
        'backoff' => 'exponential',             // Exponential backoff strategy
        'base_delay' => 500,                    // Start with 500ms delay
        'max_delay' => 30000,                  // Max 30 seconds delay
        'jitter' => true,                       // Add randomization
        'retry_on_server_errors' => true,      // Retry on all 5xx errors (500, 501, 502, 503, 504, etc.)
        'retry_on_connection_errors' => true,  // Network/timeout errors (408, 598, 599)
        'retry_on_rate_limit' => true,         // Rate limit errors (429)
        'circuit_breaker_enabled' => true      // Use circuit breaker
    ],
    
    // Error reporting to track 503 patterns
    'error_reporting' => true,
    'telemetry' => [
        'enabled' => true,
        'auto_report_errors' => true
    ]
]);

function demonstrateErrorHandling($client) {
    echo "=== WioEX SDK Global Error Handling Demo ===\n\n";
    
    try {
        // Attempt to get stock data - this may trigger 503 errors
        echo "Attempting to fetch AAPL stock data...\n";
        $response = $client->stocks()->getRealTimePrice('AAPL');
        
        if ($response->successful()) {
            echo "✅ Successfully retrieved data:\n";
            echo "Price: $" . $response['price'] . "\n";
            echo "Change: " . $response['change_percent'] . "%\n";
        }
        
    } catch (ServerException $e) {
        echo "🔴 Server Error Caught: " . $e->getMessage() . "\n";
        
        // Check if this was any server error (5xx)
        if (str_contains($e->getMessage(), 'Service temporarily unavailable') || 
            str_contains($e->getMessage(), 'Internal server error') ||
            str_contains($e->getMessage(), 'Bad Gateway')) {
            echo "📋 This was a server error (5xx)\n";
            echo "💡 The SDK automatically retried with exponential backoff\n";
            echo "🔄 Global retry policy covers all server errors, not just 503\n";
        }
        
    } catch (RateLimitException $e) {
        echo "⏱️  Rate Limit Error: " . $e->getMessage() . "\n";
        echo "🔄 Retry after: " . $e->getRetryAfter() . " seconds\n";
        
    } catch (\Exception $e) {
        echo "❌ Unexpected Error: " . $e->getMessage() . "\n";
    }
}

function demonstrateRetryStats($client) {
    echo "\n=== Retry Statistics ===\n";
    
    try {
        // Get retry manager stats
        $retryManager = $client->retry();
        $stats = $retryManager->getRetryStats();
        
        echo "Total attempts: " . $stats['total_attempts'] . "\n";
        echo "Total retries: " . $stats['total_retries'] . "\n";
        echo "Successful retries: " . $stats['successful_retries'] . "\n";
        echo "Failed retries: " . $stats['failed_retries'] . "\n";
        echo "Total backoff time: " . round($stats['total_backoff_time'], 2) . "s\n";
        
    } catch (\Exception $e) {
        echo "Could not retrieve retry stats: " . $e->getMessage() . "\n";
    }
}

function demonstrateBestPractices() {
    echo "\n=== Global Error Handling Best Practices ===\n\n";
    
    echo "1. 🔄 Global Retry Configuration:\n";
    echo "   - retry_on_server_errors: All 5xx errors (500, 501, 502, 503, 504, etc.)\n";
    echo "   - retry_on_connection_errors: Network/timeout issues (408, 598, 599)\n";
    echo "   - retry_on_rate_limit: Rate limiting (429)\n";
    echo "   - Default: 5 attempts, 500ms base delay, max 30s\n\n";
    
    echo "2. 🛡️  Implement Circuit Breaker:\n";
    echo "   - Prevents cascading failures during service outages\n";
    echo "   - Works across all error types, not just specific codes\n";
    echo "   - Automatically enabled in enhanced retry config\n\n";
    
    echo "3. 📊 Monitor Error Patterns:\n";
    echo "   - Enable telemetry to track all error frequencies\n";
    echo "   - Use error reporting for comprehensive pattern analysis\n";
    echo "   - Track server errors, connection issues, and rate limits\n\n";
    
    echo "4. 🎯 Optimize Timeout Settings:\n";
    echo "   - Set appropriate connect_timeout and timeout values\n";
    echo "   - Default: 10s connect, 30s total timeout\n\n";
    
    echo "5. 🔀 Implement Fallback Strategies:\n";
    echo "   - Cache previous responses for critical data\n";
    echo "   - Use alternative data sources when available\n";
    echo "   - Consider graceful degradation for non-critical features\n\n";
}

function demonstrateManualRetry($client) {
    echo "=== Manual Retry Implementation ===\n";
    
    $maxRetries = 3;
    $baseDelay = 1; // seconds
    
    for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
        try {
            echo "Attempt $attempt of $maxRetries...\n";
            
            $response = $client->stocks()->getRealTimePrice('TSLA');
            
            if ($response->successful()) {
                echo "✅ Success on attempt $attempt\n";
                echo "TSLA Price: $" . $response['price'] . "\n";
                return;
            }
            
        } catch (ServerException $e) {
            if ($attempt < $maxRetries) {
                $delay = $baseDelay * pow(2, $attempt - 1); // Exponential backoff
                echo "🔄 Server error, retrying in {$delay}s...\n";
                sleep($delay);
                continue;
            }
            
            echo "🔴 Failed after $maxRetries attempts: " . $e->getMessage() . "\n";
            return;
            
        } catch (\Exception $e) {
            echo "❌ Non-retryable error: " . $e->getMessage() . "\n";
            return;
        }
    }
}

// Run demonstrations
try {
    demonstrateErrorHandling($client);
    demonstrateRetryStats($client);
    demonstrateBestPractices();
    demonstrateManualRetry($client);
    
} catch (\Exception $e) {
    echo "Demo failed: " . $e->getMessage() . "\n";
}

echo "\n=== Demo Complete ===\n";
echo "The WioEX SDK now includes enhanced global error handling with:\n";
echo "✅ Automatic retry for all server errors (5xx)\n";
echo "✅ Connection error handling (timeouts, network issues)\n";
echo "✅ Rate limit handling with backoff\n";
echo "✅ Circuit breaker protection across all error types\n";
echo "✅ Detailed error reporting and telemetry\n";
echo "✅ Configurable retry strategies per error category\n";