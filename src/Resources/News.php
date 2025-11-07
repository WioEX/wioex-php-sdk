<?php

declare(strict_types=1);

namespace Wioex\SDK\Resources;

use Wioex\SDK\Http\Response;

class News extends Resource
{
    /**
     * Get latest news articles for a specific stock
     */
    public function latest(string $ticker): Response
    {
        return $this->get('/api/news', ['ticker' => $ticker]);
    }

    /**
     * Get AI-powered company analysis and insights
     */
    public function companyAnalysis(string $ticker): Response
    {
        return $this->get('/api/companyAnalysis', ['ticker' => $ticker]);
    }

    /**
     * Get Trump Effect - Social media posts and market impact analysis
     *
     * @param array{
     *     sentiment?: array<string>,
     *     mood?: string,
     *     page?: int,
     *     pageSize?: int
     * } $params Optional parameters:
     *   - sentiment: array (optional) Filter by sentiment: 'trumpy', 'neutral', 'grumpy'
     *   - mood: string (optional) Filter by mood index: 'bullish' (>=0.6), 'bearish' (<=0.4), 'neutral' (0.4-0.6)
     *   - page: int (optional) Page number, defaults to 1
     *   - pageSize: int (optional) Items per page (1-100), defaults to 20
     *
     * @return Response Trump Effect data with mood_index, posts, and pagination
     *
     * @example
     * ```php
     * // Get all posts (default: 20 per page)
     * $posts = $client->news()->trumpEffect();
     *
     * // Filter by sentiment
     * $trumpyPosts = $client->news()->trumpEffect(['sentiment' => ['trumpy']]);
     *
     * // Filter by mood
     * $bullish = $client->news()->trumpEffect(['mood' => 'bullish']);
     * $bearish = $client->news()->trumpEffect(['mood' => 'bearish']);
     *
     * // Combine filters
     * $bullishTrumpy = $client->news()->trumpEffect([
     *     'mood' => 'bullish',
     *     'sentiment' => ['trumpy']
     * ]);
     *
     * // Pagination
     * $page2 = $client->news()->trumpEffect(['page' => 2, 'pageSize' => 50]);
     *
     * // Multiple sentiments
     * $mixed = $client->news()->trumpEffect([
     *     'sentiment' => ['trumpy', 'neutral']
     * ]);
     *
     * // Access data
     * if ($posts->successful()) {
     *     $moodIndex = $posts['data']['mood_index'];
     *     foreach ($posts['data']['posts'] as $post) {
     *         echo $post['timestamp'] . ': ' . $post['summary'] . "\n";
     *         echo 'Content: ' . $post['content'] . "\n";  // Full tweet/post text
     *         echo 'Sentiment: ' . $post['sentiment']['name'] . "\n";
     *         echo 'Affected securities: ' . implode(', ', array_column($post['affected_securities'], 'ticker')) . "\n";
     *     }
     * }
     * ```
     */
    public function trumpEffect(array $params = []): Response
    {
        $queryParams = [];

        // Handle sentiment filter
        if (isset($params['sentiment']) && is_array($params['sentiment'])) {
            $queryParams['sentiment'] = implode(',', $params['sentiment']);
        }

        // Handle mood filter
        if (isset($params['mood']) && is_string($params['mood'])) {
            $queryParams['mood'] = $params['mood'];
        }

        // Handle pagination
        if (isset($params['page'])) {
            $queryParams['page'] = (int)$params['page'];
        }

        if (isset($params['pageSize'])) {
            $queryParams['pageSize'] = (int)$params['pageSize'];
        }

        return $this->get('/api/news/trump-effect', $queryParams);
    }
}
