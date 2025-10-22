<?php

declare(strict_types=1);

namespace Wioex\SDK\Cache;

/**
 * Cache interface for WioEX SDK
 *
 * Defines the contract for cache implementations across different drivers
 * (Redis, File, Memory, etc.)
 */
interface CacheInterface
{
    /**
     * Get an item from the cache
     *
     * @param string $key Cache key
     * @return mixed|null Cached value or null if not found/expired
     */
    public function get(string $key);

    /**
     * Store an item in the cache
     *
     * @param string $key Cache key
     * @param mixed $value Value to cache
     * @param int $ttl Time to live in seconds (0 = no expiration)
     * @return bool True on success, false on failure
     */
    public function set(string $key, $value, int $ttl = 0): bool;

    /**
     * Check if an item exists in the cache
     *
     * @param string $key Cache key
     * @return bool True if item exists and is not expired
     */
    public function has(string $key): bool;

    /**
     * Remove an item from the cache
     *
     * @param string $key Cache key
     * @return bool True on success, false on failure
     */
    public function delete(string $key): bool;

    /**
     * Clear all items from the cache
     *
     * @return bool True on success, false on failure
     */
    public function clear(): bool;

    /**
     * Get multiple items from the cache
     *
     * @param array $keys Array of cache keys
     * @return array Array of key-value pairs (missing keys will have null values)
     */
    public function getMultiple(array $keys): array;

    /**
     * Store multiple items in the cache
     *
     * @param array $items Array of key-value pairs
     * @param int $ttl Time to live in seconds (0 = no expiration)
     * @return bool True if all items were stored successfully
     */
    public function setMultiple(array $items, int $ttl = 0): bool;

    /**
     * Remove multiple items from the cache
     *
     * @param array $keys Array of cache keys
     * @return bool True if all items were removed successfully
     */
    public function deleteMultiple(array $keys): bool;

    /**
     * Increment a numeric value in the cache
     *
     * @param string $key Cache key
     * @param int $step Amount to increment by (default: 1)
     * @return int|false New value on success, false on failure
     */
    public function increment(string $key, int $step = 1);

    /**
     * Decrement a numeric value in the cache
     *
     * @param string $key Cache key
     * @param int $step Amount to decrement by (default: 1)
     * @return int|false New value on success, false on failure
     */
    public function decrement(string $key, int $step = 1);

    /**
     * Get cache statistics
     *
     * @return array Statistics about cache usage, hits, misses, etc.
     */
    public function getStatistics(): array;

    /**
     * Get information about the cache driver
     *
     * @return array Driver name, version, configuration, etc.
     */
    public function getDriverInfo(): array;

    /**
     * Test the cache connection
     *
     * @return bool True if cache is accessible and working
     */
    public function isHealthy(): bool;

    /**
     * Get the remaining TTL for a cache key
     *
     * @param string $key Cache key
     * @return int|false Remaining TTL in seconds, false if key doesn't exist
     */
    public function getTtl(string $key);

    /**
     * Update the TTL for an existing cache key
     *
     * @param string $key Cache key
     * @param int $ttl New TTL in seconds
     * @return bool True on success, false on failure
     */
    public function touch(string $key, int $ttl): bool;

    /**
     * Get cache keys matching a pattern
     *
     * @param string $pattern Pattern to match (implementation-specific)
     * @return array Array of matching cache keys
     */
    public function getKeys(string $pattern = '*'): array;

    /**
     * Get the size of cached data for a key
     *
     * @param string $key Cache key
     * @return int|false Size in bytes, false if key doesn't exist
     */
    public function getSize(string $key);

    /**
     * Flush expired items from the cache
     *
     * @return int Number of expired items removed
     */
    public function flushExpired(): int;
}
