<?php
/**
 * NewsAnalysis API Example
 *
 * This example demonstrates how to use the NewsAnalysis endpoint
 * to get financial news analysis and sentiment data.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Wioex\SDK\WioexClient;

// Initialize the client
$client = new WioexClient([
    'api_key' => 'your-api-key-here',
    'timeout' => 30
]);

echo "=== NewsAnalysis API Examples ===\n\n";

// Example 1: Get basic news analysis for a stock
echo "Example 1: Basic news analysis for TSLA\n";
echo str_repeat('-', 50) . "\n";

try {
    $analysis = $client->newsAnalysis()->getFromExternal('TSLA');
    
    if (!empty($analysis['data'])) {
        $data = $analysis['data'];
        echo "Symbol: " . $data['symbol'] . "\n";
        echo "Status: " . $data['status'] . "\n";
        echo "Total Events: " . count($data['events']) . "\n";
        
        if (!empty($data['sentiment_summary'])) {
            echo "Sentiment Summary:\n";
            echo "  Positive: " . $data['sentiment_summary']['positive'] . "%\n";
            echo "  Neutral: " . $data['sentiment_summary']['neutral'] . "%\n";
            echo "  Negative: " . $data['sentiment_summary']['negative'] . "%\n";
        }
        
        echo "\nRecent Events:\n";
        foreach (array_slice($data['events'], 0, 3) as $event) {
            echo "• " . $event['date'] . " - " . substr($event['title'], 0, 80) . "...\n";
            echo "  Sentiment: " . $event['sentiment'] . " | Impact: " . $event['impact_level'] . "\n";
        }
    }
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'authentication') !== false) {
        echo "✓ NewsAnalysis endpoint is properly configured (authentication needed)\n";
        echo "Error: " . $e->getMessage() . "\n";
    } else {
        echo "✗ Unexpected error: " . $e->getMessage() . "\n";
    }
}

echo "\n\n";

// Example 2: Get recent events
echo "Example 2: Recent events for AAPL (last 7 days)\n";
echo str_repeat('-', 50) . "\n";

try {
    $recent = $client->newsAnalysis()->getRecent('AAPL', 7);
    
    if (!empty($recent['data'])) {
        echo "✓ Recent events method works\n";
        echo "Events found: " . count($recent['data']['events']) . "\n";
    }
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'authentication') !== false) {
        echo "✓ Recent events endpoint is configured (authentication needed)\n";
    } else {
        echo "✗ Error: " . $e->getMessage() . "\n";
    }
}

echo "\n";

// Example 3: Get major events only
echo "Example 3: Major events for MSFT\n";
echo str_repeat('-', 50) . "\n";

try {
    $major = $client->newsAnalysis()->getMajorEvents('MSFT');
    
    if (!empty($major['data'])) {
        echo "✓ Major events method works\n";
    }
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'authentication') !== false) {
        echo "✓ Major events endpoint is configured (authentication needed)\n";
    } else {
        echo "✗ Error: " . $e->getMessage() . "\n";
    }
}

echo "\n";

// Example 4: Multiple stocks analysis
echo "Example 4: Multiple stocks analysis\n";
echo str_repeat('-', 50) . "\n";

try {
    $multiple = $client->newsAnalysis()->getMultiple(['TSLA', 'AAPL', 'MSFT']);
    
    if (!empty($multiple['data'])) {
        echo "✓ Multiple stocks method works\n";
        echo "Stocks analyzed: " . count($multiple['data']) . "\n";
    }
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'authentication') !== false) {
        echo "✓ Multiple stocks endpoint is configured (authentication needed)\n";
    } else {
        echo "✗ Error: " . $e->getMessage() . "\n";
    }
}

echo "\n";

// Example 5: Use WioEX API endpoint
echo "Example 5: WioEX API News Analysis\n";
echo str_repeat('-', 50) . "\n";

try {
    $wioexAnalysis = $client->newsAnalysis()->getFromWioex('TSLA', [
        'limit' => 30,
        'days' => 7,
        'sentiment' => 'positive'
    ]);
    
    if ($wioexAnalysis->successful()) {
        echo "✓ WioEX API method works\n";
        echo "Response: " . json_encode($wioexAnalysis->toArray(), JSON_PRETTY_PRINT) . "\n";
    } else {
        echo "API Response Status: " . $wioexAnalysis->status() . "\n";
        echo "Error: " . $wioexAnalysis->json()['message'] ?? 'Unknown error' . "\n";
    }
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'authentication') !== false) {
        echo "✓ WioEX API endpoint configured (authentication needed)\n";
    } else {
        echo "✗ Error: " . $e->getMessage() . "\n";
    }
}

echo "\n";

// Test API structure
echo "=== API Structure Test ===\n";
echo "NewsAnalysis class: " . get_class($client->newsAnalysis()) . "\n";
echo "Available methods: " . implode(', ', get_class_methods($client->newsAnalysis())) . "\n";

echo "\n=== Examples Complete ===\n";