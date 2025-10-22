<?php

declare(strict_types=1);

namespace Wioex\SDK\Http;

use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Rate limiting middleware for WioEX SDK
 * 
 * Implements various rate limiting strategies to prevent API quota exhaustion
 * and ensure compliance with WioEX API limits.
 */
class RateLimitingMiddleware
{
    private bool $enabled;
    private int $maxRequests;
    private int $windowSeconds;
    private string $strategy;
    private int $burstAllowance;
    
    // Sliding window tracking
    private array $requestTimes = [];
    
    // Token bucket implementation
    private float $tokens;
    private float $lastRefill;
    private float $refillRate;
    
    // Fixed window tracking
    private int $fixedWindowStart;
    private int $fixedWindowCount;

    /**
     * @param array{enabled: bool, requests: int, window: int, strategy: string, burst_allowance: int} $config
     */
    public function __construct(array $config)
    {
        $this->enabled = $config['enabled'];
        $this->maxRequests = $config['requests'];
        $this->windowSeconds = $config['window'];
        $this->strategy = $config['strategy'];
        $this->burstAllowance = $config['burst_allowance'];
        
        // Initialize strategy-specific state
        $this->initializeStrategy();
    }

    /**
     * Guzzle middleware function
     */
    public function __invoke(callable $handler): callable
    {
        return function (RequestInterface $request, array $options) use ($handler) {
            if (!$this->enabled) {
                return $handler($request, $options);
            }

            // Check rate limit before making request
            $waitTime = $this->checkRateLimit();
            
            if ($waitTime > 0) {
                // If we need to wait, delay the request
                return $this->delayRequest($handler, $request, $options, $waitTime);
            }

            // Record the request
            $this->recordRequest();

            return $handler($request, $options);
        };
    }

    /**
     * Initialize strategy-specific state
     */
    private function initializeStrategy(): void
    {
        $now = microtime(true);
        
        switch ($this->strategy) {
            case 'token_bucket':
                $this->tokens = (float) ($this->maxRequests + $this->burstAllowance);
                $this->lastRefill = $now;
                $this->refillRate = ($this->maxRequests + $this->burstAllowance) / $this->windowSeconds;
                break;
                
            case 'fixed_window':
                $this->fixedWindowStart = (int) $now;
                $this->fixedWindowCount = 0;
                break;
                
            case 'sliding_window':
            default:
                // Sliding window uses requestTimes array, no additional initialization needed
                break;
        }
    }

    /**
     * Check if request should be rate limited
     * 
     * @return int Wait time in milliseconds (0 if no wait needed)
     */
    private function checkRateLimit(): int
    {
        return match ($this->strategy) {
            'sliding_window' => $this->checkSlidingWindow(),
            'fixed_window' => $this->checkFixedWindow(),
            'token_bucket' => $this->checkTokenBucket(),
            default => $this->checkSlidingWindow(),
        };
    }

    /**
     * Check sliding window rate limit
     */
    private function checkSlidingWindow(): int
    {
        $now = microtime(true);
        $windowStart = $now - $this->windowSeconds;
        
        // Remove old requests outside the window
        $this->requestTimes = array_filter(
            $this->requestTimes,
            fn($time) => $time > $windowStart
        );
        
        // Check if we're at the limit
        if (count($this->requestTimes) >= $this->maxRequests) {
            // Calculate wait time until oldest request falls out of window
            $oldestRequest = min($this->requestTimes);
            $waitTime = ($oldestRequest + $this->windowSeconds - $now) * 1000;
            return max(0, (int) ceil($waitTime));
        }
        
        return 0;
    }

    /**
     * Check fixed window rate limit
     */
    private function checkFixedWindow(): int
    {
        $now = (int) microtime(true);
        
        // Check if we're in a new window
        if ($now - $this->fixedWindowStart >= $this->windowSeconds) {
            $this->fixedWindowStart = $now;
            $this->fixedWindowCount = 0;
        }
        
        // Check if we're at the limit
        if ($this->fixedWindowCount >= $this->maxRequests) {
            $windowEnd = $this->fixedWindowStart + $this->windowSeconds;
            $waitTime = ($windowEnd - $now) * 1000;
            return max(0, (int) $waitTime);
        }
        
        return 0;
    }

    /**
     * Check token bucket rate limit
     */
    private function checkTokenBucket(): int
    {
        $now = microtime(true);
        
        // Refill tokens based on elapsed time
        $elapsed = $now - $this->lastRefill;
        $tokensToAdd = $elapsed * $this->refillRate;
        $this->tokens = min(
            $this->maxRequests + $this->burstAllowance,
            $this->tokens + $tokensToAdd
        );
        $this->lastRefill = $now;
        
        // Check if we have enough tokens
        if ($this->tokens < 1.0) {
            // Calculate wait time for next token
            $waitTime = (1.0 / $this->refillRate) * 1000;
            return (int) ceil($waitTime);
        }
        
        return 0;
    }

    /**
     * Record a request for rate limiting purposes
     */
    private function recordRequest(): void
    {
        $now = microtime(true);
        
        switch ($this->strategy) {
            case 'sliding_window':
                $this->requestTimes[] = $now;
                break;
                
            case 'fixed_window':
                $this->fixedWindowCount++;
                break;
                
            case 'token_bucket':
                $this->tokens -= 1.0;
                break;
        }
    }

    /**
     * Delay a request due to rate limiting
     */
    private function delayRequest(
        callable $handler,
        RequestInterface $request,
        array $options,
        int $waitTimeMs
    ): PromiseInterface {
        // Create a promise that will resolve after the delay
        $promise = new Promise(function () use (&$promise, $handler, $request, $options, $waitTimeMs) {
            // Sleep for the required time
            usleep($waitTimeMs * 1000);
            
            // Record the request after delay
            $this->recordRequest();
            
            // Make the actual request
            $actualPromise = $handler($request, $options);
            
            if ($actualPromise instanceof PromiseInterface) {
                $actualPromise->then(
                    function ($response) use ($promise) {
                        $promise->resolve($response);
                    },
                    function ($reason) use ($promise) {
                        $promise->reject($reason);
                    }
                );
            } else {
                $promise->resolve($actualPromise);
            }
        });

        return $promise;
    }

    /**
     * Get current rate limiting status
     */
    public function getStatus(): array
    {
        $now = microtime(true);
        
        $status = [
            'enabled' => $this->enabled,
            'strategy' => $this->strategy,
            'max_requests' => $this->maxRequests,
            'window_seconds' => $this->windowSeconds,
            'burst_allowance' => $this->burstAllowance,
        ];
        
        switch ($this->strategy) {
            case 'sliding_window':
                $windowStart = $now - $this->windowSeconds;
                $activeRequests = array_filter(
                    $this->requestTimes,
                    fn($time) => $time > $windowStart
                );
                $status['current_requests'] = count($activeRequests);
                $status['remaining_requests'] = max(0, $this->maxRequests - count($activeRequests));
                break;
                
            case 'fixed_window':
                if ($now - $this->fixedWindowStart >= $this->windowSeconds) {
                    $status['current_requests'] = 0;
                    $status['remaining_requests'] = $this->maxRequests;
                } else {
                    $status['current_requests'] = $this->fixedWindowCount;
                    $status['remaining_requests'] = max(0, $this->maxRequests - $this->fixedWindowCount);
                }
                $status['window_reset_in'] = max(0, $this->windowSeconds - ($now - $this->fixedWindowStart));
                break;
                
            case 'token_bucket':
                // Refresh tokens before reporting
                $elapsed = $now - $this->lastRefill;
                $tokensToAdd = $elapsed * $this->refillRate;
                $currentTokens = min(
                    $this->maxRequests + $this->burstAllowance,
                    $this->tokens + $tokensToAdd
                );
                
                $status['current_tokens'] = $currentTokens;
                $status['max_tokens'] = $this->maxRequests + $this->burstAllowance;
                $status['refill_rate'] = $this->refillRate;
                break;
        }
        
        return $status;
    }

    /**
     * Reset rate limiting state (useful for testing)
     */
    public function reset(): void
    {
        $this->requestTimes = [];
        $this->initializeStrategy();
    }

    /**
     * Check if rate limit would be exceeded by making a request
     */
    public function wouldExceedLimit(): bool
    {
        return $this->checkRateLimit() > 0;
    }

    /**
     * Get estimated wait time before next request can be made
     */
    public function getWaitTime(): int
    {
        return $this->checkRateLimit();
    }

    /**
     * Get rate limiting statistics for monitoring
     */
    public function getStatistics(): array
    {
        $status = $this->getStatus();
        
        return [
            'strategy' => $this->strategy,
            'configuration' => [
                'max_requests' => $this->maxRequests,
                'window_seconds' => $this->windowSeconds,
                'burst_allowance' => $this->burstAllowance,
            ],
            'current_status' => $status,
            'efficiency_metrics' => $this->calculateEfficiencyMetrics(),
        ];
    }

    /**
     * Calculate efficiency metrics for the rate limiter
     */
    private function calculateEfficiencyMetrics(): array
    {
        $now = microtime(true);
        
        switch ($this->strategy) {
            case 'sliding_window':
                $windowStart = $now - $this->windowSeconds;
                $recentRequests = array_filter(
                    $this->requestTimes,
                    fn($time) => $time > $windowStart
                );
                $utilization = count($recentRequests) / $this->maxRequests;
                break;
                
            case 'fixed_window':
                if ($now - $this->fixedWindowStart >= $this->windowSeconds) {
                    $utilization = 0.0;
                } else {
                    $utilization = $this->fixedWindowCount / $this->maxRequests;
                }
                break;
                
            case 'token_bucket':
                $elapsed = $now - $this->lastRefill;
                $tokensToAdd = $elapsed * $this->refillRate;
                $currentTokens = min(
                    $this->maxRequests + $this->burstAllowance,
                    $this->tokens + $tokensToAdd
                );
                $utilization = 1.0 - ($currentTokens / ($this->maxRequests + $this->burstAllowance));
                break;
                
            default:
                $utilization = 0.0;
        }
        
        return [
            'utilization_percentage' => round($utilization * 100, 2),
            'requests_per_second' => $this->calculateRequestsPerSecond(),
            'average_wait_time_ms' => $this->calculateAverageWaitTime(),
        ];
    }

    /**
     * Calculate recent requests per second
     */
    private function calculateRequestsPerSecond(): float
    {
        if ($this->strategy !== 'sliding_window' || empty($this->requestTimes)) {
            return 0.0;
        }
        
        $now = microtime(true);
        $oneSecondAgo = $now - 1.0;
        
        $recentRequests = array_filter(
            $this->requestTimes,
            fn($time) => $time > $oneSecondAgo
        );
        
        return (float) count($recentRequests);
    }

    /**
     * Calculate average wait time (simplified estimation)
     */
    private function calculateAverageWaitTime(): float
    {
        // This is a simplified calculation
        // In a real implementation, you might track actual wait times
        $currentWaitTime = $this->checkRateLimit();
        return (float) $currentWaitTime;
    }
}