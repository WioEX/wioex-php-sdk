<?php

declare(strict_types=1);

namespace Wioex\SDK\Enums;

enum PoolStrategy: string
{
    case ROUND_ROBIN = 'round_robin';
    case LEAST_CONNECTIONS = 'least_connections';
    case WEIGHTED_ROUND_ROBIN = 'weighted_round_robin';
    case RANDOM = 'random';
    case FIFO = 'fifo';
    case LIFO = 'lifo';
    case PRIORITY_BASED = 'priority_based';
    case LEAST_RECENTLY_USED = 'least_recently_used';
    case MOST_RECENTLY_USED = 'most_recently_used';
    case ADAPTIVE = 'adaptive';

    public function getDescription(): string
    {
        return match ($this) {
            self::ROUND_ROBIN => 'Distributes connections in circular order',
            self::LEAST_CONNECTIONS => 'Selects connection with fewest active requests',
            self::WEIGHTED_ROUND_ROBIN => 'Round robin with connection weights',
            self::RANDOM => 'Randomly selects available connections',
            self::FIFO => 'First In, First Out - uses oldest available connection',
            self::LIFO => 'Last In, First Out - uses newest available connection',
            self::PRIORITY_BASED => 'Selects connection based on priority score',
            self::LEAST_RECENTLY_USED => 'Uses connection that was idle the longest',
            self::MOST_RECENTLY_USED => 'Uses connection that was most recently active',
            self::ADAPTIVE => 'Dynamically adapts strategy based on performance',
        };
    }

    public function getComplexity(): string
    {
        return match ($this) {
            self::ROUND_ROBIN, self::RANDOM, self::FIFO, self::LIFO => 'low',
            self::LEAST_CONNECTIONS, self::WEIGHTED_ROUND_ROBIN, self::PRIORITY_BASED => 'medium',
            self::LEAST_RECENTLY_USED, self::MOST_RECENTLY_USED => 'medium',
            self::ADAPTIVE => 'high',
        };
    }

    public function getPerformanceImpact(): string
    {
        return match ($this) {
            self::ROUND_ROBIN, self::RANDOM, self::FIFO, self::LIFO => 'minimal',
            self::LEAST_CONNECTIONS, self::WEIGHTED_ROUND_ROBIN => 'low',
            self::PRIORITY_BASED, self::LEAST_RECENTLY_USED, self::MOST_RECENTLY_USED => 'medium',
            self::ADAPTIVE => 'high',
        };
    }

    public function requiresStatistics(): bool
    {
        return match ($this) {
            self::LEAST_CONNECTIONS, self::PRIORITY_BASED, self::LEAST_RECENTLY_USED,
            self::MOST_RECENTLY_USED, self::ADAPTIVE => true,
            default => false,
        };
    }

    public function isLoadBalancing(): bool
    {
        return match ($this) {
            self::ROUND_ROBIN, self::LEAST_CONNECTIONS, self::WEIGHTED_ROUND_ROBIN,
            self::RANDOM, self::ADAPTIVE => true,
            default => false,
        };
    }

    public function isOrderBased(): bool
    {
        return match ($this) {
            self::FIFO, self::LIFO, self::LEAST_RECENTLY_USED, self::MOST_RECENTLY_USED => true,
            default => false,
        };
    }

    public function isDeterministic(): bool
    {
        return match ($this) {
            self::RANDOM, self::ADAPTIVE => false,
            default => true,
        };
    }

    public function getRecommendedFor(): array
    {
        return match ($this) {
            self::ROUND_ROBIN => ['general_purpose', 'uniform_load'],
            self::LEAST_CONNECTIONS => ['varying_request_times', 'load_balancing'],
            self::WEIGHTED_ROUND_ROBIN => ['heterogeneous_backends', 'capacity_aware'],
            self::RANDOM => ['simple_load_distribution', 'testing'],
            self::FIFO => ['connection_warmup', 'fairness'],
            self::LIFO => ['cache_locality', 'recent_connections'],
            self::PRIORITY_BASED => ['quality_of_service', 'tiered_backends'],
            self::LEAST_RECENTLY_USED => ['cache_efficiency', 'connection_freshness'],
            self::MOST_RECENTLY_USED => ['hot_connections', 'locality'],
            self::ADAPTIVE => ['dynamic_environments', 'auto_optimization'],
        };
    }

    public function getDefaultWeight(): float
    {
        return match ($this) {
            self::WEIGHTED_ROUND_ROBIN => 1.0,
            self::PRIORITY_BASED => 100.0,
            default => 1.0,
        };
    }

    public static function getByComplexity(string $complexity): array
    {
        return array_filter(
            self::cases(),
            fn(self $strategy) => $strategy->getComplexity() === $complexity
        );
    }

    public static function getByPerformanceImpact(string $impact): array
    {
        return array_filter(
            self::cases(),
            fn(self $strategy) => $strategy->getPerformanceImpact() === $impact
        );
    }

    public static function getLoadBalancingStrategies(): array
    {
        return array_filter(
            self::cases(),
            fn(self $strategy) => $strategy->isLoadBalancing()
        );
    }

    public static function getOrderBasedStrategies(): array
    {
        return array_filter(
            self::cases(),
            fn(self $strategy) => $strategy->isOrderBased()
        );
    }

    public static function getDeterministicStrategies(): array
    {
        return array_filter(
            self::cases(),
            fn(self $strategy) => $strategy->isDeterministic()
        );
    }

    public static function fromString(string $strategy): self
    {
        $strategy = strtolower(str_replace(['-', '_', ' '], '_', $strategy));

        return match ($strategy) {
            'round_robin', 'roundrobin' => self::ROUND_ROBIN,
            'least_connections', 'leastconnections' => self::LEAST_CONNECTIONS,
            'weighted_round_robin', 'weightedroundrobin' => self::WEIGHTED_ROUND_ROBIN,
            'random' => self::RANDOM,
            'fifo' => self::FIFO,
            'lifo' => self::LIFO,
            'priority_based', 'prioritybased', 'priority' => self::PRIORITY_BASED,
            'least_recently_used', 'leastrecentlyused', 'lru' => self::LEAST_RECENTLY_USED,
            'most_recently_used', 'mostrecentlyused', 'mru' => self::MOST_RECENTLY_USED,
            'adaptive' => self::ADAPTIVE,
            default => throw new \InvalidArgumentException("Invalid pool strategy: {$strategy}"),
        };
    }

    public function toArray(): array
    {
        return [
            'value' => $this->value,
            'name' => $this->name,
            'description' => $this->getDescription(),
            'complexity' => $this->getComplexity(),
            'performance_impact' => $this->getPerformanceImpact(),
            'requires_statistics' => $this->requiresStatistics(),
            'is_load_balancing' => $this->isLoadBalancing(),
            'is_order_based' => $this->isOrderBased(),
            'is_deterministic' => $this->isDeterministic(),
            'recommended_for' => $this->getRecommendedFor(),
            'default_weight' => $this->getDefaultWeight(),
        ];
    }
}
