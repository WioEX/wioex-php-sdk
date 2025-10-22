<?php

declare(strict_types=1);

namespace Wioex\SDK\Http;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class RetryHandler
{
    private int $maxRetries;
    private int $baseDelay;
    private int $multiplier;
    private int $maxDelay;

    // Enhanced retry configuration
    private bool $enhancedMode;
    private string $backoffStrategy;
    private bool $jitter;
    private float $exponentialBase;
    private array $retryableStatusCodes;
    private array $nonRetryableStatusCodes;

    /**
     * @param array{times?: int, delay?: int, multiplier?: int, max_delay?: int} $config Legacy retry config
     * @param array{enabled?: bool, attempts?: int, backoff?: string, base_delay?: int, max_delay?: int, jitter?: bool, exponential_base?: float}|null $enhancedConfig Enhanced retry config
     */
    public function __construct(array $config, ?array $enhancedConfig = null)
    {
        // Legacy configuration (backward compatibility)
        $this->maxRetries = $config['times'] ?? 3;
        $this->baseDelay = $config['delay'] ?? 100;
        $this->multiplier = $config['multiplier'] ?? 2;
        $this->maxDelay = $config['max_delay'] ?? 5000;

        // Enhanced configuration
        $this->enhancedMode = $enhancedConfig !== null && ($enhancedConfig['enabled'] ?? false);

        if ($this->enhancedMode) {
            $this->maxRetries = $enhancedConfig['attempts'] ?? 5;
            $this->baseDelay = $enhancedConfig['base_delay'] ?? 100;
            $this->maxDelay = $enhancedConfig['max_delay'] ?? 30000;
            $this->backoffStrategy = $enhancedConfig['backoff'] ?? 'exponential';
            $this->jitter = $enhancedConfig['jitter'] ?? true;
            $this->exponentialBase = $enhancedConfig['exponential_base'] ?? 2.0;
        } else {
            $this->backoffStrategy = 'exponential';
            $this->jitter = false;
            $this->exponentialBase = 2.0;
        }

        // Define retryable and non-retryable status codes
        $this->retryableStatusCodes = [429, 500, 502, 503, 504];
        $this->nonRetryableStatusCodes = [400, 401, 403, 404, 422]; // Client errors that shouldn't be retried
    }

    /**
     * @psalm-suppress PossiblyUnusedParam
     */
    public function __invoke(
        int $retryCount,
        RequestInterface $request,
        ?ResponseInterface $response = null,
        ?\Exception $exception = null
    ): bool {
        // Don't retry if we've exceeded max retries
        if ($retryCount >= $this->maxRetries) {
            return false;
        }

        // Determine if we should retry based on exception or response
        $shouldRetry = $this->shouldRetry($exception, $response);

        if ($shouldRetry) {
            $this->sleepWithStrategy($retryCount, $response);
            return true;
        }

        return false;
    }

    /**
     * Determine if the request should be retried
     */
    private function shouldRetry(?\Exception $exception, ?ResponseInterface $response): bool
    {
        // Always retry connection exceptions
        if ($exception instanceof ConnectException || $exception instanceof RequestException) {
            return true;
        }

        if ($response === null) {
            return false;
        }

        $statusCode = $response->getStatusCode();

        // Don't retry client errors that are definitely not retryable
        if (in_array($statusCode, $this->nonRetryableStatusCodes, true)) {
            return false;
        }

        // Retry specific status codes
        if (in_array($statusCode, $this->retryableStatusCodes, true)) {
            return true;
        }

        // In enhanced mode, be more liberal with retries for server errors
        if ($this->enhancedMode && $statusCode >= 500) {
            return true;
        }

        return false;
    }

    /**
     * Sleep with the configured strategy, handling Retry-After headers
     */
    private function sleepWithStrategy(int $retryCount, ?ResponseInterface $response): void
    {
        // Handle Retry-After header for 429 responses
        if ($response !== null && $response->getStatusCode() === 429 && $response->hasHeader('Retry-After')) {
            $retryAfter = (int) $response->getHeaderLine('Retry-After');
            $delay = $retryAfter * 1000; // Convert to milliseconds

            // Apply jitter even to Retry-After delays if enabled
            if ($this->jitter) {
                $delay = $this->applyJitter($delay);
            }

            usleep(max(0, (int) ($delay * 1000))); // Convert to microseconds
            return;
        }

        // Calculate delay based on strategy
        $delay = $this->calculateDelay($retryCount);

        // Apply jitter if enabled
        if ($this->jitter) {
            $delay = $this->applyJitter($delay);
        }

        // Ensure delay doesn't exceed maximum
        $delay = min($delay, $this->maxDelay);

        usleep(max(0, (int) ($delay * 1000))); // Convert milliseconds to microseconds
    }

    /**
     * Calculate delay based on backoff strategy
     */
    private function calculateDelay(int $retryCount): int
    {
        return match ($this->backoffStrategy) {
            'exponential' => $this->calculateExponentialDelay($retryCount),
            'linear' => $this->calculateLinearDelay($retryCount),
            'fixed' => $this->baseDelay,
            default => $this->calculateExponentialDelay($retryCount),
        };
    }

    /**
     * Calculate exponential backoff delay
     */
    private function calculateExponentialDelay(int $retryCount): int
    {
        if ($this->enhancedMode) {
            return (int) ($this->baseDelay * ($this->exponentialBase ** $retryCount));
        }

        // Legacy exponential backoff
        return $this->baseDelay * ($this->multiplier ** $retryCount);
    }

    /**
     * Calculate linear backoff delay
     */
    private function calculateLinearDelay(int $retryCount): int
    {
        return $this->baseDelay + ($this->baseDelay * $retryCount);
    }

    /**
     * Apply jitter to delay to avoid thundering herd
     */
    private function applyJitter(int $delay): int
    {
        // Add random jitter of ±25%
        $jitterRange = (int) ($delay * 0.25);
        $jitter = mt_rand(-$jitterRange, $jitterRange);

        return max($this->baseDelay, $delay + $jitter);
    }

    /**
     * Legacy sleep method for backward compatibility
     */
    private function sleep(int $retryCount): void
    {
        $this->sleepWithStrategy($retryCount, null);
    }

    public function getMaxRetries(): int
    {
        return $this->maxRetries;
    }

    /**
     * Check if enhanced retry mode is enabled
     */
    public function isEnhancedMode(): bool
    {
        return $this->enhancedMode;
    }

    /**
     * Get the current backoff strategy
     */
    public function getBackoffStrategy(): string
    {
        return $this->backoffStrategy;
    }

    /**
     * Check if jitter is enabled
     */
    public function isJitterEnabled(): bool
    {
        return $this->jitter;
    }

    /**
     * Get retry statistics for debugging
     */
    public function getRetryStats(): array
    {
        return [
            'enhanced_mode' => $this->enhancedMode,
            'max_retries' => $this->maxRetries,
            'base_delay_ms' => $this->baseDelay,
            'max_delay_ms' => $this->maxDelay,
            'backoff_strategy' => $this->backoffStrategy,
            'jitter_enabled' => $this->jitter,
            'exponential_base' => $this->exponentialBase,
            'retryable_status_codes' => $this->retryableStatusCodes,
            'non_retryable_status_codes' => $this->nonRetryableStatusCodes,
        ];
    }

    /**
     * Simulate delay calculation for testing/debugging
     */
    public function simulateDelay(int $retryCount): int
    {
        $delay = $this->calculateDelay($retryCount);

        if ($this->jitter) {
            // For simulation, return the average jitter effect
            return $delay;
        }

        return min($delay, $this->maxDelay);
    }
}
