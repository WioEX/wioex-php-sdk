<?php

declare(strict_types=1);

namespace Wioex\SDK\RateLimit;

use Wioex\SDK\Config;
use Wioex\SDK\Cache\CacheInterface;
use Wioex\SDK\Exceptions\RateLimitException;

class RateLimitManager
{
    private Config $config;
    private ?CacheInterface $cache;
    private array $limiters = [];
    private array $globalMetrics = [
        'total_requests' => 0,
        'total_blocked' => 0,
        'categories' => []
    ];

    public function __construct(Config $config, ?CacheInterface $cache = null)
    {
        $this->config = $config;
        $this->cache = $cache;
    }

    /**
     * Get or create rate limiter for specific category
     */
    public function getLimiter(string $category = 'default'): RateLimiter
    {
        if (!isset($this->limiters[$category])) {
            $this->limiters[$category] = new RateLimiter($this->config, $this->cache);
        }
        
        return $this->limiters[$category];
    }

    /**
     * Check if request is allowed across all applicable rate limits
     */
    public function isRequestAllowed(string $identifier, array $categories = ['default']): bool
    {
        $this->globalMetrics['total_requests']++;
        
        foreach ($categories as $category) {
            if (!$this->getLimiter($category)->isAllowed($identifier, $category)) {
                $this->globalMetrics['total_blocked']++;
                $this->globalMetrics['categories'][$category] = 
                    ($this->globalMetrics['categories'][$category] ?? 0) + 1;
                return false;
            }
        }
        
        return true;
    }

    /**
     * Process request with rate limiting
     */
    public function processRequest(string $identifier, array $categories = ['default'], int $tokens = 1): bool
    {
        if (!$this->isRequestAllowed($identifier, $categories)) {
            $this->throwRateLimitException($identifier, $categories);
        }

        // Consume tokens from all applicable limiters
        foreach ($categories as $category) {
            $this->getLimiter($category)->consume($identifier, $tokens, $category);
        }

        return true;
    }

    /**
     * Apply fair queuing algorithm
     */
    public function fairQueue(array $requests): array
    {
        $rateLimitConfig = $this->config->get('rate_limiting', []);
        if (!($rateLimitConfig['fair_queuing'] ?? false)) {
            return $requests;
        }

        // Group requests by identifier
        $groups = [];
        foreach ($requests as $index => $request) {
            $identifier = $request['identifier'] ?? 'unknown';
            $groups[$identifier][] = ['index' => $index, 'request' => $request];
        }

        // Apply round-robin fair queuing
        $fairQueue = [];
        $maxRounds = max(array_map('count', $groups));
        
        for ($round = 0; $round < $maxRounds; $round++) {
            foreach ($groups as $identifier => $group) {
                if (isset($group[$round])) {
                    $fairQueue[] = $group[$round];
                }
            }
        }

        return array_column($fairQueue, 'request');
    }

    /**
     * Apply burst protection across categories
     */
    public function applyBurstProtection(string $identifier, array $categories = ['default']): array
    {
        $protection = [
            'burst_active' => false,
            'protection_level' => 'none',
            'recommendations' => []
        ];

        foreach ($categories as $category) {
            $limiter = $this->getLimiter($category);
            $status = $limiter->getStatus($identifier, $category);
            
            if ($status['is_burst_mode']) {
                $protection['burst_active'] = true;
                $protection['protection_level'] = 'active';
                
                // Calculate protection recommendations
                if ($status['usage_percentage'] > 90) {
                    $protection['protection_level'] = 'critical';
                    $protection['recommendations'][] = "Immediate throttling recommended for category: {$category}";
                } elseif ($status['usage_percentage'] > 75) {
                    $protection['protection_level'] = 'warning';
                    $protection['recommendations'][] = "Consider throttling for category: {$category}";
                }
            }
        }

        return $protection;
    }

    /**
     * Get comprehensive rate limiting status
     */
    public function getComprehensiveStatus(string $identifier): array
    {
        $status = [
            'identifier' => $identifier,
            'timestamp' => time(),
            'categories' => [],
            'overall_status' => 'healthy',
            'burst_protection' => false,
            'recommendations' => []
        ];

        $rateLimitConfig = $this->config->get('rate_limiting', []);
        $categories = array_keys($rateLimitConfig['categories'] ?? []);
        $categories[] = 'default';

        foreach ($categories as $category) {
            $limiter = $this->getLimiter($category);
            $categoryStatus = $limiter->getStatus($identifier, $category);
            $status['categories'][$category] = $categoryStatus;

            // Determine overall status
            if ($categoryStatus['usage_percentage'] > 90) {
                $status['overall_status'] = 'critical';
            } elseif ($categoryStatus['usage_percentage'] > 75 && $status['overall_status'] === 'healthy') {
                $status['overall_status'] = 'warning';
            }

            // Check burst protection
            if ($categoryStatus['is_burst_mode']) {
                $status['burst_protection'] = true;
            }
        }

        // Generate recommendations
        $status['recommendations'] = $this->generateRecommendations($status);

        return $status;
    }

    /**
     * Configure rate limiting categories with intelligent defaults
     */
    public function configureIntelligentLimiting(): void
    {
        $rateLimitConfig = $this->config->get('rate_limiting', []);
        
        // Intelligent default categories for financial APIs
        $intelligentCategories = [
            'quote_requests' => [
                'max_requests' => 1000,
                'refill_rate' => 100,
                'refill_period' => 60, // 100 requests per minute
                'burst_multiplier' => 2.0,
                'priority' => 'high'
            ],
            'market_data' => [
                'max_requests' => 500,
                'refill_rate' => 50,
                'refill_period' => 60, // 50 requests per minute
                'burst_multiplier' => 1.5,
                'priority' => 'medium'
            ],
            'historical_data' => [
                'max_requests' => 100,
                'refill_rate' => 10,
                'refill_period' => 60, // 10 requests per minute
                'burst_multiplier' => 1.2,
                'priority' => 'low'
            ],
            'streaming' => [
                'max_requests' => 50,
                'refill_rate' => 5,
                'refill_period' => 60, // 5 requests per minute
                'burst_multiplier' => 1.0,
                'priority' => 'high'
            ],
            'analysis' => [
                'max_requests' => 200,
                'refill_rate' => 20,
                'refill_period' => 60, // 20 requests per minute
                'burst_multiplier' => 1.3,
                'priority' => 'medium'
            ]
        ];

        $rateLimitConfig['categories'] = array_merge(
            $rateLimitConfig['categories'] ?? [],
            $intelligentCategories
        );

        $this->config->set('rate_limiting', $rateLimitConfig);
    }

    /**
     * Adaptive rate limiting based on system load
     */
    public function adaptiveRateLimit(string $identifier, string $category = 'default'): array
    {
        $systemLoad = $this->getSystemLoad();
        $limiter = $this->getLimiter($category);
        $currentStatus = $limiter->getStatus($identifier, $category);
        
        $adaptation = [
            'original_limit' => $currentStatus['max_tokens'],
            'adapted_limit' => $currentStatus['max_tokens'],
            'system_load' => $systemLoad,
            'adaptation_factor' => 1.0,
            'reason' => 'no_adaptation'
        ];

        // Adapt based on system load
        if ($systemLoad > 0.9) {
            $adaptation['adaptation_factor'] = 0.5;
            $adaptation['reason'] = 'high_system_load';
        } elseif ($systemLoad > 0.7) {
            $adaptation['adaptation_factor'] = 0.75;
            $adaptation['reason'] = 'medium_system_load';
        } elseif ($systemLoad < 0.3) {
            $adaptation['adaptation_factor'] = 1.25;
            $adaptation['reason'] = 'low_system_load';
        }

        $adaptation['adapted_limit'] = (int) ($adaptation['original_limit'] * $adaptation['adaptation_factor']);

        // Apply temporary adaptation
        if ($adaptation['adaptation_factor'] !== 1.0) {
            $this->applyTemporaryLimitAdjustment($category, $adaptation['adaptation_factor']);
        }

        return $adaptation;
    }

    /**
     * Priority-based request processing
     */
    public function processPriorityRequests(array $requests): array
    {
        $rateLimitConfig = $this->config->get('rate_limiting', []);
        if (!($rateLimitConfig['priority_processing'] ?? false)) {
            return array_map(fn($r) => array_merge($r, ['processed' => true]), $requests);
        }

        // Sort requests by priority
        usort($requests, function($a, $b) {
            $priorityA = $this->getCategoryPriority($a['category'] ?? 'default');
            $priorityB = $this->getCategoryPriority($b['category'] ?? 'default');
            return $priorityB <=> $priorityA; // Higher priority first
        });

        $processed = [];
        foreach ($requests as $request) {
            $identifier = $request['identifier'] ?? 'unknown';
            $category = $request['category'] ?? 'default';
            
            try {
                if ($this->processRequest($identifier, [$category])) {
                    $processed[] = array_merge($request, ['processed' => true, 'error' => null]);
                }
            } catch (RateLimitException $e) {
                $processed[] = array_merge($request, ['processed' => false, 'error' => $e->getMessage()]);
            }
        }

        return $processed;
    }

    /**
     * Get global rate limiting metrics
     */
    public function getGlobalMetrics(): array
    {
        $metrics = $this->globalMetrics;
        
        // Aggregate metrics from all limiters
        foreach ($this->limiters as $category => $limiter) {
            $limiterMetrics = $limiter->getMetrics();
            $metrics['limiters'][$category] = $limiterMetrics;
        }

        // Calculate global statistics
        $totalRequests = $metrics['total_requests'];
        $totalBlocked = $metrics['total_blocked'];
        
        $metrics['global_success_rate'] = $totalRequests > 0 
            ? round(((($totalRequests - $totalBlocked) / $totalRequests) * 100), 2)
            : 100;
            
        $metrics['global_block_rate'] = $totalRequests > 0 
            ? round((($totalBlocked / $totalRequests) * 100), 2)
            : 0;

        return $metrics;
    }

    /**
     * Cleanup expired data across all limiters
     */
    public function globalCleanup(): array
    {
        $cleaned = [
            'total_buckets_cleaned' => 0,
            'categories_cleaned' => 0,
            'memory_freed' => 0
        ];

        $memoryBefore = memory_get_usage();

        foreach ($this->limiters as $category => $limiter) {
            $bucketsCleaned = $limiter->cleanup();
            $cleaned['total_buckets_cleaned'] += $bucketsCleaned;
            
            if ($bucketsCleaned > 0) {
                $cleaned['categories_cleaned']++;
            }
        }

        $cleaned['memory_freed'] = $memoryBefore - memory_get_usage();

        return $cleaned;
    }

    /**
     * Generate intelligent recommendations
     */
    private function generateRecommendations(array $status): array
    {
        $recommendations = [];

        foreach ($status['categories'] as $category => $categoryStatus) {
            if ($categoryStatus['usage_percentage'] > 95) {
                $recommendations[] = "URGENT: {$category} category at {$categoryStatus['usage_percentage']}% capacity";
            } elseif ($categoryStatus['usage_percentage'] > 80) {
                $recommendations[] = "WARNING: {$category} category at {$categoryStatus['usage_percentage']}% capacity";
            }

            if ($categoryStatus['is_burst_mode']) {
                $recommendations[] = "INFO: {$category} category using burst capacity";
            }
        }

        if ($status['overall_status'] === 'critical') {
            $recommendations[] = "Consider implementing request queuing or temporary throttling";
        }

        return $recommendations;
    }

    /**
     * Get system load (simplified implementation)
     */
    private function getSystemLoad(): float
    {
        // In a real implementation, this would check actual system metrics
        // For demo purposes, we'll simulate based on current memory usage
        $memoryLimit = ini_get('memory_limit');
        $memoryUsage = memory_get_usage(true);
        
        if ($memoryLimit === '-1') {
            return 0.1; // No limit set
        }
        
        $limitBytes = $this->parseMemoryLimit($memoryLimit);
        return min(1.0, $memoryUsage / $limitBytes);
    }

    /**
     * Parse memory limit string to bytes
     */
    private function parseMemoryLimit(string $limit): int
    {
        $limit = trim($limit);
        $last = strtolower($limit[strlen($limit) - 1]);
        $value = (int) $limit;
        
        switch ($last) {
            case 'g': $value *= 1024;
            case 'm': $value *= 1024;
            case 'k': $value *= 1024;
        }
        
        return $value;
    }

    /**
     * Get category priority
     */
    private function getCategoryPriority(string $category): int
    {
        $rateLimitConfig = $this->config->get('rate_limiting', []);
        $categoryConfig = $rateLimitConfig['categories'][$category] ?? [];
        
        return match ($categoryConfig['priority'] ?? 'medium') {
            'high' => 3,
            'medium' => 2,
            'low' => 1,
            default => 1
        };
    }

    /**
     * Apply temporary limit adjustment
     */
    private function applyTemporaryLimitAdjustment(string $category, float $factor): void
    {
        $rateLimitConfig = $this->config->get('rate_limiting', []);
        $categoryConfig = $rateLimitConfig['categories'][$category] ?? $rateLimitConfig['default'] ?? [];
        
        $originalLimit = $categoryConfig['max_requests'] ?? 100;
        $adjustedLimit = (int) ($originalLimit * $factor);
        
        // Store original for restoration
        if (!isset($categoryConfig['_original_limit'])) {
            $categoryConfig['_original_limit'] = $originalLimit;
        }
        
        $categoryConfig['max_requests'] = $adjustedLimit;
        $rateLimitConfig['categories'][$category] = $categoryConfig;
        
        $this->config->set('rate_limiting', $rateLimitConfig);
        
        // Reset limiter to pick up new config
        unset($this->limiters[$category]);
    }

    /**
     * Throw appropriate rate limit exception
     */
    private function throwRateLimitException(string $identifier, array $categories): void
    {
        $retryAfter = 0;
        $blockedCategories = [];
        
        foreach ($categories as $category) {
            $limiter = $this->getLimiter($category);
            if (!$limiter->isAllowed($identifier, $category)) {
                $blockedCategories[] = $category;
                $retryAfter = max($retryAfter, $limiter->getRetryAfter($identifier, $category));
            }
        }
        
        $message = sprintf(
            'Rate limit exceeded for %s in categories: %s. Retry after %d seconds.',
            $identifier,
            implode(', ', $blockedCategories),
            $retryAfter
        );
        
        throw new RateLimitException($message, 429);
    }
}