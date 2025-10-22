<?php

declare(strict_types=1);

namespace Wioex\SDK\Middleware;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Wioex\SDK\Enums\MiddlewareType;
use Wioex\SDK\Monitoring\PerformanceMonitor;

class MonitoringMiddleware extends AbstractMiddleware
{
    private PerformanceMonitor $monitor;
    private array $activeRequests = [];

    public function __construct(PerformanceMonitor $monitor, array $config = [])
    {
        parent::__construct(MiddlewareType::MONITORING, $config);
        $this->monitor = $monitor;
    }

    protected function getDefaultConfig(): array
    {
        return [
            'track_requests' => true,
            'track_responses' => true,
            'track_errors' => true,
            'track_memory' => true,
            'track_cache_operations' => false,
            'add_monitoring_headers' => false,
            'sample_rate' => 1.0, // Sample 100% of requests
        ];
    }

    protected function shouldExecuteCustom(RequestInterface $request, array $context = []): bool
    {
        $sampleRate = $this->getConfigValue('sample_rate', 1.0);

        if ($sampleRate < 1.0) {
            return mt_rand() / mt_getrandmax() < $sampleRate;
        }

        return true;
    }

    protected function processRequestCustom(RequestInterface $request, array $context = []): RequestInterface
    {
        if (!$this->getConfigValue('track_requests', true)) {
            return $request;
        }

        $requestId = $context['request_id'] ?? uniqid('req_');
        $endpoint = $this->extractEndpoint($request);
        $method = $request->getMethod();

        $this->monitor->startRequest($requestId, $endpoint, $method, [
            'uri' => (string) $request->getUri(),
            'headers_count' => count($request->getHeaders()),
            'has_body' => !empty((string) $request->getBody()),
        ]);

        $this->activeRequests[$requestId] = [
            'start_time' => microtime(true),
            'endpoint' => $endpoint,
            'method' => $method,
            'memory_start' => memory_get_usage(true),
        ];

        return $request;
    }

    protected function processResponseCustom(ResponseInterface $response, RequestInterface $request, array $context = []): ResponseInterface
    {
        if (!$this->getConfigValue('track_responses', true)) {
            return $response;
        }

        $requestId = $context['request_id'] ?? uniqid('req_');
        $statusCode = $response->getStatusCode();
        $responseSize = strlen((string) $response->getBody());

        if (isset($this->activeRequests[$requestId])) {
            $requestData = $this->activeRequests[$requestId];
            $duration = (microtime(true) - $requestData['start_time']) * 1000; // milliseconds
            $memoryUsage = memory_get_usage(true) - $requestData['memory_start'];

            $this->monitor->endRequest($requestId, $statusCode, $responseSize, [
                'duration_ms' => $duration,
                'memory_usage' => $memoryUsage,
                'endpoint' => $requestData['endpoint'],
                'method' => $requestData['method'],
            ]);

            // Record business metrics
            $this->recordBusinessMetrics($requestData['endpoint'], $statusCode, $duration, $responseSize);

            unset($this->activeRequests[$requestId]);
        }

        if ($this->getConfigValue('add_monitoring_headers', false)) {
            $response = $this->addMonitoringHeaders($response, $context);
        }

        return $response;
    }

    protected function handleErrorCustom(\Throwable $error, RequestInterface $request, array $context = []): ?\Throwable
    {
        if (!$this->getConfigValue('track_errors', true)) {
            return $error;
        }

        $requestId = $context['request_id'] ?? uniqid('req_');
        $endpoint = $this->extractEndpoint($request);

        if (isset($this->activeRequests[$requestId])) {
            $requestData = $this->activeRequests[$requestId];
            $duration = (microtime(true) - $requestData['start_time']) * 1000;

            $this->monitor->endRequest($requestId, 0, 0, [
                'duration_ms' => $duration,
                'error' => true,
                'error_type' => get_class($error),
                'error_message' => $error->getMessage(),
            ]);

            unset($this->activeRequests[$requestId]);
        }

        // Record error metrics
        $this->monitor->recordError($endpoint, get_class($error), [
            'error_message' => $error->getMessage(),
            'error_code' => $error->getCode(),
            'error_file' => $error->getFile(),
            'error_line' => $error->getLine(),
        ]);

        return $error;
    }

    private function extractEndpoint(RequestInterface $request): string
    {
        $path = $request->getUri()->getPath();

        // Normalize common patterns
        $patterns = [
            '#^/v2/stocks/quote/([^/]+)#' => '/v2/stocks/quote/{symbol}',
            '#^/v2/stocks/timeline/([^/]+)#' => '/v2/stocks/timeline/{symbol}',
            '#^/v2/news/([^/]+)#' => '/v2/news/{symbol}',
            '#^/v2/currency/convert/([^/]+)/([^/]+)#' => '/v2/currency/convert/{from}/{to}',
        ];

        foreach ($patterns as $pattern => $replacement) {
            if (preg_match($pattern, $path)) {
                return $replacement;
            }
        }

        return $path;
    }

    private function recordBusinessMetrics(string $endpoint, int $statusCode, float $duration, int $responseSize): void
    {
        // API usage metrics
        $this->monitor->recordBusinessMetric("api.requests.{$endpoint}", 1.0);

        // Success/Error rates
        if ($statusCode >= 200 && $statusCode < 400) {
            $this->monitor->recordBusinessMetric("api.success.{$endpoint}", 1.0);
        } else {
            $this->monitor->recordBusinessMetric("api.errors.{$endpoint}", 1.0);
        }

        // Performance metrics
        if ($duration > 5000) { // > 5 seconds
            $this->monitor->recordBusinessMetric("api.slow_requests.{$endpoint}", 1.0);
        }

        // Data transfer metrics
        if ($responseSize > 1024 * 1024) { // > 1MB
            $this->monitor->recordBusinessMetric("api.large_responses.{$endpoint}", 1.0);
        }

        // Endpoint-specific metrics
        $this->recordEndpointSpecificMetrics($endpoint, $statusCode, $duration, $responseSize);
    }

    private function recordEndpointSpecificMetrics(string $endpoint, int $statusCode, float $duration, int $responseSize): void
    {
        switch (true) {
            case str_contains($endpoint, '/stocks/quote'):
                $this->monitor->recordBusinessMetric('stocks.quote_requests', 1.0);
                break;

            case str_contains($endpoint, '/stocks/timeline'):
                $this->monitor->recordBusinessMetric('stocks.timeline_requests', 1.0);
                break;

            case str_contains($endpoint, '/news'):
                $this->monitor->recordBusinessMetric('news.requests', 1.0);
                break;

            case str_contains($endpoint, '/markets/status'):
                $this->monitor->recordBusinessMetric('markets.status_requests', 1.0);
                break;

            case str_contains($endpoint, '/currency'):
                $this->monitor->recordBusinessMetric('currency.requests', 1.0);
                break;
        }
    }

    private function addMonitoringHeaders(ResponseInterface $response, array $context): ResponseInterface
    {
        $headers = [
            'X-Request-ID' => $context['request_id'] ?? 'unknown',
            'X-Response-Time' => isset($context['duration_ms']) ? round($context['duration_ms'], 2) . 'ms' : 'unknown',
            'X-Memory-Usage' => $this->formatBytes(memory_get_usage(true)),
            'X-Memory-Peak' => $this->formatBytes(memory_get_peak_usage(true)),
        ];

        foreach ($headers as $name => $value) {
            $response = $response->withHeader($name, (string) $value);
        }

        return $response;
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $index = 0;

        while ($bytes >= 1024 && $index < count($units) - 1) {
            $bytes /= 1024;
            $index++;
        }

        return round($bytes, 2) . $units[$index];
    }

    public function getActiveRequests(): array
    {
        return $this->activeRequests;
    }

    public function getActiveRequestsCount(): int
    {
        return count($this->activeRequests);
    }

    public function getMonitoringStatistics(): array
    {
        return [
            'active_requests' => $this->getActiveRequestsCount(),
            'monitor_enabled' => $this->monitor->isEnabled(),
            'config' => $this->config,
            'health_status' => $this->monitor->getHealthStatus(),
        ];
    }

    public function toArray(): array
    {
        $baseArray = parent::toArray();
        $baseArray['monitoring_statistics'] = $this->getMonitoringStatistics();
        return $baseArray;
    }
}
