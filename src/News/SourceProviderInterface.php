<?php

declare(strict_types=1);

namespace Wioex\SDK\News;

use Wioex\SDK\Http\Response;

/**
 * SourceProviderInterface - Standard interface for news source providers
 *
 * Defines the contract that all news providers must implement to ensure
 * consistent API across different news sources and data providers.
 */
interface SourceProviderInterface
{
    /**
     * Get the provider name/identifier
     *
     * @return string Unique provider identifier (e.g., 'wioex', 'perplexity', 'twitter')
     */
    public function getName(): string;

    /**
     * Get basic news/articles for a symbol
     *
     * @param string $symbol Stock symbol
     * @param array $options Provider-specific options
     * @return Response Standardized response with news data
     */
    public function getNews(string $symbol, array $options = []): Response;

    /**
     * Get analyzed news with sentiment and impact
     *
     * @param string $symbol Stock symbol  
     * @param array $options Analysis options
     * @return Response Standardized response with analysis data
     */
    public function getAnalysis(string $symbol, array $options = []): Response;

    /**
     * Get sentiment analysis for news
     *
     * @param string $symbol Stock symbol
     * @param array $options Sentiment options
     * @return Response Standardized response with sentiment data
     */
    public function getSentiment(string $symbol, array $options = []): Response;

    /**
     * Get major events and announcements
     *
     * @param string $symbol Stock symbol
     * @param array $options Event filtering options
     * @return Response Standardized response with major events
     */
    public function getEvents(string $symbol, array $options = []): Response;

    /**
     * Check if provider supports a specific content type
     *
     * @param string $type Content type: 'news', 'analysis', 'sentiment', 'events'
     * @return bool True if supported
     */
    public function supports(string $type): bool;

    /**
     * Get provider capabilities and supported features
     *
     * @return array List of supported features and limitations
     */
    public function getCapabilities(): array;

    /**
     * Get provider-specific configuration
     *
     * @return array Provider configuration and settings
     */
    public function getConfiguration(): array;

    /**
     * Health check for the provider
     *
     * @return bool True if provider is available and working
     */
    public function isHealthy(): bool;
}