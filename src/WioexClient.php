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

class WioexClient
{
    private Config $config;
    private Client $httpClient;
    private ?ConfigurationManager $configManager = null;

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
     *     headers?: array
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
     *
     * @throws \InvalidArgumentException If required options are missing or invalid
     *
     * @example
     * ```php
     * $client = new WioexClient([
     *     'api_key' => 'your-api-key-here',
     *     'timeout' => 30,
     *     'retry' => ['times' => 3]
     * ]);
     * ```
     */
    public function __construct(array $options, ?ConfigurationManager $configManager = null)
    {
        $this->config = new Config($options);
        $this->httpClient = new Client($this->config);
        $this->configManager = $configManager;
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
     * Get the configuration instance
     *
     * @return Config
     */
    public function getConfig(): Config
    {
        return $this->config;
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
     * Get async client instance
     *
     * @return \Wioex\SDK\Async\AsyncClient
     */
    public function async(): \Wioex\SDK\Async\AsyncClient
    {
        return new \Wioex\SDK\Async\AsyncClient($this->config);
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
            $health['cache'] = $this->getCache()->isHealthy();
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
}
