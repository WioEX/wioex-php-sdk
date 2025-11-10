<?php
/**
 * Unified NewsManager Example
 *
 * This comprehensive example demonstrates the new unified news management system
 * that intelligently routes requests to the best provider based on content type.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Wioex\SDK\WioexClient;

// Initialize the client
$client = new WioexClient([
    'api_key' => 'your-api-key-here',
    'timeout' => 30
]);

echo "=== WioEX Unified NewsManager Examples ===\n\n";

// Get the news manager
$newsManager = $client->newsManager();

// Example 1: Auto-selected provider based on content type
echo "Example 1: Auto Provider Selection\n";
echo str_repeat('-', 60) . "\n";

try {
    // News: Best served by WioEX native sources
    $news = $newsManager->get('TSLA', [
        'type' => 'news',
        'limit' => 10
    ]);
    
    if ($news->successful()) {
        $data = $news->json();
        echo "✓ News (auto-selected provider): " . ($data['provider'] ?? 'unknown') . "\n";
        echo "  Headlines: " . count($data['data'] ?? []) . " items\n";
    }

    // Analysis: Best served by Perplexity AI
    $analysis = $newsManager->get('TSLA', [
        'type' => 'analysis',
        'timeframe' => '7d'
    ]);
    
    if ($analysis->successful()) {
        $data = $analysis->json();
        echo "✓ Analysis (auto-selected provider): " . ($data['provider'] ?? 'unknown') . "\n";
        echo "  Events analyzed: " . count($data['events'] ?? []) . " items\n";
    }

    // Sentiment: Best served by Social (Trump Effect)
    $sentiment = $newsManager->get('TSLA', [
        'type' => 'sentiment',
        'timeframe' => '1d'
    ]);
    
    if ($sentiment->successful()) {
        $data = $sentiment->json();
        echo "✓ Sentiment (auto-selected provider): " . ($data['provider'] ?? 'unknown') . "\n";
        echo "  Mood index: " . ($data['mood_index'] ?? 'N/A') . "\n";
    }

} catch (Exception $e) {
    echo "Auto-selection test error: " . $e->getMessage() . "\n";
}

echo "\n\n";

// Example 2: Explicit provider selection
echo "Example 2: Explicit Provider Selection\n";
echo str_repeat('-', 60) . "\n";

try {
    // Force WioEX provider
    $wioexNews = $newsManager->get('AAPL', [
        'source' => 'wioex',
        'type' => 'news'
    ]);
    
    if ($wioexNews->successful()) {
        echo "✓ WioEX Provider: Successfully retrieved news\n";
        $data = $wioexNews->json();
        echo "  Status: " . ($data['status'] ?? 'unknown') . "\n";
    }

    // Force Perplexity provider
    $perplexityAnalysis = $newsManager->get('AAPL', [
        'source' => 'perplexity',
        'type' => 'analysis'
    ]);
    
    if ($perplexityAnalysis->successful()) {
        echo "✓ Perplexity Provider: Successfully retrieved analysis\n";
        $data = $perplexityAnalysis->json();
        echo "  Events: " . count($data['events'] ?? []) . " items\n";
    }

    // Force Social provider
    $socialSentiment = $newsManager->get('AAPL', [
        'source' => 'social',
        'type' => 'sentiment'
    ]);
    
    if ($socialSentiment->successful()) {
        echo "✓ Social Provider: Successfully retrieved sentiment\n";
        $data = $socialSentiment->json();
        echo "  Overall sentiment: " . ($data['overall_sentiment'] ?? 'unknown') . "\n";
    }

} catch (Exception $e) {
    echo "Explicit provider test error: " . $e->getMessage() . "\n";
}

echo "\n\n";

// Example 3: Multi-source comparison
echo "Example 3: Multi-Source Data Aggregation\n";
echo str_repeat('-', 60) . "\n";

try {
    $multiSource = $newsManager->getFromMultipleSources('MSFT', 
        ['wioex', 'perplexity', 'social'], 
        ['type' => 'analysis', 'limit' => 5]
    );
    
    if ($multiSource->successful()) {
        $data = $multiSource->json();
        echo "✓ Multi-source aggregation completed\n";
        echo "  Sources queried: " . ($data['total_sources'] ?? 0) . "\n";
        echo "  Successful sources: " . ($data['success_count'] ?? 0) . "\n";
        
        if (!empty($data['results'])) {
            echo "\n  Results by source:\n";
            foreach ($data['results'] as $source => $result) {
                echo "    - {$source}: " . (isset($result['symbol']) ? 'Success' : 'Failed') . "\n";
            }
        }
        
        if (!empty($data['errors'])) {
            echo "\n  Errors:\n";
            foreach ($data['errors'] as $source => $error) {
                echo "    - {$source}: {$error}\n";
            }
        }
    }

} catch (Exception $e) {
    echo "Multi-source test error: " . $e->getMessage() . "\n";
}

echo "\n\n";

// Example 4: Provider capabilities and health
echo "Example 4: Provider Management & Health Check\n";
echo str_repeat('-', 60) . "\n";

try {
    // Check provider health
    $health = $newsManager->getProvidersHealth();
    
    echo "Provider Health Status:\n";
    foreach ($health as $provider => $status) {
        $statusText = $status['status'] ?? 'unknown';
        $name = $status['name'] ?? $provider;
        
        echo "  • {$name} ({$provider}): {$statusText}\n";
        
        if (isset($status['capabilities'])) {
            $caps = $status['capabilities'];
            $supportedTypes = implode(', ', $caps['supports'] ?? []);
            echo "    Supports: {$supportedTypes}\n";
            
            if (isset($caps['limits']['requests_per_minute'])) {
                echo "    Rate limit: " . $caps['limits']['requests_per_minute'] . " req/min\n";
            }
        }
        
        if (isset($status['error'])) {
            echo "    Error: " . $status['error'] . "\n";
        }
        
        echo "\n";
    }

} catch (Exception $e) {
    echo "Health check error: " . $e->getMessage() . "\n";
}

echo "\n";

// Example 5: Advanced filtering and options
echo "Example 5: Advanced Filtering & Options\n";
echo str_repeat('-', 60) . "\n";

try {
    // Advanced sentiment analysis with filters
    $advancedSentiment = $newsManager->get('NVDA', [
        'type' => 'sentiment',
        'source' => 'social',
        'sentiment' => ['positive', 'negative'], // Exclude neutral
        'timeframe' => '7d',
        'limit' => 50
    ]);
    
    if ($advancedSentiment->successful()) {
        $data = $advancedSentiment->json();
        echo "✓ Advanced sentiment analysis completed\n";
        echo "  Provider: " . ($data['provider'] ?? 'unknown') . "\n";
        echo "  Overall sentiment: " . ($data['overall_sentiment'] ?? 'unknown') . "\n";
        
        if (isset($data['sentiment_metrics']['distribution'])) {
            $dist = $data['sentiment_metrics']['distribution'];
            echo "  Distribution: " . json_encode($dist) . "\n";
        }
        
        if (isset($data['sentiment_metrics']['volatility'])) {
            echo "  Volatility: " . $data['sentiment_metrics']['volatility'] . "\n";
        }
    }

    // Events analysis with type filtering
    $events = $newsManager->get('NVDA', [
        'type' => 'events',
        'source' => 'wioex',
        'event_types' => ['earnings', 'announcements'],
        'timeframe' => '30d'
    ]);
    
    if ($events->successful()) {
        $data = $events->json();
        echo "✓ Events analysis completed\n";
        echo "  Total events: " . count($data['events'] ?? []) . "\n";
        
        if (isset($data['event_types'])) {
            echo "  Event types: " . json_encode($data['event_types']) . "\n";
        }
    }

} catch (Exception $e) {
    echo "Advanced filtering error: " . $e->getMessage() . "\n";
}

echo "\n\n";

// Example 6: Direct provider access
echo "Example 6: Direct Provider Access\n";
echo str_repeat('-', 60) . "\n";

try {
    // Direct access to specific providers
    $wioexProvider = $newsManager->provider('wioex');
    $perplexityProvider = $newsManager->provider('perplexity');
    
    echo "Available providers:\n";
    echo "  • WioEX: " . $wioexProvider->getName() . "\n";
    echo "    Supports: " . implode(', ', array_keys(array_filter($wioexProvider->getCapabilities()['features'] ?? []))) . "\n";
    
    echo "  • Perplexity: " . $perplexityProvider->getName() . "\n";  
    echo "    Supports: " . implode(', ', array_keys(array_filter($perplexityProvider->getCapabilities()['features'] ?? []))) . "\n";
    
    // Use provider directly
    $directNews = $wioexProvider->getNews('GOOGL');
    if ($directNews->successful()) {
        echo "✓ Direct WioEX provider access successful\n";
    }
    
    $directAnalysis = $perplexityProvider->getAnalysis('GOOGL');
    if ($directAnalysis->successful()) {
        echo "✓ Direct Perplexity provider access successful\n";
    }

} catch (Exception $e) {
    echo "Direct provider access error: " . $e->getMessage() . "\n";
}

echo "\n\n";

// Example 7: Fallback mechanism demonstration
echo "Example 7: Fallback Mechanism\n";
echo str_repeat('-', 60) . "\n";

try {
    // Request with fallback enabled (default)
    $withFallback = $newsManager->get('AMZN', [
        'source' => 'auto', // This will auto-select
        'type' => 'analysis',
        'fallback' => true  // Enable fallback if primary fails
    ]);
    
    if ($withFallback->successful()) {
        $data = $withFallback->json();
        echo "✓ Request with fallback completed\n";
        echo "  Final provider used: " . ($data['provider'] ?? 'unknown') . "\n";
    }

    // Request without fallback
    $withoutFallback = $newsManager->get('AMZN', [
        'source' => 'non-existent-provider',
        'type' => 'analysis', 
        'fallback' => false
    ]);
    
    if (!$withoutFallback->successful()) {
        echo "✓ Request without fallback correctly failed\n";
        $data = $withoutFallback->json();
        echo "  Error: " . ($data['message'] ?? 'Unknown error') . "\n";
    }

} catch (Exception $e) {
    echo "Fallback test completed with expected error: " . $e->getMessage() . "\n";
}

echo "\n\n";

// Example 8: Performance comparison
echo "Example 8: Performance & Caching Demo\n";
echo str_repeat('-', 60) . "\n";

try {
    $symbol = 'META';
    
    // First request (should hit API)
    $start = microtime(true);
    $firstRequest = $newsManager->get($symbol, [
        'type' => 'news',
        'cache' => true
    ]);
    $firstTime = round((microtime(true) - $start) * 1000, 2);
    
    if ($firstRequest->successful()) {
        echo "✓ First request: {$firstTime}ms (API call)\n";
    }
    
    // Second request (should hit cache)
    $start = microtime(true);
    $secondRequest = $newsManager->get($symbol, [
        'type' => 'news',
        'cache' => true
    ]);
    $secondTime = round((microtime(true) - $start) * 1000, 2);
    
    if ($secondRequest->successful()) {
        echo "✓ Second request: {$secondTime}ms (cached)\n";
        echo "  Performance improvement: " . round(($firstTime - $secondTime) / $firstTime * 100, 1) . "%\n";
    }

} catch (Exception $e) {
    echo "Performance test error: " . $e->getMessage() . "\n";
}

echo "\n";

// Summary
echo "=== NewsManager System Summary ===\n";
echo "✓ Unified interface for all news sources\n";
echo "✓ Intelligent provider auto-selection\n";
echo "✓ Multi-source data aggregation\n";
echo "✓ Comprehensive error handling & fallbacks\n";
echo "✓ Advanced filtering & options\n";
echo "✓ Performance optimization with caching\n";
echo "✓ Provider health monitoring\n";
echo "✓ Backwards compatibility maintained\n";

echo "\n=== Migration Guide ===\n";
echo "Old API (still works):\n";
echo "  \$client->news()->latest('TSLA')\n";
echo "  \$client->newsAnalysis()->getFromExternal('TSLA')\n";
echo "\n";
echo "New Unified API:\n";
echo "  \$client->newsManager()->get('TSLA', ['type' => 'news'])\n";
echo "  \$client->newsManager()->get('TSLA', ['type' => 'analysis'])\n";
echo "  \$client->newsManager()->get('TSLA', ['source' => 'wioex'])\n";
echo "  \$client->newsManager()->get('TSLA', ['source' => 'perplexity'])\n";

echo "\n=== Examples Complete ===\n";