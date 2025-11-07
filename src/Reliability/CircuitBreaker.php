<?php

declare(strict_types=1);

namespace Wioex\SDK\Reliability;

use Wioex\SDK\Exceptions\CircuitBreakerException;
use Wioex\SDK\Cache\CacheInterface;

class CircuitBreaker
{
    private const STATE_CLOSED = 'closed';
    private const STATE_OPEN = 'open';
    private const STATE_HALF_OPEN = 'half_open';

    private string $name;
    private array $config;
    private string $state;
    private int $failureCount = 0;
    private int $successCount = 0;
    private int $consecutiveSuccesses = 0;
    private float $lastFailureTime = 0;
    private float $lastSuccessTime = 0;
    private ?CacheInterface $cache = null;
    private array $metrics = [
        'total_requests' => 0,
        'total_failures' => 0,
        'total_successes' => 0,
        'state_transitions' => 0,
        'last_state_change' => 0,
        'recovery_attempts' => 0
    ];

    public function __construct(string $name, array $config = [], ?CacheInterface $cache = null)
    {
        $this->name = $name;
        $this->config = array_merge($this->getDefaultConfig(), $config);
        $this->cache = $cache;
        $this->state = self::STATE_CLOSED;
        
        // Load state from cache if available
        $this->loadState();
    }

    private function getDefaultConfig(): array
    {
        return [
            'failure_threshold' => 5,        // Number of failures before opening
            'recovery_timeout' => 30,        // Seconds to wait before trying half-open
            'success_threshold' => 3,        // Successes needed to close from half-open
            'half_open_max_calls' => 3,      // Max calls allowed in half-open state
            'timeout' => 10,                 // Request timeout in seconds
            'monitor_window' => 60,          // Time window for failure counting
            'expected_exceptions' => [       // Exceptions that should trip the breaker
                \GuzzleHttp\Exception\ConnectException::class,
                \GuzzleHttp\Exception\RequestException::class,
                \Exception::class
            ],
            'ignored_exceptions' => [        // Exceptions that should NOT trip the breaker
                \InvalidArgumentException::class
            ]
        ];
    }

    public function call(callable $operation)
    {
        $this->metrics['total_requests']++;

        if (!$this->canExecute()) {
            throw new CircuitBreakerException(
                "Circuit breaker '{$this->name}' is {$this->state}. Request blocked.",
                $this->state,
                $this->getMetrics()
            );
        }

        try {
            $startTime = microtime(true);
            $result = $operation();
            $executionTime = microtime(true) - $startTime;

            $this->onSuccess($executionTime);
            return $result;

        } catch (\Throwable $e) {
            $this->onFailure($e);
            throw $e;
        }
    }

    public function callWithFallback(callable $operation, callable $fallback)
    {
        try {
            return $this->call($operation);
        } catch (CircuitBreakerException $e) {
            // Circuit breaker is open, use fallback
            return $fallback($e);
        } catch (\Throwable $e) {
            // Operation failed, but might use fallback based on configuration
            if ($this->shouldUseFallbackForException($e)) {
                return $fallback($e);
            }
            throw $e;
        }
    }

    public function execute(callable $operation, ?callable $fallback = null, array $options = [])
    {
        $retries = $options['retries'] ?? 0;
        $delay = $options['delay'] ?? 1000; // milliseconds
        $maxDelay = $options['max_delay'] ?? 30000; // 30 seconds
        
        $attempt = 0;
        $lastException = null;

        while ($attempt <= $retries) {
            try {
                if ($fallback) {
                    return $this->callWithFallback($operation, $fallback);
                } else {
                    return $this->call($operation);
                }
            } catch (\Throwable $e) {
                $lastException = $e;
                $attempt++;

                if ($attempt <= $retries) {
                    $currentDelay = min($delay * pow(2, $attempt - 1), $maxDelay);
                    usleep($currentDelay * 1000);
                }
            }
        }

        throw $lastException;
    }

    private function canExecute(): bool
    {
        switch ($this->state) {
            case self::STATE_CLOSED:
                return true;

            case self::STATE_OPEN:
                return $this->shouldAttemptRecovery();

            case self::STATE_HALF_OPEN:
                return $this->canAttemptInHalfOpen();

            default:
                return false;
        }
    }

    private function shouldAttemptRecovery(): bool
    {
        $timeSinceLastFailure = microtime(true) - $this->lastFailureTime;
        
        if ($timeSinceLastFailure >= $this->config['recovery_timeout']) {
            $this->transitionTo(self::STATE_HALF_OPEN);
            return true;
        }

        return false;
    }

    private function canAttemptInHalfOpen(): bool
    {
        // In half-open state, allow limited number of test calls
        return $this->successCount < $this->config['half_open_max_calls'];
    }

    private function onSuccess(float $executionTime): void
    {
        $this->metrics['total_successes']++;
        $this->lastSuccessTime = microtime(true);
        $this->resetFailureCount();

        if ($this->state === self::STATE_HALF_OPEN) {
            $this->consecutiveSuccesses++;
            $this->successCount++;

            if ($this->consecutiveSuccesses >= $this->config['success_threshold']) {
                $this->transitionTo(self::STATE_CLOSED);
            }
        }

        $this->saveState();
    }

    private function onFailure(\Throwable $exception): void
    {
        if (!$this->shouldTripBreaker($exception)) {
            return; // Ignored exception, don't count as failure
        }

        $this->metrics['total_failures']++;
        $this->failureCount++;
        $this->lastFailureTime = microtime(true);
        $this->consecutiveSuccesses = 0;

        switch ($this->state) {
            case self::STATE_CLOSED:
                if ($this->failureCount >= $this->config['failure_threshold']) {
                    $this->transitionTo(self::STATE_OPEN);
                }
                break;

            case self::STATE_HALF_OPEN:
                $this->transitionTo(self::STATE_OPEN);
                break;
        }

        $this->saveState();
    }

    private function shouldTripBreaker(\Throwable $exception): bool
    {
        $exceptionClass = get_class($exception);

        // Check if it's an ignored exception
        foreach ($this->config['ignored_exceptions'] as $ignoredException) {
            if ($exception instanceof $ignoredException) {
                return false;
            }
        }

        // Check if it's an expected exception that should trip the breaker
        foreach ($this->config['expected_exceptions'] as $expectedException) {
            if ($exception instanceof $expectedException) {
                return true;
            }
        }

        // Default behavior: trip on any exception
        return true;
    }

    private function shouldUseFallbackForException(\Throwable $exception): bool
    {
        // Use fallback for circuit breaker exceptions and expected exceptions
        if ($exception instanceof CircuitBreakerException) {
            return true;
        }

        return $this->shouldTripBreaker($exception);
    }

    private function resetFailureCount(): void
    {
        $this->failureCount = 0;
    }

    private function transitionTo(string $newState): void
    {
        $oldState = $this->state;
        $this->state = $newState;
        $this->metrics['state_transitions']++;
        $this->metrics['last_state_change'] = microtime(true);

        // Reset counters based on state
        switch ($newState) {
            case self::STATE_CLOSED:
                $this->resetFailureCount();
                $this->successCount = 0;
                $this->consecutiveSuccesses = 0;
                break;

            case self::STATE_OPEN:
                $this->successCount = 0;
                $this->consecutiveSuccesses = 0;
                break;

            case self::STATE_HALF_OPEN:
                $this->successCount = 0;
                $this->consecutiveSuccesses = 0;
                $this->metrics['recovery_attempts']++;
                break;
        }

    }

    public function getState(): string
    {
        return $this->state;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getFailureCount(): int
    {
        return $this->failureCount;
    }

    public function getFailureRate(): float
    {
        $totalRequests = $this->metrics['total_requests'];
        
        if ($totalRequests === 0) {
            return 0.0;
        }

        return ($this->metrics['total_failures'] / $totalRequests) * 100;
    }

    public function getSuccessRate(): float
    {
        return 100.0 - $this->getFailureRate();
    }

    public function getMetrics(): array
    {
        return array_merge($this->metrics, [
            'name' => $this->name,
            'state' => $this->state,
            'failure_count' => $this->failureCount,
            'success_count' => $this->successCount,
            'consecutive_successes' => $this->consecutiveSuccesses,
            'failure_rate' => round($this->getFailureRate(), 2),
            'success_rate' => round($this->getSuccessRate(), 2),
            'last_failure_time' => $this->lastFailureTime,
            'last_success_time' => $this->lastSuccessTime,
            'time_since_last_failure' => $this->lastFailureTime > 0 ? microtime(true) - $this->lastFailureTime : null,
            'time_until_recovery' => $this->calculateTimeUntilRecovery(),
            'config' => $this->config
        ]);
    }

    private function calculateTimeUntilRecovery(): ?float
    {
        if ($this->state !== self::STATE_OPEN) {
            return null;
        }

        $timeSinceLastFailure = microtime(true) - $this->lastFailureTime;
        $remainingTime = $this->config['recovery_timeout'] - $timeSinceLastFailure;

        return max(0, $remainingTime);
    }

    public function isOpen(): bool
    {
        return $this->state === self::STATE_OPEN;
    }

    public function isClosed(): bool
    {
        return $this->state === self::STATE_CLOSED;
    }

    public function isHalfOpen(): bool
    {
        return $this->state === self::STATE_HALF_OPEN;
    }

    public function forceOpen(): void
    {
        $this->transitionTo(self::STATE_OPEN);
        $this->saveState();
    }

    public function forceClose(): void
    {
        $this->transitionTo(self::STATE_CLOSED);
        $this->saveState();
    }

    public function reset(): void
    {
        $this->state = self::STATE_CLOSED;
        $this->failureCount = 0;
        $this->successCount = 0;
        $this->consecutiveSuccesses = 0;
        $this->lastFailureTime = 0;
        $this->lastSuccessTime = 0;
        $this->metrics = [
            'total_requests' => 0,
            'total_failures' => 0,
            'total_successes' => 0,
            'state_transitions' => 0,
            'last_state_change' => microtime(true),
            'recovery_attempts' => 0
        ];
        $this->saveState();
    }

    private function loadState(): void
    {
        if (!$this->cache) {
            return;
        }

        $cacheKey = $this->getCacheKey();
        $state = $this->cache->get($cacheKey);

        if ($state && is_array($state)) {
            $this->state = $state['state'] ?? self::STATE_CLOSED;
            $this->failureCount = $state['failure_count'] ?? 0;
            $this->successCount = $state['success_count'] ?? 0;
            $this->consecutiveSuccesses = $state['consecutive_successes'] ?? 0;
            $this->lastFailureTime = $state['last_failure_time'] ?? 0;
            $this->lastSuccessTime = $state['last_success_time'] ?? 0;
            $this->metrics = array_merge($this->metrics, $state['metrics'] ?? []);
        }
    }

    private function saveState(): void
    {
        if (!$this->cache) {
            return;
        }

        $cacheKey = $this->getCacheKey();
        $state = [
            'state' => $this->state,
            'failure_count' => $this->failureCount,
            'success_count' => $this->successCount,
            'consecutive_successes' => $this->consecutiveSuccesses,
            'last_failure_time' => $this->lastFailureTime,
            'last_success_time' => $this->lastSuccessTime,
            'metrics' => $this->metrics,
            'saved_at' => microtime(true)
        ];

        // Cache state for longer than recovery timeout
        $ttl = max($this->config['recovery_timeout'] * 2, 300); // At least 5 minutes
        $this->cache->set($cacheKey, $state, $ttl);
    }

    private function getCacheKey(): string
    {
        return "circuit_breaker:state:{$this->name}";
    }

    public function test(): bool
    {
        try {
            $this->call(function () {
                return true;
            });
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function getHealthStatus(): array
    {
        $metrics = $this->getMetrics();
        $warnings = [];
        $errors = [];

        // Check failure rate
        if ($metrics['failure_rate'] > 50) {
            $errors[] = "High failure rate: {$metrics['failure_rate']}%";
        } elseif ($metrics['failure_rate'] > 20) {
            $warnings[] = "Elevated failure rate: {$metrics['failure_rate']}%";
        }

        // Check state
        if ($this->isOpen()) {
            $errors[] = "Circuit breaker is open";
        } elseif ($this->isHalfOpen()) {
            $warnings[] = "Circuit breaker is in recovery mode";
        }

        // Check consecutive failures
        if ($this->failureCount >= $this->config['failure_threshold'] * 0.8) {
            $warnings[] = "Approaching failure threshold";
        }

        return [
            'healthy' => empty($errors),
            'status' => empty($errors) ? (empty($warnings) ? 'excellent' : 'good') : 'critical',
            'state' => $this->state,
            'warnings' => $warnings,
            'errors' => $errors,
            'metrics' => $metrics
        ];
    }

    public static function create(string $name, array $config = [], ?CacheInterface $cache = null): self
    {
        return new self($name, $config, $cache);
    }

    public function __toString(): string
    {
        return sprintf(
            'CircuitBreaker[%s] State: %s, Failures: %d/%d, Success Rate: %.1f%%',
            $this->name,
            $this->state,
            $this->failureCount,
            $this->config['failure_threshold'],
            $this->getSuccessRate()
        );
    }
}