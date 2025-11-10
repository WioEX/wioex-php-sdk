<?php

declare(strict_types=1);

namespace Wioex\SDK\News;

use Wioex\SDK\Http\Response;
use Wioex\SDK\News\SourceProviderInterface;
use Wioex\SDK\News\Providers\NativeProvider;
use Wioex\SDK\News\Providers\AnalysisProvider;
use Wioex\SDK\News\Providers\SentimentProvider;
use Wioex\SDK\Http\Client;
use Wioex\SDK\Cache\CacheInterface;
use Wioex\SDK\ErrorReporter;

/**
 * NewsManager - Central management for all news sources and providers
 *
 * Provides unified interface to access news from multiple sources with
 * intelligent routing, caching, and fallback mechanisms.
 */
class NewsManager
{
    private Client $httpClient;
    private array $providers = [];
    private array $providerInstances = [];
    private string $defaultProvider = 'native';
    private ?CacheInterface $cache = null;
    private ?ErrorReporter $errorReporter = null;

    /**
     * Supported content types across all providers
     */
    private const CONTENT_TYPES = ['news', 'analysis', 'sentiment', 'events'];
    
    /**
     * Supported source types
     */
    private const SOURCE_TYPES = ['native', 'analysis', 'sentiment'];

    public function __construct(Client $httpClient, ?CacheInterface $cache = null)
    {
        $this->httpClient = $httpClient;
        $this->cache = $cache;
        
        // Register default providers
        $this->registerDefaultProviders();
    }

    /**
     * Set error reporter for logging and monitoring
     */
    public function setErrorReporter(ErrorReporter $errorReporter): self
    {
        $this->errorReporter = $errorReporter;
        return $this;
    }

    /**
     * Register a news source provider
     *
     * @param string $name Provider identifier
     * @param string|SourceProviderInterface $provider Provider class or instance
     * @return self
     */
    public function registerProvider(string $name, $provider): self
    {
        if (is_string($provider)) {
            $this->providers[$name] = $provider;
        } else {
            $this->providerInstances[$name] = $provider;
        }
        
        return $this;
    }

    /**
     * Get a specific provider instance
     *
     * @param string $name Provider name
     * @return SourceProviderInterface
     * @throws \InvalidArgumentException If provider not found
     */
    public function provider(string $name): SourceProviderInterface
    {
        if (!isset($this->providerInstances[$name])) {
            if (isset($this->providers[$name])) {
                $className = $this->providers[$name];
                $this->providerInstances[$name] = new $className($this->httpClient);
            } else {
                throw new \InvalidArgumentException("Provider '{$name}' not registered");
            }
        }

        return $this->providerInstances[$name];
    }

    /**
     * Unified news retrieval with intelligent source routing
     *
     * @param string $symbol Stock symbol
     * @param array $options Options:
     *   - source: string Provider to use ('wioex', 'perplexity', 'social', 'auto')
     *   - type: string Content type ('news', 'analysis', 'sentiment', 'events')
     *   - format: string Response format ('summary', 'detailed', 'raw')
     *   - timeframe: string Time range ('1h', '1d', '7d', '30d')
     *   - fallback: bool Enable fallback to other providers (default: true)
     *   - cache: bool Enable caching (default: true)
     * @return Response
     */
    public function get(string $symbol, array $options = []): Response
    {
        $symbol = strtoupper($symbol);
        $source = $options['source'] ?? 'auto';
        $type = $options['type'] ?? 'news';
        $fallback = $options['fallback'] ?? true;
        $useCache = $options['cache'] ?? true;

        // Validate content type
        if (!in_array($type, self::CONTENT_TYPES)) {
            throw new \InvalidArgumentException("Invalid content type '{$type}'. Supported: " . implode(', ', self::CONTENT_TYPES));
        }

        // Generate cache key
        $cacheKey = $this->generateCacheKey($symbol, $options);
        
        // Try cache first
        if ($useCache && $this->cache) {
            $cached = $this->cache->get($cacheKey);
            if ($cached !== null) {
                $cachedBody = json_encode($cached);
                $mockResponse = new \GuzzleHttp\Psr7\Response(200, [], $cachedBody);
                return new Response($mockResponse);
            }
        }

        try {
            // Auto-select best provider if requested
            if ($source === 'auto') {
                $source = $this->selectBestProvider($type, $options);
            }

            // Get data from primary provider
            $response = $this->getFromProvider($source, $type, $symbol, $options);
            
            // Cache successful responses
            if ($useCache && $this->cache && $response->successful()) {
                $this->cache->put($cacheKey, $response->body(), 300); // 5 min cache
            }

            return $response;

        } catch (\Exception $e) {
            // Report error
            if ($this->errorReporter) {
                $this->errorReporter->report($e, [
                    'context' => 'news_manager_get_error',
                    'symbol' => $symbol,
                    'source' => $source,
                    'type' => $type,
                    'options' => $options
                ]);
            }

            // Try fallback providers if enabled
            if ($fallback && $source !== 'auto') {
                return $this->tryFallbackProviders($type, $symbol, $options, $source);
            }

            // Return error response
            $errorBody = json_encode([
                'error' => 'News retrieval failed',
                'message' => $e->getMessage(),
                'symbol' => $symbol,
                'source' => $source,
                'type' => $type
            ]);
            
            $mockResponse = new \GuzzleHttp\Psr7\Response(500, [], $errorBody);
            return new Response($mockResponse);
        }
    }

    /**
     * Get news from multiple sources and merge results
     *
     * @param string $symbol Stock symbol
     * @param array $sources List of sources to query
     * @param array $options Common options for all sources
     * @return Response Merged response from all sources
     */
    public function getFromMultipleSources(string $symbol, array $sources, array $options = []): Response
    {
        $results = [];
        $errors = [];

        foreach ($sources as $source) {
            try {
                $sourceOptions = array_merge($options, ['source' => $source, 'fallback' => false]);
                $response = $this->get($symbol, $sourceOptions);
                
                if ($response->successful()) {
                    $results[$source] = $response->data();
                } else {
                    $responseData = $response->data();
                    $errors[$source] = $responseData['message'] ?? 'Unknown error';
                }
            } catch (\Exception $e) {
                $errors[$source] = $e->getMessage();
            }
        }

        $multiSourceBody = json_encode([
            'symbol' => $symbol,
            'sources' => $sources,
            'results' => $results,
            'errors' => $errors,
            'timestamp' => time(),
            'success_count' => count($results),
            'total_sources' => count($sources)
        ]);
        
        $mockResponse = new \GuzzleHttp\Psr7\Response(200, [], $multiSourceBody);
        return new Response($mockResponse);
    }

    /**
     * Get provider health status for all registered providers
     *
     * @return array Provider health information
     */
    public function getProvidersHealth(): array
    {
        $health = [];
        
        foreach ($this->providers as $name => $class) {
            try {
                $provider = $this->provider($name);
                $health[$name] = [
                    'status' => $provider->isHealthy() ? 'healthy' : 'unhealthy',
                    'capabilities' => $provider->getCapabilities(),
                    'name' => $provider->getName()
                ];
            } catch (\Exception $e) {
                $health[$name] = [
                    'status' => 'error',
                    'error' => $e->getMessage()
                ];
            }
        }

        return $health;
    }

    /**
     * Register default providers
     */
    private function registerDefaultProviders(): void
    {
        $this->registerProvider('native', NativeProvider::class);
        $this->registerProvider('analysis', AnalysisProvider::class);
        $this->registerProvider('sentiment', SentimentProvider::class);
        
        // Legacy aliases for backwards compatibility
        $this->registerProvider('wioex', NativeProvider::class);
        $this->registerProvider('perplexity', AnalysisProvider::class);
        $this->registerProvider('external', AnalysisProvider::class);
        $this->registerProvider('social', SentimentProvider::class);
    }

    /**
     * Select best provider for content type and options
     */
    private function selectBestProvider(string $type, array $options): string
    {
        // Provider priority based on content type
        $priorities = [
            'news' => ['native', 'analysis', 'sentiment'],
            'analysis' => ['analysis', 'native', 'sentiment'],
            'sentiment' => ['sentiment', 'analysis', 'native'],
            'events' => ['native', 'analysis', 'sentiment']
        ];

        $candidates = $priorities[$type] ?? ['native'];

        // Find first available and healthy provider
        foreach ($candidates as $provider) {
            try {
                $instance = $this->provider($provider);
                if ($instance->supports($type) && $instance->isHealthy()) {
                    return $provider;
                }
            } catch (\Exception $e) {
                continue; // Try next provider
            }
        }

        // Fallback to default
        return $this->defaultProvider;
    }

    /**
     * Get data from specific provider
     */
    private function getFromProvider(string $source, string $type, string $symbol, array $options): Response
    {
        $provider = $this->provider($source);

        if (!$provider->supports($type)) {
            throw new \RuntimeException("Provider '{$source}' does not support content type '{$type}'");
        }

        // Route to appropriate provider method
        return match($type) {
            'news' => $provider->getNews($symbol, $options),
            'analysis' => $provider->getAnalysis($symbol, $options),
            'sentiment' => $provider->getSentiment($symbol, $options),
            'events' => $provider->getEvents($symbol, $options),
            default => throw new \InvalidArgumentException("Unknown content type: {$type}")
        };
    }

    /**
     * Try fallback providers when primary fails
     */
    private function tryFallbackProviders(string $type, string $symbol, array $options, string $excludeSource): Response
    {
        $fallbackOrder = $this->selectBestProvider($type, $options);
        $tried = [$excludeSource];

        foreach ([$fallbackOrder, 'native', 'analysis'] as $fallbackSource) {
            if (in_array($fallbackSource, $tried)) {
                continue;
            }

            try {
                $response = $this->getFromProvider($fallbackSource, $type, $symbol, $options);
                if ($response->successful()) {
                    return $response;
                }
            } catch (\Exception $e) {
                $tried[] = $fallbackSource;
                continue;
            }
        }

        throw new \RuntimeException("All providers failed for {$type} request");
    }

    /**
     * Generate cache key for request
     */
    private function generateCacheKey(string $symbol, array $options): string
    {
        $keyData = [
            'symbol' => $symbol,
            'source' => $options['source'] ?? 'auto',
            'type' => $options['type'] ?? 'news',
            'timeframe' => $options['timeframe'] ?? '1d'
        ];
        
        return 'news:' . md5(serialize($keyData));
    }
}