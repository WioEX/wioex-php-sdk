<?php

declare(strict_types=1);

namespace Wioex\SDK\Connection;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\Handler\CurlMultiHandler;
use GuzzleHttp\Middleware;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Wioex\SDK\Enums\Environment;
use Wioex\SDK\Enums\PoolStrategy;
use Wioex\SDK\Config;
use Wioex\SDK\Logging\Logger;
use Wioex\SDK\Monitoring\Metrics;

class ConnectionPoolManager
{
    private array $pools = [];
    private array $config;
    private bool $enabled = true;
    private ?Logger $logger = null;
    private ?Metrics $metrics = null;
    private array $defaultHandlerStack = [];

    public function __construct(array $config = [], Logger $logger = null, Metrics $metrics = null)
    {
        $this->config = array_merge($this->getDefaultConfig(), $config);
        $this->enabled = $this->config['enabled'] ?? true;
        $this->logger = $logger;
        $this->metrics = $metrics;

        $this->initializeDefaultHandlerStack();
    }

    public static function create(array $config = []): self
    {
        return new self($config);
    }

    public static function forEnvironment(Environment $environment, array $config = []): self
    {
        $defaultConfig = [
            'enabled' => $environment->shouldEnableConnectionPooling(),
            'default_strategy' => $environment->getDefaultPoolStrategy(),
            'min_connections' => $environment->getMinConnections(),
            'max_connections' => $environment->getMaxConnections(),
        ];

        return new self(array_merge($defaultConfig, $config));
    }

    public static function fromConfig(Config $sdkConfig): self
    {
        $poolConfig = $sdkConfig->getConnectionPoolConfig();
        $environment = $sdkConfig->getEnvironment();
        $logger = $sdkConfig->getLogger();
        $metrics = $sdkConfig->getMetrics();

        return new self($poolConfig, $logger, $metrics);
    }

    public function getPooledClient(string $baseUri, array $clientConfig = []): GuzzleClient
    {
        if (!$this->enabled) {
            return $this->createStandardClient($baseUri, $clientConfig);
        }

        $poolKey = $this->getPoolKey($baseUri);

        if (!isset($this->pools[$poolKey])) {
            $this->pools[$poolKey] = $this->createPool($baseUri, $clientConfig);
        }

        $pool = $this->pools[$poolKey];
        $connection = $pool->acquire();

        if ($connection === null) {
            return $this->createStandardClient($baseUri, $clientConfig);
        }

        return $this->createPooledClient($connection, $pool, $clientConfig);
    }

    public function createPool(string $baseUri, array $config = []): ConnectionPool
    {
        $poolConfig = array_merge($this->config, $config);
        $poolConfig['connection_defaults']['base_uri'] = $baseUri;

        return new ConnectionPool($poolConfig, $this->logger, $this->metrics);
    }

    public function getPool(string $baseUri): ?ConnectionPool
    {
        $poolKey = $this->getPoolKey($baseUri);
        return $this->pools[$poolKey] ?? null;
    }

    public function hasPool(string $baseUri): bool
    {
        $poolKey = $this->getPoolKey($baseUri);
        return isset($this->pools[$poolKey]);
    }

    public function removePool(string $baseUri): self
    {
        $poolKey = $this->getPoolKey($baseUri);
        unset($this->pools[$poolKey]);

        return $this;
    }

    public function clearPools(): self
    {
        $this->pools = [];
        return $this;
    }

    public function getAllPools(): array
    {
        return $this->pools;
    }

    public function getPoolCount(): int
    {
        return count($this->pools);
    }

    public function getTotalConnections(): int
    {
        return array_sum(array_map(fn($pool) => $pool->getConnectionCount(), $this->pools));
    }

    public function getTotalActiveConnections(): int
    {
        return array_sum(array_map(fn($pool) => $pool->getActiveCount(), $this->pools));
    }

    public function getTotalAvailableConnections(): int
    {
        return array_sum(array_map(fn($pool) => $pool->getAvailableCount(), $this->pools));
    }

    public function setDefaultStrategy(PoolStrategy $strategy): self
    {
        $this->config['default_strategy'] = $strategy->value;

        foreach ($this->pools as $pool) {
            $pool->setStrategy($strategy);
        }

        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function enable(): self
    {
        $this->enabled = true;

        foreach ($this->pools as $pool) {
            $pool->enable();
        }

        return $this;
    }

    public function disable(): self
    {
        $this->enabled = false;

        foreach ($this->pools as $pool) {
            $pool->disable();
        }

        return $this;
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function setConfig(array $config): self
    {
        $this->config = array_merge($this->config, $config);
        return $this;
    }

    public function getStatistics(): array
    {
        $totalStats = [
            'manager_enabled' => $this->enabled,
            'total_pools' => $this->getPoolCount(),
            'total_connections' => $this->getTotalConnections(),
            'total_active_connections' => $this->getTotalActiveConnections(),
            'total_available_connections' => $this->getTotalAvailableConnections(),
            'config' => $this->config,
            'pools' => [],
        ];

        foreach ($this->pools as $poolKey => $pool) {
            $totalStats['pools'][$poolKey] = $pool->getStatistics();
        }

        return $totalStats;
    }

    public function performMaintenance(): self
    {
        foreach ($this->pools as $pool) {
            // Trigger maintenance by attempting to acquire and immediately release a connection
            $connection = $pool->acquire();
            if ($connection !== null) {
                $pool->release($connection);
            }
        }

        return $this;
    }

    private function createStandardClient(string $baseUri, array $config): GuzzleClient
    {
        $clientConfig = array_merge([
            'base_uri' => $baseUri,
            'handler' => HandlerStack::create(),
        ], $config);

        return new GuzzleClient($clientConfig);
    }

    private function createPooledClient(Connection $connection, ConnectionPool $pool, array $config): GuzzleClient
    {
        $handlerStack = $this->createPooledHandlerStack($connection, $pool);

        $clientConfig = array_merge($connection->getClient()->getConfig(), [
            'handler' => $handlerStack,
        ], $config);

        return new GuzzleClient($clientConfig);
    }

    private function createPooledHandlerStack(Connection $connection, ConnectionPool $pool): HandlerStack
    {
        $stack = clone $this->defaultHandlerStack[0] ?? HandlerStack::create();

        // Add connection pool middleware
        $stack->push($this->createConnectionPoolMiddleware($connection, $pool), 'connection_pool');

        return $stack;
    }

    private function createConnectionPoolMiddleware(Connection $connection, ConnectionPool $pool): callable
    {
        return function (callable $handler) use ($connection, $pool) {
            return function (RequestInterface $request, array $options) use ($handler, $connection, $pool) {
                $startTime = microtime(true);

                try {
                    $promise = $handler($request, $options);

                    return $promise->then(
                        function (ResponseInterface $response) use ($connection, $pool, $startTime) {
                            $duration = (microtime(true) - $startTime) * 1000;
                            $success = $response->getStatusCode() < 400;

                            $connection->recordRequest($duration, $success);
                            $pool->release($connection);

                            return $response;
                        },
                        function (\Exception $reason) use ($connection, $pool, $startTime) {
                            $duration = (microtime(true) - $startTime) * 1000;

                            $connection->recordRequest($duration, false);
                            $pool->release($connection);

                            throw $reason;
                        }
                    );
                } catch (\Throwable $e) {
                    $duration = (microtime(true) - $startTime) * 1000;
                    $connection->recordRequest($duration, false);
                    $pool->release($connection);

                    throw $e;
                }
            };
        };
    }

    private function getPoolKey(string $baseUri): string
    {
        $parsed = parse_url($baseUri);
        $host = $parsed['host'] ?? 'unknown';
        $port = $parsed['port'] ?? ($parsed['scheme'] === 'https' ? 443 : 80);
        $scheme = $parsed['scheme'] ?? 'http';

        return "{$scheme}://{$host}:{$port}";
    }

    private function initializeDefaultHandlerStack(): void
    {
        $stack = HandlerStack::create();

        // Add performance monitoring middleware
        if ($this->metrics !== null) {
            $stack->push($this->createMetricsMiddleware(), 'pool_metrics');
        }

        $this->defaultHandlerStack = [$stack];
    }

    private function createMetricsMiddleware(): callable
    {
        return function (callable $handler) {
            return function (RequestInterface $request, array $options) use ($handler) {
                $startTime = microtime(true);

                $this->metrics?->increment(
                    \Wioex\SDK\Enums\MetricType::COUNTER,
                    'connection_pool.requests',
                    1.0,
                    ['uri' => $request->getUri()->getHost()]
                );

                try {
                    $promise = $handler($request, $options);

                    return $promise->then(
                        function (ResponseInterface $response) use ($startTime, $request) {
                            $duration = (microtime(true) - $startTime) * 1000;

                            $this->metrics?->recordLatency(
                                'connection_pool.request_duration',
                                $duration,
                                ['uri' => $request->getUri()->getHost()]
                            );

                            return $response;
                        }
                    );
                } catch (\Throwable $e) {
                    $this->metrics?->recordError(
                        'connection_pool.errors',
                        get_class($e),
                        ['uri' => $request->getUri()->getHost()]
                    );

                    throw $e;
                }
            };
        };
    }

    private function getDefaultConfig(): array
    {
        return [
            'enabled' => true,
            'default_strategy' => 'round_robin',
            'min_connections' => 1,
            'max_connections' => 10,
            'cleanup_interval' => 300,
            'connection_defaults' => [
                'timeout' => 30,
                'connect_timeout' => 10,
                'verify' => true,
            ],
            'connection_config' => [
                'max_age' => 3600,
                'max_requests' => 1000,
                'max_errors' => 10,
            ],
        ];
    }

    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled,
            'config' => $this->config,
            'statistics' => $this->getStatistics(),
        ];
    }

    public function __destruct()
    {
        foreach ($this->pools as $pool) {
            unset($pool);
        }
    }
}
