<?php

declare(strict_types=1);

namespace Wioex\SDK\Cache\Drivers;

use Wioex\SDK\Cache\CacheInterface;

/**
 * In-memory cache driver for WioEX SDK
 *
 * Stores cache data in PHP memory for the duration of the script execution.
 * Useful for development, testing, or scenarios where external cache systems
 * are not available.
 */
class MemoryDriver implements CacheInterface
{
    private array $cache = [];
    private array $expiry = [];
    private array $statistics = [
        'hits' => 0,
        'misses' => 0,
        'sets' => 0,
        'deletes' => 0,
        'clears' => 0,
    ];

    public function get(string $key)
    {
        if (!$this->has($key)) {
            $this->statistics['misses']++;
            return null;
        }

        $this->statistics['hits']++;
        return $this->cache[$key];
    }

    public function set(string $key, $value, int $ttl = 0): bool
    {
        $this->cache[$key] = $value;

        if ($ttl > 0) {
            $this->expiry[$key] = time() + $ttl;
        } else {
            unset($this->expiry[$key]);
        }

        $this->statistics['sets']++;
        return true;
    }

    public function has(string $key): bool
    {
        if (!array_key_exists($key, $this->cache)) {
            return false;
        }

        // Check if expired
        if (isset($this->expiry[$key]) && $this->expiry[$key] <= time()) {
            $this->delete($key);
            return false;
        }

        return true;
    }

    public function delete(string $key): bool
    {
        if (array_key_exists($key, $this->cache)) {
            unset($this->cache[$key]);
            unset($this->expiry[$key]);
            $this->statistics['deletes']++;
            return true;
        }

        return false;
    }

    public function clear(): bool
    {
        $this->cache = [];
        $this->expiry = [];
        $this->statistics['clears']++;
        return true;
    }

    public function getMultiple(array $keys): array
    {
        $result = [];

        foreach ($keys as $key) {
            $result[$key] = $this->get($key);
        }

        return $result;
    }

    public function setMultiple(array $items, int $ttl = 0): bool
    {
        $success = true;

        foreach ($items as $key => $value) {
            if (!$this->set($key, $value, $ttl)) {
                $success = false;
            }
        }

        return $success;
    }

    public function deleteMultiple(array $keys): bool
    {
        $success = true;

        foreach ($keys as $key) {
            if (!$this->delete($key)) {
                $success = false;
            }
        }

        return $success;
    }

    public function increment(string $key, int $step = 1)
    {
        $value = $this->get($key);

        if ($value === null) {
            $value = 0;
        }

        if (!is_numeric($value)) {
            return false;
        }

        $newValue = (int) $value + $step;

        // Preserve original TTL
        $ttl = $this->getTtl($key);
        $ttlToSet = $ttl !== false ? $ttl : 0;

        $this->set($key, $newValue, $ttlToSet);

        return $newValue;
    }

    public function decrement(string $key, int $step = 1)
    {
        return $this->increment($key, -$step);
    }

    public function getStatistics(): array
    {
        $total = $this->statistics['hits'] + $this->statistics['misses'];
        $hitRate = $total > 0 ? ($this->statistics['hits'] / $total) * 100 : 0;

        return array_merge($this->statistics, [
            'total_requests' => $total,
            'hit_rate_percentage' => round($hitRate, 2),
            'memory_usage_bytes' => $this->calculateMemoryUsage(),
            'item_count' => count($this->cache),
            'expired_items' => $this->countExpiredItems(),
        ]);
    }

    public function getDriverInfo(): array
    {
        return [
            'driver' => 'memory',
            'version' => '1.0.0',
            'description' => 'In-memory cache driver',
            'persistent' => false,
            'supports_expiration' => true,
            'supports_increment' => true,
            'supports_patterns' => true,
            'configuration' => [
                'max_memory' => 'unlimited',
                'serialization' => 'none',
            ],
        ];
    }

    public function isHealthy(): bool
    {
        // Memory driver is always healthy if the object exists
        return true;
    }

    public function getTtl(string $key)
    {
        if (!$this->has($key)) {
            return false;
        }

        if (!isset($this->expiry[$key])) {
            return -1; // No expiration
        }

        $remaining = $this->expiry[$key] - time();
        return max(0, $remaining);
    }

    public function touch(string $key, int $ttl): bool
    {
        if (!$this->has($key)) {
            return false;
        }

        if ($ttl > 0) {
            $this->expiry[$key] = time() + $ttl;
        } else {
            unset($this->expiry[$key]);
        }

        return true;
    }

    public function getKeys(string $pattern = '*'): array
    {
        // Clean up expired keys first
        $this->flushExpired();

        if ($pattern === '*') {
            return array_keys($this->cache);
        }

        // Convert simple wildcard pattern to regex
        $regexPattern = '/^' . str_replace(['*', '?'], ['.*', '.'], preg_quote($pattern, '/')) . '$/';

        return array_filter(array_keys($this->cache), function ($key) use ($regexPattern) {
            return preg_match($regexPattern, $key);
        });
    }

    public function getSize(string $key)
    {
        if (!$this->has($key)) {
            return false;
        }

        $value = $this->cache[$key];
        return strlen(serialize($value));
    }

    public function flushExpired(): int
    {
        $expired = 0;
        $now = time();

        foreach ($this->expiry as $key => $expiryTime) {
            if ($expiryTime <= $now) {
                unset($this->cache[$key]);
                unset($this->expiry[$key]);
                $expired++;
            }
        }

        return $expired;
    }

    /**
     * Calculate approximate memory usage of cached data
     */
    private function calculateMemoryUsage(): int
    {
        $size = 0;

        foreach ($this->cache as $key => $value) {
            $size += strlen($key);
            $size += strlen(serialize($value));
        }

        // Add overhead for expiry tracking
        foreach ($this->expiry as $key => $expiry) {
            $size += strlen($key) + 8; // 8 bytes for timestamp
        }

        return $size;
    }

    /**
     * Count expired items without removing them
     */
    private function countExpiredItems(): int
    {
        $expired = 0;
        $now = time();

        foreach ($this->expiry as $expiryTime) {
            if ($expiryTime <= $now) {
                $expired++;
            }
        }

        return $expired;
    }

    /**
     * Get raw cache data (useful for debugging)
     */
    public function getRawData(): array
    {
        return [
            'cache' => $this->cache,
            'expiry' => $this->expiry,
            'statistics' => $this->statistics,
        ];
    }

    /**
     * Load cache data from array (useful for testing)
     */
    public function loadData(array $data): void
    {
        $this->cache = $data['cache'] ?? [];
        $this->expiry = $data['expiry'] ?? [];
        $this->statistics = array_merge($this->statistics, $data['statistics'] ?? []);
    }
}
