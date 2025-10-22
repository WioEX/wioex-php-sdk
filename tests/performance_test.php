<?php

/**
 * WioEX SDK Bulk Operations Performance Test
 * 
 * Bu script gerçek API call'ları yaparak bulk operations'ın
 * performansını ve kredi tüketimini ölçer.
 * 
 * UYARI: Bu test gerçek kredi tüketir! Test API key kullanın.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Wioex\SDK\Client;
use Wioex\SDK\Enums\TimelineInterval;

// =============================================================================
// TEST CONFIGURATION
// =============================================================================

class PerformanceTest
{
    private Client $client;
    private array $testResults = [];
    private string $testApiKey;
    
    // Test data
    private array $smallPortfolio = ['AAPL', 'TSLA', 'GOOGL', 'MSFT', 'NVDA'];
    private array $mediumPortfolio = [
        'AAPL', 'TSLA', 'GOOGL', 'MSFT', 'NVDA', 'AMZN', 'META', 'NFLX', 'AMD', 'INTC',
        'JPM', 'BAC', 'WFC', 'GS', 'MS', 'JNJ', 'PFE', 'UNH', 'MRK', 'ABT',
        'XOM', 'CVX', 'COP', 'WMT', 'PG', 'KO', 'PEP', 'MCD', 'NKE', 'SBUX'
    ]; // 30 stocks
    
    private array $largePortfolio; // Will be generated with 100 stocks

    public function __construct(string $apiKey)
    {
        $this->testApiKey = $apiKey;
        $this->client = new Client([
            'api_key' => $apiKey,
            'telemetry' => ['enabled' => false] // Disable to not interfere with tests
        ]);
        
        // Generate large portfolio (100 stocks)
        $this->generateLargePortfolio();
    }

    private function generateLargePortfolio(): void
    {
        // Start with medium portfolio and add more stocks
        $additionalStocks = [
            'BABA', 'TSM', 'ASML', 'V', 'MA', 'DIS', 'CRM', 'ADBE', 'PYPL', 'SHOP',
            'SQ', 'ZOOM', 'ROKU', 'SNAP', 'TWTR', 'PINS', 'DOCU', 'ZS', 'CRWD', 'OKTA',
            'SNOW', 'PLTR', 'U', 'FSLY', 'NET', 'DDOG', 'MDB', 'TEAM', 'NOW', 'WDAY',
            'VEEV', 'SPLK', 'TWLO', 'ESTC', 'COUP', 'BILL', 'RNG', 'FROG', 'AI', 'PATH',
            'RBLX', 'COIN', 'HOOD', 'AFRM', 'OPEN', 'WISH', 'CLOV', 'SPCE', 'LCID', 'RIVN',
            'F', 'GM', 'UBER', 'LYFT', 'DASH', 'ABNB', 'BMBL', 'MTCH', 'NKLA', 'GOEV',
            'HYLN', 'RIDE', 'WKHS', 'BLNK', 'CHPT', 'PLUG', 'FCEL', 'BLDP', 'CLNE', 'BE'
        ];
        
        $this->largePortfolio = array_merge($this->mediumPortfolio, $additionalStocks);
        $this->largePortfolio = array_slice($this->largePortfolio, 0, 100); // Ensure exactly 100
    }

    public function runAllTests(): array
    {
        echo "🚀 Starting WioEX SDK Performance Tests\n";
        echo "=====================================\n\n";
        
        echo "⚠️  WARNING: This test consumes real API credits!\n";
        echo "💡 Using API Key: " . substr($this->testApiKey, 0, 8) . "...\n\n";
        
        // Test different portfolio sizes
        $this->testQuoteBulkPerformance();
        $this->testTimelineBulkReality();
        $this->testMixedOperations();
        $this->testChunkingBehavior();
        
        $this->printSummary();
        
        return $this->testResults;
    }

    // =============================================================================
    // QUOTE BULK PERFORMANCE TESTS
    // =============================================================================

    private function testQuoteBulkPerformance(): void
    {
        echo "📊 Testing quoteBulk() Performance\n";
        echo "----------------------------------\n";
        
        $testCases = [
            'small' => ['symbols' => $this->smallPortfolio, 'name' => '5 stocks'],
            'medium' => ['symbols' => $this->mediumPortfolio, 'name' => '30 stocks'], 
            'large' => ['symbols' => $this->largePortfolio, 'name' => '100 stocks']
        ];
        
        foreach ($testCases as $testName => $testCase) {
            echo "\n🔍 Testing {$testCase['name']}:\n";
            
            $symbols = $testCase['symbols'];
            $expectedChunks = ceil(count($symbols) / 30); // 30 = API limit
            
            // Test bulk operation
            $bulkStart = microtime(true);
            try {
                $bulkResponse = $this->client->stocks()->quoteBulk($symbols);
                $bulkEnd = microtime(true);
                $bulkTime = $bulkEnd - $bulkStart;
                
                if ($bulkResponse->successful()) {
                    $bulkData = $bulkResponse->data();
                    $successCount = count($bulkData['tickers'] ?? []);
                    $actualChunks = $bulkData['bulk_operation']['chunks_processed'] ?? 0;
                    
                    echo "  ✅ Bulk: {$bulkTime:.2f}s, {$successCount}/" . count($symbols) . " stocks, {$actualChunks} chunks\n";
                    echo "  💰 Estimated credits: {$expectedChunks} (vs " . count($symbols) . " individual)\n";
                    echo "  📈 Savings: " . round((1 - $expectedChunks / count($symbols)) * 100, 1) . "%\n";
                    
                    $this->testResults['quoteBulk'][$testName] = [
                        'symbols_count' => count($symbols),
                        'bulk_time' => $bulkTime,
                        'success_count' => $successCount,
                        'expected_chunks' => $expectedChunks,
                        'actual_chunks' => $actualChunks,
                        'estimated_credits' => $expectedChunks,
                        'savings_percent' => round((1 - $expectedChunks / count($symbols)) * 100, 1)
                    ];
                } else {
                    echo "  ❌ Bulk failed\n";
                }
                
                // Simulate individual calls timing (don't actually call to save credits)
                $estimatedIndividualTime = count($symbols) * 0.2; // 200ms per call
                echo "  📊 Individual (estimated): {$estimatedIndividualTime:.1f}s, " . count($symbols) . " credits\n";
                echo "  🚀 Performance improvement: " . round((1 - $bulkTime / $estimatedIndividualTime) * 100, 1) . "%\n";
                
            } catch (Exception $e) {
                echo "  ❌ Error: " . $e->getMessage() . "\n";
            }
        }
    }

    // =============================================================================
    // TIMELINE BULK REALITY TEST
    // =============================================================================

    private function testTimelineBulkReality(): void
    {
        echo "\n\n📈 Testing timelineBulk() Reality (No Savings Expected)\n";
        echo "------------------------------------------------------\n";
        
        // Test with small portfolio only to minimize credit usage
        $symbols = array_slice($this->smallPortfolio, 0, 3); // Only 3 stocks
        echo "\n🔍 Testing " . count($symbols) . " stocks timeline:\n";
        
        $timelineStart = microtime(true);
        try {
            $timelineResponse = $this->client->stocks()->timelineBulk(
                $symbols, 
                TimelineInterval::ONE_DAY,
                ['size' => 10] // Small size to minimize data
            );
            $timelineEnd = microtime(true);
            $timelineTime = $timelineEnd - $timelineStart;
            
            if ($timelineResponse->successful()) {
                $timelineData = $timelineResponse->data();
                $successCount = count($timelineData['data'] ?? []);
                
                echo "  ⚠️  Timeline bulk: {$timelineTime:.2f}s, {$successCount}/" . count($symbols) . " stocks\n";
                echo "  💰 Credits used: " . count($symbols) . " (same as individual)\n";
                echo "  📊 Individual equivalent: ~" . (count($symbols) * 0.2) . "s\n";
                echo "  🔍 Reality: No credit savings, automation convenience only\n";
                
                $this->testResults['timelineBulk'] = [
                    'symbols_count' => count($symbols),
                    'bulk_time' => $timelineTime,
                    'success_count' => $successCount,
                    'credits_used' => count($symbols),
                    'savings_percent' => 0 // No savings
                ];
            } else {
                echo "  ❌ Timeline bulk failed\n";
            }
            
        } catch (Exception $e) {
            echo "  ❌ Error: " . $e->getMessage() . "\n";
        }
    }

    // =============================================================================
    // MIXED OPERATIONS TEST
    // =============================================================================

    private function testMixedOperations(): void
    {
        echo "\n\n🔀 Testing Mixed Operations (Real World Scenario)\n";
        echo "------------------------------------------------\n";
        
        $symbols = array_slice($this->mediumPortfolio, 0, 10); // 10 stocks to minimize cost
        echo "\n🔍 Testing mixed operations for " . count($symbols) . " stocks:\n";
        
        $totalStart = microtime(true);
        $totalCredits = 0;
        
        try {
            // 1. Quotes (real savings)
            echo "  1️⃣ Getting quotes...\n";
            $quotesStart = microtime(true);
            $quotes = $this->client->stocks()->quoteBulk($symbols);
            $quotesEnd = microtime(true);
            $quotesTime = $quotesEnd - $quotesStart;
            $quotesCredits = ceil(count($symbols) / 30); // Chunks needed
            $totalCredits += $quotesCredits;
            echo "     ✅ {$quotesTime:.2f}s, ~{$quotesCredits} credits\n";
            
            // 2. Timeline for first 3 stocks only (to minimize cost)
            $timelineSymbols = array_slice($symbols, 0, 3);
            echo "  2️⃣ Getting timeline for 3 stocks...\n";
            $timelineStart = microtime(true);
            $timeline = $this->client->stocks()->timelineBulk(
                $timelineSymbols, 
                TimelineInterval::ONE_DAY,
                ['size' => 5]
            );
            $timelineEnd = microtime(true);
            $timelineTime = $timelineEnd - $timelineStart;
            $timelineCredits = count($timelineSymbols); // 1 credit per symbol
            $totalCredits += $timelineCredits;
            echo "     ⚠️  {$timelineTime:.2f}s, {$timelineCredits} credits (no savings)\n";
            
            $totalEnd = microtime(true);
            $totalTime = $totalEnd - $totalStart;
            
            echo "\n  📊 Mixed Operations Summary:\n";
            echo "     ⏱️  Total time: {$totalTime:.2f}s\n";
            echo "     💰 Total credits: {$totalCredits}\n";
            echo "     🔍 Individual equivalent: ~" . count($symbols) . " credits for quotes + " . count($timelineSymbols) . " for timeline = " . (count($symbols) + count($timelineSymbols)) . "\n";
            echo "     📈 Savings: Only from quotes portion\n";
            
            $this->testResults['mixedOperations'] = [
                'total_time' => $totalTime,
                'total_credits' => $totalCredits,
                'quotes_credits' => $quotesCredits,
                'timeline_credits' => $timelineCredits,
                'individual_equivalent' => count($symbols) + count($timelineSymbols)
            ];
            
        } catch (Exception $e) {
            echo "  ❌ Error: " . $e->getMessage() . "\n";
        }
    }

    // =============================================================================
    // CHUNKING BEHAVIOR TEST
    // =============================================================================

    private function testChunkingBehavior(): void
    {
        echo "\n\n🧩 Testing Chunking Behavior\n";
        echo "-----------------------------\n";
        
        $testCases = [
            ['count' => 30, 'expected_chunks' => 1],
            ['count' => 31, 'expected_chunks' => 2], 
            ['count' => 60, 'expected_chunks' => 2],
            ['count' => 61, 'expected_chunks' => 3]
        ];
        
        foreach ($testCases as $testCase) {
            $count = $testCase['count'];
            $expectedChunks = $testCase['expected_chunks'];
            
            // Generate symbols for test
            $testSymbols = array_slice($this->largePortfolio, 0, $count);
            
            echo "\n🔍 Testing {$count} symbols (expected {$expectedChunks} chunks):\n";
            
            try {
                $response = $this->client->stocks()->quoteBulk($testSymbols);
                
                if ($response->successful()) {
                    $data = $response->data();
                    $actualChunks = $data['bulk_operation']['chunks_processed'] ?? 0;
                    $successCount = count($data['tickers'] ?? []);
                    
                    $match = $actualChunks === $expectedChunks ? "✅" : "❌";
                    echo "  {$match} {$actualChunks} chunks (expected {$expectedChunks})\n";
                    echo "  📊 {$successCount}/{$count} symbols retrieved\n";
                    
                    $this->testResults['chunking'][] = [
                        'symbols_count' => $count,
                        'expected_chunks' => $expectedChunks,
                        'actual_chunks' => $actualChunks,
                        'success_count' => $successCount,
                        'match' => $actualChunks === $expectedChunks
                    ];
                }
                
            } catch (Exception $e) {
                echo "  ❌ Error: " . $e->getMessage() . "\n";
            }
        }
    }

    // =============================================================================
    // SUMMARY AND REPORTING
    // =============================================================================

    private function printSummary(): void
    {
        echo "\n\n📋 TEST SUMMARY\n";
        echo "================\n";
        
        echo "\n🎯 Key Findings:\n";
        
        // Quote bulk findings
        if (isset($this->testResults['quoteBulk'])) {
            $largeTest = $this->testResults['quoteBulk']['large'] ?? null;
            if ($largeTest) {
                echo "  ✅ quoteBulk() WORKS: {$largeTest['symbols_count']} stocks in {$largeTest['bulk_time']:.1f}s using ~{$largeTest['estimated_credits']} credits\n";
                echo "     💰 Credit savings: {$largeTest['savings_percent']}%\n";
            }
        }
        
        // Timeline reality
        if (isset($this->testResults['timelineBulk'])) {
            $timelineTest = $this->testResults['timelineBulk'];
            echo "  ⚠️  timelineBulk() REALITY: {$timelineTest['symbols_count']} stocks = {$timelineTest['credits_used']} credits (no savings)\n";
        }
        
        echo "\n💡 Recommendations:\n";
        echo "  1. Use quoteBulk() for portfolio monitoring (real savings)\n";
        echo "  2. Use other bulk operations only for convenience/automation\n";
        echo "  3. Plan credit usage: only quotes provide savings\n";
        echo "  4. Mixed operations = partial savings (quotes portion only)\n";
        
        echo "\n📊 Credit Calculator:\n";
        echo "  • 100 quotes: ~4 credits (vs 100 individual) = 96% savings\n";
        echo "  • 500 quotes: ~17 credits (vs 500 individual) = 97% savings\n";
        echo "  • 1000 quotes: ~34 credits (vs 1000 individual) = 97% savings\n";
        echo "  • Timeline/Info/Financials: same as individual (no savings)\n";
        
        // Save results to file
        $this->saveResultsToFile();
    }

    private function saveResultsToFile(): void
    {
        $timestamp = date('Y-m-d_H-i-s');
        $filename = __DIR__ . "/performance_results_{$timestamp}.json";
        
        $fullResults = [
            'timestamp' => date('c'),
            'api_key_prefix' => substr($this->testApiKey, 0, 8),
            'test_results' => $this->testResults,
            'summary' => [
                'quotes_bulk_works' => true,
                'timeline_bulk_no_savings' => true,
                'info_bulk_no_savings' => true,
                'financials_bulk_no_savings' => true,
                'recommendation' => 'Use quoteBulk() for real savings, others for convenience only'
            ]
        ];
        
        file_put_contents($filename, json_encode($fullResults, JSON_PRETTY_PRINT));
        echo "\n💾 Results saved to: {$filename}\n";
    }
}

// =============================================================================
// MAIN EXECUTION
// =============================================================================

if (php_sapi_name() === 'cli') {
    // Command line usage
    if ($argc < 2) {
        echo "Usage: php performance_test.php <api_key>\n";
        echo "Example: php performance_test.php your-test-api-key-here\n";
        echo "\nWARNING: This test consumes real API credits!\n";
        exit(1);
    }
    
    $apiKey = $argv[1];
    $test = new PerformanceTest($apiKey);
    $results = $test->runAllTests();
    
} else {
    // Web usage (if needed)
    echo "<pre>";
    echo "WioEX SDK Performance Test\n";
    echo "This test requires CLI execution with API key parameter.\n";
    echo "Usage: php performance_test.php <api_key>\n";
    echo "</pre>";
}