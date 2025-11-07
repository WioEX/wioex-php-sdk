<?php

declare(strict_types=1);

namespace Wioex\SDK;

use Wioex\SDK\Http\Client;
use Wioex\SDK\Resources\Account;
use Wioex\SDK\Resources\Currency;
use Wioex\SDK\Resources\Logos;
use Wioex\SDK\Resources\Markets;
use Wioex\SDK\Resources\News;
use Wioex\SDK\Resources\Screens;
use Wioex\SDK\Resources\Signals;
use Wioex\SDK\Resources\Stocks;
use Wioex\SDK\Resources\Streaming;
use Wioex\SDK\Configuration\ConfigurationManager;
use Wioex\SDK\Enums\Environment;
use Wioex\SDK\Version;
use Wioex\SDK\Cache\CacheManager;
use Wioex\SDK\Cache\CacheInterface;
use Wioex\SDK\Async\AsyncClient;
use Wioex\SDK\Async\BatchRequestManager;
use Wioex\SDK\Reliability\CircuitBreakerManager;
use Wioex\SDK\Security\SecurityManager;
use Wioex\SDK\RateLimit\RateLimitManager;
use Wioex\SDK\Debug\DebugManager;
use Wioex\SDK\Debug\PerformanceProfiler;
use Wioex\SDK\Retry\RetryManager;
use Wioex\SDK\ErrorReporter;

class WioexClient
{
    private Config $config;
    private Client $httpClient;
    private ?ConfigurationManager $configManager = null;
    private ?CacheManager $cacheManager = null;
    private ?AsyncClient $asyncClient = null;
    private ?BatchRequestManager $batchManager = null;
    private ?CircuitBreakerManager $circuitBreakerManager = null;
    private ?SecurityManager $securityManager = null;
    private ?RateLimitManager $rateLimitManager = null;
    private ?DebugManager $debugManager = null;
    private ?PerformanceProfiler $performanceProfiler = null;
    private ?RetryManager $retryManager = null;
    private ?ErrorReporter $errorReporter = null;

    private ?Stocks $stocks = null;
    private ?Screens $screens = null;
    private ?Signals $signals = null;
    private ?Markets $markets = null;
    private ?News $news = null;
    private ?Currency $currency = null;
    private ?Account $account = null;
    private ?Streaming $streaming = null;
    private ?Logos $logos = null;

    /**
     * Create a new WioEX API client instance
     *
     * @param array{
     *     api_key?: string,
     *     base_url?: string,
     *     timeout?: int,
     *     connect_timeout?: int,
     *     retry?: array,
     *     headers?: array,
     *     cache?: array
     * } $options Configuration options:
     *   - api_key: string (required) Your WioEX API key
     *   - base_url: string (optional) API base URL, defaults to https://api.wioex.com
     *   - timeout: int (optional) Request timeout in seconds, defaults to 30
     *   - connect_timeout: int (optional) Connection timeout in seconds, defaults to 10
     *   - retry: array (optional) Retry configuration:
     *       - times: int (default: 3) Number of retry attempts
     *       - delay: int (default: 100) Initial delay in milliseconds
     *       - multiplier: int (default: 2) Exponential backoff multiplier
     *       - max_delay: int (default: 5000) Maximum delay in milliseconds
     *   - headers: array (optional) Additional HTTP headers
     *   - cache: array (optional) Cache configuration:
     *       - enabled: bool (default: false) Enable caching
     *       - driver: string (default: 'auto') Cache driver (redis, memcached, opcache, memory, file, auto)
     *       - ttl: array TTL settings for different data types
     *       - prefix: string (default: 'wioex_') Cache key prefix
     *
     * @throws \InvalidArgumentException If required options are missing or invalid
     *
     * @example
     * ```php
     * $client = new WioexClient([
     *     'api_key' => 'your-api-key-here',
     *     'timeout' => 30,
     *     'retry' => ['times' => 3],
     *     'cache' => [
     *         'enabled' => true,
     *         'driver' => 'redis',
     *         'ttl' => [
     *             'stream_token' => 1800,
     *             'market_data' => 60,
     *             'static_data' => 3600
     *         ]
     *     ]
     * ]);
     * ```
     */
    public function __construct(array $options, ?ConfigurationManager $configManager = null)
    {
        $this->config = new Config($options);
        $this->httpClient = new Client($this->config);
        $this->configManager = $configManager;
        
        // Initialize cache if configured
        $this->initializeCache($options['cache'] ?? []);
    }

    /**
     * Create client from configuration file
     *
     * @param string $configPath Path to configuration file (.php, .json, .yaml, .env)
     * @return self
     *
     * @example
     * ```php
     * $client = WioexClient::fromConfig('config/wioex.php');
     * ```
     */
    public static function fromConfig(string $configPath): self
    {
        $configManager = ConfigurationManager::create()
            ->addSource(\Wioex\SDK\Enums\ConfigurationSource::fromPath($configPath), $configPath);

        $configData = $configManager->load();
        return new self($configData, $configManager);
    }

    /**
     * Create client for specific environment
     *
     * @param Environment $environment
     * @param string $basePath
     * @return self
     *
     * @example
     * ```php
     * $client = WioexClient::fromEnvironment(Environment::PRODUCTION);
     * $client->setEnvironment(Environment::DEVELOPMENT);
     * ```
     */
    public static function fromEnvironment(Environment $environment, string $basePath = ''): self
    {
        $configManager = ConfigurationManager::create($environment, $basePath);
        $configData = $configManager->load();

        return new self($configData, $configManager);
    }

    /**
     * Set environment and reload configuration
     *
     * @param Environment $environment
     * @return self
     *
     * @example
     * ```php
     * $client->setEnvironment(Environment::PRODUCTION);
     * ```
     */
    public function setEnvironment(Environment $environment): self
    {
        if ($this->configManager === null) {
            $this->configManager = ConfigurationManager::create($environment);
        }

        $this->configManager->setEnvironment($environment);
        $newConfigData = $this->configManager->reload();

        // Update client configuration
        $this->config = new Config($newConfigData);
        $this->httpClient = new Client($this->config);

        // Reset resource instances to use new client
        $this->resetResources();

        return $this;
    }

    /**
     * Get current environment
     *
     * @return Environment|null
     */
    public function getEnvironment(): ?Environment
    {
        return $this->configManager?->getEnvironment();
    }

    /**
     * Get the configuration instance for dot notation access
     *
     * @return Config
     *
     * @example
     * ```php
     * $redisHost = $client->getConfig()->get('cache.redis.host');
     * $client->getConfig()->set('debug.enabled', true);
     * ```
     */
    public function getConfig(): Config
    {
        return $this->config;
    }

    /**
     * Configure client with new options
     *
     * @param array $options
     * @return self
     *
     * @example
     * ```php
     * $client->configure([
     *     'logging' => ['driver' => 'monolog', 'level' => 'info'],
     *     'metrics' => ['track_latency' => true, 'track_errors' => true]
     * ]);
     * ```
     */
    public function configure(array $options): self
    {
        if ($this->configManager === null) {
            $this->configManager = ConfigurationManager::create();
        }

        foreach ($options as $key => $value) {
            $this->configManager->set($key, $value);
        }

        // Reload configuration and update client
        $newConfigData = $this->configManager->reload();
        $this->config = new Config($newConfigData);
        $this->httpClient = new Client($this->config);

        // Reset resource instances to use new client
        $this->resetResources();

        return $this;
    }

    /**
     * Access stocks-related endpoints
     *
     * @return Stocks
     *
     * @example
     * ```php
     * $client->stocks()->search('AAPL');
     * $client->stocks()->quote('AAPL');
     * $client->stocks()->info('AAPL');
     * ```
     */
    public function stocks(): Stocks
    {
        if ($this->stocks === null) {
            $this->stocks = new Stocks($this->httpClient);
        }

        return $this->stocks;
    }

    /**
     * Access stock screening and market movers endpoints
     *
     * @return Screens
     *
     * @example
     * ```php
     * $client->screens()->gainers();
     * $client->screens()->losers();
     * $client->screens()->active(50);
     * ```
     */
    public function screens(): Screens
    {
        if ($this->screens === null) {
            $this->screens = new Screens($this->httpClient);
        }

        return $this->screens;
    }

    /**
     * Access trading signals endpoints
     *
     * @return Signals
     *
     * @example
     * ```php
     * $client->signals()->active(['symbol' => 'AAPL']);
     * $client->signals()->history(['days' => 7]);
     * ```
     */
    public function signals(): Signals
    {
        if ($this->signals === null) {
            $this->signals = new Signals($this->httpClient);
        }

        return $this->signals;
    }

    /**
     * Access market status and trading hours endpoints
     *
     * @return Markets
     *
     * @example
     * ```php
     * $status = $client->markets()->status();
     * echo "NYSE is " . ($status['markets']['nyse']['is_open'] ? 'open' : 'closed');
     * ```
     */
    public function markets(): Markets
    {
        if ($this->markets === null) {
            $this->markets = new Markets($this->httpClient);
        }

        return $this->markets;
    }

    /**
     * Access news-related endpoints
     *
     * @return News
     *
     * @example
     * ```php
     * $client->news()->latest('AAPL');
     * $client->news()->companyAnalysis('AAPL');
     * ```
     */
    public function news(): News
    {
        if ($this->news === null) {
            $this->news = new News($this->httpClient);
        }

        return $this->news;
    }

    /**
     * Access currency exchange rate endpoints
     *
     * @return Currency
     *
     * @example
     * ```php
     * $client->currency()->baseUsd();
     * $client->currency()->graph('USD', 'EUR', '1d');
     * $client->currency()->calculator('USD', 'EUR', 100);
     * ```
     */
    public function currency(): Currency
    {
        if ($this->currency === null) {
            $this->currency = new Currency($this->httpClient);
        }

        return $this->currency;
    }

    /**
     * Access account management endpoints
     *
     * @return Account
     *
     * @example
     * ```php
     * $client->account()->balance();
     * $client->account()->usage(30);
     * $client->account()->analytics('month');
     * ```
     */
    public function account(): Account
    {
        if ($this->account === null) {
            $this->account = new Account($this->httpClient);
        }

        return $this->account;
    }

    /**
     * Access WebSocket streaming endpoints
     *
     * @return Streaming
     *
     * @example
     * ```php
     * $token = $client->streaming()->getToken();
     * if ($token->successful()) {
     *     $auth = $token['token'];
     *     $wsUrl = $token['websocket_url'];
     * }
     * ```
     */
    public function streaming(): Streaming
    {
        if ($this->streaming === null) {
            $this->streaming = new Streaming($this->httpClient);
        }

        return $this->streaming;
    }

    /**
     * Access logo-related endpoints
     *
     * @return Logos
     *
     * @example
     * ```php
     * $logoUrl = $client->logos()->getUrl('AAPL');
     * $logoExists = $client->logos()->exists('AAPL');
     * $logoUrls = $client->logos()->getBatch(['AAPL', 'GOOGL', 'MSFT']);
     * ```
     */
    public function logos(): Logos
    {
        if ($this->logos === null) {
            $this->logos = new Logos($this->httpClient);
        }

        return $this->logos;
    }

    /**
     * Reset all resource instances to use updated client
     */
    private function resetResources(): void
    {
        $this->stocks = null;
        $this->screens = null;
        $this->signals = null;
        $this->markets = null;
        $this->news = null;
        $this->currency = null;
        $this->account = null;
        $this->streaming = null;
        $this->logos = null;
    }

    /**
     * Watch configuration changes
     *
     * @param callable $callback
     * @return string Watcher ID
     */
    public function watchConfiguration(callable $callback): string
    {
        if ($this->configManager === null) {
            throw new \RuntimeException('Configuration manager not available');
        }

        return $this->configManager->watch(function ($oldConfig, $newConfig) use ($callback) {
            // Update client with new configuration
            $this->config = new Config($newConfig);
            $this->httpClient = new Client($this->config);
            $this->resetResources();

            // Notify callback
            $callback($oldConfig, $newConfig, $this);
        });
    }

    /**
     * Stop watching configuration changes
     *
     * @param string $watcherId
     * @return bool
     */
    public function unwatchConfiguration(string $watcherId): bool
    {
        return $this->configManager?->unwatch($watcherId) ?? false;
    }

    /**
     * Check if configuration is valid
     *
     * @return bool
     */
    public function isConfigurationValid(): bool
    {
        return $this->configManager?->isValid() ?? true;
    }

    /**
     * Get configuration validation results
     *
     * @return array
     */
    public function getConfigurationValidation(): array
    {
        return $this->configManager?->getValidationResults() ?? ['state' => 'unknown'];
    }

    /**
     * Export configuration to file
     *
     * @param \Wioex\SDK\Enums\ConfigurationSource $target
     * @param string $path
     * @return bool
     */
    public function exportConfiguration(\Wioex\SDK\Enums\ConfigurationSource $target, string $path = ''): bool
    {
        if ($this->configManager === null) {
            throw new \RuntimeException('Configuration manager not available');
        }

        return $this->configManager->export($target, $path);
    }

    /**
     * Get configuration statistics
     *
     * @return array
     */
    public function getConfigurationStatistics(): array
    {
        return $this->configManager?->getStatistics() ?? [];
    }

    /**
     * Get configuration manager
     *
     * @return ConfigurationManager|null
     */
    public function getConfigManager(): ?ConfigurationManager
    {
        return $this->configManager;
    }


    /**
     * Get cache interface
     *
     * @return \Wioex\SDK\Cache\CacheInterface|null
     */
    public function getCache(): ?\Wioex\SDK\Cache\CacheInterface
    {
        return $this->httpClient->getCache();
    }

    /**
     * Enable debug mode
     *
     * @return self
     */
    public function enableDebug(): self
    {
        return $this->configure(['debug' => true]);
    }

    /**
     * Disable debug mode
     *
     * @return self
     */
    public function disableDebug(): self
    {
        return $this->configure(['debug' => false]);
    }

    /**
     * Set request timeout
     *
     * @param int $timeout
     * @return self
     */
    public function setTimeout(int $timeout): self
    {
        return $this->configure(['timeout' => $timeout]);
    }

    /**
     * Set API key
     *
     * @param string $apiKey
     * @return self
     */
    public function setApiKey(string $apiKey): self
    {
        return $this->configure(['api_key' => $apiKey]);
    }

    /**
     * Set base URL
     *
     * @param string $baseUrl
     * @return self
     */
    public function setBaseUrl(string $baseUrl): self
    {
        return $this->configure(['base_url' => $baseUrl]);
    }

    /**
     * Enable caching with configuration
     *
     * @param array $cacheConfig
     * @return self
     */
    public function enableCaching(array $cacheConfig = []): self
    {
        $defaultConfig = [
            'cache' => array_merge([
                'default' => 'file',
                'file' => ['cache_dir' => sys_get_temp_dir() . '/wioex_cache']
            ], $cacheConfig)
        ];

        return $this->configure($defaultConfig);
    }

    /**
     * Enable rate limiting with configuration
     *
     * @param array $rateLimitConfig
     * @return self
     */
    public function enableRateLimiting(array $rateLimitConfig = []): self
    {
        $defaultConfig = [
            'rate_limiting' => array_merge([
                'enabled' => true,
                'requests' => 100,
                'window' => 60,
                'strategy' => 'sliding_window'
            ], $rateLimitConfig)
        ];

        return $this->configure($defaultConfig);
    }

    /**
     * Enable logging with configuration
     *
     * @param array $loggingConfig
     * @return self
     */
    public function enableLogging(array $loggingConfig = []): self
    {
        $defaultConfig = [
            'logging' => array_merge([
                'enabled' => true,
                'driver' => 'monolog',
                'level' => 'info'
            ], $loggingConfig)
        ];

        return $this->configure($defaultConfig);
    }

    /**
     * Enable metrics tracking with configuration
     *
     * @param array $metricsConfig
     * @return self
     */
    public function enableMetrics(array $metricsConfig = []): self
    {
        $defaultConfig = [
            'metrics' => array_merge([
                'track_latency' => true,
                'track_errors' => true,
                'track_cache_hits' => true
            ], $metricsConfig)
        ];

        return $this->configure($defaultConfig);
    }

    /**
     * Perform health check
     *
     * @return array
     */
    public function healthCheck(): array
    {
        $health = [
            'client' => [
                'status' => 'healthy',
                'config_valid' => $this->isConfigurationValid(),
                'environment' => $this->getEnvironment()?->value ?? 'unknown',
            ],
            'configuration' => $this->getConfigurationValidation(),
        ];

        // Add cache health if available
        try {
            $cache = $this->getCache();
            $health['cache'] = $cache !== null ? $cache->isHealthy() : false;
        } catch (\Throwable $e) {
            $health['cache'] = false;
        }

        // Add async health if available
        try {
            $health['async'] = $this->async()->getEventLoop()->getHealthMetrics();
        } catch (\Throwable $e) {
            $health['async'] = ['is_healthy' => false, 'error' => $e->getMessage()];
        }

        return $health;
    }

    /**
     * Get bulk operation optimizer for strategy analysis
     *
     * @return \Wioex\SDK\Optimization\BulkOperationOptimizer
     * 
     * @example
     * ```php
     * $optimizer = $client->optimizer();
     * $analysis = $optimizer->analyzeOperations([
     *     'quotes' => 500,
     *     'timeline' => 100,
     *     'info' => 50
     * ]);
     * 
     * echo "Total credits: {$analysis['total_credits']}\n";
     * echo "Credit savings: {$analysis['overall_credit_savings_percent']}%\n";
     * echo "Optimization score: {$analysis['optimization_score']}/100\n";
     * 
     * foreach ($analysis['recommendations'] as $recommendation) {
     *     echo "• {$recommendation['message']}\n";
     * }
     * ```
     */
    public function optimizer(): \Wioex\SDK\Optimization\BulkOperationOptimizer
    {
        return new \Wioex\SDK\Optimization\BulkOperationOptimizer();
    }

    /**
     * Get SDK version
     *
     * @return string
     */
    public static function getVersion(): string
    {
        return Version::current();
    }

    /**
     * Get full SDK version information
     *
     * @return array
     */
    public static function getVersionInfo(): array
    {
        return Version::info();
    }

    /**
     * Initialize cache manager based on configuration
     */
    private function initializeCache(array $cacheConfig): void
    {
        if (count($cacheConfig) === 0 || !($cacheConfig['enabled'] ?? false)) {
            return; // Cache not enabled
        }

        try {
            $defaultConfig = [
                'enabled' => true,
                'driver' => 'auto', // Auto-detect best driver
                'prefix' => 'wioex_',
                'ttl' => [
                    'stream_token' => 1800,  // 30 minutes
                    'market_data' => 60,     // 1 minute
                    'static_data' => 3600,   // 1 hour
                    'user_data' => 300,      // 5 minutes
                    'news' => 1800,          // 30 minutes
                    'signals' => 120,        // 2 minutes
                    'account' => 600,        // 10 minutes
                    'default' => 900         // 15 minutes fallback
                ],
                'redis' => [
                    'host' => '127.0.0.1',
                    'port' => 6379,
                    'persistent' => true
                ],
                'memcached' => [
                    'servers' => [['host' => '127.0.0.1', 'port' => 11211]],
                    'persistent_id' => 'wioex_cache'
                ],
                'opcache' => [
                    'cache_dir' => sys_get_temp_dir() . '/wioex_opcache'
                ],
                'file' => [
                    'cache_dir' => sys_get_temp_dir() . '/wioex_cache'
                ]
            ];

            $config = array_merge($defaultConfig, $cacheConfig);

            // Handle 'auto' driver selection
            if ($config['driver'] === 'auto') {
                $config['default'] = 'auto'; // Let CacheManager auto-detect
            } else {
                $config['default'] = $config['driver'];
            }

            $this->cacheManager = new CacheManager($config);
            
        } catch (\Exception $e) {
            // Graceful degradation: Disable cache on initialization error
            $this->cacheManager = null;
            
            // Report error if ErrorReporter is available
            if ($this->errorReporter !== null) {
                $this->errorReporter->report($e, [
                    'context' => 'wioex_client_cache_initialization_error',
                    'requested_config' => $cacheConfig
                ]);
            }
        }
    }

    /**
     * Get the cache manager instance
     */
    public function cache(): ?CacheManager
    {
        return $this->cacheManager;
    }

    /**
     * Check if caching is enabled
     */
    public function isCacheEnabled(): bool
    {
        return $this->cacheManager !== null && $this->cacheManager->isHealthy();
    }

    /**
     * Cache a value with automatic TTL based on data type
     */
    public function cacheSet(string $key, mixed $value, string $dataType = 'default'): bool
    {
        if (!$this->isCacheEnabled() || $this->cacheManager === null) {
            return false;
        }

        $config = $this->config->toArray();
        $ttl = $config['cache']['ttl'][$dataType] ?? $config['cache']['ttl']['default'] ?? 900;

        return $this->cacheManager->set($key, $value, $ttl);
    }

    /**
     * Get a cached value
     */
    public function cacheGet(string $key): mixed
    {
        if (!$this->isCacheEnabled() || $this->cacheManager === null) {
            return null;
        }

        return $this->cacheManager->get($key);
    }

    /**
     * Check if a key exists in cache
     */
    public function cacheHas(string $key): bool
    {
        if (!$this->isCacheEnabled() || $this->cacheManager === null) {
            return false;
        }

        return $this->cacheManager->has($key);
    }

    /**
     * Delete a cached value
     */
    public function cacheDelete(string $key): bool
    {
        if (!$this->isCacheEnabled() || $this->cacheManager === null) {
            return false;
        }

        return $this->cacheManager->delete($key);
    }

    /**
     * Clear all cached values
     */
    public function cacheClear(): bool
    {
        if (!$this->isCacheEnabled() || $this->cacheManager === null) {
            return false;
        }

        return $this->cacheManager->clear();
    }

    /**
     * Get cache statistics
     */
    public function getCacheStatistics(): array
    {
        if (!$this->isCacheEnabled() || $this->cacheManager === null) {
            return ['cache_enabled' => false];
        }

        return array_merge(
            ['cache_enabled' => true],
            $this->cacheManager->getStatistics()
        );
    }

    /**
     * Get cache recommendations for current system
     */
    public function getCacheRecommendations(): array
    {
        if ($this->cacheManager === null) {
            $tempManager = new CacheManager(['default' => 'memory']);
            return $tempManager->getSystemRecommendations();
        }

        return $this->cacheManager->getSystemRecommendations();
    }

    /**
     * Remember a value in cache using a callback
     */
    public function remember(string $key, callable $callback, string $dataType = 'default'): mixed
    {
        if (!$this->isCacheEnabled() || $this->cacheManager === null) {
            return $callback();
        }

        $config = $this->config->toArray();
        $ttl = $config['cache']['ttl'][$dataType] ?? $config['cache']['ttl']['default'] ?? 900;

        return $this->cacheManager->remember($key, $callback, $ttl);
    }

    /**
     * Remember a value in cache forever (no expiration)
     */
    public function rememberForever(string $key, callable $callback): mixed
    {
        if (!$this->isCacheEnabled() || $this->cacheManager === null) {
            return $callback();
        }

        return $this->cacheManager->rememberForever($key, $callback);
    }

    /**
     * Create a namespaced cache instance
     */
    public function cacheNamespace(string $namespace): ?CacheInterface
    {
        if (!$this->isCacheEnabled() || $this->cacheManager === null) {
            return null;
        }

        return $this->cacheManager->namespace($namespace);
    }

    /**
     * Create a tagged cache instance
     */
    public function cacheWithTags(array $tags): ?CacheInterface
    {
        if (!$this->isCacheEnabled() || $this->cacheManager === null) {
            return null;
        }

        return $this->cacheManager->tags($tags);
    }

    /**
     * Flush expired cache entries
     */
    public function flushExpiredCache(): int
    {
        if (!$this->isCacheEnabled() || $this->cacheManager === null) {
            return 0;
        }

        return $this->cacheManager->flushExpired();
    }

    /**
     * Enable cache for this client instance
     */
    public function enableCache(array $config = []): self
    {
        if (count($config) === 0) {
            $config = ['enabled' => true, 'driver' => 'auto'];
        } else {
            $config['enabled'] = true;
        }

        $this->initializeCache($config);
        
        return $this;
    }

    /**
     * Disable cache for this client instance
     */
    public function disableCache(): self
    {
        $this->cacheManager = null;
        
        return $this;
    }

    /**
     * Configure cache for specific use case
     */
    public function configureCacheForUseCase(string $useCase): self
    {
        if ($this->cacheManager === null) {
            $tempManager = new CacheManager(['default' => 'memory']);
            $config = $tempManager->configureForUseCase($useCase);
        } else {
            $config = $this->cacheManager->configureForUseCase($useCase);
        }
        
        $config['enabled'] = true;
        $this->initializeCache($config);
        
        return $this;
    }

    /**
     * Get async client for promise-based operations
     */
    public function async(): AsyncClient
    {
        if ($this->asyncClient === null) {
            $this->asyncClient = new AsyncClient($this->config);
        }
        return $this->asyncClient;
    }

    /**
     * Create a new batch request manager
     */
    public function batch(): BatchRequestManager
    {
        if ($this->batchManager === null) {
            $this->batchManager = new BatchRequestManager(
                $this->httpClient,
                $this->config->toArray()['batch'] ?? [],
                $this->cacheManager,
                $this->circuitBreakerManager
            );
        }
        return $this->batchManager;
    }

    /**
     * Get circuit breaker manager
     */
    public function circuitBreaker(): CircuitBreakerManager
    {
        if ($this->circuitBreakerManager === null) {
            $this->circuitBreakerManager = new CircuitBreakerManager($this->cacheManager);
        }
        return $this->circuitBreakerManager;
    }

    /**
     * Execute multiple requests in batch
     */
    public function executeBatch(array $requests): array
    {
        $batchManager = $this->batch();
        
        foreach ($requests as $request) {
            if (is_array($request)) {
                $batchManager->add(
                    $request['method'] ?? 'GET',
                    $request['endpoint'],
                    $request['params'] ?? [],
                    $request['options'] ?? []
                );
            }
        }
        
        return $batchManager->execute();
    }

    /**
     * Convenience method for batch stock quotes
     */
    public function getBatchQuotes(array $symbols, array $options = []): array
    {
        $batchManager = $this->batch();
        
        foreach ($symbols as $symbol) {
            $batchManager->addQuote($symbol, $options);
        }
        
        return $batchManager->execute();
    }

    /**
     * Get multiple stock info in batch
     */
    public function getBatchStockInfo(array $symbols, array $options = []): array
    {
        $batchManager = $this->batch();
        
        foreach ($symbols as $symbol) {
            $batchManager->add('GET', '/v2/stocks/info', ['ticker' => $symbol], $options);
        }
        
        return $batchManager->execute();
    }

    /**
     * Get multiple timelines in batch
     */
    public function getBatchTimelines(array $symbols, string $interval = '1d', array $options = []): array
    {
        $batchManager = $this->batch();
        
        foreach ($symbols as $symbol) {
            $batchManager->addTimeline($symbol, $interval, $options);
        }
        
        return $batchManager->execute();
    }

    /**
     * Get comprehensive data for multiple stocks
     */
    public function getPortfolioData(array $symbols, array $options = []): array
    {
        $batchManager = $this->batch();
        
        foreach ($symbols as $symbol) {
            // Add quote
            $batchManager->addQuote($symbol, array_merge($options, ['priority' => 3]));
            
            // Add news
            $batchManager->addNews($symbol, array_merge($options, ['priority' => 1]));
            
            // Add timeline if requested
            if ($options['include_timeline'] ?? false) {
                $interval = $options['timeline_interval'] ?? '1d';
                $batchManager->addTimeline($symbol, $interval, array_merge($options, ['priority' => 2]));
            }
            
            // Add stock info if requested
            if ($options['include_info'] ?? false) {
                $batchManager->add('GET', '/v2/stocks/info', ['ticker' => $symbol], array_merge($options, ['priority' => 1]));
            }
        }
        
        return $batchManager->execute();
    }

    /**
     * Execute requests with circuit breaker protection
     */
    public function withCircuitBreaker(string $service, callable $operation, ?callable $fallback = null): mixed
    {
        $circuitBreaker = $this->circuitBreaker();
        
        if ($fallback !== null) {
            return $circuitBreaker->callWithFallback($service, $operation, $fallback);
        }
        
        return $circuitBreaker->call($service, $operation);
    }

    /**
     * Configure circuit breaker for a service
     */
    public function configureCircuitBreaker(string $serviceName, array $config = []): self
    {
        $this->circuitBreaker()->configureForService($serviceName, $config);
        return $this;
    }

    /**
     * Get batch processing statistics
     */
    public function getBatchStatistics(): array
    {
        if ($this->batchManager === null) {
            return ['batch_manager_enabled' => false];
        }
        
        return array_merge(
            ['batch_manager_enabled' => true],
            $this->batchManager->getMetrics()
        );
    }

    /**
     * Get circuit breaker health status
     */
    public function getCircuitBreakerHealth(): array
    {
        if ($this->circuitBreakerManager === null) {
            return ['circuit_breaker_enabled' => false];
        }
        
        return array_merge(
            ['circuit_breaker_enabled' => true],
            $this->circuitBreakerManager->getHealthStatus()
        );
    }

    /**
     * Example usage for batch operations
     */
    public function createPortfolioBatch(array $symbols): BatchRequestManager
    {
        $batch = $this->batch();
        
        // Essential data with high priority
        foreach ($symbols as $symbol) {
            $batch->addQuote($symbol, ['priority' => 3]);
        }
        
        // Market status (shared across all)
        $batch->addMarketStatus(['priority' => 3]);
        
        // Additional data with lower priority
        foreach ($symbols as $symbol) {
            $batch->addTimeline($symbol, '1d', ['priority' => 2]);
            $batch->addNews($symbol, ['priority' => 1]);
        }
        
        return $batch;
    }

    /**
     * Benchmark batch processing performance
     */
    public function benchmarkBatch(int $requestCount = 100): array
    {
        return $this->batch()->benchmark($requestCount);
    }

    /**
     * Test circuit breaker functionality
     */
    public function testCircuitBreaker(string $serviceName): bool
    {
        return $this->circuitBreaker()->test($serviceName);
    }

    /**
     * Get security manager instance
     *
     * @return SecurityManager
     */
    public function security(): SecurityManager
    {
        if ($this->securityManager === null) {
            $this->securityManager = new SecurityManager($this->config);
        }
        return $this->securityManager;
    }

    /**
     * Validate request security
     *
     * @param string $method
     * @param string $url
     * @param array $headers
     * @param string $body
     * @param string|null $clientIp
     * @return array
     */
    public function validateRequestSecurity(string $method, string $url, array $headers = [], string $body = '', ?string $clientIp = null): array
    {
        return $this->security()->validateRequest($method, $url, $headers, $body, $clientIp);
    }

    /**
     * Secure a request with signing and encryption
     *
     * @param string $method
     * @param string $url
     * @param array $headers
     * @param string $body
     * @return array
     */
    public function secureRequest(string $method, string $url, array $headers = [], string $body = ''): array
    {
        return $this->security()->secureRequest($method, $url, $headers, $body);
    }

    /**
     * Encrypt sensitive data
     *
     * @param string $data
     * @param string|null $key
     * @return array
     */
    public function encrypt(string $data, ?string $key = null): array
    {
        return $this->security()->getEncryptionManager()->encrypt($data, $key);
    }

    /**
     * Decrypt sensitive data
     *
     * @param array $encryptedData
     * @param string|null $key
     * @return string
     */
    public function decrypt(array $encryptedData, ?string $key = null): string
    {
        return $this->security()->getEncryptionManager()->decrypt($encryptedData, $key);
    }

    /**
     * Generate secure token
     *
     * @param int $length
     * @return string
     */
    public function generateSecureToken(int $length = 32): string
    {
        return $this->security()->getEncryptionManager()->createSecureToken($length);
    }

    /**
     * Get security status and configuration
     *
     * @return array
     */
    public function getSecurityStatus(): array
    {
        return $this->security()->getSecurityStatus();
    }

    /**
     * Enable IP whitelist protection
     *
     * @param array $allowedIps
     * @return self
     */
    public function withIpWhitelist(array $allowedIps): self
    {
        $securityConfig = $this->config->get('security', []);
        $securityConfig['ip_whitelist'] = $allowedIps;
        $this->config->set('security', $securityConfig);
        
        // Reset security manager to pick up new config
        $this->securityManager = null;
        
        return $this;
    }

    /**
     * Enable request signing with specified algorithm
     *
     * @param string $secretKey
     * @param string $algorithm
     * @return self
     */
    public function withRequestSigning(string $secretKey, string $algorithm = 'hmac-sha256'): self
    {
        $securityConfig = $this->config->get('security', []);
        $securityConfig['request_signing'] = true;
        $securityConfig['secret_key'] = $secretKey;
        $securityConfig['signature_algorithm'] = $algorithm;
        $this->config->set('security', $securityConfig);
        
        // Reset security manager to pick up new config
        $this->securityManager = null;
        
        return $this;
    }

    /**
     * Enable encryption for request/response data
     *
     * @param string $encryptionKey
     * @param string $algorithm
     * @return self
     */
    public function withEncryption(string $encryptionKey, string $algorithm = 'aes-256-gcm'): self
    {
        $securityConfig = $this->config->get('security', []);
        $securityConfig['encryption'] = [
            'enabled' => true,
            'key' => $encryptionKey,
            'algorithm' => $algorithm
        ];
        $this->config->set('security', $securityConfig);
        
        // Reset security manager to pick up new config
        $this->securityManager = null;
        
        return $this;
    }

    /**
     * Get audit log of security events
     *
     * @return array
     */
    public function getSecurityAuditLog(): array
    {
        return $this->security()->getAuditLog();
    }

    /**
     * Clear security audit log
     *
     * @return self
     */
    public function clearSecurityAuditLog(): self
    {
        $this->security()->clearAuditLog();
        return $this;
    }

    /**
     * Get rate limiting manager instance
     *
     * @return RateLimitManager
     */
    public function rateLimit(): RateLimitManager
    {
        if ($this->rateLimitManager === null) {
            $this->rateLimitManager = new RateLimitManager($this->config, $this->cacheManager);
        }
        return $this->rateLimitManager;
    }

    /**
     * Check if request is allowed under rate limiting
     *
     * @param string $identifier
     * @param array $categories
     * @return bool
     */
    public function isRequestAllowed(string $identifier, array $categories = ['default']): bool
    {
        return $this->rateLimit()->isRequestAllowed($identifier, $categories);
    }

    /**
     * Process request with rate limiting
     *
     * @param string $identifier
     * @param array $categories
     * @param int $tokens
     * @return bool
     */
    public function processRateLimitedRequest(string $identifier, array $categories = ['default'], int $tokens = 1): bool
    {
        return $this->rateLimit()->processRequest($identifier, $categories, $tokens);
    }

    /**
     * Get rate limiting status for identifier
     *
     * @param string $identifier
     * @return array
     */
    public function getRateLimitStatus(string $identifier): array
    {
        return $this->rateLimit()->getComprehensiveStatus($identifier);
    }

    /**
     * Configure intelligent rate limiting categories
     *
     * @return self
     */
    public function withIntelligentRateLimiting(): self
    {
        $this->rateLimit()->configureIntelligentLimiting();
        
        // Reset manager to pick up new config
        $this->rateLimitManager = null;
        
        return $this;
    }

    /**
     * Enable fair queuing for request processing
     *
     * @param array $requests
     * @return array
     */
    public function applyFairQueuing(array $requests): array
    {
        return $this->rateLimit()->fairQueue($requests);
    }

    /**
     * Apply burst protection for identifier
     *
     * @param string $identifier
     * @param array $categories
     * @return array
     */
    public function applyBurstProtection(string $identifier, array $categories = ['default']): array
    {
        return $this->rateLimit()->applyBurstProtection($identifier, $categories);
    }

    /**
     * Get adaptive rate limiting recommendations
     *
     * @param string $identifier
     * @param string $category
     * @return array
     */
    public function getAdaptiveRateLimiting(string $identifier, string $category = 'default'): array
    {
        return $this->rateLimit()->adaptiveRateLimit($identifier, $category);
    }

    /**
     * Process priority-based requests
     *
     * @param array $requests
     * @return array
     */
    public function processPriorityRequests(array $requests): array
    {
        return $this->rateLimit()->processPriorityRequests($requests);
    }

    /**
     * Get global rate limiting metrics
     *
     * @return array
     */
    public function getRateLimitingMetrics(): array
    {
        return $this->rateLimit()->getGlobalMetrics();
    }

    /**
     * Cleanup expired rate limiting data
     *
     * @return array
     */
    public function cleanupRateLimiting(): array
    {
        return $this->rateLimit()->globalCleanup();
    }

    /**
     * Configure custom rate limiting for specific operations
     *
     * @param string $category
     * @param int $maxRequests
     * @param int $refillRate
     * @param int $refillPeriod
     * @param float $burstMultiplier
     * @return self
     */
    public function withCustomRateLimit(string $category, int $maxRequests, int $refillRate, int $refillPeriod = 60, float $burstMultiplier = 1.5): self
    {
        $this->rateLimit()->getLimiter($category)->configurateCategory($category, [
            'max_requests' => $maxRequests,
            'refill_rate' => $refillRate,
            'refill_period' => $refillPeriod,
            'burst_multiplier' => $burstMultiplier
        ]);
        
        return $this;
    }

    /**
     * Get debug manager instance
     *
     * @return DebugManager
     */
    public function debug(): DebugManager
    {
        if ($this->debugManager === null) {
            $this->debugManager = new DebugManager($this->config, $this->cacheManager);
        }
        return $this->debugManager;
    }

    /**
     * Get performance profiler instance
     *
     * @return PerformanceProfiler
     */
    public function profiler(): PerformanceProfiler
    {
        if ($this->performanceProfiler === null) {
            $this->performanceProfiler = new PerformanceProfiler($this->config);
        }
        return $this->performanceProfiler;
    }

    /**
     * Enable debug mode with specific features
     *
     * @param bool $queryLogging
     * @param bool $performanceProfiling
     * @param bool $responseValidation
     * @return self
     */
    public function withDebugMode(bool $queryLogging = true, bool $performanceProfiling = true, bool $responseValidation = true): self
    {
        $debugConfig = $this->config->get('debug', []);
        $debugConfig['enabled'] = true;
        $debugConfig['query_logging'] = $queryLogging;
        $debugConfig['performance_profiling'] = $performanceProfiling;
        $debugConfig['response_validation'] = $responseValidation;
        
        $this->config->set('debug', $debugConfig);
        
        // Reset managers to pick up new config
        $this->debugManager = null;
        $this->performanceProfiler = null;
        
        return $this;
    }

    /**
     * Profile a specific operation
     *
     * @param string $name
     * @param callable $callback
     * @param array $metadata
     * @return mixed
     */
    public function profileOperation(string $name, callable $callback, array $metadata = []): mixed
    {
        return $this->profiler()->profileCallable($name, $callback, $metadata);
    }

    /**
     * Take a memory snapshot
     *
     * @param string $name
     * @param array $context
     * @return array
     */
    public function takeMemorySnapshot(string $name, array $context = []): array
    {
        return $this->profiler()->takeMemorySnapshot($name, $context);
    }

    /**
     * Start profiling an operation
     *
     * @param string $name
     * @param array $metadata
     * @return self
     */
    public function startProfiling(string $name, array $metadata = []): self
    {
        $this->profiler()->startProfiling($name, $metadata);
        return $this;
    }

    /**
     * Stop profiling and get results
     *
     * @param string $name
     * @return array
     */
    public function stopProfiling(string $name): array
    {
        return $this->profiler()->stopProfiling($name);
    }

    /**
     * Add a checkpoint during profiling
     *
     * @param string $profileName
     * @param string $checkpointName
     * @param array $data
     * @return self
     */
    public function addCheckpoint(string $profileName, string $checkpointName, array $data = []): self
    {
        $this->profiler()->checkpoint($profileName, $checkpointName, $data);
        return $this;
    }

    /**
     * Log a query for debugging
     *
     * @param string $method
     * @param string $url
     * @param array $headers
     * @param string $body
     * @param array|null $response
     * @param float $executionTime
     * @return self
     */
    public function logQuery(string $method, string $url, array $headers = [], string $body = '', ?array $response = null, float $executionTime = 0): self
    {
        $this->debug()->logQuery($method, $url, $headers, $body, $response, $executionTime);
        return $this;
    }

    /**
     * Log an error for debugging
     *
     * @param \Throwable $error
     * @param array $context
     * @return self
     */
    public function logError(\Throwable $error, array $context = []): self
    {
        $this->debug()->logError($error, $context);
        return $this;
    }

    /**
     * Report error to WioEX for monitoring and improvements
     *
     * @param \Throwable $error
     * @param array $context
     * @return bool Success status
     */
    public function reportError(\Throwable $error, array $context = []): bool
    {
        if ($this->errorReporter === null) {
            $this->errorReporter = new ErrorReporter($this->config);
        }
        
        return $this->errorReporter->report($error, $context);
    }

    /**
     * Get debug summary
     *
     * @return array
     */
    public function getDebugSummary(): array
    {
        return $this->debug()->getDebugSummary();
    }

    /**
     * Get performance report
     *
     * @return array
     */
    public function getPerformanceReport(): array
    {
        return $this->profiler()->getPerformanceReport();
    }

    /**
     * Get query log with optional filters
     *
     * @param array $filters
     * @return array
     */
    public function getQueryLog(array $filters = []): array
    {
        return $this->debug()->getQueryLog($filters);
    }

    /**
     * Get slow operations
     *
     * @param int $limit
     * @return array
     */
    public function getSlowOperations(int $limit = 10): array
    {
        return $this->profiler()->getSlowOperations($limit);
    }

    /**
     * Get memory intensive operations
     *
     * @param int $limit
     * @return array
     */
    public function getMemoryIntensiveOperations(int $limit = 10): array
    {
        return $this->profiler()->getMemoryIntensiveOperations($limit);
    }

    /**
     * Detect potential memory leaks
     *
     * @return array
     */
    public function detectMemoryLeaks(): array
    {
        return $this->profiler()->detectMemoryLeaks();
    }

    /**
     * Generate performance recommendations
     *
     * @return array
     */
    public function getPerformanceRecommendations(): array
    {
        return $this->profiler()->generateRecommendations();
    }

    /**
     * Export debug data to file
     *
     * @param string $format
     * @param string $filename
     * @return string
     */
    public function exportDebugData(string $format = 'json', string $filename = ''): string
    {
        return $this->debug()->exportDebugData($format, $filename);
    }

    /**
     * Clear all debug logs and profiling data
     *
     * @return self
     */
    public function clearDebugData(): self
    {
        $this->debug()->clearLogs();
        $this->profiler()->clearProfiles();
        return $this;
    }

    /**
     * Get real-time debug information
     *
     * @return array
     */
    public function getRealTimeDebugInfo(): array
    {
        return $this->debug()->getRealTimeDebugInfo();
    }

    /**
     * Compare memory snapshots
     *
     * @param string $snapshot1Name
     * @param string $snapshot2Name
     * @return array
     */
    public function compareMemorySnapshots(string $snapshot1Name, string $snapshot2Name): array
    {
        return $this->profiler()->compareMemorySnapshots($snapshot1Name, $snapshot2Name);
    }

    /**
     * Check if debug mode is enabled
     *
     * @return bool
     */
    public function isDebugEnabled(): bool
    {
        return $this->debug()->isDebugEnabled();
    }

    /**
     * Comprehensive performance and debug analysis
     *
     * @return array
     */
    public function getComprehensiveAnalysis(): array
    {
        return [
            'debug_summary' => $this->getDebugSummary(),
            'performance_report' => $this->getPerformanceReport(),
            'slow_operations' => $this->getSlowOperations(5),
            'memory_intensive_operations' => $this->getMemoryIntensiveOperations(5),
            'memory_leaks' => $this->detectMemoryLeaks(),
            'recommendations' => $this->getPerformanceRecommendations(),
            'real_time_info' => $this->getRealTimeDebugInfo(),
            'rate_limit_metrics' => $this->getRateLimitingMetrics(),
            'security_status' => $this->getSecurityStatus(),
            'circuit_breaker_health' => $this->getCircuitBreakerHealth()
        ];
    }

    /**
     * Get retry manager instance
     *
     * @return RetryManager
     */
    public function retry(): RetryManager
    {
        if ($this->retryManager === null) {
            $this->retryManager = new RetryManager($this->config);
        }
        return $this->retryManager;
    }

    /**
     * Execute operation with retry logic
     *
     * @param callable $operation
     * @param array $retryConfig
     * @return mixed
     */
    public function executeWithRetry(callable $operation, array $retryConfig = []): mixed
    {
        return $this->retry()->execute($operation, $retryConfig);
    }

    /**
     * Execute operation with async retry
     *
     * @param callable $operation
     * @param array $retryConfig
     * @return array
     */
    public function executeWithAsyncRetry(callable $operation, array $retryConfig = []): array
    {
        return $this->retry()->executeAsync($operation, $retryConfig);
    }

    /**
     * Execute bulk operations with retry
     *
     * @param array $operations
     * @param array $retryConfig
     * @return array
     */
    public function executeBulkWithRetry(array $operations, array $retryConfig = []): array
    {
        return $this->retry()->executeBulk($operations, $retryConfig);
    }

    /**
     * Configure retry strategy
     *
     * @param string $strategy
     * @param int $maxAttempts
     * @param int $baseDelay
     * @param float $multiplier
     * @param bool $jitter
     * @return self
     */
    public function withRetryStrategy(string $strategy = 'exponential_backoff', int $maxAttempts = 3, int $baseDelay = 1000, float $multiplier = 2.0, bool $jitter = true): self
    {
        $retryConfig = $this->config->get('retry', []);
        $retryConfig = array_merge($retryConfig, [
            'strategy' => $strategy,
            'max_attempts' => $maxAttempts,
            'base_delay' => $baseDelay,
            'multiplier' => $multiplier,
            'jitter' => $jitter
        ]);
        
        $this->config->set('retry', $retryConfig);
        
        // Reset manager to pick up new config
        $this->retryManager = null;
        
        return $this;
    }

    /**
     * Configure exponential backoff retry
     *
     * @param int $maxAttempts
     * @param int $baseDelay
     * @param float $multiplier
     * @return self
     */
    public function withExponentialBackoff(int $maxAttempts = 3, int $baseDelay = 1000, float $multiplier = 2.0): self
    {
        return $this->withRetryStrategy('exponential_backoff', $maxAttempts, $baseDelay, $multiplier, true);
    }

    /**
     * Configure linear backoff retry
     *
     * @param int $maxAttempts
     * @param int $baseDelay
     * @return self
     */
    public function withLinearBackoff(int $maxAttempts = 3, int $baseDelay = 1000): self
    {
        return $this->withRetryStrategy('linear_backoff', $maxAttempts, $baseDelay);
    }

    /**
     * Configure fibonacci backoff retry
     *
     * @param int $maxAttempts
     * @param int $baseDelay
     * @return self
     */
    public function withFibonacciBackoff(int $maxAttempts = 5, int $baseDelay = 1000): self
    {
        return $this->withRetryStrategy('fibonacci_backoff', $maxAttempts, $baseDelay);
    }

    /**
     * Configure adaptive backoff retry
     *
     * @param int $maxAttempts
     * @param int $baseDelay
     * @param float $multiplier
     * @return self
     */
    public function withAdaptiveBackoff(int $maxAttempts = 3, int $baseDelay = 1000, float $multiplier = 2.0): self
    {
        return $this->withRetryStrategy('adaptive_backoff', $maxAttempts, $baseDelay, $multiplier, true);
    }

    /**
     * Get retry statistics
     *
     * @return array
     */
    public function getRetryStatistics(): array
    {
        return $this->retry()->getRetryStatistics();
    }

    /**
     * Get retry history
     *
     * @param array $filters
     * @return array
     */
    public function getRetryHistory(array $filters = []): array
    {
        return $this->retry()->getRetryHistory($filters);
    }

    /**
     * Analyze retry patterns
     *
     * @return array
     */
    public function analyzeRetryPatterns(): array
    {
        return $this->retry()->analyzeRetryPatterns();
    }

    /**
     * Get retry recommendations
     *
     * @return array
     */
    public function getRetryRecommendations(): array
    {
        return $this->retry()->generateRetryRecommendations();
    }

    /**
     * Test retry configuration
     *
     * @param array $config
     * @param int $simulatedFailures
     * @return array
     */
    public function testRetryConfig(array $config = [], int $simulatedFailures = 2): array
    {
        return $this->retry()->testRetryConfig($config, $simulatedFailures);
    }

    /**
     * Reset retry statistics
     *
     * @return self
     */
    public function resetRetryStatistics(): self
    {
        $this->retry()->resetStatistics();
        return $this;
    }

    /**
     * Execute API request with automatic retry on failures
     *
     * @param callable $apiCall
     * @param array $retryConfig
     * @return mixed
     */
    public function makeResilientApiCall(callable $apiCall, array $retryConfig = []): mixed
    {
        $defaultRetryConfig = [
            'strategy' => 'exponential_backoff',
            'max_attempts' => 3,
            'base_delay' => 1000,
            'multiplier' => 2.0,
            'jitter' => true,
            'retryable_status_codes' => [408, 429, 500, 502, 503, 504],
            'non_retryable_status_codes' => [400, 401, 403, 404]
        ];
        
        $config = array_merge($defaultRetryConfig, $retryConfig);
        
        return $this->executeWithRetry($apiCall, $config);
    }

    /**
     * Get current retry configuration
     *
     * @return array
     */
    public function getRetryConfiguration(): array
    {
        return $this->retry()->getCurrentConfig();
    }

}
