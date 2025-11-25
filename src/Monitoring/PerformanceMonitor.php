<?php

declare(strict_types=1);

namespace Wioex\SDK\Monitoring;

use Wioex\SDK\Enums\MetricType;
use Wioex\SDK\Enums\Environment;
use Wioex\SDK\Enums\LogLevel;
use Wioex\SDK\Logging\Logger;

class PerformanceMonitor
{
    private Metrics $metrics;
    private Logger $logger;
    private array $config;
    private bool $enabled;
    private array $activeRequests = [];
    private array $thresholds;

    public function __construct(
        ?Metrics $metrics = null,
        ?Logger $logger = null,
        array $config = [],
        bool $enabled = true
    ) {
        $this->metrics = $metrics !== null ? $metrics : new Metrics();
        $this->logger = $logger !== null ? $logger : new Logger();
        $this->config = array_merge($this->getDefaultConfig(), $config);
        $this->enabled = $enabled;
        $this->thresholds = $this->config['thresholds'] ?? [];
    }

    public static function create(array $config = []): self
    {
        return new self(null, null, $config);
    }

    public static function forEnvironment(Environment $environment, array $config = []): self
    {
        $metrics = Metrics::forEnvironment($environment);
        $logger = Logger::forEnvironment($environment);
        $enabled = $environment->shouldEnableMetrics();

        return new self($metrics, $logger, $config, $enabled);
    }

    public function startRequest(string $requestId, string $endpoint, string $method = 'GET', array $context = []): self
    {
        if (!$this->enabled) {
            return $this;
        }

        $this->activeRequests[$requestId] = [
            'start_time' => microtime(true),
            'endpoint' => $endpoint,
            'method' => $method,
            'context' => $context,
            'memory_start' => memory_get_usage(true),
        ];

        $this->metrics->increment(MetricType::REQUEST_COUNT, 'started', 1.0, [
            'endpoint' => $endpoint,
            'method' => $method,
        ]);

        $this->logger->debug("Request started", [
            'request_id' => $requestId,
            'endpoint' => $endpoint,
            'method' => $method,
            'context' => $context,
        ]);

        return $this;
    }

    public function endRequest(string $requestId, int $statusCode = 200, int $responseSize = 0, array $context = []): self
    {
        if (!$this->enabled || !isset($this->activeRequests[$requestId])) {
            return $this;
        }

        $request = $this->activeRequests[$requestId];
        $duration = (microtime(true) - $request['start_time']) * 1000; // milliseconds
        $memoryUsage = memory_get_usage(true) - $request['memory_start'];

        $tags = [
            'endpoint' => $request['endpoint'],
            'method' => $request['method'],
            'status_code' => (string) $statusCode,
            'status_class' => $this->getStatusClass($statusCode),
        ];

        // Record metrics
        $this->metrics->recordLatency($request['endpoint'], $duration, $tags);
        $this->metrics->increment(MetricType::REQUEST_COUNT, 'completed', 1.0, $tags);

        if ($responseSize > 0) {
            $this->metrics->recordResponseSize($responseSize, $tags);
        }

        if ($memoryUsage > 0) {
            $this->metrics->histogram(MetricType::MEMORY_USAGE, 'request_memory', $memoryUsage, $tags);
        }

        // Record success or error
        if ($statusCode >= 200 && $statusCode < 400) {
            $this->metrics->recordSuccess($request['endpoint'], $tags);
        } else {
            $this->metrics->recordError($request['endpoint'], $this->getErrorType($statusCode), $tags);
        }

        // Check thresholds and log warnings
        $this->checkThresholds($request['endpoint'], $duration, $statusCode, $responseSize);

        // Log completion
        $logLevel = $this->getLogLevel($statusCode, $duration);
        $this->logger->log($logLevel, "Request completed", [
            'request_id' => $requestId,
            'endpoint' => $request['endpoint'],
            'method' => $request['method'],
            'status_code' => $statusCode,
            'duration_ms' => $duration,
            'response_size' => $responseSize,
            'memory_usage' => $memoryUsage,
            'context' => array_merge($request['context'], $context),
        ]);

        unset($this->activeRequests[$requestId]);
        return $this;
    }

    public function recordCacheOperation(string $operation, string $key, bool $hit, ?float $duration = null, array $context = []): self
    {
        if (!$this->enabled) {
            return $this;
        }

        $tags = ['operation' => $operation, 'key_type' => $this->getCacheKeyType($key)];

        if ($hit) {
            $this->metrics->recordCacheHit($key, $tags);
        } else {
            $this->metrics->recordCacheMiss($key, $tags);
        }

        if ($duration !== null) {
            $this->metrics->recordLatency('cache_' . $operation, $duration, $tags);
        }

        $this->logger->debug("Cache operation", [
            'operation' => $operation,
            'key' => $key,
            'hit' => $hit,
            'duration_ms' => $duration,
            'context' => $context,
        ]);

        return $this;
    }

    public function recordDatabaseQuery(string $query, float $duration, bool $success = true, array $context = []): self
    {
        if (!$this->enabled) {
            return $this;
        }

        $queryType = $this->getQueryType($query);
        $tags = ['query_type' => $queryType, 'success' => $success ? 'true' : 'false'];

        $this->metrics->recordLatency('database_query', $duration, $tags);
        $this->metrics->increment(MetricType::REQUEST_COUNT, 'database_queries', 1.0, $tags);

        if (!$success) {
            $this->metrics->recordError('database_query', 'sql_error', $tags);
        }

        $logLevel = $success ? LogLevel::DEBUG : LogLevel::ERROR;
        $this->logger->log($logLevel, "Database query executed", [
            'query_type' => $queryType,
            'duration_ms' => $duration,
            'success' => $success,
            'context' => $context,
        ]);

        return $this;
    }

    public function recordExternalApiCall(string $service, string $endpoint, float $duration, int $statusCode, array $context = []): self
    {
        if (!$this->enabled) {
            return $this;
        }

        $tags = [
            'service' => $service,
            'endpoint' => $endpoint,
            'status_code' => (string) $statusCode,
            'status_class' => $this->getStatusClass($statusCode),
        ];

        $this->metrics->recordLatency('external_api', $duration, $tags);
        $this->metrics->increment(MetricType::REQUEST_COUNT, 'external_calls', 1.0, $tags);

        if ($statusCode >= 200 && $statusCode < 400) {
            $this->metrics->recordSuccess('external_api_' . $service, $tags);
        } else {
            $this->metrics->recordError('external_api_' . $service, $this->getErrorType($statusCode), $tags);
        }

        $logLevel = $this->getLogLevel($statusCode, $duration);
        $this->logger->log($logLevel, "External API call", [
            'service' => $service,
            'endpoint' => $endpoint,
            'duration_ms' => $duration,
            'status_code' => $statusCode,
            'context' => $context,
        ]);

        return $this;
    }

    public function recordBusinessMetric(string $metricName, float $value, MetricType $type = MetricType::COUNTER, array $context = []): self
    {
        if (!$this->enabled) {
            return $this;
        }

        $tags = ['business_metric' => 'true'];

        if ($type->isAccumulative()) {
            $this->metrics->increment($type, $metricName, $value, $tags);
        } elseif ($type->isInstantaneous()) {
            $this->metrics->gauge($type, $metricName, $value, $tags);
        } elseif ($type->requiresAggregation()) {
            $this->metrics->histogram($type, $metricName, $value, $tags);
        }

        $this->logger->info("Business metric recorded", [
            'metric_name' => $metricName,
            'value' => $value,
            'type' => $type->value,
            'context' => $context,
        ]);

        return $this;
    }

    public function getHealthStatus(): array
    {
        $metrics = $this->metrics->getAll();
        $performance = $this->metrics->getPerformanceSummary();

        $health = [
            'status' => 'healthy',
            'timestamp' => time(),
            'uptime_seconds' => $this->getUptime(),
            'metrics_enabled' => $this->enabled,
            'active_requests' => count($this->activeRequests),
            'performance' => $performance,
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
        ];

        // Determine overall health status
        if ($performance['error_rate'] > 10) {
            $health['status'] = 'unhealthy';
            $health['issues'][] = 'High error rate: ' . round($performance['error_rate'], 2) . '%';
        }

        if ($performance['avg_latency'] > 5000) {
            $health['status'] = 'degraded';
            $health['issues'][] = 'High average latency: ' . round($performance['avg_latency'], 2) . 'ms';
        }

        $memoryUsagePercent = (memory_get_usage(true) / (1024 * 1024 * 1024)) * 100; // GB
        if ($memoryUsagePercent > 80) {
            $health['status'] = 'warning';
            $health['issues'][] = 'High memory usage: ' . round($memoryUsagePercent, 2) . '%';
        }

        return $health;
    }

    public function generateReport(int $periodSeconds = 3600): array
    {
        $report = [
            'period_seconds' => $periodSeconds,
            'generated_at' => time(),
            'summary' => $this->metrics->getPerformanceSummary(),
            'metrics_by_category' => [
                'performance' => $this->metrics->getPerformanceMetrics(),
                'system' => $this->metrics->getSystemMetrics(),
                'basic' => $this->metrics->getBasicMetrics(),
            ],
            'top_endpoints' => $this->getTopEndpoints(),
            'error_analysis' => $this->getErrorAnalysis(),
            'recommendations' => $this->getRecommendations(),
        ];

        $this->logger->info("Performance report generated", [
            'period_seconds' => $periodSeconds,
            'total_requests' => $report['summary']['total_requests'],
            'error_rate' => $report['summary']['error_rate'],
            'avg_latency' => $report['summary']['avg_latency'],
        ]);

        return $report;
    }

    public function getMetrics(): Metrics
    {
        return $this->metrics;
    }

    public function getLogger(): Logger
    {
        return $this->logger;
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

    private function getDefaultConfig(): array
    {
        return [
            'thresholds' => [
                'latency_warning' => 1000, // ms
                'latency_critical' => 5000, // ms
                'error_rate_warning' => 5, // %
                'error_rate_critical' => 10, // %
                'memory_warning' => 100 * 1024 * 1024, // 100MB
                'memory_critical' => 500 * 1024 * 1024, // 500MB
            ],
            'log_slow_requests' => true,
            'log_errors' => true,
            'track_memory' => true,
        ];
    }

    private function getStatusClass(int $statusCode): string
    {
        return match (true) {
            $statusCode >= 200 && $statusCode < 300 => '2xx',
            $statusCode >= 300 && $statusCode < 400 => '3xx',
            $statusCode >= 400 && $statusCode < 500 => '4xx',
            $statusCode >= 500 => '5xx',
            default => 'unknown',
        };
    }

    private function getErrorType(int $statusCode): string
    {
        return match (true) {
            $statusCode === 400 => 'bad_request',
            $statusCode === 401 => 'unauthorized',
            $statusCode === 403 => 'forbidden',
            $statusCode === 404 => 'not_found',
            $statusCode === 429 => 'rate_limited',
            $statusCode >= 400 && $statusCode < 500 => 'client_error',
            $statusCode >= 500 => 'server_error',
            default => 'unknown_error',
        };
    }

    private function getLogLevel(int $statusCode, float $duration): LogLevel
    {
        if ($statusCode >= 500) {
            return LogLevel::ERROR;
        }

        if ($statusCode >= 400 || $duration > $this->thresholds['latency_critical']) {
            return LogLevel::WARNING;
        }

        if ($duration > $this->thresholds['latency_warning']) {
            return LogLevel::NOTICE;
        }

        return LogLevel::DEBUG;
    }

    private function getCacheKeyType(string $key): string
    {
        if (strpos($key, 'quote:') === 0) {
            return 'quote';
        }
        if (strpos($key, 'timeline:') === 0) {
            return 'timeline';
        }
        if (strpos($key, 'news:') === 0) {
            return 'news';
        }
        if (strpos($key, 'market:') === 0) {
            return 'market';
        }
        return 'other';
    }

    private function getQueryType(string $query): string
    {
        $query = strtoupper(trim($query));
        if (strpos($query, 'SELECT') === 0) {
            return 'select';
        }
        if (strpos($query, 'INSERT') === 0) {
            return 'insert';
        }
        if (strpos($query, 'UPDATE') === 0) {
            return 'update';
        }
        if (strpos($query, 'DELETE') === 0) {
            return 'delete';
        }
        return 'other';
    }

    private function checkThresholds(string $endpoint, float $duration, int $statusCode, int $responseSize): void
    {
        if ($duration > $this->thresholds['latency_critical']) {
            $this->logger->critical("Request exceeded critical latency threshold", [
                'endpoint' => $endpoint,
                'duration_ms' => $duration,
                'threshold' => $this->thresholds['latency_critical'],
            ]);
        } elseif ($duration > $this->thresholds['latency_warning']) {
            $this->logger->warning("Request exceeded latency warning threshold", [
                'endpoint' => $endpoint,
                'duration_ms' => $duration,
                'threshold' => $this->thresholds['latency_warning'],
            ]);
        }

        if ($statusCode >= 500) {
            $this->logger->error("Server error response", [
                'endpoint' => $endpoint,
                'status_code' => $statusCode,
            ]);
        }
    }

    private function getUptime(): int
    {
        static $startTime = null;
        if ($startTime === null) {
            $startTime = time();
        }
        return time() - $startTime;
    }

    private function getTopEndpoints(int $limit = 10): array
    {
        $endpointMetrics = [];
        $performanceMetrics = $this->metrics->getPerformanceMetrics();

        foreach ($performanceMetrics as $key => $value) {
            if (strpos($key, 'request_count:') === 0) {
                $parts = explode(':', $key);
                if (count($parts) >= 2) {
                    $endpoint = $parts[1];
                    $count = is_array($value) ? ($value['count'] ?? 0) : $value;
                    $endpointMetrics[$endpoint] = ($endpointMetrics[$endpoint] ?? 0) + $count;
                }
            }
        }

        arsort($endpointMetrics);
        return array_slice($endpointMetrics, 0, $limit, true);
    }

    private function getErrorAnalysis(): array
    {
        $errorMetrics = [];
        $performanceMetrics = $this->metrics->getPerformanceMetrics();

        foreach ($performanceMetrics as $key => $value) {
            if (strpos($key, 'error_rate:') === 0) {
                $parts = explode(':', $key);
                if (count($parts) >= 2) {
                    $errorType = $parts[1];
                    $count = is_array($value) ? ($value['count'] ?? 0) : $value;
                    $errorMetrics[$errorType] = ($errorMetrics[$errorType] ?? 0) + $count;
                }
            }
        }

        arsort($errorMetrics);
        return $errorMetrics;
    }

    private function getRecommendations(): array
    {
        $recommendations = [];
        $performance = $this->metrics->getPerformanceSummary();

        if ($performance['error_rate'] > 5) {
            $recommendations[] = "High error rate detected. Consider implementing better error handling and retries.";
        }

        if ($performance['avg_latency'] > 2000) {
            $recommendations[] = "High average latency detected. Consider optimizing API calls or implementing caching.";
        }

        $memoryUsage = memory_get_usage(true);
        if ($memoryUsage > 100 * 1024 * 1024) { // 100MB
            $recommendations[] = "High memory usage detected. Consider optimizing data structures and implementing garbage collection.";
        }

        return $recommendations;
    }
}
