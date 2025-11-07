<?php
/**
 * Trump Effect API Example
 *
 * This example demonstrates how to use the Trump Effect endpoint
 * to get social media posts and their market impact analysis.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Wioex\SDK\WioexClient;

// Initialize the client
$client = new WioexClient([
    'api_key' => 'your-api-key-here',
    'timeout' => 30
]);

echo "=== Trump Effect API Examples ===\n\n";

// Example 1: Get latest posts with default settings
echo "Example 1: Get latest posts (default: 20 per page)\n";
echo str_repeat('-', 60) . "\n";

$response = $client->news()->trumpEffect();

if ($response->successful()) {
    $data = $response['data'];

    echo "Overall Mood Index: " . $data['mood_index'] . " (0=bearish, 1=bullish)\n";
    echo "Total Posts: " . count($data['posts']) . "\n";
    echo "Has More: " . ($data['pagination']['has_more'] ? 'Yes' : 'No') . "\n\n";

    // Display first 3 posts
    foreach (array_slice($data['posts'], 0, 3) as $post) {
        echo "Post at " . $post['time'] . " (" . $post['sentiment']['name'] . "):\n";
        echo "  Summary: " . $post['summary'] . "\n";

        if (!empty($post['sectors'])) {
            echo "  Sectors: " . implode(', ', $post['sectors']) . "\n";
        }

        if (!empty($post['affected_securities'])) {
            $tickers = array_column($post['affected_securities'], 'ticker');
            echo "  Affected Tickers: " . implode(', ', $tickers) . "\n";
        }

        echo "\n";
    }
}

echo "\n";

// Example 2: Filter by sentiment (only bullish posts)
echo "Example 2: Filter by sentiment (trumpy/bullish only)\n";
echo str_repeat('-', 60) . "\n";

$response = $client->news()->trumpEffect([
    'sentiment' => ['trumpy'],
    'pageSize' => 10
]);

if ($response->successful()) {
    $data = $response['data'];
    echo "Bullish Posts: " . count($data['posts']) . "\n";
    echo "Mood Index: " . $data['mood_index'] . "\n\n";

    foreach ($data['posts'] as $post) {
        echo "• " . substr($post['summary'], 0, 80) . "...\n";
    }
}

echo "\n\n";

// Example 3: Track specific sectors
echo "Example 3: Find posts affecting Technology sector\n";
echo str_repeat('-', 60) . "\n";

$response = $client->news()->trumpEffect([
    'pageSize' => 50
]);

if ($response->successful()) {
    $data = $response['data'];
    $techPosts = array_filter($data['posts'], function($post) {
        return !empty($post['sectors']) && in_array('Technology', $post['sectors']);
    });

    echo "Found " . count($techPosts) . " posts affecting Technology sector\n\n";

    foreach (array_slice($techPosts, 0, 3) as $post) {
        echo "• " . $post['summary'] . "\n";
        if (!empty($post['affected_securities'])) {
            $names = array_column($post['affected_securities'], 'name');
            echo "  Companies: " . implode(', ', $names) . "\n";
        }
        echo "\n";
    }
}

echo "\n";

// Example 4: Monitor specific stocks
echo "Example 4: Check if TSLA is mentioned\n";
echo str_repeat('-', 60) . "\n";

$response = $client->news()->trumpEffect([
    'pageSize' => 100
]);

if ($response->successful()) {
    $data = $response['data'];

    $teslaPosts = array_filter($data['posts'], function($post) {
        if (empty($post['affected_securities'])) {
            return false;
        }

        $tickers = array_column($post['affected_securities'], 'ticker');
        return in_array('TSLA', $tickers);
    });

    if (!empty($teslaPosts)) {
        echo "TSLA mentioned in " . count($teslaPosts) . " posts\n\n";

        foreach ($teslaPosts as $post) {
            echo "Time: " . $post['timestamp'] . "\n";
            echo "Sentiment: " . $post['sentiment']['name'] . "\n";
            echo "Summary: " . $post['summary'] . "\n";
            echo "\n";
        }
    } else {
        echo "No mentions of TSLA found\n";
    }
}

echo "\n";

// Example 5: Pagination through all posts
echo "Example 5: Paginate through posts\n";
echo str_repeat('-', 60) . "\n";

$page = 1;
$totalPosts = 0;

while ($page <= 3) {  // Get first 3 pages
    $response = $client->news()->trumpEffect([
        'page' => $page,
        'pageSize' => 20
    ]);

    if ($response->successful()) {
        $data = $response['data'];
        $count = count($data['posts']);
        $totalPosts += $count;

        echo "Page $page: $count posts\n";

        if (!$data['pagination']['has_more']) {
            echo "No more posts available\n";
            break;
        }

        $page++;
    } else {
        echo "Error fetching page $page\n";
        break;
    }
}

echo "\nTotal posts retrieved: $totalPosts\n";

echo "\n";

// Example 6: Sentiment distribution analysis
echo "Example 6: Analyze sentiment distribution\n";
echo str_repeat('-', 60) . "\n";

$response = $client->news()->trumpEffect([
    'pageSize' => 100
]);

if ($response->successful()) {
    $data = $response['data'];

    $sentiments = [
        'trumpy' => 0,
        'neutral' => 0,
        'grumpy' => 0
    ];

    foreach ($data['posts'] as $post) {
        $sentiment = $post['sentiment']['name'];
        if (isset($sentiments[$sentiment])) {
            $sentiments[$sentiment]++;
        }
    }

    echo "Sentiment Distribution:\n";
    echo "  Trumpy (Bullish): " . $sentiments['trumpy'] . " posts\n";
    echo "  Neutral: " . $sentiments['neutral'] . " posts\n";
    echo "  Grumpy (Bearish): " . $sentiments['grumpy'] . " posts\n";
    echo "\nOverall Mood Index: " . $data['mood_index'] . "\n";
}

echo "\n=== Examples Complete ===\n";
