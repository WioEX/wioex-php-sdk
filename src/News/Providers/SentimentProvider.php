<?php

declare(strict_types=1);

namespace Wioex\SDK\News\Providers;

use Wioex\SDK\News\SourceProviderInterface;
use Wioex\SDK\Http\Response;
use Wioex\SDK\Http\Client;

/**
 * SentimentProvider - Social media sentiment and market impact provider
 *
 * Specializes in social media analysis including:
 * - Social media sentiment monitoring
 * - Market sentiment analysis
 * - Market mood tracking
 * - Influencer impact analysis
 */
class SentimentProvider implements SourceProviderInterface
{
    private Client $client;
    
    private const SUPPORTED_TYPES = ['sentiment', 'news', 'analysis'];

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'sentiment';
    }

    /**
     * {@inheritdoc}
     */
    public function getNews(string $symbol, array $options = []): Response
    {
        // For social provider, "news" means social media posts
        return $this->getSentiment($symbol, array_merge($options, ['format' => 'posts']));
    }

    /**
     * {@inheritdoc}
     */
    public function getAnalysis(string $symbol, array $options = []): Response
    {
        // Get comprehensive social media analysis
        $sentiment = $options['sentiment'] ?? [];
        $mood = $options['mood'] ?? null;
        $pageSize = $options['limit'] ?? 50;
        $page = $options['page'] ?? 1;

        $params = [
            'pageSize' => min($pageSize, 100),
            'page' => $page
        ];

        // Add sentiment filter
        if (($sentiment !== null && $sentiment !== '' && $sentiment !== [])) {
            $params['sentiment'] = is_array($sentiment) ? $sentiment : [$sentiment];
        }

        // Add mood filter  
        if ($mood) {
            $params['mood'] = $mood;
        }

        $response = $this->client->get('/api/news/trump-effect', $params);

        if (!$response->successful()) {
            return $response;
        }

        // Transform to analysis format
        return $this->transformToAnalysisFormat($response, $symbol);
    }

    /**
     * {@inheritdoc}
     */
    public function getSentiment(string $symbol, array $options = []): Response
    {
        $sentiment = $options['sentiment'] ?? [];
        $mood = $options['mood'] ?? null;
        $pageSize = $options['limit'] ?? 20;
        $timeframe = $options['timeframe'] ?? '1d';

        $params = [
            'pageSize' => min($pageSize, 100)
        ];

        // Add sentiment filter
        if (($sentiment !== null && $sentiment !== '' && $sentiment !== [])) {
            // Map standard sentiments to Trump Effect sentiments
            $trumpSentiments = $this->mapToTrumpSentiments($sentiment);
            $params['sentiment'] = $trumpSentiments;
        }

        // Add mood filter
        if ($mood) {
            $params['mood'] = $this->mapMoodFilter($mood);
        }

        // Add timeframe-based pagination
        if ($timeframe !== '1d') {
            $params = array_merge($params, $this->calculateTimeframePagination($timeframe));
        }

        $response = $this->client->get('/api/news/trump-effect', $params);

        if (!$response->successful()) {
            return $response;
        }

        // Transform to sentiment-focused format
        return $this->transformToSentimentFormat($response, $symbol, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function getEvents(string $symbol, array $options = []): Response
    {
        // Social provider doesn't track traditional financial events
        // Return social "events" (viral posts, sentiment spikes, etc.)
        
        $analysisOptions = array_merge($options, [
            'sentiment' => ['trumpy', 'grumpy'], // High impact sentiments only
            'limit' => $options['limit'] ?? 50
        ]);

        $response = $this->getAnalysis($symbol, $analysisOptions);

        if (!$response->successful()) {
            return $response;
        }

        return $this->transformToSocialEventsFormat($response, $symbol);
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
            'provider' => 'sentiment',
            'supports' => self::SUPPORTED_TYPES,
            'features' => [
                'trump_effect_monitoring' => true,
                'social_sentiment_analysis' => true,
                'mood_index_tracking' => true,
                'influencer_impact' => true,
                'viral_content_detection' => true,
                'real_time_social_data' => true,
                'pagination' => true,
                'sentiment_filtering' => true,
                'mood_filtering' => true
            ],
            'limits' => [
                'requests_per_minute' => 500,
                'max_page_size' => 100,
                'historical_data_days' => 30
            ],
            'sentiment_types' => [
                'trumpy' => 'Bullish/Positive social sentiment',
                'neutral' => 'Neutral social sentiment', 
                'grumpy' => 'Bearish/Negative social sentiment'
            ],
            'mood_types' => [
                'bullish' => 'Mood index >= 0.6',
                'bearish' => 'Mood index <= 0.4',
                'neutral' => 'Mood index 0.4-0.6'
            ],
            'data_sources' => [
                'trump_social_media',
                'social_market_impact',
                'influencer_analysis',
                'viral_content_tracking'
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
            'base_endpoint' => '/api/news/trump-effect',
            'default_options' => [
                'page_size' => 20,
                'timeout' => 30,
                'sentiment_mapping' => [
                    'positive' => 'trumpy',
                    'negative' => 'grumpy',
                    'neutral' => 'neutral'
                ],
                'mood_mapping' => [
                    'bullish' => 'bullish',
                    'bearish' => 'bearish', 
                    'neutral' => 'neutral'
                ]
            ],
            'authentication' => 'api_key_required',
            'rate_limits' => [
                'requests_per_minute' => 500,
                'max_concurrent' => 10
            ]
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function isHealthy(): bool
    {
        try {
            // Test Trump Effect endpoint with minimal request
            $response = $this->client->get('/api/news/trump-effect', [
                'pageSize' => 1
            ]);
            
            return $response->status() < 500;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Map standard sentiment values to Trump Effect sentiments
     */
    private function mapToTrumpSentiments(array $sentiments): array
    {
        $mapping = [
            'positive' => 'trumpy',
            'bullish' => 'trumpy',
            'negative' => 'grumpy',
            'bearish' => 'grumpy',
            'neutral' => 'neutral'
        ];

        $trumpSentiments = [];
        foreach ($sentiments as $sentiment) {
            $mapped = $mapping[strtolower($sentiment)] ?? $sentiment;
            if (!in_array($mapped, $trumpSentiments)) {
                $trumpSentiments[] = $mapped;
            }
        }

        return $trumpSentiments;
    }

    /**
     * Map mood filter values
     */
    private function mapMoodFilter(string $mood): string
    {
        $mapping = [
            'positive' => 'bullish',
            'negative' => 'bearish',
            'neutral' => 'neutral'
        ];

        return $mapping[strtolower($mood)] ?? $mood;
    }

    /**
     * Calculate pagination for different timeframes
     */
    private function calculateTimeframePagination(string $timeframe): array
    {
        // Estimate pages needed for timeframe coverage
        $estimatedPages = match($timeframe) {
            '1h' => 1,
            '1d' => 2,
            '7d' => 10,
            '30d' => 30,
            default => 5
        };

        return [
            'page' => 1,
            'estimated_pages' => $estimatedPages
        ];
    }

    /**
     * Transform Trump Effect response to analysis format
     */
    private function transformToAnalysisFormat(Response $response, string $symbol): Response
    {
        $data = $response->json();
        
        if (!isset($data['data'])) {
            return $response;
        }

        $posts = $data['data']['posts'] ?? [];
        $moodIndex = $data['data']['mood_index'] ?? 0.5;

        // Calculate analysis metrics
        $analysis = [
            'symbol' => $symbol,
            'provider' => 'sentiment',
            'sentiment_analysis' => [
                'mood_index' => $moodIndex,
                'mood_interpretation' => $this->interpretMoodIndex($moodIndex),
                'total_posts' => count($posts),
                'sentiment_distribution' => $this->calculateSentimentDistribution($posts),
                'trending_topics' => $this->extractTrendingTopics($posts),
                'engagement_metrics' => $this->calculateEngagementMetrics($posts),
                'influencer_mentions' => $this->detectInfluencerMentions($posts)
            ],
            'posts' => $this->formatPostsForAnalysis($posts),
            'pagination' => $data['data']['pagination'] ?? null,
            'timestamp' => time()
        ];

        return new Response($response->status(), $response->headers(), $analysis);
    }

    /**
     * Transform to sentiment-focused format
     */
    private function transformToSentimentFormat(Response $response, string $symbol, array $options): Response
    {
        $data = $response->json();
        
        if (!isset($data['data'])) {
            return $response;
        }

        $posts = $data['data']['posts'] ?? [];
        $moodIndex = $data['data']['mood_index'] ?? 0.5;

        $sentimentData = [
            'symbol' => $symbol,
            'provider' => 'sentiment',
            'overall_sentiment' => $this->interpretMoodIndex($moodIndex),
            'mood_index' => $moodIndex,
            'sentiment_metrics' => [
                'distribution' => $this->calculateSentimentDistribution($posts),
                'confidence' => $this->calculateSentimentConfidence($posts, $moodIndex),
                'volatility' => $this->calculateSentimentVolatility($posts),
                'trending_direction' => $this->calculateTrendingDirection($posts)
            ],
            'post_analysis' => [
                'total_posts' => count($posts),
                'positive_posts' => $this->countPostsBySentiment($posts, 'trumpy'),
                'negative_posts' => $this->countPostsBySentiment($posts, 'grumpy'),
                'neutral_posts' => $this->countPostsBySentiment($posts, 'neutral')
            ],
            'key_posts' => $this->extractKeyPosts($posts, 3),
            'timestamp' => time()
        ];

        return new Response($response->status(), $response->headers(), $sentimentData);
    }

    /**
     * Transform to social events format
     */
    private function transformToSocialEventsFormat(Response $response, string $symbol): Response
    {
        $data = $response->json();
        $posts = $data['posts'] ?? $data['sentiment_analysis']['posts'] ?? [];

        // Identify "events" in social media (viral posts, sentiment spikes, etc.)
        $socialEvents = [];
        
        foreach ($posts as $post) {
            if ($this->isSignificantSocialEvent($post)) {
                $socialEvents[] = [
                    'id' => $post['id'] ?? uniqid(),
                    'type' => 'social_viral',
                    'title' => 'Viral Social Media Post',
                    'description' => $post['summary'] ?? $post['content'] ?? '',
                    'sentiment' => $this->normalizeSentiment($post['sentiment']['name'] ?? 'neutral'),
                    'impact_level' => $this->calculateSocialImpact($post),
                    'timestamp' => $post['timestamp'] ?? $post['time'] ?? time(),
                    'source' => 'sentiment',
                    'engagement_score' => $this->calculateEngagementScore($post),
                    'virality_score' => $this->calculateViralityScore($post)
                ];
            }
        }

        return new Response($response->status(), $response->headers(), [
            'symbol' => $symbol,
            'social_events' => $socialEvents,
            'total_events' => count($socialEvents),
            'provider' => 'sentiment',
            'event_types' => ['social_viral', 'sentiment_spike'],
            'timestamp' => time()
        ]);
    }

    /**
     * Interpret mood index value
     */
    private function interpretMoodIndex(float $moodIndex): string
    {
        if ($moodIndex >= 0.6) return 'bullish';
        if ($moodIndex <= 0.4) return 'bearish';
        return 'neutral';
    }

    /**
     * Calculate sentiment distribution from posts
     */
    private function calculateSentimentDistribution(array $posts): array
    {
        $distribution = ['trumpy' => 0, 'neutral' => 0, 'grumpy' => 0];
        
        foreach ($posts as $post) {
            $sentiment = $post['sentiment']['name'] ?? 'neutral';
            if (isset($distribution[$sentiment])) {
                $distribution[$sentiment]++;
            }
        }

        // Convert to percentages
        $total = array_sum($distribution);
        if ($total > 0) {
            foreach ($distribution as $key => $count) {
                $distribution[$key] = round(($count / $total) * 100, 1);
            }
        }

        return $distribution;
    }

    /**
     * Extract trending topics from posts
     */
    private function extractTrendingTopics(array $posts): array
    {
        $topics = [];
        
        foreach ($posts as $post) {
            if (isset($post['sectors']) && is_array($post['sectors'])) {
                foreach ($post['sectors'] as $sector) {
                    $topics[$sector] = ($topics[$sector] ?? 0) + 1;
                }
            }
        }

        // Sort by frequency and return top topics
        arsort($topics);
        return array_slice($topics, 0, 5, true);
    }

    /**
     * Calculate engagement metrics
     */
    private function calculateEngagementMetrics(array $posts): array
    {
        $totalPosts = count($posts);
        $withSectors = 0;
        $withSecurities = 0;

        foreach ($posts as $post) {
            if (($post['sectors'] !== null && $post['sectors'] !== '' && $post['sectors'] !== [])) $withSectors++;
            if (($post['affected_securities'] !== null && $post['affected_securities'] !== '' && $post['affected_securities'] !== [])) $withSecurities++;
        }

        return [
            'sector_mention_rate' => $totalPosts > 0 ? round(($withSectors / $totalPosts) * 100, 1) : 0,
            'security_mention_rate' => $totalPosts > 0 ? round(($withSecurities / $totalPosts) * 100, 1) : 0,
            'avg_content_length' => $this->calculateAverageContentLength($posts)
        ];
    }

    /**
     * Detect influencer mentions in posts
     */
    private function detectInfluencerMentions(array $posts): array
    {
        // This would be more sophisticated in practice
        $mentions = [];
        
        foreach ($posts as $post) {
            $content = strtolower($post['content'] ?? $post['summary'] ?? '');
            
            // Check for common influencer indicators
            if (strpos($content, 'trump') !== false) {
                $mentions['trump'] = ($mentions['trump'] ?? 0) + 1;
            }
        }

        return $mentions;
    }

    /**
     * Format posts for analysis
     */
    private function formatPostsForAnalysis(array $posts): array
    {
        return array_map(function($post) {
            return [
                'id' => $post['id'] ?? uniqid(),
                'summary' => $post['summary'] ?? '',
                'sentiment' => $this->normalizeSentiment($post['sentiment']['name'] ?? 'neutral'),
                'timestamp' => $post['timestamp'] ?? $post['time'] ?? time(),
                'sectors' => $post['sectors'] ?? [],
                'securities' => array_column($post['affected_securities'] ?? [], 'ticker')
            ];
        }, array_slice($posts, 0, 10)); // Limit for analysis
    }

    /**
     * Normalize sentiment for standard format
     */
    private function normalizeSentiment(string $sentiment): string
    {
        $mapping = [
            'trumpy' => 'positive',
            'grumpy' => 'negative',
            'neutral' => 'neutral'
        ];

        return $mapping[strtolower($sentiment)] ?? 'neutral';
    }

    /**
     * Calculate sentiment confidence
     */
    private function calculateSentimentConfidence(array $posts, float $moodIndex): float
    {
        // Higher confidence when mood index is closer to extremes
        $extremeDistance = abs($moodIndex - 0.5);
        return min(0.5 + ($extremeDistance * 2), 1.0);
    }

    /**
     * Calculate sentiment volatility
     */
    private function calculateSentimentVolatility(array $posts): string
    {
        $sentiments = [];
        foreach ($posts as $post) {
            $sentiments[] = $post['sentiment']['name'] ?? 'neutral';
        }

        $uniqueSentiments = count(array_unique($sentiments));
        
        if ($uniqueSentiments >= 3) return 'high';
        if ($uniqueSentiments == 2) return 'medium';
        return 'low';
    }

    /**
     * Calculate trending direction
     */
    private function calculateTrendingDirection(array $posts): string
    {
        // Simple implementation - would be more sophisticated with timestamps
        $recent = array_slice($posts, 0, 5);
        $older = array_slice($posts, -5);

        $recentPositive = $this->countPostsBySentiment($recent, 'trumpy');
        $olderPositive = $this->countPostsBySentiment($older, 'trumpy');

        if ($recentPositive > $olderPositive) return 'improving';
        if ($recentPositive < $olderPositive) return 'declining';
        return 'stable';
    }

    /**
     * Count posts by sentiment type
     */
    private function countPostsBySentiment(array $posts, string $sentiment): int
    {
        return count(array_filter($posts, function($post) use ($sentiment) {
            return ($post['sentiment']['name'] ?? 'neutral') === $sentiment;
        }));
    }

    /**
     * Extract key posts
     */
    private function extractKeyPosts(array $posts, int $limit): array
    {
        // Sort by significance and return top posts
        usort($posts, function($a, $b) {
            $scoreA = $this->calculatePostSignificance($a);
            $scoreB = $this->calculatePostSignificance($b);
            return $scoreB <=> $scoreA;
        });

        return array_slice($posts, 0, $limit);
    }

    /**
     * Check if post represents significant social event
     */
    private function isSignificantSocialEvent(array $post): bool
    {
        // Check for high engagement or extreme sentiment
        $sentiment = $post['sentiment']['name'] ?? 'neutral';
        $hasMultipleSecurities = count($post['affected_securities'] ?? []) > 2;
        $hasMultipleSectors = count($post['sectors'] ?? []) > 1;
        
        return in_array($sentiment, ['trumpy', 'grumpy']) && 
               ($hasMultipleSecurities || $hasMultipleSectors);
    }

    /**
     * Calculate social impact level
     */
    private function calculateSocialImpact(array $post): string
    {
        $score = 0;
        
        // Sentiment extremity
        $sentiment = $post['sentiment']['name'] ?? 'neutral';
        if (in_array($sentiment, ['trumpy', 'grumpy'])) $score += 2;
        
        // Multiple securities affected
        $securitiesCount = count($post['affected_securities'] ?? []);
        if ($securitiesCount > 3) $score += 2;
        elseif ($securitiesCount > 1) $score += 1;
        
        // Multiple sectors
        $sectorsCount = count($post['sectors'] ?? []);
        if ($sectorsCount > 2) $score += 1;
        
        if ($score >= 4) return 'high';
        if ($score >= 2) return 'medium';
        return 'low';
    }

    /**
     * Calculate engagement score
     */
    private function calculateEngagementScore(array $post): float
    {
        $score = 0.5; // Base score
        
        // Content length indicates engagement
        $contentLength = strlen($post['content'] ?? $post['summary'] ?? '');
        if ($contentLength > 200) $score += 0.2;
        
        // Multiple affected securities/sectors
        if (($post['affected_securities'] !== null && $post['affected_securities'] !== '' && $post['affected_securities'] !== [])) $score += 0.1;
        if (($post['sectors'] !== null && $post['sectors'] !== '' && $post['sectors'] !== [])) $score += 0.1;
        
        return min($score, 1.0);
    }

    /**
     * Calculate virality score  
     */
    private function calculateViralityScore(array $post): float
    {
        // Simple virality calculation based on reach indicators
        $score = 0.3; // Base
        
        $securitiesCount = count($post['affected_securities'] ?? []);
        $sectorsCount = count($post['sectors'] ?? []);
        
        // More affected stocks = higher virality
        $score += ($securitiesCount * 0.1);
        $score += ($sectorsCount * 0.05);
        
        return min($score, 1.0);
    }

    /**
     * Calculate post significance
     */
    private function calculatePostSignificance(array $post): float
    {
        $significance = 0;
        
        // Sentiment extremity
        $sentiment = $post['sentiment']['name'] ?? 'neutral';
        if ($sentiment === 'trumpy' || $sentiment === 'grumpy') {
            $significance += 0.5;
        }
        
        // Multiple securities/sectors
        $significance += count($post['affected_securities'] ?? []) * 0.1;
        $significance += count($post['sectors'] ?? []) * 0.05;
        
        // Content richness
        $contentLength = strlen($post['content'] ?? $post['summary'] ?? '');
        if ($contentLength > 100) $significance += 0.2;
        
        return $significance;
    }

    /**
     * Calculate average content length
     */
    private function calculateAverageContentLength(array $posts): int
    {
        if (($posts === null || $posts === '' || $posts === [])) return 0;
        
        $totalLength = 0;
        foreach ($posts as $post) {
            $totalLength += strlen($post['content'] ?? $post['summary'] ?? '');
        }
        
        return intval($totalLength / count($posts));
    }
}