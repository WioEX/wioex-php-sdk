<?php

declare(strict_types=1);

namespace Wioex\SDK\Enums;

enum MetricType: string
{
    case COUNTER = 'counter';
    case GAUGE = 'gauge';
    case HISTOGRAM = 'histogram';
    case TIMER = 'timer';
    case LATENCY = 'latency';
    case THROUGHPUT = 'throughput';
    case ERROR_RATE = 'error_rate';
    case CACHE_HIT_RATE = 'cache_hit_rate';
    case REQUEST_COUNT = 'request_count';
    case RESPONSE_SIZE = 'response_size';
    case MEMORY_USAGE = 'memory_usage';
    case CPU_USAGE = 'cpu_usage';

    public function getDescription(): string
    {
        return match ($this) {
            self::COUNTER => 'Incrementing counter metric',
            self::GAUGE => 'Current value gauge metric',
            self::HISTOGRAM => 'Distribution of values over time',
            self::TIMER => 'Timing measurements',
            self::LATENCY => 'Request/response latency tracking',
            self::THROUGHPUT => 'Operations per second',
            self::ERROR_RATE => 'Percentage of failed requests',
            self::CACHE_HIT_RATE => 'Cache hit/miss ratio',
            self::REQUEST_COUNT => 'Total number of requests',
            self::RESPONSE_SIZE => 'Response payload size',
            self::MEMORY_USAGE => 'Memory consumption',
            self::CPU_USAGE => 'CPU utilization',
        };
    }

    public function getUnit(): string
    {
        return match ($this) {
            self::COUNTER, self::REQUEST_COUNT => 'count',
            self::GAUGE => 'value',
            self::HISTOGRAM => 'distribution',
            self::TIMER, self::LATENCY => 'milliseconds',
            self::THROUGHPUT => 'ops/sec',
            self::ERROR_RATE, self::CACHE_HIT_RATE => 'percentage',
            self::RESPONSE_SIZE => 'bytes',
            self::MEMORY_USAGE => 'bytes',
            self::CPU_USAGE => 'percentage',
        };
    }

    public function getCategory(): string
    {
        return match ($this) {
            self::COUNTER, self::GAUGE, self::HISTOGRAM, self::TIMER => 'basic',
            self::LATENCY, self::THROUGHPUT, self::REQUEST_COUNT => 'performance',
            self::ERROR_RATE => 'reliability',
            self::CACHE_HIT_RATE => 'caching',
            self::RESPONSE_SIZE => 'network',
            self::MEMORY_USAGE, self::CPU_USAGE => 'system',
        };
    }

    public function isTimeBasedMetric(): bool
    {
        return match ($this) {
            self::TIMER, self::LATENCY, self::THROUGHPUT => true,
            default => false,
        };
    }

    public function isAccumulative(): bool
    {
        return match ($this) {
            self::COUNTER, self::REQUEST_COUNT => true,
            default => false,
        };
    }

    public function isInstantaneous(): bool
    {
        return match ($this) {
            self::GAUGE, self::MEMORY_USAGE, self::CPU_USAGE => true,
            default => false,
        };
    }

    public function requiresAggregation(): bool
    {
        return match ($this) {
            self::HISTOGRAM, self::LATENCY, self::THROUGHPUT, self::ERROR_RATE, self::CACHE_HIT_RATE => true,
            default => false,
        };
    }

    public function getDefaultThresholds(): array
    {
        return match ($this) {
            self::LATENCY => [
                'warning' => 1000,   // 1 second
                'critical' => 5000,  // 5 seconds
            ],
            self::ERROR_RATE => [
                'warning' => 5,      // 5%
                'critical' => 10,    // 10%
            ],
            self::CACHE_HIT_RATE => [
                'warning' => 80,     // 80%
                'critical' => 60,    // 60%
            ],
            self::MEMORY_USAGE => [
                'warning' => 75,     // 75%
                'critical' => 90,    // 90%
            ],
            self::CPU_USAGE => [
                'warning' => 70,     // 70%
                'critical' => 85,    // 85%
            ],
            default => [],
        };
    }

    public function getStorageRetention(): int
    {
        return match ($this) {
            self::COUNTER, self::REQUEST_COUNT => 86400 * 30,        // 30 days
            self::GAUGE, self::MEMORY_USAGE, self::CPU_USAGE => 86400 * 7,  // 7 days
            self::LATENCY, self::THROUGHPUT => 86400 * 14,           // 14 days
            self::ERROR_RATE, self::CACHE_HIT_RATE => 86400 * 14,    // 14 days
            self::HISTOGRAM => 86400 * 7,                            // 7 days
            self::TIMER => 86400 * 3,                                // 3 days
            self::RESPONSE_SIZE => 86400 * 7,                        // 7 days
        };
    }

    public function getAggregationMethods(): array
    {
        return match ($this) {
            self::COUNTER, self::REQUEST_COUNT => ['sum', 'rate'],
            self::GAUGE => ['avg', 'min', 'max', 'current'],
            self::HISTOGRAM => ['avg', 'min', 'max', 'p50', 'p95', 'p99'],
            self::TIMER, self::LATENCY => ['avg', 'min', 'max', 'p50', 'p95', 'p99'],
            self::THROUGHPUT => ['avg', 'min', 'max'],
            self::ERROR_RATE, self::CACHE_HIT_RATE => ['avg', 'current'],
            self::RESPONSE_SIZE => ['avg', 'min', 'max', 'total'],
            self::MEMORY_USAGE, self::CPU_USAGE => ['avg', 'min', 'max', 'current'],
        };
    }

    public static function fromString(string $type): self
    {
        $type = strtolower(str_replace(['-', '_', ' '], '_', $type));

        return match ($type) {
            'counter' => self::COUNTER,
            'gauge' => self::GAUGE,
            'histogram' => self::HISTOGRAM,
            'timer' => self::TIMER,
            'latency' => self::LATENCY,
            'throughput' => self::THROUGHPUT,
            'error_rate', 'errorrate' => self::ERROR_RATE,
            'cache_hit_rate', 'cachehitrate' => self::CACHE_HIT_RATE,
            'request_count', 'requestcount' => self::REQUEST_COUNT,
            'response_size', 'responsesize' => self::RESPONSE_SIZE,
            'memory_usage', 'memoryusage' => self::MEMORY_USAGE,
            'cpu_usage', 'cpuusage' => self::CPU_USAGE,
            default => throw new \InvalidArgumentException("Invalid metric type: {$type}"),
        };
    }

    public static function getByCategory(string $category): array
    {
        return array_filter(
            self::cases(),
            fn(self $metric) => $metric->getCategory() === $category
        );
    }

    public static function getPerformanceMetrics(): array
    {
        return self::getByCategory('performance');
    }

    public static function getSystemMetrics(): array
    {
        return self::getByCategory('system');
    }

    public static function getBasicMetrics(): array
    {
        return self::getByCategory('basic');
    }

    public function toArray(): array
    {
        return [
            'value' => $this->value,
            'name' => $this->name,
            'description' => $this->getDescription(),
            'unit' => $this->getUnit(),
            'category' => $this->getCategory(),
            'is_time_based' => $this->isTimeBasedMetric(),
            'is_accumulative' => $this->isAccumulative(),
            'is_instantaneous' => $this->isInstantaneous(),
            'requires_aggregation' => $this->requiresAggregation(),
            'default_thresholds' => $this->getDefaultThresholds(),
            'storage_retention_seconds' => $this->getStorageRetention(),
            'aggregation_methods' => $this->getAggregationMethods(),
        ];
    }
}
