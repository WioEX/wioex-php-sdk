<?php

declare(strict_types=1);

namespace Wioex\SDK\Reliability;

use Wioex\SDK\Cache\CacheInterface;
use Wioex\SDK\Exceptions\CircuitBreakerException;

class CircuitBreakerManager
{
    private array $circuitBreakers = [];
    private array $configs = [];
    private ?CacheInterface $cache = null;
    private array $globalMetrics = [
        'total_requests' => 0,
        'total_failures' => 0,
        'total_successes' => 0,
        'active_breakers' => 0,
        'open_breakers' => 0,
        'half_open_breakers' => 0,
        'closed_breakers' => 0
    ];

    public function __construct(?CacheInterface $cache = null)
    {
        $this->cache = $cache;
    }

    public function createCircuitBreaker(string $name, array $config = []): CircuitBreaker
    {
        if (isset($this->circuitBreakers[$name])) {
            return $this->circuitBreakers[$name];
        }

        $circuitBreaker = new CircuitBreaker($name, $config, $this->cache);
        $this->circuitBreakers[$name] = $circuitBreaker;
        $this->configs[$name] = $config;

        return $circuitBreaker;
    }

    public function getCircuitBreaker(string $name): ?CircuitBreaker
    {
        return $this->circuitBreakers[$name] ?? null;
    }

    public function hasCircuitBreaker(string $name): bool
    {
        return isset($this->circuitBreakers[$name]);
    }

    public function removeCircuitBreaker(string $name): bool
    {
        if (isset($this->circuitBreakers[$name])) {
            unset($this->circuitBreakers[$name]);
            unset($this->configs[$name]);
            return true;
        }
        
        return false;
    }

    public function call(string $name, callable $operation, array $config = [])
    {
        $circuitBreaker = $this->getOrCreateCircuitBreaker($name, $config);
        
        try {
            $result = $circuitBreaker->call($operation);
            $this->recordSuccess();
            return $result;
        } catch (\Throwable $e) {
            $this->recordFailure();
            throw $e;
        } finally {
            $this->recordRequest();
        }
    }

    public function callWithFallback(string $name, callable $operation, callable $fallback, array $config = [])
    {
        $circuitBreaker = $this->getOrCreateCircuitBreaker($name, $config);
        
        try {
            $result = $circuitBreaker->callWithFallback($operation, $fallback);
            $this->recordSuccess();
            return $result;
        } catch (\Throwable $e) {
            $this->recordFailure();
            throw $e;
        } finally {
            $this->recordRequest();
        }
    }

    public function execute(string $name, callable $operation, ?callable $fallback = null, array $options = []): mixed
    {
        $config = $options['circuit_breaker'] ?? [];
        $circuitBreaker = $this->getOrCreateCircuitBreaker($name, $config);
        
        try {
            $result = $circuitBreaker->execute($operation, $fallback, $options);
            $this->recordSuccess();
            return $result;
        } catch (\Throwable $e) {
            $this->recordFailure();
            throw $e;
        } finally {
            $this->recordRequest();
        }
    }

    public function executeMultiple(array $operations, array $options = []): array
    {
        $concurrency = $options['concurrency'] ?? 5;
        $failFast = $options['fail_fast'] ?? false;
        $results = [];
        $errors = [];
        $promises = [];

        // For now, execute sequentially (can be enhanced with async support later)
        foreach ($operations as $name => $operation) {
            try {
                if (is_array($operation)) {
                    $callable = $operation['callable'];
                    $fallback = $operation['fallback'] ?? null;
                    $config = $operation['config'] ?? [];
                    
                    $results[$name] = $this->callWithFallback($name, $callable, $fallback ?: function() { return null; }, $config);
                } else {
                    $results[$name] = $this->call($name, $operation);
                }
            } catch (\Throwable $e) {
                $errors[$name] = $e;
                
                if ($failFast) {
                    throw new CircuitBreakerException(
                        "Batch execution failed fast on operation '{$name}': " . $e->getMessage(),
                        'batch_failed',
                        ['failed_operation' => $name, 'partial_results' => $results, 'errors' => $errors]
                    );
                }
            }
        }

        return [
            'results' => $results,
            'errors' => $errors,
            'success_count' => count($results),
            'error_count' => count($errors),
            'total_operations' => count($operations)
        ];
    }

    private function getOrCreateCircuitBreaker(string $name, array $config = []): CircuitBreaker
    {
        if (!isset($this->circuitBreakers[$name])) {
            return $this->createCircuitBreaker($name, $config);
        }

        return $this->circuitBreakers[$name];
    }

    public function getAllCircuitBreakers(): array
    {
        return $this->circuitBreakers;
    }

    public function getCircuitBreakerNames(): array
    {
        return array_keys($this->circuitBreakers);
    }

    public function getCircuitBreakersCount(): int
    {
        return count($this->circuitBreakers);
    }

    public function getOpenCircuitBreakers(): array
    {
        return array_filter($this->circuitBreakers, fn(CircuitBreaker $cb) => $cb->isOpen());
    }

    public function getClosedCircuitBreakers(): array
    {
        return array_filter($this->circuitBreakers, fn(CircuitBreaker $cb) => $cb->isClosed());
    }

    public function getHalfOpenCircuitBreakers(): array
    {
        return array_filter($this->circuitBreakers, fn(CircuitBreaker $cb) => $cb->isHalfOpen());
    }

    public function resetAll(): void
    {
        foreach ($this->circuitBreakers as $circuitBreaker) {
            $circuitBreaker->reset();
        }

        $this->globalMetrics = [
            'total_requests' => 0,
            'total_failures' => 0,
            'total_successes' => 0,
            'active_breakers' => count($this->circuitBreakers),
            'open_breakers' => 0,
            'half_open_breakers' => 0,
            'closed_breakers' => count($this->circuitBreakers)
        ];
    }

    public function reset(string $name): bool
    {
        if (isset($this->circuitBreakers[$name])) {
            $this->circuitBreakers[$name]->reset();
            return true;
        }
        
        return false;
    }

    public function forceOpen(string $name): bool
    {
        if (isset($this->circuitBreakers[$name])) {
            $this->circuitBreakers[$name]->forceOpen();
            return true;
        }
        
        return false;
    }

    public function forceClose(string $name): bool
    {
        if (isset($this->circuitBreakers[$name])) {
            $this->circuitBreakers[$name]->forceClose();
            return true;
        }
        
        return false;
    }

    public function forceOpenAll(): void
    {
        foreach ($this->circuitBreakers as $circuitBreaker) {
            $circuitBreaker->forceOpen();
        }
    }

    public function forceCloseAll(): void
    {
        foreach ($this->circuitBreakers as $circuitBreaker) {
            $circuitBreaker->forceClose();
        }
    }

    public function getGlobalMetrics(): array
    {
        $this->updateGlobalMetrics();
        return $this->globalMetrics;
    }

    private function updateGlobalMetrics(): void
    {
        $totalRequests = 0;
        $totalFailures = 0;
        $totalSuccesses = 0;
        $openCount = 0;
        $halfOpenCount = 0;
        $closedCount = 0;

        foreach ($this->circuitBreakers as $circuitBreaker) {
            $metrics = $circuitBreaker->getMetrics();
            $totalRequests += $metrics['total_requests'];
            $totalFailures += $metrics['total_failures'];
            $totalSuccesses += $metrics['total_successes'];

            switch ($circuitBreaker->getState()) {
                case 'open':
                    $openCount++;
                    break;
                case 'half_open':
                    $halfOpenCount++;
                    break;
                case 'closed':
                    $closedCount++;
                    break;
            }
        }

        $this->globalMetrics = [
            'total_requests' => $totalRequests,
            'total_failures' => $totalFailures,
            'total_successes' => $totalSuccesses,
            'active_breakers' => count($this->circuitBreakers),
            'open_breakers' => $openCount,
            'half_open_breakers' => $halfOpenCount,
            'closed_breakers' => $closedCount,
            'global_failure_rate' => $totalRequests > 0 ? ($totalFailures / $totalRequests) * 100 : 0,
            'global_success_rate' => $totalRequests > 0 ? ($totalSuccesses / $totalRequests) * 100 : 0
        ];
    }

    public function getDetailedMetrics(): array
    {
        $breakerMetrics = [];
        
        foreach ($this->circuitBreakers as $name => $circuitBreaker) {
            $breakerMetrics[$name] = $circuitBreaker->getMetrics();
        }

        return [
            'global_metrics' => $this->getGlobalMetrics(),
            'circuit_breakers' => $breakerMetrics,
            'summary' => [
                'total_circuit_breakers' => count($this->circuitBreakers),
                'healthy_breakers' => count($this->getClosedCircuitBreakers()),
                'recovering_breakers' => count($this->getHalfOpenCircuitBreakers()),
                'failed_breakers' => count($this->getOpenCircuitBreakers()),
                'configurations' => $this->configs
            ]
        ];
    }

    public function getHealthStatus(): array
    {
        $globalMetrics = $this->getGlobalMetrics();
        $openBreakers = $this->getOpenCircuitBreakers();
        $halfOpenBreakers = $this->getHalfOpenCircuitBreakers();
        
        $warnings = [];
        $errors = [];

        // Check for open breakers
        if (count($openBreakers) > 0) {
            $openNames = array_keys($openBreakers);
            $errors[] = 'Open circuit breakers: ' . implode(', ', $openNames);
        }

        // Check for half-open breakers
        if (count($halfOpenBreakers) > 0) {
            $halfOpenNames = array_keys($halfOpenBreakers);
            $warnings[] = 'Recovering circuit breakers: ' . implode(', ', $halfOpenNames);
        }

        // Check global failure rate
        if ($globalMetrics['global_failure_rate'] > 25) {
            $errors[] = "High global failure rate: {$globalMetrics['global_failure_rate']}%";
        } elseif ($globalMetrics['global_failure_rate'] > 10) {
            $warnings[] = "Elevated global failure rate: {$globalMetrics['global_failure_rate']}%";
        }

        $healthyBreakers = count($this->getClosedCircuitBreakers());
        $totalBreakers = count($this->circuitBreakers);
        $healthPercentage = $totalBreakers > 0 ? ($healthyBreakers / $totalBreakers) * 100 : 100;

        return [
            'healthy' => ($errors === null || $errors === '' || $errors === []),
            'status' => ($errors === null || $errors === '' || $errors === []) ? (($warnings === null || $warnings === '' || $warnings === []) ? 'excellent' : 'good') : 'critical',
            'health_percentage' => round($healthPercentage, 1),
            'warnings' => $warnings,
            'errors' => $errors,
            'metrics' => $globalMetrics,
            'breakdown' => [
                'total_breakers' => $totalBreakers,
                'healthy_breakers' => $healthyBreakers,
                'recovering_breakers' => count($halfOpenBreakers),
                'failed_breakers' => count($openBreakers)
            ]
        ];
    }

    private function recordRequest(): void
    {
        $this->globalMetrics['total_requests']++;
    }

    private function recordSuccess(): void
    {
        $this->globalMetrics['total_successes']++;
    }

    private function recordFailure(): void
    {
        $this->globalMetrics['total_failures']++;
    }

    public function configureForService(string $serviceName, array $config = []): CircuitBreaker
    {
        $defaultServiceConfigs = [
            'api' => [
                'failure_threshold' => 5,
                'recovery_timeout' => 30,
                'success_threshold' => 3,
                'half_open_max_calls' => 3,
                'timeout' => 10
            ],
            'database' => [
                'failure_threshold' => 3,
                'recovery_timeout' => 60,
                'success_threshold' => 2,
                'half_open_max_calls' => 2,
                'timeout' => 5
            ],
            'cache' => [
                'failure_threshold' => 10,
                'recovery_timeout' => 15,
                'success_threshold' => 5,
                'half_open_max_calls' => 5,
                'timeout' => 2
            ],
            'external_service' => [
                'failure_threshold' => 5,
                'recovery_timeout' => 60,
                'success_threshold' => 3,
                'half_open_max_calls' => 3,
                'timeout' => 30
            ]
        ];

        $serviceConfig = array_merge(
            $defaultServiceConfigs[$serviceName] ?? $defaultServiceConfigs['api'],
            $config
        );

        return $this->createCircuitBreaker($serviceName, $serviceConfig);
    }

    public function createBulkProtection(array $serviceConfigs): array
    {
        $circuitBreakers = [];
        
        foreach ($serviceConfigs as $serviceName => $config) {
            $circuitBreakers[$serviceName] = $this->createCircuitBreaker($serviceName, $config);
        }
        
        return $circuitBreakers;
    }

    public function test(string $name): bool
    {
        if (!isset($this->circuitBreakers[$name])) {
            return false;
        }

        return $this->circuitBreakers[$name]->test();
    }

    public function testAll(): array
    {
        $results = [];
        
        foreach ($this->circuitBreakers as $name => $circuitBreaker) {
            $results[$name] = $circuitBreaker->test();
        }
        
        return $results;
    }

    public function getDashboardData(): array
    {
        $healthStatus = $this->getHealthStatus();
        $detailedMetrics = $this->getDetailedMetrics();
        
        return [
            'overview' => [
                'status' => $healthStatus['status'],
                'health_percentage' => $healthStatus['health_percentage'],
                'total_requests' => $detailedMetrics['global_metrics']['total_requests'],
                'failure_rate' => round($detailedMetrics['global_metrics']['global_failure_rate'], 2),
                'active_breakers' => $detailedMetrics['global_metrics']['active_breakers']
            ],
            'circuit_breakers' => array_map(function($breaker) {
                return [
                    'name' => $breaker['name'],
                    'state' => $breaker['state'],
                    'failure_rate' => $breaker['failure_rate'],
                    'requests' => $breaker['total_requests'],
                    'time_until_recovery' => $breaker['time_until_recovery']
                ];
            }, $detailedMetrics['circuit_breakers']),
            'alerts' => array_merge($healthStatus['warnings'], $healthStatus['errors']),
            'timestamp' => time()
        ];
    }

    public static function create(?CacheInterface $cache = null): self
    {
        return new self($cache);
    }

    public function __toString(): string
    {
        $metrics = $this->getGlobalMetrics();
        return sprintf(
            'CircuitBreakerManager[%d breakers] Open: %d, Closed: %d, Half-Open: %d, Global Failure Rate: %.1f%%',
            $metrics['active_breakers'],
            $metrics['open_breakers'],
            $metrics['closed_breakers'],
            $metrics['half_open_breakers'],
            $metrics['global_failure_rate']
        );
    }
}