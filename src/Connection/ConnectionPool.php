<?php

declare(strict_types=1);

namespace Wioex\SDK\Connection;

use GuzzleHttp\Client as GuzzleClient;
use Wioex\SDK\Enums\ConnectionState;
use Wioex\SDK\Enums\PoolStrategy;
use Wioex\SDK\Enums\Environment;
use Wioex\SDK\Logging\Logger;
use Wioex\SDK\Monitoring\Metrics;

class ConnectionPool
{
    private array $connections = [];
    private array $config;
    private PoolStrategy $strategy;
    private int $currentIndex = 0;
    private bool $enabled = true;
    private ?Logger $logger = null;
    private ?Metrics $metrics = null;
    private array $statistics = [];
    private float $lastCleanup = 0;

    public function __construct(array $config = [], Logger $logger = null, Metrics $metrics = null)
    {
        $this->config = array_merge($this->getDefaultConfig(), $config);
        $this->strategy = PoolStrategy::fromString($this->config['strategy'] ?? 'round_robin');
        $this->enabled = $this->config['enabled'] ?? true;
        $this->logger = $logger;
        $this->metrics = $metrics;
        $this->lastCleanup = microtime(true);

        $this->initializePool();
    }

    public static function create(array $config = []): self
    {
        return new self($config);
    }

    public static function forEnvironment(Environment $environment, array $config = []): self
    {
        $defaultConfig = [
            'enabled' => $environment->shouldEnableConnectionPooling(),
            'min_connections' => $environment->getMinConnections(),
            'max_connections' => $environment->getMaxConnections(),
            'strategy' => $environment->getDefaultPoolStrategy(),
            'cleanup_interval' => $environment->getCleanupInterval(),
        ];

        return new self(array_merge($defaultConfig, $config));
    }

    public function acquire(): ?Connection
    {
        if (!$this->enabled) {
            return $this->createNewConnection();
        }

        $this->performMaintenance();

        $connection = $this->selectConnection();

        if ($connection === null) {
            $connection = $this->expandPool();
        }

        if ($connection !== null && $connection->acquire()) {
            $this->recordAcquisition($connection);
            return $connection;
        }

        return null;
    }

    public function release(Connection $connection): self
    {
        if (!$this->enabled) {
            return $this;
        }

        $connection->release();
        $this->recordRelease($connection);

        return $this;
    }

    public function remove(Connection $connection): self
    {
        $connectionId = $connection->getId();

        if (isset($this->connections[$connectionId])) {
            $connection->close();
            unset($this->connections[$connectionId]);
            $this->recordRemoval($connection);
        }

        return $this;
    }

    public function addConnection(Connection $connection): self
    {
        if (count($this->connections) >= $this->config['max_connections']) {
            throw new \RuntimeException('Connection pool is at maximum capacity');
        }

        $this->connections[$connection->getId()] = $connection;
        $this->recordAddition($connection);

        return $this;
    }

    public function createConnection(array $clientConfig = []): Connection
    {
        $config = array_merge($this->config['connection_defaults'] ?? [], $clientConfig);
        $client = new GuzzleClient($config);
        $id = uniqid('conn_', true);

        return new Connection($id, $client, $this->config['connection_config'] ?? []);
    }

    private function createNewConnection(): Connection
    {
        return $this->createConnection();
    }

    private function selectConnection(): ?Connection
    {
        $availableConnections = $this->getAvailableConnections();

        if (empty($availableConnections)) {
            return null;
        }

        return match ($this->strategy) {
            PoolStrategy::ROUND_ROBIN => $this->selectRoundRobin($availableConnections),
            PoolStrategy::LEAST_CONNECTIONS => $this->selectLeastConnections($availableConnections),
            PoolStrategy::WEIGHTED_ROUND_ROBIN => $this->selectWeightedRoundRobin($availableConnections),
            PoolStrategy::RANDOM => $this->selectRandom($availableConnections),
            PoolStrategy::FIFO => $this->selectFifo($availableConnections),
            PoolStrategy::LIFO => $this->selectLifo($availableConnections),
            PoolStrategy::PRIORITY_BASED => $this->selectPriorityBased($availableConnections),
            PoolStrategy::LEAST_RECENTLY_USED => $this->selectLeastRecentlyUsed($availableConnections),
            PoolStrategy::MOST_RECENTLY_USED => $this->selectMostRecentlyUsed($availableConnections),
            PoolStrategy::ADAPTIVE => $this->selectAdaptive($availableConnections),
        };
    }

    private function selectRoundRobin(array $connections): Connection
    {
        $connectionList = array_values($connections);
        $connection = $connectionList[$this->currentIndex % count($connectionList)];
        $this->currentIndex = ($this->currentIndex + 1) % count($connectionList);

        return $connection;
    }

    private function selectLeastConnections(array $connections): Connection
    {
        return array_reduce($connections, function ($carry, $connection) {
            if ($carry === null || $connection->getRequestCount() < $carry->getRequestCount()) {
                return $connection;
            }
            return $carry;
        });
    }

    private function selectWeightedRoundRobin(array $connections): Connection
    {
        $totalWeight = array_sum(array_map(fn($c) => $c->getWeight(), $connections));
        $random = mt_rand() / mt_getrandmax() * $totalWeight;

        $currentWeight = 0;
        foreach ($connections as $connection) {
            $currentWeight += $connection->getWeight();
            if ($random <= $currentWeight) {
                return $connection;
            }
        }

        return array_values($connections)[0]; // Fallback
    }

    private function selectRandom(array $connections): Connection
    {
        $keys = array_keys($connections);
        $randomKey = $keys[array_rand($keys)];

        return $connections[$randomKey];
    }

    private function selectFifo(array $connections): Connection
    {
        return array_reduce($connections, function ($carry, $connection) {
            if ($carry === null || $connection->getCreatedAt() < $carry->getCreatedAt()) {
                return $connection;
            }
            return $carry;
        });
    }

    private function selectLifo(array $connections): Connection
    {
        return array_reduce($connections, function ($carry, $connection) {
            if ($carry === null || $connection->getCreatedAt() > $carry->getCreatedAt()) {
                return $connection;
            }
            return $carry;
        });
    }

    private function selectPriorityBased(array $connections): Connection
    {
        return array_reduce($connections, function ($carry, $connection) {
            if ($carry === null || $connection->getPriority() > $carry->getPriority()) {
                return $connection;
            }
            return $carry;
        });
    }

    private function selectLeastRecentlyUsed(array $connections): Connection
    {
        return array_reduce($connections, function ($carry, $connection) {
            if ($carry === null || $connection->getLastUsedAt() < $carry->getLastUsedAt()) {
                return $connection;
            }
            return $carry;
        });
    }

    private function selectMostRecentlyUsed(array $connections): Connection
    {
        return array_reduce($connections, function ($carry, $connection) {
            if ($carry === null || $connection->getLastUsedAt() > $carry->getLastUsedAt()) {
                return $connection;
            }
            return $carry;
        });
    }

    private function selectAdaptive(array $connections): Connection
    {
        // Adaptive strategy combines multiple factors
        $bestConnection = null;
        $bestScore = -1;

        foreach ($connections as $connection) {
            $score = $this->calculateAdaptiveScore($connection);
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestConnection = $connection;
            }
        }

        return $bestConnection ?? array_values($connections)[0];
    }

    private function calculateAdaptiveScore(Connection $connection): float
    {
        $healthScore = $connection->getHealthScore();
        $errorRate = $connection->getErrorRate();
        $avgResponseTime = $connection->getAverageRequestTime();
        $idleTime = $connection->getIdleTime();

        // Normalize and weight factors
        $score = ($healthScore * 0.4) - ($errorRate * 0.3) - (min($avgResponseTime / 1000, 10) * 0.2) + (min($idleTime / 60, 5) * 0.1);

        return max(0, $score);
    }

    private function expandPool(): ?Connection
    {
        if (count($this->connections) >= $this->config['max_connections']) {
            return null;
        }

        $connection = $this->createConnection();
        $this->addConnection($connection);

        return $connection;
    }

    private function initializePool(): void
    {
        $minConnections = $this->config['min_connections'] ?? 1;

        for ($i = 0; $i < $minConnections; $i++) {
            $connection = $this->createConnection();
            $this->connections[$connection->getId()] = $connection;
        }
    }

    private function performMaintenance(): void
    {
        $now = microtime(true);
        $cleanupInterval = $this->config['cleanup_interval'] ?? 300; // 5 minutes

        if (($now - $this->lastCleanup) < $cleanupInterval) {
            return;
        }

        $this->cleanup();
        $this->ensureMinimumConnections();
        $this->updateStatistics();
        $this->lastCleanup = $now;
    }

    private function cleanup(): void
    {
        $removed = 0;
        $connectionsToRemove = [];

        foreach ($this->connections as $connection) {
            if ($connection->shouldBeRemoved()) {
                $connectionsToRemove[] = $connection;
            }
        }

        foreach ($connectionsToRemove as $connection) {
            $this->remove($connection);
            $removed++;
        }

        if ($removed > 0 && $this->logger !== null) {
            $this->logger->debug("Connection pool cleanup removed {$removed} connections");
        }
    }

    private function ensureMinimumConnections(): void
    {
        $minConnections = $this->config['min_connections'] ?? 1;
        $currentCount = count($this->connections);

        if ($currentCount < $minConnections) {
            $needed = $minConnections - $currentCount;

            for ($i = 0; $i < $needed; $i++) {
                if (count($this->connections) < $this->config['max_connections']) {
                    $connection = $this->createConnection();
                    $this->addConnection($connection);
                }
            }
        }
    }

    private function getAvailableConnections(): array
    {
        return array_filter(
            $this->connections,
            fn(Connection $conn) => $conn->isAvailable()
        );
    }

    public function getConnections(): array
    {
        return $this->connections;
    }

    public function getConnectionCount(): int
    {
        return count($this->connections);
    }

    public function getAvailableCount(): int
    {
        return count($this->getAvailableConnections());
    }

    public function getActiveCount(): int
    {
        return count(array_filter(
            $this->connections,
            fn(Connection $conn) => $conn->isInUse()
        ));
    }

    public function getStrategy(): PoolStrategy
    {
        return $this->strategy;
    }

    public function setStrategy(PoolStrategy $strategy): self
    {
        $this->strategy = $strategy;
        $this->currentIndex = 0; // Reset round-robin index

        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function enable(): self
    {
        $this->enabled = true;
        return $this;
    }

    public function disable(): self
    {
        $this->enabled = false;
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
        $this->updateStatistics();
        return $this->statistics;
    }

    private function updateStatistics(): void
    {
        $connections = $this->connections;

        $this->statistics = [
            'total_connections' => count($connections),
            'available_connections' => $this->getAvailableCount(),
            'active_connections' => $this->getActiveCount(),
            'strategy' => $this->strategy->value,
            'enabled' => $this->enabled,
            'total_requests' => array_sum(array_map(fn($c) => $c->getRequestCount(), $connections)),
            'total_errors' => array_sum(array_map(fn($c) => $c->getErrorCount(), $connections)),
            'average_error_rate' => $this->calculateAverageErrorRate($connections),
            'average_health_score' => $this->calculateAverageHealthScore($connections),
            'connection_states' => $this->getConnectionStateDistribution($connections),
            'pool_efficiency' => $this->calculatePoolEfficiency(),
            'last_cleanup' => $this->lastCleanup,
            'config' => $this->config,
        ];
    }

    private function calculateAverageErrorRate(array $connections): float
    {
        if (empty($connections)) {
            return 0.0;
        }

        $totalErrorRate = array_sum(array_map(fn($c) => $c->getErrorRate(), $connections));
        return $totalErrorRate / count($connections);
    }

    private function calculateAverageHealthScore(array $connections): float
    {
        if (empty($connections)) {
            return 0.0;
        }

        $totalHealthScore = array_sum(array_map(fn($c) => $c->getHealthScore(), $connections));
        return $totalHealthScore / count($connections);
    }

    private function getConnectionStateDistribution(array $connections): array
    {
        $distribution = [];

        foreach (ConnectionState::cases() as $state) {
            $distribution[$state->value] = 0;
        }

        foreach ($connections as $connection) {
            $distribution[$connection->getState()->value]++;
        }

        return $distribution;
    }

    private function calculatePoolEfficiency(): float
    {
        $totalConnections = count($this->connections);
        $activeConnections = $this->getActiveCount();

        if ($totalConnections === 0) {
            return 0.0;
        }

        return ($activeConnections / $totalConnections) * 100;
    }

    private function recordAcquisition(Connection $connection): void
    {
        $this->metrics?->increment(\Wioex\SDK\Enums\MetricType::COUNTER, 'connection_pool.acquisitions', 1.0, [
            'strategy' => $this->strategy->value,
            'connection_id' => $connection->getId(),
        ]);
    }

    private function recordRelease(Connection $connection): void
    {
        $this->metrics?->increment(\Wioex\SDK\Enums\MetricType::COUNTER, 'connection_pool.releases', 1.0, [
            'strategy' => $this->strategy->value,
            'connection_id' => $connection->getId(),
        ]);
    }

    private function recordAddition(Connection $connection): void
    {
        $this->metrics?->increment(\Wioex\SDK\Enums\MetricType::COUNTER, 'connection_pool.additions', 1.0, [
            'strategy' => $this->strategy->value,
        ]);
    }

    private function recordRemoval(Connection $connection): void
    {
        $this->metrics?->increment(\Wioex\SDK\Enums\MetricType::COUNTER, 'connection_pool.removals', 1.0, [
            'strategy' => $this->strategy->value,
            'reason' => $connection->shouldBeRemoved() ? 'expired' : 'manual',
        ]);
    }

    private function getDefaultConfig(): array
    {
        return [
            'enabled' => true,
            'min_connections' => 1,
            'max_connections' => 10,
            'strategy' => 'round_robin',
            'cleanup_interval' => 300, // 5 minutes
            'connection_defaults' => [
                'timeout' => 30,
                'connect_timeout' => 10,
            ],
            'connection_config' => [
                'max_age' => 3600,      // 1 hour
                'max_requests' => 1000,
                'max_errors' => 10,
            ],
        ];
    }

    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled,
            'strategy' => $this->strategy->toArray(),
            'config' => $this->config,
            'statistics' => $this->getStatistics(),
            'connections' => array_map(fn($c) => $c->toArray(), $this->connections),
        ];
    }

    public function __destruct()
    {
        foreach ($this->connections as $connection) {
            $connection->close();
        }
    }
}
