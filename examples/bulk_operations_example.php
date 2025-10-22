<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Wioex\SDK\Client;
use Wioex\SDK\Config;

/**
 * Bulk Operations Example
 *
 * This example demonstrates how to use the enhanced bulk operations
 * for efficient processing of large datasets with batching and performance optimization.
 */

// Initialize the WioEX SDK
$config = Config::create([
    'api_key' => 'your_api_key_here',
    'base_url' => 'https://api.wioex.com',
    'timeout' => 60, // Longer timeout for bulk operations
    'cache' => [
        'default' => 'memory',
    ]
]);

$client = new Client($config);

echo "=== WioEX Bulk Operations Example ===\n\n";

try {
    // 1. Bulk Quote Requests
    echo "1. Bulk Quote Operations:\n";

    // Define symbols for bulk operations
    $symbols = ['AAPL', 'GOOGL', 'MSFT', 'AMZN', 'TSLA', 'META', 'NVDA', 'AMD', 'INTC', 'CRM'];

    // Basic bulk quotes
    echo "   a) Basic bulk quotes for " . count($symbols) . " symbols...\n";
    $quotes = $client->stocks()->quoteBulk($symbols);

    echo "   ✓ Retrieved " . count($quotes['data']) . " quotes\n";
    echo "   ✓ Processing time: " . $quotes['metadata']['processing_time'] . "ms\n";
    echo "   ✓ Batch count: " . $quotes['metadata']['batch_count'] . "\n";

    // Display sample quote
    if (!empty($quotes['data'])) {
        $sample = reset($quotes['data']);
        echo "   📊 Sample quote - {$sample['symbol']}: \${$sample['price']} ({$sample['change_percent']}%)\n";
    }
    echo "\n";

    // Advanced bulk quotes with options
    echo "   b) Advanced bulk quotes with custom options...\n";
    $advancedQuotes = $client->stocks()->quoteBulk($symbols, [
        'batch_size' => 5,
        'delay_between_batches' => 100, // 100ms delay
        'include_extended_hours' => true,
        'include_fundamentals' => true,
        'continue_on_error' => true,
    ]);

    echo "   ✓ Batch processing completed\n";
    echo "   ✓ Successful: " . $advancedQuotes['metadata']['successful_requests'] . "\n";
    echo "   ✓ Failed: " . $advancedQuotes['metadata']['failed_requests'] . "\n";
    echo "   ✓ Total time: " . $advancedQuotes['metadata']['total_processing_time'] . "ms\n\n";

    // 2. Bulk Timeline Data
    echo "2. Bulk Timeline Operations:\n";

    $timelineSymbols = ['AAPL', 'GOOGL', 'MSFT'];
    echo "   Getting 1-month timeline data for " . count($timelineSymbols) . " symbols...\n";

    $timelines = $client->stocks()->timelineBulk($timelineSymbols, [
        'period' => '1M',
        'interval' => '1d',
        'batch_size' => 2,
        'include_volume' => true,
        'include_adjusted' => true,
    ]);

    echo "   ✓ Retrieved timeline data for " . count($timelines['data']) . " symbols\n";
    echo "   ✓ Processing time: " . $timelines['metadata']['processing_time'] . "ms\n";

    // Display sample timeline data
    if (!empty($timelines['data'])) {
        $sampleSymbol = array_key_first($timelines['data']);
        $sampleData = $timelines['data'][$sampleSymbol];
        echo "   📈 Sample timeline - {$sampleSymbol}: " . count($sampleData) . " data points\n";
        echo "   📅 Date range: " . $sampleData[0]['date'] . " to " . end($sampleData)['date'] . "\n";
    }
    echo "\n";

    // 3. Bulk Company Information
    echo "3. Bulk Company Info Operations:\n";

    $infoSymbols = ['AAPL', 'GOOGL', 'MSFT', 'AMZN'];
    echo "   Getting company information for " . count($infoSymbols) . " symbols...\n";

    $companyInfo = $client->stocks()->infoBulk($infoSymbols, [
        'include_fundamentals' => true,
        'include_financials' => true,
        'include_analysts' => true,
        'batch_size' => 2,
    ]);

    echo "   ✓ Retrieved company info for " . count($companyInfo['data']) . " companies\n";
    echo "   ✓ Processing time: " . $companyInfo['metadata']['processing_time'] . "ms\n";

    // Display sample company info
    if (!empty($companyInfo['data'])) {
        $sampleCompany = reset($companyInfo['data']);
        echo "   🏢 Sample company - {$sampleCompany['symbol']}: {$sampleCompany['name']}\n";
        echo "   🏭 Sector: {$sampleCompany['sector']} | Industry: {$sampleCompany['industry']}\n";
        echo "   💰 Market Cap: \$" . number_format($sampleCompany['market_cap']) . "\n";
    }
    echo "\n";

    // 4. Mixed Batch Processing
    echo "4. Mixed Batch Processing:\n";

    echo "   Processing mixed operations in a single batch...\n";
    $mixedOperations = [
        ['type' => 'quote', 'symbol' => 'AAPL'],
        ['type' => 'quote', 'symbol' => 'GOOGL'],
        ['type' => 'info', 'symbol' => 'MSFT'],
        ['type' => 'timeline', 'symbol' => 'AMZN', 'period' => '5d'],
        ['type' => 'quote', 'symbol' => 'TSLA'],
    ];

    $mixedResults = $client->stocks()->batchProcess($mixedOperations, [
        'batch_size' => 3,
        'continue_on_error' => true,
        'delay_between_batches' => 50,
    ]);

    echo "   ✓ Mixed batch completed\n";
    echo "   ✓ Total operations: " . count($mixedOperations) . "\n";
    echo "   ✓ Successful: " . $mixedResults['metadata']['successful_requests'] . "\n";
    echo "   ✓ Failed: " . $mixedResults['metadata']['failed_requests'] . "\n";
    echo "   ✓ Processing time: " . $mixedResults['metadata']['total_processing_time'] . "ms\n\n";

    // 5. Bulk Search Operations
    echo "5. Bulk Search Operations:\n";

    $searchQueries = ['technology stocks', 'dividend stocks', 'growth stocks', 'value stocks'];
    echo "   Performing bulk search for " . count($searchQueries) . " queries...\n";

    $searchResults = $client->stocks()->searchBulk($searchQueries, [
        'limit' => 5,
        'include_details' => true,
        'batch_size' => 2,
    ]);

    echo "   ✓ Search completed for " . count($searchResults['data']) . " queries\n";
    echo "   ✓ Processing time: " . $searchResults['metadata']['processing_time'] . "ms\n";

    // Display search results summary
    foreach ($searchResults['data'] as $query => $results) {
        echo "   🔍 '{$query}': " . count($results) . " results\n";
    }
    echo "\n";

    // 6. Performance Optimization Examples
    echo "6. Performance Optimization Tips:\n\n";

    echo "   a) Optimal Batch Sizing:\n";
    $performanceTest = function ($batchSize) use ($client, $symbols) {
        $start = microtime(true);
        $result = $client->stocks()->quoteBulk(array_slice($symbols, 0, 6), [
            'batch_size' => $batchSize,
        ]);
        $duration = (microtime(true) - $start) * 1000;
        return ['duration' => $duration, 'batch_count' => $result['metadata']['batch_count']];
    };

    $batchSizes = [2, 3, 6];
    foreach ($batchSizes as $size) {
        $result = $performanceTest($size);
        echo "      Batch size {$size}: {$result['duration']}ms ({$result['batch_count']} batches)\n";
    }
    echo "\n";

    echo "   b) Memory-Efficient Processing:\n";
    echo "      // Process large datasets with streaming\n";
    echo "      \$processor = function(\$batch) {\n";
    echo "          // Process each batch immediately\n";
    echo "          foreach (\$batch as \$item) {\n";
    echo "              // Handle individual items\n";
    echo "              yield \$item;\n";
    echo "          }\n";
    echo "      };\n\n";

    echo "   c) Error Handling Best Practices:\n";
    echo "      \$options = [\n";
    echo "          'continue_on_error' => true,\n";
    echo "          'retry_failed' => true,\n";
    echo "          'max_retries' => 3,\n";
    echo "          'retry_delay' => 1000, // 1 second\n";
    echo "      ];\n\n";

    // 7. Advanced Usage Patterns
    echo "7. Advanced Usage Patterns:\n\n";

    echo "   a) Progressive Data Loading:\n";
    echo "```php\n";
    echo "class ProgressiveLoader {\n";
    echo "    private \$client;\n";
    echo "    private \$batchSize = 10;\n";
    echo "    \n";
    echo "    public function loadStockData(array \$symbols, callable \$callback = null) {\n";
    echo "        \$chunks = array_chunk(\$symbols, \$this->batchSize);\n";
    echo "        \$results = [];\n";
    echo "        \n";
    echo "        foreach (\$chunks as \$i => \$chunk) {\n";
    echo "            \$quotes = \$this->client->stocks()->quoteBulk(\$chunk);\n";
    echo "            \$results = array_merge(\$results, \$quotes['data']);\n";
    echo "            \n";
    echo "            if (\$callback) {\n";
    echo "                \$callback(\$i + 1, count(\$chunks), \$results);\n";
    echo "            }\n";
    echo "        }\n";
    echo "        \n";
    echo "        return \$results;\n";
    echo "    }\n";
    echo "}\n";
    echo "```\n\n";

    echo "   b) Caching Integration:\n";
    echo "```php\n";
    echo "// Cache results for subsequent requests\n";
    echo "\$cache = \$client->getCache();\n";
    echo "\$cacheKey = 'bulk_quotes_' . md5(implode(',', \$symbols));\n";
    echo "\n";
    echo "if (\$cache->has(\$cacheKey)) {\n";
    echo "    \$quotes = \$cache->get(\$cacheKey);\n";
    echo "} else {\n";
    echo "    \$quotes = \$client->stocks()->quoteBulk(\$symbols);\n";
    echo "    \$cache->set(\$cacheKey, \$quotes, 300); // 5 minutes\n";
    echo "}\n";
    echo "```\n\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";

    if ($e instanceof \Wioex\SDK\Exceptions\RateLimitException) {
        echo "💡 Rate limit exceeded. Consider:\n";
        echo "   - Increasing batch delays\n";
        echo "   - Reducing batch sizes\n";
        echo "   - Implementing exponential backoff\n";
    } elseif ($e instanceof \Wioex\SDK\Exceptions\ValidationException) {
        echo "💡 Validation error. Check:\n";
        echo "   - Symbol formats\n";
        echo "   - Parameter values\n";
        echo "   - Required fields\n";
    }
}

echo "\n=== Example completed ===\n";
echo "\n💡 Best Practices for Bulk Operations:\n";
echo "- Start with small batch sizes and optimize based on performance\n";
echo "- Use continue_on_error for resilient processing\n";
echo "- Implement progress tracking for long-running operations\n";
echo "- Cache results when appropriate to reduce API calls\n";
echo "- Monitor rate limits and adjust delays accordingly\n";
echo "- Use mixed batches to combine different operation types\n";
echo "- Consider memory usage for large datasets\n";
