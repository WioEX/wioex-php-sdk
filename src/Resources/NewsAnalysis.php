<?php

declare(strict_types=1);

namespace Wioex\SDK\Resources;

use Wioex\SDK\Http\Client;
use Wioex\SDK\Http\Response;

/**
 * NewsAnalysis Resource - Stock News Analysis and Financial Events
 *
 * Provides access to analyzed financial news and events for stocks including
 * sentiment analysis, impact assessment, and categorized news events.
 */
class NewsAnalysis extends Resource
{

    /**
     * Get analyzed financial news and events for a symbol from external source
     *
     * @param string $symbol Stock symbol (e.g., 'TSLA', 'AAPL')
     * @param array $options Additional options:
     *   - version: string (default: '2.18') API version
     *   - source: string (default: 'default') Data source
     *   - format: string (default: 'wioex') Response format
     *   - limit: int (default: 50) Number of news events
     * 
     * @return array Analyzed news data with sentiment and impact
     *
     * @example
     * ```php
     * $news = $client->newsAnalysis()->getFromExternal('TSLA');
     * $news = $client->newsAnalysis()->getFromExternal('AAPL', [
     *     'version' => '2.18',
     *     'limit' => 100
     * ]);
     * ```
     */
    public function getFromExternal(string $symbol, array $options = []): array
    {
        $symbol = strtoupper($symbol);
        
        $defaultOptions = [
            'version' => '2.18',
            'source' => 'default',
            'format' => 'wioex',
            'limit' => 50
        ];
        
        $options = array_merge($defaultOptions, $options);
        
        try {
            // Step 1: Get trace information first
            $traceData = $this->getTraceData();
            
            // Step 2: Fetch news data from external API
            $rawData = $this->fetchNewsData($symbol, $options, $traceData);
            
            // Step 3: Format data according to WioEX standards
            return $this->formatNewsData($rawData, $symbol, $options);
            
        } catch (\Exception $e) {
            // Report error if ErrorReporter is available
            if (class_exists('\Wioex\SDK\ErrorReporter') && class_exists('\Wioex\SDK\Config')) {
                try {
                    $config = new \Wioex\SDK\Config(['api_key' => 'timeline_error']);
                    (new \Wioex\SDK\ErrorReporter($config))->report($e, [
                        'context' => 'news_analysis_fetch_error',
                        'symbol' => $symbol,
                        'options' => $options
                    ]);
                } catch (\Exception $reportError) {
                    // Silent fail on error reporting
                }
            }
            
            return $this->getErrorResponse($symbol, $e->getMessage());
        }
    }

    /**
     * Get analyzed news for multiple symbols
     *
     * @param array $symbols List of stock symbols
     * @param array $options Options for all requests
     * @return array Multi-symbol news analysis data
     */
    public function getMultiple(array $symbols, array $options = []): array
    {
        $results = [];
        
        foreach ($symbols as $symbol) {
            $results[$symbol] = $this->getFromExternal($symbol, $options);
        }
        
        return [
            'data' => $results,
            'symbols' => $symbols,
            'timestamp' => time(),
            'total_count' => count($symbols)
        ];
    }

    /**
     * Get recent news analysis for a symbol
     *
     * @param string $symbol Stock symbol
     * @param int $days Number of days to look back (default: 30)
     * @return array Recent news events with analysis
     */
    public function getRecent(string $symbol, int $days = 30): array
    {
        return $this->getFromExternal($symbol, [
            'limit' => 100,
            'days_back' => $days,
            'filter' => 'recent'
        ]);
    }

    /**
     * Get major financial news and events only
     *
     * @param string $symbol Stock symbol
     * @param array $options Additional options
     * @return array Major news events (earnings, announcements, etc.) with analysis
     */
    public function getMajorEvents(string $symbol, array $options = []): array
    {
        $options['filter'] = 'major';
        $options['event_types'] = ['earnings', 'announcements', 'splits', 'dividends'];
        
        return $this->getFromExternal($symbol, $options);
    }

    /**
     * Get news analysis from WioEX API endpoint
     *
     * @param string $symbol Stock symbol (e.g., 'TSLA', 'AAPL')
     * @param array $options Additional options:
     *   - limit: int (default: 50) Number of news events to return
     *   - days: int (default: 30) Number of days to look back
     *   - sentiment: string Filter by sentiment: 'positive', 'negative', 'neutral'
     *   - impact: string Filter by impact: 'low', 'medium', 'high', 'major'
     *   - type: string Filter by event type: 'earnings', 'news', 'announcements'
     * 
     * @return Response WioEX standardized news analysis response
     *
     * @example
     * ```php
     * // Get basic news analysis from WioEX API
     * $analysis = $client->newsAnalysis()->getFromWioex('TSLA');
     * 
     * // With filters
     * $recent = $client->newsAnalysis()->getFromWioex('AAPL', [
     *     'limit' => 100,
     *     'days' => 7,
     *     'sentiment' => 'positive',
     *     'impact' => 'high'
     * ]);
     *
     * // Filter by event type
     * $earnings = $client->newsAnalysis()->getFromWioex('MSFT', [
     *     'type' => 'earnings',
     *     'limit' => 20
     * ]);
     * ```
     */
    public function getFromWioex(string $symbol, array $options = []): Response
    {
        $symbol = strtoupper($symbol);
        
        $params = [
            'ticker' => $symbol,
            'limit' => $options['limit'] ?? 50,
            'days' => $options['days'] ?? 30
        ];

        // Add optional filters
        if (isset($options['sentiment'])) {
            $params['sentiment'] = $options['sentiment'];
        }

        if (isset($options['impact'])) {
            $params['impact'] = $options['impact'];
        }

        if (isset($options['type'])) {
            $params['type'] = $options['type'];
        }

        return $this->get('/api/news/analysis', $params);
    }

    /**
     * Fetch trace data from Perplexity CDN
     *
     * @return array Trace data for session management
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
     * Build cookie header for Perplexity requests
     *
     * @return string Formatted cookie header
     */
    private function buildCookieHeader(): string
    {
        $cookies = [
            '__cf_bm' => 'cUZV8sMHWsNuYrLCH0U4yQO70LI0gIrNIiU2AcgdwR0-1762773479-1.0.1.1-rAFsjjYS0cqh74iCoHAZHBYhM5aIxNoCFf7qk0m9dA2yksiGGpEeWXnIupZYkyFkiP7Qqi7mVcam_5jZumMSD__V_T.UjX4ATChvN36p5DE',
            'pplx.visitor-id' => '50253d7b-f757-4758-a38e-cf16cd6ea088',
            'pplx.session-id' => '20ce22e4-6976-4ca8-b2da-9e46b86fa061',
            'pplx.metadata' => urlencode('{"qc":0,"qcu":0,"qcm":0,"qcc":0,"qcco":0,"qccol":0,"qcdr":0,"qcs":0,"qcd":0,"hli":false,"hcga":false,"hcds":false,"hso":false,"hfo":false,"hsco":false,"hfco":false,"hsma":false,"hdc":false}'),
            'next-auth.csrf-token' => '9a28570a449d9d3c65176471ceb69270785c20ed82caa4588b266435a985615d%7Caa127c0e13b4449496b55beeac5e26b84d820557dd7a3628c8b0ae6858cca83d',
            'next-auth.callback-url' => 'https%3A%2F%2Fwww.perplexity.ai%2Fapi%2Fauth%2Fsignin-callback%3Fredirect%3Dhttps%253A%252F%252Fwww.perplexity.ai',
            '__cflb' => '02DiuDyvFMmK5p9jVbVnMNSKYZhUL9aGmQYnDE5NWuDHe',
            'gov-badge' => '2',
            'cf_clearance' => 'D2t6RmxIGy1jiUiRvnBmAeQrLSOnhN.BikvP1VQSj_k-1762772576-1.2.1.1-8ylY_pH5PjuILbDtqWLFJU2UsGlZi_C0ASGLib5pDyQH6dSAQgpqWwYaJeZQBa1EKUdMhU48wg.QoOQfQmATzLoT_aAEWpnPQ3d5wkkmbBfO3vmJJOUSKQED6srRWkiUg6b.3xJWt2II1YzU5d2d6rbyoKvoAVPDmL7fv8.bB1GNnUpWkMcX10LCV85g.QJBJA0EszXhzAD.TsKmK6V3JnfhwqTLln9R1bpbFD7Wn7I',
            'g_state' => urlencode('{"i_l":1,"i_ll":1762773176480,"i_b":"csQt60sxM6S/qNcETSjLxxgxe6aImnJ5/3QD1L95EU8","i_p":1762779857656}'),
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
     *
     * @param \Wioex\SDK\Http\Response $response Trace response
     * @return array Parsed trace data
     */
    private function parseTraceResponse($response): array
    {
        $traceData = [
            'timestamp' => time(),
            'success' => true,
            'cf_data' => []
        ];

        // Get response as text
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

        return $traceData;
    }

    /**
     * Fetch news data from external Finance API
     *
     * @param string $symbol Stock symbol
     * @param array $options Request options
     * @param array $traceData Trace data for session
     * @return array Raw news data
     */
    private function fetchNewsData(string $symbol, array $options, array $traceData): array
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
            'Referer' => 'https://www.perplexity.ai/',
            'DNT' => '1',
            'Sec-GPC' => '1',
            'Sec-Fetch-Dest' => 'empty',
            'Sec-Fetch-Mode' => 'cors',
            'Sec-Fetch-Site' => 'same-origin',
            'Cookie' => $this->buildCookieHeader()
        ];

        $response = $this->client->get($url, [
            'query' => $params,
            'headers' => $headers,
            'timeout' => 15
        ]);

        // Convert Response object to array
        return $response->data();
    }

    /**
     * Format news analysis data according to WioEX standards
     *
     * @param array $rawData Raw data from external API
     * @param string $symbol Stock symbol
     * @param array $options Request options
     * @return array Formatted news analysis data
     */
    private function formatNewsData(array $rawData, string $symbol, array $options): array
    {
        $newsAnalysis = [
            'symbol' => $symbol,
            'data_source' => 'external_finance_api',
            'api_version' => $options['version'],
            'timestamp' => time(),
            'events' => [],
            'summary' => [
                'total_events' => 0,
                'date_range' => [],
                'event_types' => []
            ],
            'metadata' => [
                'request_id' => uniqid('na_'),
                'processing_time_ms' => 0,
                'cache_hit' => false,
                'data_freshness' => 'real_time'
            ]
        ];

        $startTime = microtime(true);

        if (isset($rawData['data']['timeline']) && is_array($rawData['data']['timeline'])) {
            $events = $rawData['data']['timeline'];
            $eventTypes = [];
            $dates = [];

            foreach ($events as $event) {
                $formattedEvent = $this->formatSingleEvent($event, $symbol);
                if ($formattedEvent) {
                    $newsAnalysis['events'][] = $formattedEvent;
                    $eventTypes[] = $formattedEvent['event_type'];
                    $dates[] = $formattedEvent['date'];
                }

                // Apply limit if specified
                if (count($newsAnalysis['events']) >= $options['limit']) {
                    break;
                }
            }

            // Update summary
            $newsAnalysis['summary'] = [
                'total_events' => count($newsAnalysis['events']),
                'date_range' => [
                    'earliest' => !empty($dates) ? min($dates) : null,
                    'latest' => !empty($dates) ? max($dates) : null
                ],
                'event_types' => array_unique($eventTypes)
            ];
        }

        $newsAnalysis['metadata']['processing_time_ms'] = round((microtime(true) - $startTime) * 1000, 2);

        return $newsAnalysis;
    }

    /**
     * Format a single timeline event
     *
     * @param array $event Raw event data
     * @param string $symbol Stock symbol
     * @return array|null Formatted event or null if invalid
     */
    private function formatSingleEvent(array $event, string $symbol): ?array
    {
        if (!isset($event['date']) || !isset($event['type'])) {
            return null;
        }

        return [
            'id' => $event['id'] ?? uniqid('evt_'),
            'symbol' => $symbol,
            'date' => $event['date'],
            'timestamp' => strtotime($event['date']),
            'event_type' => $this->normalizeEventType($event['type']),
            'title' => $event['title'] ?? '',
            'description' => $event['description'] ?? '',
            'impact' => $this->determineImpact($event),
            'price_impact' => [
                'pre_price' => $event['pre_price'] ?? null,
                'post_price' => $event['post_price'] ?? null,
                'change_percent' => $event['change_percent'] ?? null,
                'volume_change' => $event['volume_change'] ?? null
            ],
            'source' => $event['source'] ?? 'perplexity',
            'url' => $event['url'] ?? null,
            'tags' => $event['tags'] ?? [],
            'sentiment' => $this->analyzeSentiment($event),
            'metadata' => [
                'raw_type' => $event['type'] ?? null,
                'confidence' => $event['confidence'] ?? null,
                'verified' => $event['verified'] ?? false
            ]
        ];
    }

    /**
     * Normalize event type to WioEX standard
     *
     * @param string $type Raw event type
     * @return string Normalized event type
     */
    private function normalizeEventType(string $type): string
    {
        $typeMap = [
            'earnings' => 'earnings_report',
            'earnings_call' => 'earnings_call',
            'announcement' => 'company_announcement',
            'news' => 'news_event',
            'split' => 'stock_split',
            'dividend' => 'dividend_announcement',
            'merger' => 'merger_acquisition',
            'ipo' => 'initial_public_offering',
            'analyst' => 'analyst_rating',
            'regulatory' => 'regulatory_filing',
            'insider' => 'insider_trading',
            'partnership' => 'partnership_deal'
        ];

        $normalizedType = strtolower(trim($type));
        return $typeMap[$normalizedType] ?? 'other_event';
    }

    /**
     * Determine event impact level
     *
     * @param array $event Event data
     * @return string Impact level (low, medium, high, major)
     */
    private function determineImpact(array $event): string
    {
        $type = strtolower($event['type'] ?? '');
        $changePercent = abs($event['change_percent'] ?? 0);

        // High impact event types
        if (in_array($type, ['earnings', 'merger', 'ipo', 'split'])) {
            return 'high';
        }

        // Impact based on price change
        if ($changePercent >= 10) {
            return 'major';
        } elseif ($changePercent >= 5) {
            return 'high';
        } elseif ($changePercent >= 2) {
            return 'medium';
        }

        return 'low';
    }

    /**
     * Analyze sentiment of the event
     *
     * @param array $event Event data
     * @return array Sentiment analysis
     */
    private function analyzeSentiment(array $event): array
    {
        $text = ($event['title'] ?? '') . ' ' . ($event['description'] ?? '');
        $positiveWords = ['beat', 'exceed', 'growth', 'positive', 'bullish', 'strong', 'improve'];
        $negativeWords = ['miss', 'decline', 'negative', 'bearish', 'weak', 'concern', 'risk'];

        $positiveCount = 0;
        $negativeCount = 0;

        foreach ($positiveWords as $word) {
            $positiveCount += substr_count(strtolower($text), $word);
        }

        foreach ($negativeWords as $word) {
            $negativeCount += substr_count(strtolower($text), $word);
        }

        if ($positiveCount > $negativeCount) {
            $sentiment = 'positive';
            $confidence = min(($positiveCount / max($negativeCount + $positiveCount, 1)), 1.0);
        } elseif ($negativeCount > $positiveCount) {
            $sentiment = 'negative';
            $confidence = min(($negativeCount / max($negativeCount + $positiveCount, 1)), 1.0);
        } else {
            $sentiment = 'neutral';
            $confidence = 0.5;
        }

        return [
            'sentiment' => $sentiment,
            'confidence' => round($confidence, 2),
            'positive_signals' => $positiveCount,
            'negative_signals' => $negativeCount
        ];
    }

    /**
     * Get error response structure
     *
     * @param string $symbol Stock symbol
     * @param string $message Error message
     * @return array Error response
     */
    private function getErrorResponse(string $symbol, string $message): array
    {
        return [
            'symbol' => $symbol,
            'data_source' => 'external_finance_api',
            'timestamp' => time(),
            'events' => [],
            'error' => [
                'code' => 'NEWS_ANALYSIS_FETCH_ERROR',
                'message' => $message,
                'occurred_at' => date('Y-m-d H:i:s')
            ],
            'summary' => [
                'total_events' => 0,
                'date_range' => [],
                'event_types' => []
            ],
            'metadata' => [
                'request_id' => uniqid('err_'),
                'processing_time_ms' => 0,
                'cache_hit' => false,
                'data_freshness' => 'error'
            ]
        ];
    }
}