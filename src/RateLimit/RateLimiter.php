<?php

declare(strict_types=1);

namespace Wioex\SDK\RateLimit;

use Wioex\SDK\Config;
use Wioex\SDK\Cache\CacheInterface;
use Wioex\SDK\Exceptions\RateLimitException;

class RateLimiter
{
    private Config $config;
    private ?CacheInterface $cache;
    private array $buckets = [];
    private array $metrics = [
        'total_requests' => 0,
        'allowed_requests' => 0,
        'blocked_requests' => 0,
        'burst_requests' => 0
    ];

    public function __construct(Config $config, ?CacheInterface $cache = null)
    {
        $this->config = $config;
        $this->cache = $cache;
    }

    /**
     * Check if request is allowed under rate limiting rules
     */
    public function isAllowed(string $identifier, string $category = 'default'): bool
    {
        $this->metrics['total_requests']++;
        
        $rateLimitConfig = $this->config->get('rate_limiting', []);
        if (!($rateLimitConfig['enabled'] ?? false)) {
            $this->metrics['allowed_requests']++;
            return true;
        }

        $categoryConfig = $rateLimitConfig['categories'][$category] ?? $rateLimitConfig['default'] ?? [];
        
        // Token bucket algorithm with burst support
        if ($this->checkTokenBucket($identifier, $category, $categoryConfig)) {
            $this->metrics['allowed_requests']++;
            return true;
        }

        $this->metrics['blocked_requests']++;
        return false;
    }

    /**
     * Allow request and consume tokens
     */
    public function consume(string $identifier, int $tokens = 1, string $category = 'default'): bool
    {
        if (!$this->isAllowed($identifier, $category)) {
            throw new RateLimitException("Rate limit exceeded for identifier: {$identifier}");
        }

        return $this->consumeTokens($identifier, $category, $tokens);
    }

    /**
     * Check remaining tokens for an identifier
     */
    public function getRemainingTokens(string $identifier, string $category = 'default'): int
    {
        $bucket = $this->getTokenBucket($identifier, $category);
        return max(0, (int) $bucket['tokens']);
    }

    /**
     * Get time until next token replenishment
     */
    public function getRetryAfter(string $identifier, string $category = 'default'): int
    {
        $rateLimitConfig = $this->config->get('rate_limiting', []);
        $categoryConfig = $rateLimitConfig['categories'][$category] ?? $rateLimitConfig['default'] ?? [];
        
        $refillRate = $categoryConfig['refill_rate'] ?? 1;
        $refillPeriod = $categoryConfig['refill_period'] ?? 1;
        
        return (int) ceil($refillPeriod / $refillRate);
    }

    /**
     * Token bucket algorithm implementation
     */
    private function checkTokenBucket(string $identifier, string $category, array $config): bool
    {
        $bucket = $this->getTokenBucket($identifier, $category);
        $now = microtime(true);
        
        $maxTokens = $config['max_requests'] ?? 100;
        $refillRate = $config['refill_rate'] ?? 1; // tokens per period
        $refillPeriod = $config['refill_period'] ?? 1; // seconds
        $burstMultiplier = $config['burst_multiplier'] ?? 1.5;
        $maxBurstTokens = (int) ($maxTokens * $burstMultiplier);

        // Calculate tokens to add based on elapsed time
        $timePassed = $now - $bucket['last_refill'];
        $tokensToAdd = ($timePassed / $refillPeriod) * $refillRate;
        
        // Update bucket
        $bucket['tokens'] = min($maxBurstTokens, $bucket['tokens'] + $tokensToAdd);
        $bucket['last_refill'] = $now;
        
        // Check if request can be allowed
        $canAllow = $bucket['tokens'] >= 1;
        
        if ($canAllow) {
            $bucket['tokens']--;
            
            // Track burst usage
            if ($bucket['tokens'] > $maxTokens) {
                $this->metrics['burst_requests']++;
            }
        }
        
        // Update bucket in storage
        $this->updateTokenBucket($identifier, $category, $bucket);
        
        return $canAllow;
    }

    /**
     * Get token bucket for identifier and category
     */
    private function getTokenBucket(string $identifier, string $category): array
    {
        $key = $this->getBucketKey($identifier, $category);
        
        // Try cache first
        if ($this->cache) {
            $bucket = $this->cache->get($key);
            if ($bucket !== null) {
                return $bucket;
            }
        }
        
        // Try memory cache
        if (isset($this->buckets[$key])) {
            return $this->buckets[$key];
        }
        
        // Initialize new bucket
        $rateLimitConfig = $this->config->get('rate_limiting', []);
        $categoryConfig = $rateLimitConfig['categories'][$category] ?? $rateLimitConfig['default'] ?? [];
        $maxTokens = $categoryConfig['max_requests'] ?? 100;
        
        return [
            'tokens' => $maxTokens,
            'last_refill' => microtime(true),
            'created_at' => time(),
            'category' => $category,
            'identifier' => $identifier
        ];
    }

    /**
     * Update token bucket in storage
     */
    private function updateTokenBucket(string $identifier, string $category, array $bucket): void
    {
        $key = $this->getBucketKey($identifier, $category);
        
        // Update memory cache
        $this->buckets[$key] = $bucket;
        
        // Update persistent cache if available
        if ($this->cache) {
            $ttl = $this->config->get('rate_limiting.bucket_ttl', 3600);
            $this->cache->set($key, $bucket, $ttl);
        }
    }

    /**
     * Consume tokens from bucket
     */
    private function consumeTokens(string $identifier, string $category, int $tokens): bool
    {
        $bucket = $this->getTokenBucket($identifier, $category);
        
        if ($bucket['tokens'] >= $tokens) {
            $bucket['tokens'] -= $tokens;
            $this->updateTokenBucket($identifier, $category, $bucket);
            return true;
        }
        
        return false;
    }

    /**
     * Generate cache key for bucket
     */
    private function getBucketKey(string $identifier, string $category): string
    {
        return "rate_limit_bucket:{$category}:{$identifier}";
    }

    /**
     * Clear rate limiting data for identifier
     */
    public function clearLimits(string $identifier, ?string $category = null): void
    {
        if ($category !== null) {
            $key = $this->getBucketKey($identifier, $category);
            unset($this->buckets[$key]);
            
            if ($this->cache) {
                $this->cache->delete($key);
            }
        } else {
            // Clear all categories for identifier
            $rateLimitConfig = $this->config->get('rate_limiting', []);
            $categories = array_keys($rateLimitConfig['categories'] ?? []);
            $categories[] = 'default';
            
            foreach ($categories as $cat) {
                $this->clearLimits($identifier, $cat);
            }
        }
    }

    /**
     * Get rate limiting status for identifier
     */
    public function getStatus(string $identifier, string $category = 'default'): array
    {
        $bucket = $this->getTokenBucket($identifier, $category);
        $rateLimitConfig = $this->config->get('rate_limiting', []);
        $categoryConfig = $rateLimitConfig['categories'][$category] ?? $rateLimitConfig['default'] ?? [];
        
        $maxTokens = $categoryConfig['max_requests'] ?? 100;
        $burstMultiplier = $categoryConfig['burst_multiplier'] ?? 1.5;
        $maxBurstTokens = (int) ($maxTokens * $burstMultiplier);
        
        return [
            'identifier' => $identifier,
            'category' => $category,
            'current_tokens' => (int) $bucket['tokens'],
            'max_tokens' => $maxTokens,
            'max_burst_tokens' => $maxBurstTokens,
            'refill_rate' => $categoryConfig['refill_rate'] ?? 1,
            'refill_period' => $categoryConfig['refill_period'] ?? 1,
            'retry_after' => $this->getRetryAfter($identifier, $category),
            'last_refill' => $bucket['last_refill'],
            'usage_percentage' => round((1 - ($bucket['tokens'] / $maxTokens)) * 100, 2),
            'is_burst_mode' => $bucket['tokens'] > $maxTokens
        ];
    }

    /**
     * Get rate limiting metrics
     */
    public function getMetrics(): array
    {
        $allowedRate = $this->metrics['total_requests'] > 0 
            ? ($this->metrics['allowed_requests'] / $this->metrics['total_requests']) * 100 
            : 100;
            
        $burstRate = $this->metrics['total_requests'] > 0 
            ? ($this->metrics['burst_requests'] / $this->metrics['total_requests']) * 100 
            : 0;

        return array_merge($this->metrics, [
            'allowed_rate' => round($allowedRate, 2),
            'blocked_rate' => round(100 - $allowedRate, 2),
            'burst_rate' => round($burstRate, 2),
            'active_buckets' => count($this->buckets)
        ]);
    }

    /**
     * Reset metrics
     */
    public function resetMetrics(): void
    {
        $this->metrics = [
            'total_requests' => 0,
            'allowed_requests' => 0,
            'blocked_requests' => 0,
            'burst_requests' => 0
        ];
    }

    /**
     * Clean up expired buckets
     */
    public function cleanup(): int
    {
        $cleaned = 0;
        $now = time();
        $maxAge = $this->config->get('rate_limiting.bucket_max_age', 7200); // 2 hours
        
        foreach ($this->buckets as $key => $bucket) {
            if (isset($bucket['created_at']) && ($now - $bucket['created_at']) > $maxAge) {
                unset($this->buckets[$key]);
                $cleaned++;
                
                if ($this->cache) {
                    $this->cache->delete($key);
                }
            }
        }
        
        return $cleaned;
    }

    /**
     * Get all active rate limit buckets
     */
    public function getActiveBuckets(): array
    {
        $buckets = [];
        
        foreach ($this->buckets as $key => $bucket) {
            $parts = explode(':', $key);
            if (count($parts) >= 3) {
                $category = $parts[1];
                $identifier = implode(':', array_slice($parts, 2));
                
                $buckets[] = [
                    'key' => $key,
                    'identifier' => $identifier,
                    'category' => $category,
                    'tokens' => $bucket['tokens'],
                    'last_refill' => $bucket['last_refill'],
                    'age' => time() - ($bucket['created_at'] ?? 0)
                ];
            }
        }
        
        return $buckets;
    }

    /**
     * Configure rate limiting for specific category
     */
    public function configurateCategory(string $category, array $config): void
    {
        $rateLimitConfig = $this->config->get('rate_limiting', []);
        $rateLimitConfig['categories'] = $rateLimitConfig['categories'] ?? [];
        $rateLimitConfig['categories'][$category] = array_merge(
            $rateLimitConfig['categories'][$category] ?? [],
            $config
        );
        
        $this->config->set('rate_limiting', $rateLimitConfig);
    }

    /**
     * Check if burst protection is active
     */
    public function isBurstProtectionActive(string $identifier, string $category = 'default'): bool
    {
        $bucket = $this->getTokenBucket($identifier, $category);
        $rateLimitConfig = $this->config->get('rate_limiting', []);
        $categoryConfig = $rateLimitConfig['categories'][$category] ?? $rateLimitConfig['default'] ?? [];
        
        $maxTokens = $categoryConfig['max_requests'] ?? 100;
        return $bucket['tokens'] > $maxTokens;
    }

    /**
     * Get burst capacity remaining
     */
    public function getBurstCapacityRemaining(string $identifier, string $category = 'default'): int
    {
        $bucket = $this->getTokenBucket($identifier, $category);
        $rateLimitConfig = $this->config->get('rate_limiting', []);
        $categoryConfig = $rateLimitConfig['categories'][$category] ?? $rateLimitConfig['default'] ?? [];
        
        $maxTokens = $categoryConfig['max_requests'] ?? 100;
        $burstMultiplier = $categoryConfig['burst_multiplier'] ?? 1.5;
        $maxBurstTokens = (int) ($maxTokens * $burstMultiplier);
        
        return max(0, $maxBurstTokens - (int) $bucket['tokens']);
    }
}