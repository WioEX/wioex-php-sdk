<?php

declare(strict_types=1);

namespace Wioex\SDK\Retry;

use Wioex\SDK\Config;
use Wioex\SDK\Exceptions\RequestException;

class RetryManager
{
    private Config $config;
    private array $retryStats = [
        'total_attempts' => 0,
        'total_retries' => 0,
        'successful_retries' => 0,
        'failed_retries' => 0,
        'total_backoff_time' => 0
    ];
    
    private array $retryHistory = [];

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * Execute operation with retry logic
     */
    public function execute(callable $operation, array $retryConfig = []): mixed
    {
        $config = $this->mergeRetryConfig($retryConfig);
        $attempt = 0;
        $lastException = null;
        $startTime = microtime(true);

        $this->retryStats['total_attempts']++;

        while ($attempt < $config['max_attempts']) {
            $attempt++;
            $attemptStart = microtime(true);

            try {
                $result = $operation($attempt);
                
                // Log successful retry if it wasn't the first attempt
                if ($attempt > 1) {
                    $this->retryStats['successful_retries']++;
                    $this->logRetryAttempt($config, $attempt, true, $lastException, microtime(true) - $startTime);
                }

                return $result;
            } catch (\Throwable $e) {
                $lastException = $e;
                
                // Check if this exception is retryable
                if (!$this->isRetryableException($e, $config)) {
                    $this->logRetryAttempt($config, $attempt, false, $e, microtime(true) - $startTime, 'non_retryable');
                    throw $e;
                }

                // If this was the last attempt, don't retry
                if ($attempt >= $config['max_attempts']) {
                    $this->retryStats['failed_retries']++;
                    $this->logRetryAttempt($config, $attempt, false, $e, microtime(true) - $startTime, 'max_attempts');
                    break;
                }

                // Calculate delay and wait
                $delay = $this->calculateDelay($attempt, $config);
                $this->retryStats['total_retries']++;
                $this->retryStats['total_backoff_time'] += $delay;
                
                $this->logRetryAttempt($config, $attempt, false, $e, microtime(true) - $startTime, 'retrying', $delay);

                if ($delay > 0) {
                    $this->sleep($delay);
                }
            }
        }

        // All attempts exhausted
        if ($lastException) {
            throw new RequestException(
                sprintf(
                    'Operation failed after %d attempts. Last error: %s',
                    $config['max_attempts'],
                    $lastException->getMessage()
                ),
                $lastException->getCode(),
                $lastException
            );
        }

        throw new RequestException('Unexpected retry failure');
    }

    /**
     * Execute with async retry (returns promise-like structure)
     */
    public function executeAsync(callable $operation, array $retryConfig = []): array
    {
        $config = $this->mergeRetryConfig($retryConfig);
        $attempt = 0;
        $results = [];

        while ($attempt < $config['max_attempts']) {
            $attempt++;
            
            try {
                $result = $operation($attempt);
                $results[] = [
                    'attempt' => $attempt,
                    'success' => true,
                    'result' => $result,
                    'error' => null
                ];
                
                return [
                    'success' => true,
                    'result' => $result,
                    'attempts' => $attempt,
                    'history' => $results
                ];
            } catch (\Throwable $e) {
                $results[] = [
                    'attempt' => $attempt,
                    'success' => false,
                    'result' => null,
                    'error' => $e
                ];

                if (!$this->isRetryableException($e, $config) || $attempt >= $config['max_attempts']) {
                    break;
                }

                $delay = $this->calculateDelay($attempt, $config);
                if ($delay > 0) {
                    $this->sleep($delay);
                }
            }
        }

        return [
            'success' => false,
            'result' => null,
            'attempts' => $attempt,
            'history' => $results,
            'final_error' => end($results)['error']
        ];
    }

    /**
     * Bulk retry operations with different strategies
     */
    public function executeBulk(array $operations, array $retryConfig = []): array
    {
        $config = $this->mergeRetryConfig($retryConfig);
        $results = [];
        $strategy = $config['bulk_strategy'] ?? 'parallel';

        switch ($strategy) {
            case 'sequential':
                $results = $this->executeBulkSequential($operations, $config);
                break;
            case 'parallel':
                $results = $this->executeBulkParallel($operations, $config);
                break;
            case 'batch':
                $results = $this->executeBulkBatch($operations, $config);
                break;
            default:
                throw new \InvalidArgumentException("Unsupported bulk strategy: {$strategy}");
        }

        return $results;
    }

    /**
     * Calculate delay for exponential backoff
     */
    private function calculateDelay(int $attempt, array $config): float
    {
        $strategy = $config['strategy'];
        $baseDelay = $config['base_delay'] ?? 1000; // milliseconds
        $maxDelay = $config['max_delay'] ?? 30000; // 30 seconds
        $jitter = $config['jitter'] ?? true;

        $delay = match ($strategy) {
            'exponential_backoff' => $this->exponentialBackoff($attempt, $baseDelay, $config['multiplier'] ?? 2.0),
            'linear_backoff' => $this->linearBackoff($attempt, $baseDelay),
            'fixed_delay' => $baseDelay,
            'fibonacci_backoff' => $this->fibonacciBackoff($attempt, $baseDelay),
            'adaptive_backoff' => $this->adaptiveBackoff($attempt, $baseDelay, $config),
            default => $baseDelay
        };

        // Apply maximum delay limit
        $delay = min($delay, $maxDelay);

        // Apply jitter if enabled
        if ($jitter) {
            $delay = $this->applyJitter($delay, $config['jitter_type'] ?? 'full');
        }

        return $delay / 1000; // Convert to seconds
    }

    /**
     * Exponential backoff calculation
     */
    private function exponentialBackoff(int $attempt, float $baseDelay, float $multiplier): float
    {
        return $baseDelay * pow($multiplier, $attempt - 1);
    }

    /**
     * Linear backoff calculation
     */
    private function linearBackoff(int $attempt, float $baseDelay): float
    {
        return $baseDelay * $attempt;
    }

    /**
     * Fibonacci backoff calculation
     */
    private function fibonacciBackoff(int $attempt, float $baseDelay): float
    {
        $fib = $this->fibonacci($attempt);
        return $baseDelay * $fib;
    }

    /**
     * Adaptive backoff based on previous retry success/failure rates
     */
    private function adaptiveBackoff(int $attempt, float $baseDelay, array $config): float
    {
        $successRate = $this->retryStats['total_retries'] > 0 
            ? $this->retryStats['successful_retries'] / $this->retryStats['total_retries']
            : 0.5;

        // Adjust delay based on success rate
        $adaptiveFactor = $successRate > 0.7 ? 0.8 : ($successRate < 0.3 ? 1.5 : 1.0);
        
        return $this->exponentialBackoff($attempt, $baseDelay, $config['multiplier'] ?? 2.0) * $adaptiveFactor;
    }

    /**
     * Apply jitter to delay
     */
    private function applyJitter(float $delay, string $jitterType): float
    {
        return match ($jitterType) {
            'full' => $delay * mt_rand() / mt_getrandmax(),
            'equal' => $delay * 0.5 + ($delay * 0.5 * mt_rand() / mt_getrandmax()),
            'decorrelated' => min($delay * 3, mt_rand(0, (int)($delay * mt_rand() / mt_getrandmax() * 3))),
            default => $delay
        };
    }

    /**
     * Check if exception is retryable
     */
    private function isRetryableException(\Throwable $e, array $config): bool
    {
        $retryableExceptions = $config['retryable_exceptions'] ?? [
            'GuzzleHttp\\Exception\\ConnectException',
            'GuzzleHttp\\Exception\\RequestException',
            'Wioex\\SDK\\Exceptions\\RequestException',
            'Wioex\\SDK\\Exceptions\\ServerException' // Add ServerException for 503 handling
        ];

        $nonRetryableExceptions = $config['non_retryable_exceptions'] ?? [
            'Wioex\\SDK\\Exceptions\\AuthenticationException',
            'Wioex\\SDK\\Exceptions\\ValidationException',
            'InvalidArgumentException'
        ];

        // Check non-retryable first
        foreach ($nonRetryableExceptions as $exceptionClass) {
            if ($e instanceof $exceptionClass) {
                return false;
            }
        }

        // Check retryable
        foreach ($retryableExceptions as $exceptionClass) {
            if ($e instanceof $exceptionClass) {
                return true;
            }
        }

        // Check by error code if it's an HTTP-related exception
        if (method_exists($e, 'getCode')) {
            $code = $e->getCode();
            
            // Build retryable codes based on configuration
            $retryableCodes = [];
            
            // Server errors (5xx) - configurable
            if ($config['retry_on_server_errors'] ?? true) {
                $retryableCodes = array_merge($retryableCodes, [500, 501, 502, 503, 504, 505, 507, 508, 510, 511]);
            }
            
            // Rate limiting (429) - configurable
            if ($config['retry_on_rate_limit'] ?? true) {
                $retryableCodes[] = 429;
            }
            
            // Timeout/connection issues (408) - configurable  
            if ($config['retry_on_connection_errors'] ?? true) {
                $retryableCodes = array_merge($retryableCodes, [408, 598, 599]);
            }
            
            // Allow custom additional codes
            if (isset($config['custom_retryable_codes']) && is_array($config['custom_retryable_codes'])) {
                $retryableCodes = array_merge($retryableCodes, $config['custom_retryable_codes']);
            }
            
            // Non-retryable codes (client errors)
            $nonRetryableCodes = $config['non_retryable_status_codes'] ?? [400, 401, 403, 404, 405, 406, 409, 410, 422];

            if (in_array($code, $nonRetryableCodes)) {
                return false;
            }
            
            if (in_array($code, $retryableCodes)) {
                return true;
            }
        }

        // Default behavior based on configuration
        return $config['default_retryable'] ?? false;
    }

    /**
     * Execute bulk operations sequentially
     */
    private function executeBulkSequential(array $operations, array $config): array
    {
        $results = [];
        
        foreach ($operations as $key => $operation) {
            try {
                $result = $this->execute($operation, $config);
                $results[$key] = ['success' => true, 'result' => $result, 'error' => null];
            } catch (\Throwable $e) {
                $results[$key] = ['success' => false, 'result' => null, 'error' => $e];
            }
        }

        return $results;
    }

    /**
     * Execute bulk operations in parallel (simulated)
     */
    private function executeBulkParallel(array $operations, array $config): array
    {
        // In a real implementation, this would use actual parallel processing
        // For now, we'll simulate it by executing them quickly in sequence
        $results = [];
        
        foreach ($operations as $key => $operation) {
            try {
                $result = $this->execute($operation, $config);
                $results[$key] = ['success' => true, 'result' => $result, 'error' => null];
            } catch (\Throwable $e) {
                $results[$key] = ['success' => false, 'result' => null, 'error' => $e];
            }
        }

        return $results;
    }

    /**
     * Execute bulk operations in batches
     */
    private function executeBulkBatch(array $operations, array $config): array
    {
        $batchSize = $config['batch_size'] ?? 5;
        $batches = array_chunk($operations, $batchSize, true);
        $results = [];

        foreach ($batches as $batch) {
            foreach ($batch as $key => $operation) {
                try {
                    $result = $this->execute($operation, $config);
                    $results[$key] = ['success' => true, 'result' => $result, 'error' => null];
                } catch (\Throwable $e) {
                    $results[$key] = ['success' => false, 'result' => null, 'error' => $e];
                }
            }
            
            // Add delay between batches
            $batchDelay = $config['batch_delay'] ?? 0;
            if ($batchDelay > 0) {
                $this->sleep($batchDelay / 1000);
            }
        }

        return $results;
    }

    /**
     * Merge retry configuration with defaults
     */
    private function mergeRetryConfig(array $config): array
    {
        $defaultConfig = $this->config->get('retry', []);
        $enhancedRetryConfig = $this->config->getEnhancedRetryConfig();
        
        return array_merge([
            'strategy' => 'exponential_backoff',
            'max_attempts' => $enhancedRetryConfig['attempts'] ?? 3,
            'base_delay' => $enhancedRetryConfig['base_delay'] ?? 1000, // ms
            'max_delay' => $enhancedRetryConfig['max_delay'] ?? 30000, // ms
            'multiplier' => $enhancedRetryConfig['exponential_base'] ?? 2.0,
            'jitter' => $enhancedRetryConfig['jitter'] ?? true,
            'jitter_type' => 'full',
            'retryable_exceptions' => [
                'GuzzleHttp\\Exception\\ConnectException',
                'GuzzleHttp\\Exception\\RequestException',
                'Wioex\\SDK\\Exceptions\\ServerException'
            ],
            'non_retryable_exceptions' => [
                'InvalidArgumentException',
                'Wioex\\SDK\\Exceptions\\AuthenticationException',
                'Wioex\\SDK\\Exceptions\\ValidationException'
            ],
            // Use enhanced retry config for global error handling
            'retry_on_server_errors' => $enhancedRetryConfig['retry_on_server_errors'] ?? true,
            'retry_on_connection_errors' => $enhancedRetryConfig['retry_on_connection_errors'] ?? true,
            'retry_on_rate_limit' => $enhancedRetryConfig['retry_on_rate_limit'] ?? true,
            'non_retryable_status_codes' => [400, 401, 403, 404, 405, 406, 409, 410, 422],
            'default_retryable' => false,
            'bulk_strategy' => 'parallel',
            'batch_size' => 5,
            'batch_delay' => 1000
        ], $defaultConfig, $config);
    }

    /**
     * Log retry attempt
     */
    private function logRetryAttempt(array $config, int $attempt, bool $success, ?\Throwable $exception, float $totalTime, ?string $status = null, ?float $delay = null): void
    {
        $logEntry = [
            'timestamp' => microtime(true),
            'attempt' => $attempt,
            'max_attempts' => $config['max_attempts'],
            'success' => $success,
            'status' => $status,
            'delay' => $delay,
            'total_time' => $totalTime,
            'strategy' => $config['strategy'],
            'exception' => $exception ? [
                'type' => get_class($exception),
                'message' => $exception->getMessage(),
                'code' => $exception->getCode()
            ] : null
        ];

        $this->retryHistory[] = $logEntry;

        // Limit history size
        $maxHistorySize = $this->config->get('retry.max_history_size', 1000);
        if (count($this->retryHistory) > $maxHistorySize) {
            $this->retryHistory = array_slice($this->retryHistory, -$maxHistorySize);
        }
    }

    /**
     * Sleep implementation (can be mocked for testing)
     */
    protected function sleep(float $seconds): void
    {
        usleep((int)($seconds * 1000000));
    }

    /**
     * Calculate Fibonacci number
     */
    private function fibonacci(int $n): int
    {
        if ($n <= 1) return $n;
        
        $a = 0;
        $b = 1;
        
        for ($i = 2; $i <= $n; $i++) {
            $temp = $a + $b;
            $a = $b;
            $b = $temp;
        }
        
        return $b;
    }

    /**
     * Get retry statistics
     */
    public function getRetryStatistics(): array
    {
        $totalAttempts = $this->retryStats['total_attempts'];
        $totalRetries = $this->retryStats['total_retries'];
        
        return array_merge($this->retryStats, [
            'retry_rate' => $totalAttempts > 0 ? ($totalRetries / $totalAttempts) * 100 : 0,
            'success_rate' => $totalRetries > 0 ? ($this->retryStats['successful_retries'] / $totalRetries) * 100 : 100,
            'average_backoff_time' => $totalRetries > 0 ? $this->retryStats['total_backoff_time'] / $totalRetries : 0,
            'history_entries' => count($this->retryHistory)
        ]);
    }

    /**
     * Get retry history with filtering
     */
    public function getRetryHistory(array $filters = []): array
    {
        $history = $this->retryHistory;

        if (isset($filters['success'])) {
            $history = array_filter($history, fn($entry) => $entry['success'] === $filters['success']);
        }

        if (isset($filters['strategy'])) {
            $history = array_filter($history, fn($entry) => $entry['strategy'] === $filters['strategy']);
        }

        if (isset($filters['since'])) {
            $history = array_filter($history, fn($entry) => $entry['timestamp'] >= $filters['since']);
        }

        if (isset($filters['min_attempts'])) {
            $history = array_filter($history, fn($entry) => $entry['attempt'] >= $filters['min_attempts']);
        }

        return array_values($history);
    }

    /**
     * Analyze retry patterns
     */
    public function analyzeRetryPatterns(): array
    {
        if (($this->retryHistory === null || $this->retryHistory === '' || $this->retryHistory === [])) {
            return ['no_data' => true];
        }

        $strategies = [];
        $exceptionTypes = [];
        $attempts = [];
        $delays = [];

        foreach ($this->retryHistory as $entry) {
            $strategies[$entry['strategy']] = ($strategies[$entry['strategy']] ?? 0) + 1;
            $attempts[] = $entry['attempt'];
            
            if ($entry['delay'] !== null) {
                $delays[] = $entry['delay'];
            }

            if ($entry['exception']) {
                $type = $entry['exception']['type'];
                $exceptionTypes[$type] = ($exceptionTypes[$type] ?? 0) + 1;
            }
        }

        return [
            'total_entries' => count($this->retryHistory),
            'strategy_distribution' => $strategies,
            'exception_distribution' => $exceptionTypes,
            'attempt_statistics' => [
                'min' => min($attempts),
                'max' => max($attempts),
                'average' => array_sum($attempts) / count($attempts)
            ],
            'delay_statistics' => ($delays !== null && $delays !== '' && $delays !== []) ? [
                'min' => min($delays),
                'max' => max($delays),
                'average' => array_sum($delays) / count($delays)
            ] : ['no_delay_data' => true]
        ];
    }

    /**
     * Generate retry recommendations
     */
    public function generateRetryRecommendations(): array
    {
        $stats = $this->getRetryStatistics();
        $patterns = $this->analyzeRetryPatterns();
        $recommendations = [];

        // Check success rate
        if ($stats['success_rate'] < 50) {
            $recommendations[] = [
                'type' => 'success_rate',
                'priority' => 'high',
                'message' => sprintf('Retry success rate is low (%.1f%%). Consider adjusting retry strategy or reviewing error conditions.', $stats['success_rate'])
            ];
        }

        // Check retry rate
        if ($stats['retry_rate'] > 30) {
            $recommendations[] = [
                'type' => 'retry_rate',
                'priority' => 'medium',
                'message' => sprintf('High retry rate (%.1f%%). Consider implementing circuit breakers or rate limiting.', $stats['retry_rate'])
            ];
        }

        // Check average backoff time
        if ($stats['average_backoff_time'] > 5000) {
            $recommendations[] = [
                'type' => 'backoff_time',
                'priority' => 'low',
                'message' => sprintf('Average backoff time is high (%.1fs). Consider reducing max delay or adjusting strategy.', $stats['average_backoff_time'] / 1000)
            ];
        }

        // Analyze exception patterns
        if (($patterns['exception_distribution'] !== null && $patterns['exception_distribution'] !== '' && $patterns['exception_distribution'] !== [])) {
            $topException = array_keys($patterns['exception_distribution'], max($patterns['exception_distribution']))[0];
            $topCount = max($patterns['exception_distribution']);
            
            if ($topCount > count($this->retryHistory) * 0.5) {
                $recommendations[] = [
                    'type' => 'exception_pattern',
                    'priority' => 'medium',
                    'message' => sprintf('Frequent %s exceptions (%d occurrences). Consider addressing root cause.', basename($topException), $topCount)
                ];
            }
        }

        return $recommendations;
    }

    /**
     * Reset retry statistics and history
     */
    public function resetStatistics(): void
    {
        $this->retryStats = [
            'total_attempts' => 0,
            'total_retries' => 0,
            'successful_retries' => 0,
            'failed_retries' => 0,
            'total_backoff_time' => 0
        ];
        
        $this->retryHistory = [];
    }

    /**
     * Get current configuration
     */
    public function getCurrentConfig(): array
    {
        return $this->mergeRetryConfig([]);
    }

    /**
     * Test retry configuration with dry run
     */
    public function testRetryConfig(array $config, int $simulatedFailures = 2): array
    {
        $testConfig = $this->mergeRetryConfig($config);
        $results = [];
        
        for ($attempt = 1; $attempt <= $testConfig['max_attempts']; $attempt++) {
            $delay = $attempt < $simulatedFailures ? $this->calculateDelay($attempt, $testConfig) : 0;
            $wouldRetry = $attempt < $simulatedFailures && $attempt < $testConfig['max_attempts'];
            
            $results[] = [
                'attempt' => $attempt,
                'would_succeed' => $attempt > $simulatedFailures,
                'delay' => $delay,
                'would_retry' => $wouldRetry
            ];
            
            if ($attempt > $simulatedFailures) {
                break;
            }
        }

        return [
            'config' => $testConfig,
            'simulated_failures' => $simulatedFailures,
            'results' => $results,
            'total_delay' => array_sum(array_column($results, 'delay')),
            'would_succeed' => end($results)['would_succeed']
        ];
    }
}