<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Wioex\SDK\Client;
use Wioex\SDK\Config;
use Wioex\SDK\Transformers\TransformerPipeline;
use Wioex\SDK\Transformers\BuiltIn\FilterTransformer;
use Wioex\SDK\Transformers\BuiltIn\MapTransformer;
use Wioex\SDK\Transformers\BuiltIn\NormalizationTransformer;

/**
 * Complete Integration Example
 *
 * This example demonstrates how all the enhanced features work together:
 * - WebSocket streaming with token management
 * - Bulk operations with intelligent batching
 * - Advanced caching with multiple drivers
 * - Data transformation pipelines
 * - Error handling and monitoring
 */

echo "=== WioEX Complete Integration Example ===\n\n";

// 1. Initialize SDK with Full Configuration
echo "1. Initializing SDK with complete configuration...\n";

$config = Config::create([
    'api_key' => 'your_api_key_here',
    'base_url' => 'https://api.wioex.com',
    'timeout' => 60,
    'connect_timeout' => 10,

    // Enhanced retry configuration
    'retry' => [
        'max_attempts' => 3,
        'initial_delay' => 1000,
        'max_delay' => 30000,
        'backoff_strategy' => 'exponential',
        'jitter' => true,
    ],

    // Rate limiting configuration
    'rate_limiting' => [
        'enabled' => true,
        'requests' => 100,
        'window' => 60,
        'strategy' => 'sliding_window',
        'burst_allowance' => 10,
    ],

    // Advanced caching configuration
    'cache' => [
        'default' => 'file',
        'drivers' => [
            'memory' => [
                'driver' => 'memory',
                'config' => []
            ],
            'file' => [
                'driver' => 'file',
                'config' => [
                    'cache_dir' => __DIR__ . '/cache/complete_example',
                    'extension' => '.cache',
                ]
            ]
        ]
    ],
]);

$client = new Client($config);
echo "✓ SDK initialized with advanced configuration\n\n";

try {
    // 2. WebSocket Integration with Caching
    echo "2. WebSocket Integration with Caching:\n";

    $cache = $client->getCache();
    $streamingCache = $cache->tags(['streaming', 'websocket']);

    // Check cached token first
    $token = $streamingCache->remember('streaming_token', function () use ($client) {
        echo "   🔄 Fetching new streaming token...\n";
        $response = $client->streaming()->getStreamingToken();
        return $response;
    }, 3300); // Cache for 55 minutes (token expires in 60)

    echo "   ✓ Streaming token: " . substr($token['token'], 0, 20) . "...\n";

    // Validate and refresh if needed
    if (!$client->streaming()->validateToken()) {
        echo "   🔄 Token invalid, refreshing...\n";
        $client->streaming()->refreshToken();
        $streamingCache->forget('streaming_token');
    }

    $websocketUrl = $client->streaming()->getWebSocketUrl();
    echo "   🌐 WebSocket URL ready: " . substr($websocketUrl, 0, 50) . "...\n\n";

    // 3. Bulk Operations with Transformation Pipeline
    echo "3. Bulk Operations with Transformation Pipeline:\n";

    // Create transformation pipeline for stock data
    $stockPipeline = new TransformerPipeline();
    $stockPipeline
        ->add(new NormalizationTransformer([
            'key_case' => 'camelCase',
            'convert_numeric_strings' => true,
            'remove_null_values' => true,
        ]), 100)
        ->add(new FilterTransformer([
            'mode' => 'whitelist',
            'fields' => ['symbol', 'price', 'changePercent', 'volume', 'marketCap', 'name'],
        ]), 90)
        ->add(new MapTransformer([
            'value_mappings' => [
                'price' => fn($p) => round($p, 2),
                'changePercent' => fn($c) => round($c, 2),
                'volume' => fn($v) => (int) $v,
                'marketCap' => fn($m) => (int) $m,
            ],
        ]), 80);

    // Define portfolio symbols
    $portfolioSymbols = ['AAPL', 'GOOGL', 'MSFT', 'AMZN', 'TSLA', 'META', 'NVDA', 'AMD'];

    echo "   📊 Processing portfolio of " . count($portfolioSymbols) . " symbols...\n";

    // Bulk quotes with caching
    $portfolioCache = $cache->tags(['portfolio', 'quotes']);
    $portfolioKey = 'portfolio_quotes_' . md5(implode(',', $portfolioSymbols));

    $portfolioData = $portfolioCache->remember($portfolioKey, function () use ($client, $portfolioSymbols, $stockPipeline) {
        echo "   🔄 Fetching fresh portfolio data...\n";

        $quotes = $client->stocks()->quoteBulk($portfolioSymbols, [
            'batch_size' => 4,
            'delay_between_batches' => 100,
            'continue_on_error' => true,
        ]);

        // Transform each quote through pipeline
        $transformedQuotes = [];
        foreach ($quotes['data'] as $symbol => $quote) {
            $transformedQuotes[$symbol] = $stockPipeline->transform($quote);
        }

        return [
            'quotes' => $transformedQuotes,
            'metadata' => $quotes['metadata'],
            'fetched_at' => time(),
        ];
    }, 300); // Cache for 5 minutes

    echo "   ✓ Portfolio data processed\n";
    echo "   📈 Successful quotes: " . count($portfolioData['quotes']) . "\n";
    echo "   ⏱️  Processing time: " . $portfolioData['metadata']['processing_time'] . "ms\n";
    echo "   💾 Data from: " . (time() - $portfolioData['fetched_at'] < 60 ? 'Cache' : 'API') . "\n\n";

    // 4. Real-time Portfolio Monitoring Service
    echo "4. Real-time Portfolio Monitoring Service:\n";

    class PortfolioMonitor
    {
        private $client;
        private $cache;
        private $pipeline;
        private $symbols;

        public function __construct($client, $cache, $pipeline, $symbols)
        {
            $this->client = $client;
            $this->cache = $cache->tags(['portfolio', 'monitoring']);
            $this->pipeline = $pipeline;
            $this->symbols = $symbols;
        }

        public function getPortfolioSummary(): array
        {
            $quotes = $this->getLatestQuotes();

            $totalValue = 0;
            $totalChange = 0;
            $winners = 0;
            $losers = 0;

            foreach ($quotes as $quote) {
                $totalValue += $quote['marketCap'] ?? 0;
                $change = $quote['changePercent'] ?? 0;
                $totalChange += $change;

                if ($change > 0) {
                    $winners++;
                } elseif ($change < 0) {
                    $losers++;
                }
            }

            return [
                'total_symbols' => count($quotes),
                'total_market_cap' => $totalValue,
                'average_change' => round($totalChange / count($quotes), 2),
                'winners' => $winners,
                'losers' => $losers,
                'neutral' => count($quotes) - $winners - $losers,
                'last_updated' => date('H:i:s'),
            ];
        }

        public function getTopMovers(int $limit = 3): array
        {
            $quotes = $this->getLatestQuotes();

            // Sort by change percentage
            uasort($quotes, fn($a, $b) => ($b['changePercent'] ?? 0) <=> ($a['changePercent'] ?? 0));

            return array_slice($quotes, 0, $limit, true);
        }

        public function getAlerts(): array
        {
            $quotes = $this->getLatestQuotes();
            $alerts = [];

            foreach ($quotes as $symbol => $quote) {
                $change = abs($quote['changePercent'] ?? 0);

                if ($change > 5) {
                    $alerts[] = [
                        'type' => 'high_volatility',
                        'symbol' => $symbol,
                        'message' => "{$symbol} moved {$quote['changePercent']}% today",
                        'severity' => $change > 10 ? 'high' : 'medium',
                    ];
                }

                if (($quote['volume'] ?? 0) > 100000000) {
                    $alerts[] = [
                        'type' => 'high_volume',
                        'symbol' => $symbol,
                        'message' => "{$symbol} has unusually high volume: " . number_format($quote['volume']),
                        'severity' => 'medium',
                    ];
                }
            }

            return $alerts;
        }

        private function getLatestQuotes(): array
        {
            return $this->cache->remember('latest_quotes', function () {
                $quotes = $this->client->stocks()->quoteBulk($this->symbols, [
                    'batch_size' => 5,
                    'continue_on_error' => true,
                ]);

                $transformed = [];
                foreach ($quotes['data'] as $symbol => $quote) {
                    $transformed[$symbol] = $this->pipeline->transform($quote);
                }

                return $transformed;
            }, 60); // 1 minute cache
        }
    }

    $monitor = new PortfolioMonitor($client, $cache, $stockPipeline, $portfolioSymbols);

    $summary = $monitor->getPortfolioSummary();
    echo "   📊 Portfolio Summary:\n";
    echo "     • Total symbols: {$summary['total_symbols']}\n";
    echo "     • Average change: {$summary['average_change']}%\n";
    echo "     • Winners: {$summary['winners']} | Losers: {$summary['losers']} | Neutral: {$summary['neutral']}\n";
    echo "     • Last updated: {$summary['last_updated']}\n";

    $topMovers = $monitor->getTopMovers(3);
    echo "   🚀 Top Movers:\n";
    foreach ($topMovers as $symbol => $quote) {
        $change = $quote['changePercent'] ?? 0;
        $emoji = $change > 0 ? '📈' : ($change < 0 ? '📉' : '➡️');
        echo "     {$emoji} {$symbol}: \${$quote['price']} ({$change}%)\n";
    }

    $alerts = $monitor->getAlerts();
    echo "   🚨 Active Alerts: " . count($alerts) . "\n";
    foreach (array_slice($alerts, 0, 3) as $alert) {
        $emoji = $alert['severity'] === 'high' ? '🔴' : '🟡';
        echo "     {$emoji} {$alert['message']}\n";
    }
    echo "\n";

    // 5. Historical Data Analysis with Caching
    echo "5. Historical Data Analysis with Caching:\n";

    $analysisSymbols = ['AAPL', 'GOOGL', 'MSFT'];
    echo "   📈 Analyzing historical data for " . count($analysisSymbols) . " symbols...\n";

    $historicalCache = $cache->tags(['historical', 'analysis']);

    foreach ($analysisSymbols as $symbol) {
        $cacheKey = "historical_analysis_{$symbol}_1m";

        $analysis = $historicalCache->remember($cacheKey, function () use ($client, $symbol) {
            $timeline = $client->stocks()->timelineBulk([$symbol], [
                'period' => '1M',
                'interval' => '1d',
            ]);

            $data = $timeline['data'][$symbol] ?? [];

            if (empty($data)) {
                return null;
            }

            $prices = array_column($data, 'close');
            $volumes = array_column($data, 'volume');

            return [
                'symbol' => $symbol,
                'data_points' => count($data),
                'price_range' => [
                    'min' => min($prices),
                    'max' => max($prices),
                    'avg' => array_sum($prices) / count($prices),
                ],
                'volume_avg' => array_sum($volumes) / count($volumes),
                'volatility' => $this->calculateVolatility($prices),
                'trend' => $this->calculateTrend($prices),
            ];
        }, 3600); // Cache for 1 hour

        if ($analysis) {
            echo "   📊 {$symbol} Analysis:\n";
            echo "     • Price range: \$" . number_format($analysis['price_range']['min'], 2) .
                 " - \$" . number_format($analysis['price_range']['max'], 2) . "\n";
            echo "     • Average price: \$" . number_format($analysis['price_range']['avg'], 2) . "\n";
            echo "     • Volatility: " . number_format($analysis['volatility'], 2) . "%\n";
            echo "     • Trend: " . $analysis['trend'] . "\n";
        }
    }
    echo "\n";

    // 6. System Health and Performance Monitoring
    echo "6. System Health and Performance Monitoring:\n";

    // Cache statistics
    $cacheStats = $cache->getStatistics();
    echo "   💾 Cache Performance:\n";
    echo "     • Default driver: {$cacheStats['default_driver']}\n";

    foreach ($cacheStats['drivers'] as $driver => $stats) {
        $hitRate = $stats['hit_rate_percentage'] ?? 0;
        echo "     • {$driver}: {$hitRate}% hit rate ({$stats['hits']} hits, {$stats['misses']} misses)\n";
    }

    // Transformer pipeline statistics
    $pipelineStats = $stockPipeline->getStatistics();
    echo "   🔄 Transformation Performance:\n";
    echo "     • Total executions: {$pipelineStats['total_executions']}\n";
    echo "     • Success rate: " . number_format($pipelineStats['success_rate'], 1) . "%\n";
    echo "     • Avg processing time: " . number_format($pipelineStats['average_processing_time'] * 1000, 2) . "ms\n";

    // Rate limiting status
    $rateLimitStatus = $client->getConfig()->getRateLimitingConfig();
    echo "   🚦 Rate Limiting:\n";
    echo "     • Strategy: {$rateLimitStatus['strategy']}\n";
    echo "     • Limit: {$rateLimitStatus['requests']} per {$rateLimitStatus['window']}s\n";
    echo "     • Status: " . ($rateLimitStatus['enabled'] ? 'Enabled' : 'Disabled') . "\n\n";

    // 7. Automated Trading Signal Generation
    echo "7. Automated Trading Signal Generation:\n";

    class TradingSignalGenerator
    {
        private $client;
        private $cache;

        public function __construct($client, $cache)
        {
            $this->client = $client;
            $this->cache = $cache->tags(['trading', 'signals']);
        }

        public function generateSignals(array $symbols): array
        {
            $signals = [];

            // Get bulk quotes and historical data
            $quotes = $this->client->stocks()->quoteBulk($symbols, [
                'batch_size' => 3,
                'continue_on_error' => true,
            ]);

            foreach ($quotes['data'] as $symbol => $quote) {
                $signal = $this->analyzeStock($symbol, $quote);
                if ($signal) {
                    $signals[] = $signal;
                }
            }

            return $signals;
        }

        private function analyzeStock(string $symbol, array $quote): ?array
        {
            $price = $quote['price'] ?? 0;
            $change = $quote['change_percent'] ?? 0;
            $volume = $quote['volume'] ?? 0;

            // Simple signal generation logic
            $signal = null;
            $confidence = 0;
            $reasons = [];

            // Momentum signals
            if ($change > 3) {
                $signal = 'buy';
                $confidence += 30;
                $reasons[] = 'Strong upward momentum';
            } elseif ($change < -3) {
                $signal = 'sell';
                $confidence += 30;
                $reasons[] = 'Strong downward momentum';
            }

            // Volume signals
            if ($volume > 50000000) {
                $confidence += 20;
                $reasons[] = 'High volume confirmation';
            }

            // Price level signals (simplified)
            if ($price < 50 && $change > 2) {
                $confidence += 15;
                $reasons[] = 'Low price with positive movement';
            }

            if ($confidence < 30) {
                return null; // Insufficient confidence
            }

            return [
                'symbol' => $symbol,
                'signal' => $signal,
                'confidence' => min($confidence, 100),
                'price' => $price,
                'change' => $change,
                'reasons' => $reasons,
                'generated_at' => date('Y-m-d H:i:s'),
            ];
        }
    }

    $signalGenerator = new TradingSignalGenerator($client, $cache);
    $signals = $signalGenerator->generateSignals(array_slice($portfolioSymbols, 0, 5));

    echo "   🎯 Generated " . count($signals) . " trading signals:\n";
    foreach ($signals as $signal) {
        $emoji = $signal['signal'] === 'buy' ? '🟢' : '🔴';
        echo "     {$emoji} {$signal['symbol']}: " . strtoupper($signal['signal']) .
             " (confidence: {$signal['confidence']}%)\n";
        echo "       Reasons: " . implode(', ', $signal['reasons']) . "\n";
    }
    echo "\n";

    // 8. WebSocket Event Simulation
    echo "8. WebSocket Event Simulation:\n";

    echo "   🌐 WebSocket connection simulation:\n";
    echo "   ```javascript\n";
    echo "   const ws = new WebSocket('{$websocketUrl}');\n";
    echo "   \n";
    echo "   ws.onopen = function() {\n";
    echo "       // Subscribe to portfolio symbols\n";
    echo "       ws.send(JSON.stringify({\n";
    echo "           action: 'subscribe',\n";
    echo "           channel: 'stocks.quotes',\n";
    echo "           symbols: ['" . implode("', '", $portfolioSymbols) . "']\n";
    echo "       }));\n";
    echo "   };\n";
    echo "   \n";
    echo "   ws.onmessage = function(event) {\n";
    echo "       const data = JSON.parse(event.data);\n";
    echo "       updatePortfolio(data);\n";
    echo "       checkForSignals(data);\n";
    echo "       updateCache(data);\n";
    echo "   };\n";
    echo "   ```\n\n";

    // 9. Performance Summary
    echo "9. Performance Summary:\n";

    $endTime = microtime(true);
    $totalTime = ($endTime - ($_SERVER['REQUEST_TIME_FLOAT'] ?? $endTime)) * 1000;

    echo "   ⚡ Total execution time: " . number_format($totalTime, 2) . "ms\n";
    echo "   📊 Operations performed:\n";
    echo "     • WebSocket token management: ✓\n";
    echo "     • Bulk quote processing: ✓\n";
    echo "     • Data transformations: ✓\n";
    echo "     • Cache operations: ✓\n";
    echo "     • Portfolio analysis: ✓\n";
    echo "     • Signal generation: ✓\n";
    echo "   🎯 All systems operational\n\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "📁 Error type: " . get_class($e) . "\n";

    if ($e instanceof \Wioex\SDK\Exceptions\RateLimitException) {
        echo "💡 Rate limit handling:\n";
        echo "   - Implement exponential backoff\n";
        echo "   - Reduce batch sizes\n";
        echo "   - Increase delays between requests\n";
    } elseif ($e instanceof \Wioex\SDK\Exceptions\AuthenticationException) {
        echo "💡 Authentication issue:\n";
        echo "   - Verify API key configuration\n";
        echo "   - Check token expiration\n";
        echo "   - Refresh streaming token\n";
    }
}

// Helper functions for analysis
function calculateVolatility(array $prices): float
{
    if (count($prices) < 2) {
        return 0;
    }

    $returns = [];
    for ($i = 1; $i < count($prices); $i++) {
        $returns[] = ($prices[$i] - $prices[$i - 1]) / $prices[$i - 1];
    }

    $mean = array_sum($returns) / count($returns);
    $variance = array_sum(array_map(fn($r) => pow($r - $mean, 2), $returns)) / count($returns);

    return sqrt($variance) * 100;
}

function calculateTrend(array $prices): string
{
    if (count($prices) < 10) {
        return 'insufficient_data';
    }

    $firstHalf = array_slice($prices, 0, (int)(count($prices) / 2));
    $secondHalf = array_slice($prices, (int)(count($prices) / 2));

    $firstAvg = array_sum($firstHalf) / count($firstHalf);
    $secondAvg = array_sum($secondHalf) / count($secondHalf);

    $change = ($secondAvg - $firstAvg) / $firstAvg * 100;

    if ($change > 2) {
        return 'uptrend';
    }
    if ($change < -2) {
        return 'downtrend';
    }
    return 'sideways';
}

echo "\n=== Complete Integration Example Completed ===\n";
echo "\n🎉 This example demonstrated:\n";
echo "• WebSocket token management with caching\n";
echo "• Bulk operations with intelligent batching\n";
echo "• Data transformation pipelines\n";
echo "• Multi-driver caching strategies\n";
echo "• Real-time portfolio monitoring\n";
echo "• Historical data analysis\n";
echo "• Trading signal generation\n";
echo "• System health monitoring\n";
echo "• Error handling and resilience\n";
echo "\n💡 Ready for production use with proper configuration!\n";
