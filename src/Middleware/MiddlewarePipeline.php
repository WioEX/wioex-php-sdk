<?php

declare(strict_types=1);

namespace Wioex\SDK\Middleware;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Wioex\SDK\Enums\MiddlewareType;
use Wioex\SDK\Enums\Environment;
use Wioex\SDK\Logging\Logger;

class MiddlewarePipeline
{
    private array $middleware = [];
    private array $config;
    private bool $enabled = true;
    private ?Logger $logger = null;
    private array $executionMetrics = [];

    public function __construct(array $config = [], Logger $logger = null)
    {
        $this->config = array_merge($this->getDefaultConfig(), $config);
        $this->enabled = $this->config['enabled'] ?? true;
        $this->logger = $logger;
    }

    public static function create(array $config = []): self
    {
        return new self($config);
    }

    public static function forEnvironment(Environment $environment, array $config = []): self
    {
        $defaultConfig = [
            'enabled' => $environment->shouldEnableMiddleware(),
            'log_execution' => $environment->isDevelopment(),
            'track_performance' => $environment->shouldEnableMetrics(),
            'fail_fast' => $environment->isProduction(),
        ];

        return new self(array_merge($defaultConfig, $config));
    }

    public function add(MiddlewareInterface $middleware): self
    {
        $this->middleware[] = $middleware;
        $this->sortMiddleware();
        return $this;
    }

    public function addMultiple(array $middleware): self
    {
        foreach ($middleware as $mw) {
            if ($mw instanceof MiddlewareInterface) {
                $this->middleware[] = $mw;
            }
        }
        $this->sortMiddleware();
        return $this;
    }

    public function remove(string $middlewareClass): self
    {
        $this->middleware = array_filter(
            $this->middleware,
            fn($mw) => !($mw instanceof $middlewareClass)
        );
        return $this;
    }

    public function removeByType(MiddlewareType $type): self
    {
        $this->middleware = array_filter(
            $this->middleware,
            fn($mw) => $mw->getType() !== $type
        );
        return $this;
    }

    public function clear(): self
    {
        $this->middleware = [];
        return $this;
    }

    public function processRequest(RequestInterface $request, array $context = []): RequestInterface
    {
        if (!$this->enabled) {
            return $request;
        }

        $startTime = microtime(true);
        $processedRequest = $request;
        $executedMiddleware = [];

        try {
            foreach ($this->getRequestMiddleware() as $middleware) {
                if (!$middleware->shouldExecute($processedRequest, $context)) {
                    continue;
                }

                $middlewareStart = microtime(true);
                $processedRequest = $middleware->processRequest($processedRequest, $context);
                $middlewareEnd = microtime(true);

                $executedMiddleware[] = [
                    'middleware' => get_class($middleware),
                    'type' => $middleware->getType()->value,
                    'duration_ms' => ($middlewareEnd - $middlewareStart) * 1000,
                ];

                $this->logMiddlewareExecution('request', $middleware, $middlewareEnd - $middlewareStart);
            }
        } catch (\Throwable $e) {
            $this->handlePipelineError($e, $request, $context);
            if ($this->config['fail_fast']) {
                throw $e;
            }
        }

        $totalDuration = (microtime(true) - $startTime) * 1000;
        $this->recordExecutionMetrics('request', $executedMiddleware, $totalDuration);

        return $processedRequest;
    }

    public function processResponse(ResponseInterface $response, RequestInterface $request, array $context = []): ResponseInterface
    {
        if (!$this->enabled) {
            return $response;
        }

        $startTime = microtime(true);
        $processedResponse = $response;
        $executedMiddleware = [];

        try {
            foreach ($this->getResponseMiddleware() as $middleware) {
                if (!$middleware->shouldExecute($request, $context)) {
                    continue;
                }

                $middlewareStart = microtime(true);
                $processedResponse = $middleware->processResponse($processedResponse, $request, $context);
                $middlewareEnd = microtime(true);

                $executedMiddleware[] = [
                    'middleware' => get_class($middleware),
                    'type' => $middleware->getType()->value,
                    'duration_ms' => ($middlewareEnd - $middlewareStart) * 1000,
                ];

                $this->logMiddlewareExecution('response', $middleware, $middlewareEnd - $middlewareStart);
            }
        } catch (\Throwable $e) {
            $this->handlePipelineError($e, $request, $context);
            if ($this->config['fail_fast']) {
                throw $e;
            }
        }

        $totalDuration = (microtime(true) - $startTime) * 1000;
        $this->recordExecutionMetrics('response', $executedMiddleware, $totalDuration);

        return $processedResponse;
    }

    public function handleError(\Throwable $error, RequestInterface $request, array $context = []): \Throwable
    {
        if (!$this->enabled) {
            return $error;
        }

        $processedError = $error;

        foreach ($this->getErrorMiddleware() as $middleware) {
            if (!$middleware->shouldExecute($request, $context)) {
                continue;
            }

            try {
                $result = $middleware->handleError($processedError, $request, $context);
                if ($result !== null) {
                    $processedError = $result;
                }

                $this->logMiddlewareExecution('error', $middleware, 0);
            } catch (\Throwable $middlewareError) {
                $this->logMiddlewareError($middleware, $middlewareError);
                if ($this->config['fail_fast']) {
                    throw $middlewareError;
                }
            }
        }

        return $processedError;
    }

    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    public function getMiddlewareByType(MiddlewareType $type): array
    {
        return array_filter(
            $this->middleware,
            fn($mw) => $mw->getType() === $type
        );
    }

    public function getRequestMiddleware(): array
    {
        return array_filter(
            $this->middleware,
            fn($mw) => $mw->getType()->isRequestPhase() && $mw->isEnabled()
        );
    }

    public function getResponseMiddleware(): array
    {
        return array_filter(
            $this->middleware,
            fn($mw) => $mw->getType()->isResponsePhase() && $mw->isEnabled()
        );
    }

    public function getErrorMiddleware(): array
    {
        return array_filter(
            $this->middleware,
            fn($mw) => $mw->getType()->isErrorPhase() && $mw->isEnabled()
        );
    }

    public function hasMiddleware(string $middlewareClass): bool
    {
        foreach ($this->middleware as $mw) {
            if ($mw instanceof $middlewareClass) {
                return true;
            }
        }
        return false;
    }

    public function hasMiddlewareType(MiddlewareType $type): bool
    {
        foreach ($this->middleware as $mw) {
            if ($mw->getType() === $type) {
                return true;
            }
        }
        return false;
    }

    public function count(): int
    {
        return count($this->middleware);
    }

    public function countEnabled(): int
    {
        return count(array_filter($this->middleware, fn($mw) => $mw->isEnabled()));
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

    public function enableAll(): self
    {
        foreach ($this->middleware as $mw) {
            $mw->enable();
        }
        return $this;
    }

    public function disableAll(): self
    {
        foreach ($this->middleware as $mw) {
            $mw->disable();
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

    public function getExecutionMetrics(): array
    {
        return $this->executionMetrics;
    }

    public function clearExecutionMetrics(): self
    {
        $this->executionMetrics = [];
        return $this;
    }

    public function getStatistics(): array
    {
        $stats = [
            'total_middleware' => count($this->middleware),
            'enabled_middleware' => $this->countEnabled(),
            'disabled_middleware' => count($this->middleware) - $this->countEnabled(),
            'pipeline_enabled' => $this->enabled,
            'by_type' => [],
            'by_phase' => [
                'request' => count($this->getRequestMiddleware()),
                'response' => count($this->getResponseMiddleware()),
                'error' => count($this->getErrorMiddleware()),
            ],
            'execution_metrics' => $this->getExecutionSummary(),
        ];

        foreach (MiddlewareType::cases() as $type) {
            $stats['by_type'][$type->value] = count($this->getMiddlewareByType($type));
        }

        return $stats;
    }

    private function sortMiddleware(): void
    {
        usort($this->middleware, fn($a, $b) => $b->getPriority() <=> $a->getPriority());
    }

    private function getDefaultConfig(): array
    {
        return [
            'enabled' => true,
            'log_execution' => false,
            'track_performance' => true,
            'fail_fast' => false,
            'max_execution_time' => 5000, // milliseconds
        ];
    }

    private function logMiddlewareExecution(string $phase, MiddlewareInterface $middleware, float $duration): void
    {
        if (!$this->config['log_execution'] || $this->logger === null) {
            return;
        }

        $this->logger->debug("Middleware executed", [
            'phase' => $phase,
            'middleware' => get_class($middleware),
            'type' => $middleware->getType()->value,
            'priority' => $middleware->getPriority(),
            'duration_ms' => $duration * 1000,
        ]);
    }

    private function logMiddlewareError(MiddlewareInterface $middleware, \Throwable $error): void
    {
        if ($this->logger === null) {
            return;
        }

        $this->logger->error("Middleware error", [
            'middleware' => get_class($middleware),
            'type' => $middleware->getType()->value,
            'error' => $error->getMessage(),
            'file' => $error->getFile(),
            'line' => $error->getLine(),
        ]);
    }

    private function handlePipelineError(\Throwable $error, RequestInterface $request, array $context): void
    {
        if ($this->logger !== null) {
            $this->logger->error("Pipeline error", [
                'error' => $error->getMessage(),
                'file' => $error->getFile(),
                'line' => $error->getLine(),
                'request_uri' => (string) $request->getUri(),
                'request_method' => $request->getMethod(),
            ]);
        }
    }

    private function recordExecutionMetrics(string $phase, array $executedMiddleware, float $totalDuration): void
    {
        if (!$this->config['track_performance']) {
            return;
        }

        $this->executionMetrics[] = [
            'timestamp' => time(),
            'phase' => $phase,
            'total_duration_ms' => $totalDuration,
            'middleware_count' => count($executedMiddleware),
            'middleware' => $executedMiddleware,
        ];

        // Keep only recent metrics to prevent memory issues
        if (count($this->executionMetrics) > 100) {
            $this->executionMetrics = array_slice($this->executionMetrics, -50);
        }
    }

    private function getExecutionSummary(): array
    {
        if (($this->executionMetrics === null || $this->executionMetrics === '' || $this->executionMetrics === [])) {
            return [];
        }

        $requestMetrics = array_filter($this->executionMetrics, fn($m) => $m['phase'] === 'request');
        $responseMetrics = array_filter($this->executionMetrics, fn($m) => $m['phase'] === 'response');

        return [
            'total_executions' => count($this->executionMetrics),
            'request_executions' => count($requestMetrics),
            'response_executions' => count($responseMetrics),
            'avg_request_duration_ms' => $this->calculateAverageDuration($requestMetrics),
            'avg_response_duration_ms' => $this->calculateAverageDuration($responseMetrics),
            'max_request_duration_ms' => $this->calculateMaxDuration($requestMetrics),
            'max_response_duration_ms' => $this->calculateMaxDuration($responseMetrics),
        ];
    }

    private function calculateAverageDuration(array $metrics): float
    {
        if (($metrics === null || $metrics === '' || $metrics === [])) {
            return 0.0;
        }

        $total = array_sum(array_column($metrics, 'total_duration_ms'));
        return $total / count($metrics);
    }

    private function calculateMaxDuration(array $metrics): float
    {
        if (($metrics === null || $metrics === '' || $metrics === [])) {
            return 0.0;
        }

        return max(array_column($metrics, 'total_duration_ms'));
    }

    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled,
            'config' => $this->config,
            'middleware_count' => count($this->middleware),
            'middleware' => array_map(fn($mw) => $mw->toArray(), $this->middleware),
            'statistics' => $this->getStatistics(),
        ];
    }
}
