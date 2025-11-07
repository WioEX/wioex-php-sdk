<?php

declare(strict_types=1);

namespace Wioex\SDK\Monitoring;

use Wioex\SDK\Enums\MetricType;
use Wioex\SDK\Enums\Environment;

class Metrics
{
    private array $metrics = [];
    private array $config;
    private bool $enabled;
    private array $collectors = [];
    private array $exporters = [];
    private ?object $startTime = null;

    public function __construct(array $config = [], bool $enabled = true)
    {
        $this->config = array_merge($this->getDefaultConfig(), $config);
        $this->enabled = $enabled;
        $this->startTime = new \DateTime();

        $this->initializeCollectors();
    }

    public static function create(array $config = []): self
    {
        return new self($config);
    }

    public static function forEnvironment(Environment $environment, array $config = []): self
    {
        $enabled = $environment->shouldEnableMetrics();
        $defaultConfig = [
            'collection_interval' => $environment->getMetricsInterval(),
            'retention_period' => $environment->getMetricsRetention(),
            'export_enabled' => $environment->shouldExportMetrics(),
        ];

        return new self(array_merge($defaultConfig, $config), $enabled);
    }

    public function increment(MetricType $type, string $name, float $value = 1.0, array $tags = []): self
    {
        if (!$this->enabled || !$type->isAccumulative()) {
            return $this;
        }

        $key = $this->createMetricKey($type, $name, $tags);
        $this->metrics[$key] = ($this->metrics[$key] ?? 0) + $value;

        $this->recordMetricEvent($type, $name, $value, $tags, 'increment');
        return $this;
    }

    public function gauge(MetricType $type, string $name, float $value, array $tags = []): self
    {
        if (!$this->enabled || !$type->isInstantaneous()) {
            return $this;
        }

        $key = $this->createMetricKey($type, $name, $tags);
        $this->metrics[$key] = $value;

        $this->recordMetricEvent($type, $name, $value, $tags, 'gauge');
        return $this;
    }

    public function histogram(MetricType $type, string $name, float $value, array $tags = []): self
    {
        if (!$this->enabled || !$type->requiresAggregation()) {
            return $this;
        }

        $key = $this->createMetricKey($type, $name, $tags);

        if (!isset($this->metrics[$key])) {
            $this->metrics[$key] = [
                'count' => 0,
                'sum' => 0,
                'min' => PHP_FLOAT_MAX,
                'max' => PHP_FLOAT_MIN,
                'values' => [],
            ];
        }

        $this->metrics[$key]['count']++;
        $this->metrics[$key]['sum'] += $value;
        $this->metrics[$key]['min'] = min($this->metrics[$key]['min'], $value);
        $this->metrics[$key]['max'] = max($this->metrics[$key]['max'], $value);

        if (count($this->metrics[$key]['values']) < $this->config['histogram_bucket_size']) {
            $this->metrics[$key]['values'][] = $value;
        }

        $this->recordMetricEvent($type, $name, $value, $tags, 'histogram');
        return $this;
    }

    public function timer(MetricType $type, string $name, array $tags = []): Timer
    {
        return new Timer($this, $type, $name, $tags);
    }

    public function recordLatency(string $operation, float $latencyMs, array $tags = []): self
    {
        $this->histogram(MetricType::LATENCY, $operation, $latencyMs, $tags);

        $thresholds = MetricType::LATENCY->getDefaultThresholds();
        if ($latencyMs > $thresholds['critical']) {
            $this->increment(MetricType::ERROR_RATE, $operation . '_slow', 1.0, array_merge($tags, ['severity' => 'critical']));
        } elseif ($latencyMs > $thresholds['warning']) {
            $this->increment(MetricType::ERROR_RATE, $operation . '_slow', 1.0, array_merge($tags, ['severity' => 'warning']));
        }

        return $this;
    }

    public function recordThroughput(string $operation, float $opsPerSecond, array $tags = []): self
    {
        return $this->gauge(MetricType::THROUGHPUT, $operation, $opsPerSecond, $tags);
    }

    public function recordError(string $operation, string $errorType = 'general', array $tags = []): self
    {
        $this->increment(MetricType::ERROR_RATE, $operation . '_errors', 1.0, array_merge($tags, ['error_type' => $errorType]));
        $this->increment(MetricType::REQUEST_COUNT, $operation . '_total', 1.0, array_merge($tags, ['status' => 'error']));

        return $this;
    }

    public function recordSuccess(string $operation, array $tags = []): self
    {
        $this->increment(MetricType::REQUEST_COUNT, $operation . '_total', 1.0, array_merge($tags, ['status' => 'success']));
        return $this;
    }

    public function recordCacheHit(string $cacheKey, array $tags = []): self
    {
        $this->increment(MetricType::CACHE_HIT_RATE, 'cache_hits', 1.0, array_merge($tags, ['key' => $cacheKey]));
        return $this;
    }

    public function recordCacheMiss(string $cacheKey, array $tags = []): self
    {
        $this->increment(MetricType::CACHE_HIT_RATE, 'cache_misses', 1.0, array_merge($tags, ['key' => $cacheKey]));
        return $this;
    }

    public function recordMemoryUsage(array $tags = []): self
    {
        $usage = memory_get_usage(true);
        $peak = memory_get_peak_usage(true);

        $this->gauge(MetricType::MEMORY_USAGE, 'current_usage', $usage, $tags);
        $this->gauge(MetricType::MEMORY_USAGE, 'peak_usage', $peak, $tags);

        return $this;
    }

    public function recordResponseSize(int $bytes, array $tags = []): self
    {
        return $this->histogram(MetricType::RESPONSE_SIZE, 'response_bytes', $bytes, $tags);
    }

    public function get(MetricType $type, string $name, array $tags = []): mixed
    {
        $key = $this->createMetricKey($type, $name, $tags);
        return $this->metrics[$key] ?? null;
    }

    public function getAll(): array
    {
        return $this->metrics;
    }

    public function getByCategory(string $category): array
    {
        $categoryMetrics = [];
        $categoryTypes = array_filter(
            MetricType::cases(),
            fn(MetricType $type) => $type->getCategory() === $category
        );

        foreach ($this->metrics as $key => $value) {
            foreach ($categoryTypes as $type) {
                if (strpos($key, $type->value . ':') === 0) {
                    $categoryMetrics[$key] = $value;
                    break;
                }
            }
        }

        return $categoryMetrics;
    }

    public function getPerformanceMetrics(): array
    {
        return $this->getByCategory('performance');
    }

    public function getSystemMetrics(): array
    {
        return $this->getByCategory('system');
    }

    public function getBasicMetrics(): array
    {
        return $this->getByCategory('basic');
    }

    public function calculatePercentiles(MetricType $type, string $name, array $percentiles = [50, 95, 99], array $tags = []): array
    {
        if (!$type->requiresAggregation()) {
            return [];
        }

        $key = $this->createMetricKey($type, $name, $tags);
        $metric = $this->metrics[$key] ?? null;

        if (!$metric || empty($metric['values'])) {
            return [];
        }

        $values = $metric['values'];
        sort($values);
        $count = count($values);

        $result = [];
        foreach ($percentiles as $percentile) {
            $index = (int) ceil(($percentile / 100) * $count) - 1;
            $index = max(0, min($index, $count - 1));
            $result["p{$percentile}"] = $values[$index];
        }

        return $result;
    }

    public function getAggregatedMetrics(MetricType $type, string $name, array $tags = []): array
    {
        $key = $this->createMetricKey($type, $name, $tags);
        $metric = $this->metrics[$key] ?? null;

        if (!$metric) {
            return [];
        }

        if (is_array($metric) && isset($metric['count'])) {
            return [
                'count' => $metric['count'],
                'sum' => $metric['sum'],
                'avg' => $metric['count'] > 0 ? $metric['sum'] / $metric['count'] : 0,
                'min' => $metric['min'] === PHP_FLOAT_MAX ? 0 : $metric['min'],
                'max' => $metric['max'] === PHP_FLOAT_MIN ? 0 : $metric['max'],
                'percentiles' => $this->calculatePercentiles($type, $name, [50, 95, 99], $tags),
            ];
        }

        return ['value' => $metric];
    }

    public function addCollector(callable $collector, string $name = ''): self
    {
        $this->collectors[$name ?: uniqid('collector_')] = $collector;
        return $this;
    }

    public function addExporter(callable $exporter, string $name = ''): self
    {
        $this->exporters[$name ?: uniqid('exporter_')] = $exporter;
        return $this;
    }

    public function collect(): self
    {
        if (!$this->enabled) {
            return $this;
        }

        foreach ($this->collectors as $collector) {
            try {
                $collector($this);
            } catch (\Throwable $e) {
                // Report metrics collector error and continue with others
                if (class_exists('\Wioex\SDK\ErrorReporter')) {
                    (new \Wioex\SDK\ErrorReporter([]))->report($e, [
                        'context' => 'metrics_collector_error',
                        'collector_count' => count($this->collectors)
                    ]);
                }
            }
        }

        return $this;
    }

    public function export(): array
    {
        $exportData = [
            'timestamp' => time(),
            'uptime_seconds' => time() - $this->startTime->getTimestamp(),
            'metrics' => $this->getAll(),
            'aggregated' => $this->getAggregatedSummary(),
            'performance_summary' => $this->getPerformanceSummary(),
        ];

        foreach ($this->exporters as $exporter) {
            try {
                $exporter($exportData, $this);
            } catch (\Throwable $e) {
                // Report metrics exporter error and continue with others
                if (class_exists('\Wioex\SDK\ErrorReporter')) {
                    (new \Wioex\SDK\ErrorReporter([]))->report($e, [
                        'context' => 'metrics_exporter_error',
                        'exporter_count' => count($this->exporters)
                    ]);
                }
            }
        }

        return $exportData;
    }

    public function reset(): self
    {
        $this->metrics = [];
        return $this;
    }

    public function clear(MetricType $type = null, string $name = '', array $tags = []): self
    {
        if ($type === null) {
            $this->metrics = [];
            return $this;
        }

        $key = $this->createMetricKey($type, $name, $tags);
        unset($this->metrics[$key]);

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

    public function getStatistics(): array
    {
        return [
            'enabled' => $this->enabled,
            'metrics_count' => count($this->metrics),
            'collectors_count' => count($this->collectors),
            'exporters_count' => count($this->exporters),
            'uptime_seconds' => time() - $this->startTime->getTimestamp(),
            'memory_usage' => memory_get_usage(true),
            'config' => $this->config,
        ];
    }

    private function createMetricKey(MetricType $type, string $name, array $tags): string
    {
        $tagString = '';
        if (!empty($tags)) {
            ksort($tags);
            $tagPairs = array_map(fn($k, $v) => "{$k}={$v}", array_keys($tags), $tags);
            $tagString = ',' . implode(',', $tagPairs);
        }

        return "{$type->value}:{$name}{$tagString}";
    }

    private function recordMetricEvent(MetricType $type, string $name, float $value, array $tags, string $operation): void
    {
        if (!$this->config['track_events']) {
            return;
        }

        $event = [
            'timestamp' => microtime(true),
            'type' => $type,
            'name' => $name,
            'value' => $value,
            'tags' => $tags,
            'operation' => $operation,
        ];

        if (isset($this->config['event_callback']) && is_callable($this->config['event_callback'])) {
            $this->config['event_callback']($event);
        }
    }

    private function getDefaultConfig(): array
    {
        return [
            'histogram_bucket_size' => 1000,
            'collection_interval' => 60,
            'retention_period' => 3600,
            'export_enabled' => true,
            'track_events' => false,
            'event_callback' => null,
        ];
    }

    private function initializeCollectors(): void
    {
        $this->addCollector([$this, 'collectSystemMetrics'], 'system');
        $this->addCollector([$this, 'collectPerformanceMetrics'], 'performance');
    }

    private function collectSystemMetrics(): void
    {
        $this->recordMemoryUsage();

        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            $this->gauge(MetricType::CPU_USAGE, 'load_1min', $load[0] ?? 0);
            $this->gauge(MetricType::CPU_USAGE, 'load_5min', $load[1] ?? 0);
            $this->gauge(MetricType::CPU_USAGE, 'load_15min', $load[2] ?? 0);
        }
    }

    private function collectPerformanceMetrics(): void
    {
        $uptime = time() - $this->startTime->getTimestamp();
        $this->gauge(MetricType::GAUGE, 'uptime_seconds', $uptime);
    }

    private function getAggregatedSummary(): array
    {
        $summary = [];
        $types = MetricType::cases();

        foreach ($types as $type) {
            $categoryMetrics = $this->getByCategory($type->getCategory());
            if (!empty($categoryMetrics)) {
                $summary[$type->getCategory()][$type->value] = count($categoryMetrics);
            }
        }

        return $summary;
    }

    private function getPerformanceSummary(): array
    {
        $performance = $this->getPerformanceMetrics();
        $summary = [
            'total_requests' => 0,
            'total_errors' => 0,
            'avg_latency' => 0,
            'error_rate' => 0,
        ];

        foreach ($performance as $key => $value) {
            if (strpos($key, 'request_count:') === 0) {
                $summary['total_requests'] += is_array($value) ? ($value['count'] ?? 0) : $value;
            } elseif (strpos($key, 'error_rate:') === 0) {
                $summary['total_errors'] += is_array($value) ? ($value['count'] ?? 0) : $value;
            } elseif (strpos($key, 'latency:') === 0 && is_array($value)) {
                $summary['avg_latency'] = $value['avg'] ?? 0;
            }
        }

        $summary['error_rate'] = $summary['total_requests'] > 0
            ? ($summary['total_errors'] / $summary['total_requests']) * 100
            : 0;

        return $summary;
    }
}
