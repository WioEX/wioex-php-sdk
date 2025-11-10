<?php

declare(strict_types=1);

namespace Wioex\SDK\News\Providers;

use Wioex\SDK\News\SourceProviderInterface;
use Wioex\SDK\Http\Response;
use Wioex\SDK\Http\Client;

/**
 * WioexProvider - WioEX native news and analysis provider
 *
 * Provides access to WioEX's internal news sources including:
 * - Latest financial news
 * - AI-powered company analysis  
 * - Trump Effect social media analysis
 * - Company-specific news analysis
 */
class WioexProvider implements SourceProviderInterface
{
    private Client $client;
    
    private const SUPPORTED_TYPES = ['news', 'analysis', 'sentiment', 'events'];

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'wioex';
    }

    /**
     * {@inheritdoc}
     */
    public function getNews(string $symbol, array $options = []): Response
    {
        // Use WioEX native news endpoint
        return $this->client->get('/api/news', array_merge([
            'ticker' => $symbol
        ], $options));
    }

    /**
     * {@inheritdoc}
     */
    public function getAnalysis(string $symbol, array $options = []): Response
    {
        $timeframe = $options['timeframe'] ?? '30d';
        $analysisType = $options['analysis_type'] ?? 'comprehensive';

        // Check if specific news analysis endpoint exists
        if (isset($options['use_news_analysis']) && $options['use_news_analysis']) {
            return $this->client->get('/api/news/analysis', array_merge([
                'ticker' => $symbol,
                'days' => $this->convertTimeframeToDays($timeframe),
                'type' => $analysisType
            ], $options));
        }

        // Fallback to company analysis
        return $this->client->get('/api/companyAnalysis', array_merge([
            'ticker' => $symbol
        ], $options));
    }

    /**
     * {@inheritdoc}
     */
    public function getSentiment(string $symbol, array $options = []): Response
    {
        $sentiment = $options['sentiment'] ?? [];
        $mood = $options['mood'] ?? null;
        $pageSize = $options['limit'] ?? 20;

        $params = [
            'pageSize' => $pageSize
        ];

        // Add sentiment filter if provided
        if (!empty($sentiment)) {
            $params['sentiment'] = is_array($sentiment) ? $sentiment : [$sentiment];
        }

        // Add mood filter if provided  
        if ($mood) {
            $params['mood'] = $mood;
        }

        // Use Trump Effect endpoint for sentiment analysis
        return $this->client->get('/api/news/trump-effect', $params);
    }

    /**
     * {@inheritdoc}
     */
    public function getEvents(string $symbol, array $options = []): Response
    {
        $eventTypes = $options['event_types'] ?? ['earnings', 'announcements', 'splits', 'dividends'];
        $timeframe = $options['timeframe'] ?? '30d';

        // If news analysis endpoint supports events
        if ($this->supportsNewsAnalysisEvents()) {
            return $this->client->get('/api/news/analysis', [
                'ticker' => $symbol,
                'type' => 'events',
                'event_types' => $eventTypes,
                'days' => $this->convertTimeframeToDays($timeframe),
                'filter' => 'major'
            ]);
        }

        // Fallback: get general news and filter for events
        $response = $this->getNews($symbol, array_merge($options, [
            'event_filter' => true,
            'event_types' => $eventTypes
        ]));

        // Transform response to events format if needed
        return $this->transformToEventsFormat($response, $symbol);
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
            'provider' => 'wioex',
            'supports' => self::SUPPORTED_TYPES,
            'features' => [
                'real_time_news' => true,
                'ai_analysis' => true,
                'trump_effect' => true,
                'social_sentiment' => true,
                'company_analysis' => true,
                'event_detection' => true,
                'pagination' => true,
                'filtering' => true,
                'caching' => true
            ],
            'limits' => [
                'requests_per_minute' => 1000,
                'max_page_size' => 100,
                'max_timeframe_days' => 365
            ],
            'data_sources' => [
                'wioex_native',
                'financial_news_feeds',
                'social_media_analysis',
                'ai_analysis_engine'
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
            'base_endpoints' => [
                'news' => '/api/news',
                'analysis' => '/api/companyAnalysis',
                'sentiment' => '/api/news/trump-effect',
                'events' => '/api/news/analysis'
            ],
            'default_options' => [
                'timeout' => 30,
                'page_size' => 20,
                'timeframe' => '30d',
                'format' => 'wioex_standard'
            ],
            'authentication' => 'api_key_required'
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function isHealthy(): bool
    {
        try {
            // Simple health check - try to get news for a major stock
            $response = $this->client->get('/api/news', [
                'ticker' => 'AAPL',
                'limit' => 1
            ]);
            
            return $response->status() < 500;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Convert timeframe string to days integer
     */
    private function convertTimeframeToDays(string $timeframe): int
    {
        return match($timeframe) {
            '1h' => 1,
            '1d' => 1, 
            '7d' => 7,
            '30d' => 30,
            '90d' => 90,
            '1y' => 365,
            default => 30
        };
    }

    /**
     * Check if news analysis endpoint supports events
     */
    private function supportsNewsAnalysisEvents(): bool
    {
        // This would be determined by checking WioEX API capabilities
        // For now, assume it's available if we can reach the endpoint
        try {
            $response = $this->client->get('/api/news/analysis', [
                'ticker' => 'TEST',
                'type' => 'events',
                'limit' => 1
            ]);
            return $response->status() !== 404;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Transform general news response to events format
     */
    private function transformToEventsFormat(Response $response, string $symbol): Response
    {
        if (!$response->successful()) {
            return $response;
        }

        $data = $response->json();
        
        // Transform news items to event format
        $events = [];
        if (isset($data['data'])) {
            foreach ($data['data'] as $item) {
                if ($this->isNewsItemAnEvent($item)) {
                    $events[] = [
                        'id' => $item['id'] ?? uniqid(),
                        'symbol' => $symbol,
                        'title' => $item['title'] ?? $item['headline'] ?? '',
                        'type' => $this->detectEventType($item),
                        'date' => $item['date'] ?? $item['published_at'] ?? date('Y-m-d'),
                        'impact_level' => $this->calculateImpactLevel($item),
                        'description' => $item['summary'] ?? $item['content'] ?? '',
                        'source' => 'wioex'
                    ];
                }
            }
        }

        return new Response($response->status(), $response->headers(), [
            'symbol' => $symbol,
            'events' => $events,
            'total' => count($events),
            'source' => 'wioex',
            'timestamp' => time()
        ]);
    }

    /**
     * Check if news item represents a significant event
     */
    private function isNewsItemAnEvent(array $item): bool
    {
        $eventKeywords = ['earnings', 'dividend', 'split', 'merger', 'acquisition', 'announcement', 'launch'];
        $text = strtolower($item['title'] ?? $item['headline'] ?? $item['content'] ?? '');
        
        foreach ($eventKeywords as $keyword) {
            if (strpos($text, $keyword) !== false) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Detect event type from news item
     */
    private function detectEventType(array $item): string
    {
        $text = strtolower($item['title'] ?? $item['headline'] ?? $item['content'] ?? '');
        
        if (strpos($text, 'earnings') !== false) return 'earnings';
        if (strpos($text, 'dividend') !== false) return 'dividend';
        if (strpos($text, 'split') !== false) return 'split';
        if (strpos($text, 'merger') !== false || strpos($text, 'acquisition') !== false) return 'merger';
        if (strpos($text, 'announcement') !== false) return 'announcement';
        
        return 'news';
    }

    /**
     * Calculate impact level from news item
     */
    private function calculateImpactLevel(array $item): string
    {
        // This would use more sophisticated analysis in practice
        $text = strtolower($item['title'] ?? $item['headline'] ?? '');
        
        $highImpactKeywords = ['earnings', 'merger', 'acquisition', 'bankruptcy'];
        $mediumImpactKeywords = ['dividend', 'split', 'partnership'];
        
        foreach ($highImpactKeywords as $keyword) {
            if (strpos($text, $keyword) !== false) return 'high';
        }
        
        foreach ($mediumImpactKeywords as $keyword) {
            if (strpos($text, $keyword) !== false) return 'medium';
        }
        
        return 'low';
    }
}