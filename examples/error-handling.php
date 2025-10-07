<?php

/**
 * WioEX PHP SDK - Error Handling Example
 *
 * This example demonstrates proper error handling patterns.
 */

require __DIR__ . '/../vendor/autoload.php';

use Wioex\SDK\WioexClient;
use Wioex\SDK\Exceptions\AuthenticationException;
use Wioex\SDK\Exceptions\ValidationException;
use Wioex\SDK\Exceptions\RateLimitException;
use Wioex\SDK\Exceptions\ServerException;
use Wioex\SDK\Exceptions\RequestException;
use Wioex\SDK\Exceptions\WioexException;

echo "=== WioEX PHP SDK - Error Handling Example ===\n\n";

// Example 1: Invalid API Key
echo "1. Testing invalid API key...\n";
try {
    $client = new WioexClient([
        'api_key' => 'invalid-key-12345'
    ]);
    $result = $client->stocks()->quote('AAPL');
} catch (AuthenticationException $e) {
    echo "✓ Authentication error caught: " . $e->getMessage() . "\n";
} catch (WioexException $e) {
    echo "Other error: " . $e->getMessage() . "\n";
}
echo "\n";

// Example 2: Invalid parameters
echo "2. Testing invalid parameters...\n";
try {
    $client = new WioexClient([
        'api_key' => 'your-api-key-here'
    ]);

    // Invalid ticker symbol
    $result = $client->stocks()->quote('');
} catch (ValidationException $e) {
    echo "✓ Validation error caught: " . $e->getMessage() . "\n";
}
echo "\n";

// Example 3: Rate limiting
echo "3. Testing rate limit handling...\n";
try {
    $client = new WioexClient([
        'api_key' => 'your-api-key-here'
    ]);

    // Simulate many rapid requests
    for ($i = 0; $i < 100; $i++) {
        $result = $client->stocks()->quote('AAPL');
    }
} catch (RateLimitException $e) {
    echo "✓ Rate limit error caught: " . $e->getMessage() . "\n";

    if ($retryAfter = $e->getRetryAfter()) {
        echo "  Retry after: {$retryAfter} seconds\n";
        echo "  Waiting...\n";
        sleep($retryAfter);
        echo "  Retrying request...\n";

        // Retry the request
        try {
            $result = $client->stocks()->quote('AAPL');
            echo "  ✓ Request succeeded after waiting!\n";
        } catch (WioexException $e) {
            echo "  Request still failed: " . $e->getMessage() . "\n";
        }
    }
}
echo "\n";

// Example 4: Network errors
echo "4. Testing connection error handling...\n";
try {
    // Use invalid base URL to force connection error
    $client = new WioexClient([
        'api_key' => 'your-api-key-here',
        'base_url' => 'https://invalid-domain-that-does-not-exist.com',
        'timeout' => 5
    ]);

    $result = $client->stocks()->quote('AAPL');
} catch (RequestException $e) {
    echo "✓ Request error caught: " . $e->getMessage() . "\n";
}
echo "\n";

// Example 5: Comprehensive error handling
echo "5. Comprehensive error handling pattern...\n";

$client = new WioexClient([
    'api_key' => 'your-api-key-here'
]);

function safeApiCall(callable $callback): void
{
    try {
        $result = $callback();

        if ($result->successful()) {
            echo "✓ Request successful!\n";
            echo "  Status: " . $result->status() . "\n";
            echo "  Data count: " . count($result->data()) . "\n";
        } else {
            echo "✗ Request failed with status: " . $result->status() . "\n";
        }
    } catch (AuthenticationException $e) {
        echo "✗ Authentication failed: " . $e->getMessage() . "\n";
        echo "  Action: Check your API key\n";
    } catch (ValidationException $e) {
        echo "✗ Validation error: " . $e->getMessage() . "\n";
        echo "  Action: Check your request parameters\n";
    } catch (RateLimitException $e) {
        echo "✗ Rate limit exceeded: " . $e->getMessage() . "\n";
        if ($retryAfter = $e->getRetryAfter()) {
            echo "  Action: Wait {$retryAfter} seconds before retrying\n";
        }
    } catch (ServerException $e) {
        echo "✗ Server error: " . $e->getMessage() . "\n";
        echo "  Action: The API is experiencing issues. Try again later.\n";
    } catch (RequestException $e) {
        echo "✗ Request failed: " . $e->getMessage() . "\n";
        echo "  Action: Check your internet connection\n";
    } catch (WioexException $e) {
        echo "✗ Unexpected error: " . $e->getMessage() . "\n";
        echo "  Context: " . json_encode($e->getContext()) . "\n";
    } catch (\Exception $e) {
        echo "✗ System error: " . $e->getMessage() . "\n";
    }
}

// Test successful request
safeApiCall(fn() => $client->stocks()->quote('AAPL'));
echo "\n";

// Test failing request
safeApiCall(fn() => $client->stocks()->quote('INVALID_TICKER_XYZ'));
echo "\n";

// Example 6: Retry logic example
echo "6. Custom retry logic example...\n";

function fetchWithRetry(WioexClient $client, string $ticker, int $maxAttempts = 3): ?array
{
    $attempt = 1;

    while ($attempt <= $maxAttempts) {
        try {
            echo "  Attempt {$attempt}/{$maxAttempts}...\n";

            $result = $client->stocks()->quote($ticker);

            if ($result->successful()) {
                echo "  ✓ Success!\n";
                return $result->data();
            }
        } catch (RateLimitException $e) {
            if ($attempt >= $maxAttempts) {
                echo "  ✗ Max attempts reached\n";
                throw $e;
            }

            $waitTime = $e->getRetryAfter() ?? ($attempt * 2);
            echo "  Rate limited. Waiting {$waitTime}s...\n";
            sleep($waitTime);
        } catch (ServerException $e) {
            if ($attempt >= $maxAttempts) {
                echo "  ✗ Max attempts reached\n";
                throw $e;
            }

            echo "  Server error. Retrying...\n";
            sleep($attempt); // Simple exponential backoff
        } catch (WioexException $e) {
            echo "  ✗ Unrecoverable error: " . $e->getMessage() . "\n";
            throw $e;
        }

        $attempt++;
    }

    return null;
}

try {
    $data = fetchWithRetry($client, 'AAPL');
    if ($data) {
        echo "  Final result: {$data['symbol']} - \${$data['price']}\n";
    }
} catch (WioexException $e) {
    echo "  Failed after all retries: " . $e->getMessage() . "\n";
}

echo "\n=== Example completed ===\n";
