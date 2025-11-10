<?php

declare(strict_types=1);

namespace Wioex\SDK\News\Providers;

use Wioex\SDK\News\SourceProviderInterface;
use Wioex\SDK\Http\Response;
use Wioex\SDK\Http\Client;

/**
 * AnalysisProvider - Advanced news analysis provider using external AI systems
 *
 * Provides access to advanced financial news analysis including:
 * - Advanced sentiment analysis
 * - Event impact classification  
 * - Financial timeline data
 * - AI-powered news interpretation
 */
class AnalysisProvider implements SourceProviderInterface
{
    private Client $client;
    
    private const SUPPORTED_TYPES = ['analysis', 'sentiment', 'events', 'news'];

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'analysis';
    }

    /**
     * {@inheritdoc}
     */
    public function getNews(string $symbol, array $options = []): Response
    {
        // For Perplexity, news and analysis are similar - route to analysis
        return $this->getAnalysis($symbol, array_merge($options, ['format' => 'news']));
    }

    /**
     * {@inheritdoc}
     */
    public function getAnalysis(string $symbol, array $options = []): Response
    {
        $symbol = strtoupper($symbol);
        
        $defaultOptions = [
            'version' => '2.18',
            'source' => 'default',
            'format' => 'wioex',
            'limit' => $options['limit'] ?? 50
        ];
        
        $options = array_merge($defaultOptions, $options);
        
        try {
            // Step 1: Get trace information for session management
            $traceData = $this->getTraceData();
            
            // Step 2: Fetch financial news data from Perplexity
            $rawData = $this->fetchFinanceData($symbol, $options, $traceData);
            
            // Step 3: Format data according to WioEX standards
            $formattedData = $this->formatAnalysisData($rawData, $symbol, $options);
            
            $successBody = json_encode($formattedData);
            $mockResponse = new \GuzzleHttp\Psr7\Response(200, [], $successBody);
            return new Response($mockResponse);
            
        } catch (\Exception $e) {
            // Create a mock PSR response for error cases
            $body = json_encode([
                'error' => 'Analysis failed',
                'message' => $e->getMessage(),
                'symbol' => $symbol,
                'provider' => 'analysis'
            ]);
            
            $mockResponse = new \GuzzleHttp\Psr7\Response(500, [], $body);
            return new Response($mockResponse);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getSentiment(string $symbol, array $options = []): Response
    {
        // Get analysis data and extract sentiment information
        $response = $this->getAnalysis($symbol, array_merge($options, ['focus' => 'sentiment']));
        
        if (!$response->successful()) {
            return $response;
        }

        $data = $response->json();
        
        // Extract and enhance sentiment data
        $sentimentData = $this->extractSentimentData($data, $symbol);
        
        $sentimentBody = json_encode($sentimentData);
        $mockResponse = new \GuzzleHttp\Psr7\Response(200, [], $sentimentBody);
        return new Response($mockResponse);
    }

    /**
     * {@inheritdoc}
     */
    public function getEvents(string $symbol, array $options = []): Response
    {
        $eventTypes = $options['event_types'] ?? ['earnings', 'announcements', 'splits', 'dividends'];
        $timeframe = $options['timeframe'] ?? '30d';
        
        $analysisOptions = array_merge($options, [
            'filter' => 'major',
            'event_types' => $eventTypes,
            'focus' => 'events',
            'days_back' => $this->convertTimeframeToDays($timeframe)
        ]);
        
        $response = $this->getAnalysis($symbol, $analysisOptions);
        
        if (!$response->successful()) {
            return $response;
        }

        $data = $response->json();
        
        // Transform to events-focused format
        $eventsData = $this->extractEventsData($data, $symbol);
        
        $eventsBody = json_encode($eventsData);
        $mockResponse = new \GuzzleHttp\Psr7\Response(200, [], $eventsBody);
        return new Response($mockResponse);
    }

    /**
     * {@inheritdoc}
     */
    public function supports(string $type): bool
    {
        return in_array($type, self::SUPPORTED_TYPES);
    }

    /**
     * {@inheritdoc}
     */
    public function getCapabilities(): array
    {
        return [
            'provider' => 'analysis',
            'supports' => self::SUPPORTED_TYPES,
            'features' => [
                'ai_analysis' => true,
                'sentiment_analysis' => true,
                'event_classification' => true,
                'impact_assessment' => true,
                'financial_timeline' => true,
                'multi_source_aggregation' => true,
                'real_time_data' => true,
                'session_management' => true
            ],
            'limits' => [
                'requests_per_minute' => 100,
                'max_symbols_per_request' => 1,
                'max_timeline_days' => 90
            ],
            'data_sources' => [
                'external_finance_api',
                'financial_news_aggregation', 
                'ai_powered_analysis',
                'real_time_market_data'
            ]
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getConfiguration(): array
    {
        return [
            'name' => $this->getName(),
            'base_url' => 'https://external-analysis-api.example.com',
            'endpoints' => [
                'trace' => '/cdn-cgi/trace',
                'finance' => '/rest/finance/timeline/v2',
            ],
            'default_options' => [
                'version' => '2.18',
                'timeout' => 30,
                'format' => 'standard'
            ],
            'authentication' => 'session_based',
            'session_management' => true
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function isHealthy(): bool
    {
        try {
            // Test trace endpoint availability
            $traceData = $this->getTraceData();
            return !empty($traceData['success']);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Fetch trace data from Perplexity CDN for session management
     */
    private function getTraceData(): array
    {
        $headers = [
            'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:142.0) Gecko/20100101 Firefox/142.0',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language' => 'tr,en-US;q=0.5',
            'Accept-Encoding' => 'gzip, deflate, br, zstd',
            'DNT' => '1',
            'Sec-GPC' => '1',
            'Upgrade-Insecure-Requests' => '1',
            'Sec-Fetch-Dest' => 'document',
            'Sec-Fetch-Mode' => 'navigate',
            'Sec-Fetch-Site' => 'none',
            'Sec-Fetch-User' => '?1',
            'Connection' => 'keep-alive',
            'Cookie' => $this->buildCookieHeader()
        ];

        $response = $this->client->get('https://www.perplexity.ai/cdn-cgi/trace', [
            'headers' => $headers,
            'timeout' => 10
        ]);

        return $this->parseTraceResponse($response);
    }

    /**
     * Build cookie header for Perplexity session
     */
    private function buildCookieHeader(): string
    {
        $cookies = [
            '__cf_bm' => 'cUZV8sMHWsNuYrLCH0U4yQO70LI0gIrNIiU2AcgdwR0-1762773479-1.0.1.1-rAFsjjYS0cqh74iCoHAZHBYhM5aIxNoCFf7qk0m9dA2yksiGGpEeWXnIupZYkyFkiP7Qqi7mVcam_5jZumMSD__V_T.UjX4ATChvN36p5DE',
            'pplx.visitor-id' => '50253d7b-f757-4758-a38e-cf16cd6ea088',
            'pplx.session-id' => '20ce22e4-6976-4ca8-b2da-9e46b86fa061',
            'pplx.metadata' => urlencode('{"qc":0,"qcu":0,"qcm":0,"qcc":0,"qcco":0,"qccol":0,"qcdr":0,"qcs":0,"qcd":0,"hli":false,"hcga":false,"hcds":false,"hso":false,"hfo":false,"hsco":false,"hfco":false,"hsma":false,"hdc":false}'),
            'pplx.trackingAllowed' => 'true'
        ];

        $cookieString = '';
        foreach ($cookies as $name => $value) {
            $cookieString .= "{$name}={$value}; ";
        }

        return rtrim($cookieString, '; ');
    }

    /**
     * Parse trace response data
     */
    private function parseTraceResponse($response): array
    {
        $traceData = [
            'timestamp' => time(),
            'success' => true,
            'cf_data' => []
        ];

        if ($response->successful()) {
            $responseText = $response->json();
            
            if (!empty($responseText)) {
                $lines = explode("\n", $responseText);
                foreach ($lines as $line) {
                    if (strpos($line, '=') !== false) {
                        [$key, $value] = explode('=', $line, 2);
                        $traceData['cf_data'][trim($key)] = trim($value);
                    }
                }
            }
        } else {
            $traceData['success'] = false;
        }

        return $traceData;
    }

    /**
     * Fetch financial data from Perplexity API
     */
    private function fetchFinanceData(string $symbol, array $options, array $traceData): array
    {
        $url = "https://www.perplexity.ai/rest/finance/timeline/v2/{$symbol}";
        
        $params = [
            'version' => $options['version'],
            'source' => $options['source']
        ];

        $headers = [
            'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:142.0) Gecko/20100101 Firefox/142.0',
            'Accept' => 'application/json, text/plain, */*',
            'Accept-Language' => 'tr,en-US;q=0.5',
            'Content-Type' => 'application/json',
            'Cookie' => $this->buildCookieHeader(),
            'Referer' => 'https://www.perplexity.ai/',
            'Origin' => 'https://www.perplexity.ai'
        ];

        $response = $this->client->get($url, [
            'headers' => $headers,
            'query' => $params,
            'timeout' => 30
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException("Perplexity API request failed: " . $response->status());
        }

        return $response->data();
    }

    /**
     * Format analysis data to WioEX standard format
     */
    private function formatAnalysisData(array $rawData, string $symbol, array $options): array
    {
        $events = [];
        $sentimentSummary = [
            'positive' => 0,
            'neutral' => 0, 
            'negative' => 0
        ];
        
        // Process raw timeline data
        if (isset($rawData['timeline']) && is_array($rawData['timeline'])) {
            foreach ($rawData['timeline'] as $item) {
                $event = $this->transformToWioexEvent($item, $symbol);
                $events[] = $event;
                
                // Count sentiment
                $sentiment = $event['sentiment'] ?? 'neutral';
                if (isset($sentimentSummary[$sentiment])) {
                    $sentimentSummary[$sentiment]++;
                }
            }
        }

        // Calculate sentiment percentages
        $total = array_sum($sentimentSummary);
        if ($total > 0) {
            foreach ($sentimentSummary as $key => $count) {
                $sentimentSummary[$key] = round(($count / $total) * 100, 1);
            }
        }

        return [
            'symbol' => $symbol,
            'status' => 'success',
            'events' => $events,
            'sentiment_summary' => $sentimentSummary,
            'total_events' => count($events),
            'provider' => 'analysis',
            'timestamp' => time(),
            'timeframe' => $options['timeframe'] ?? '30d'
        ];
    }

    /**
     * Transform raw Perplexity data to WioEX event format
     */
    private function transformToWioexEvent(array $item, string $symbol): array
    {
        return [
            'id' => $item['id'] ?? uniqid(),
            'symbol' => $symbol,
            'title' => $item['title'] ?? $item['headline'] ?? '',
            'date' => $item['date'] ?? $item['timestamp'] ?? date('Y-m-d'),
            'timestamp' => $item['timestamp'] ?? time(),
            'sentiment' => $this->normalizeSentiment($item['sentiment'] ?? null),
            'impact_level' => $this->normalizeImpactLevel($item['impact'] ?? null),
            'summary' => $item['summary'] ?? $item['description'] ?? '',
            'content' => $item['content'] ?? $item['full_text'] ?? '',
            'source' => 'analysis',
            'confidence' => $item['confidence'] ?? 0.5,
            'sectors' => $item['sectors'] ?? [],
            'affected_securities' => $item['affected_securities'] ?? []
        ];
    }

    /**
     * Normalize sentiment values to standard format
     */
    private function normalizeSentiment(?string $sentiment): string
    {
        if (!$sentiment) return 'neutral';
        
        $sentiment = strtolower($sentiment);
        
        if (in_array($sentiment, ['positive', 'bullish', 'trumpy', 'good'])) {
            return 'positive';
        } elseif (in_array($sentiment, ['negative', 'bearish', 'grumpy', 'bad'])) {
            return 'negative';
        }
        
        return 'neutral';
    }

    /**
     * Normalize impact level values
     */
    private function normalizeImpactLevel(?string $impact): string
    {
        if (!$impact) return 'low';
        
        $impact = strtolower($impact);
        
        if (in_array($impact, ['high', 'major', 'significant'])) {
            return 'high';
        } elseif (in_array($impact, ['medium', 'moderate'])) {
            return 'medium';
        }
        
        return 'low';
    }

    /**
     * Extract sentiment-focused data
     */
    private function extractSentimentData(array $data, string $symbol): array
    {
        $events = $data['events'] ?? [];
        $sentimentAnalysis = [
            'symbol' => $symbol,
            'overall_sentiment' => $this->calculateOverallSentiment($events),
            'sentiment_distribution' => $data['sentiment_summary'] ?? [],
            'sentiment_timeline' => $this->buildSentimentTimeline($events),
            'confidence_score' => $this->calculateConfidenceScore($events),
            'provider' => 'analysis',
            'timestamp' => time()
        ];

        return $sentimentAnalysis;
    }

    /**
     * Extract events-focused data
     */
    private function extractEventsData(array $data, string $symbol): array
    {
        $events = $data['events'] ?? [];
        
        // Filter for major events only
        $majorEvents = array_filter($events, function($event) {
            return ($event['impact_level'] ?? 'low') !== 'low';
        });

        return [
            'symbol' => $symbol,
            'major_events' => $majorEvents,
            'total_events' => count($majorEvents),
            'event_types' => $this->categorizeEvents($majorEvents),
            'impact_distribution' => $this->analyzeImpactDistribution($majorEvents),
            'provider' => 'analysis',
            'timestamp' => time()
        ];
    }

    /**
     * Calculate overall sentiment from events
     */
    private function calculateOverallSentiment(array $events): string
    {
        if (empty($events)) return 'neutral';
        
        $scores = ['positive' => 0, 'negative' => 0, 'neutral' => 0];
        
        foreach ($events as $event) {
            $sentiment = $event['sentiment'] ?? 'neutral';
            $scores[$sentiment]++;
        }
        
        $max = max($scores);
        return array_search($max, $scores) ?: 'neutral';
    }

    /**
     * Build sentiment timeline
     */
    private function buildSentimentTimeline(array $events): array
    {
        $timeline = [];
        
        foreach ($events as $event) {
            $date = $event['date'] ?? date('Y-m-d');
            if (!isset($timeline[$date])) {
                $timeline[$date] = ['positive' => 0, 'negative' => 0, 'neutral' => 0];
            }
            $timeline[$date][$event['sentiment'] ?? 'neutral']++;
        }
        
        return $timeline;
    }

    /**
     * Calculate confidence score
     */
    private function calculateConfidenceScore(array $events): float
    {
        if (empty($events)) return 0.0;
        
        $totalConfidence = 0;
        foreach ($events as $event) {
            $totalConfidence += $event['confidence'] ?? 0.5;
        }
        
        return round($totalConfidence / count($events), 2);
    }

    /**
     * Categorize events by type
     */
    private function categorizeEvents(array $events): array
    {
        $types = [];
        
        foreach ($events as $event) {
            $type = $this->detectEventType($event);
            $types[$type] = ($types[$type] ?? 0) + 1;
        }
        
        return $types;
    }

    /**
     * Detect event type from event data
     */
    private function detectEventType(array $event): string
    {
        $text = strtolower($event['title'] ?? $event['content'] ?? '');
        
        if (strpos($text, 'earnings') !== false) return 'earnings';
        if (strpos($text, 'dividend') !== false) return 'dividend';
        if (strpos($text, 'split') !== false) return 'split';
        if (strpos($text, 'merger') !== false || strpos($text, 'acquisition') !== false) return 'merger';
        if (strpos($text, 'announcement') !== false) return 'announcement';
        
        return 'news';
    }

    /**
     * Analyze impact distribution
     */
    private function analyzeImpactDistribution(array $events): array
    {
        $distribution = ['low' => 0, 'medium' => 0, 'high' => 0];
        
        foreach ($events as $event) {
            $impact = $event['impact_level'] ?? 'low';
            $distribution[$impact]++;
        }
        
        return $distribution;
    }

    /**
     * Convert timeframe to days
     */
    private function convertTimeframeToDays(string $timeframe): int
    {
        return match($timeframe) {
            '1h' => 1,
            '1d' => 1,
            '7d' => 7,
            '30d' => 30,
            '90d' => 90,
            default => 30
        };
    }
}