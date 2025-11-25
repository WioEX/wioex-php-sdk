<?php

declare(strict_types=1);

namespace Wioex\SDK\Cache\Drivers;

use Wioex\SDK\Cache\CacheInterface;

/**
 * File-based cache driver for WioEX SDK
 *
 * Stores cache data in filesystem using serialized files.
 * Provides persistent caching across script executions without
 * requiring external cache systems.
 */
class FileDriver implements CacheInterface
{
    private string $cacheDir;
    private string $extension;
    private int $dirPermissions;
    private int $filePermissions;
    private array $statistics = [
        'hits' => 0,
        'misses' => 0,
        'sets' => 0,
        'deletes' => 0,
        'clears' => 0,
    ];

    /**
     * @param array{
     *     cache_dir?: string,
     *     extension?: string,
     *     dir_permissions?: int,
     *     file_permissions?: int
     * } $config
     */
    public function __construct(array $config = [])
    {
        $this->cacheDir = $config['cache_dir'] ?? sys_get_temp_dir() . '/wioex_cache';
        $this->extension = $config['extension'] ?? '.cache';
        $this->dirPermissions = $config['dir_permissions'] ?? 0755;
        $this->filePermissions = $config['file_permissions'] ?? 0644;

        $this->ensureCacheDirectory();
    }

    public function get(string $key)
    {
        $filePath = $this->getFilePath($key);

        if (!file_exists($filePath)) {
            $this->statistics['misses']++;
            return null;
        }

        $data = $this->readCacheFile($filePath);

        if ($data === null || $this->isExpired($data)) {
            $this->delete($key);
            $this->statistics['misses']++;
            return null;
        }

        $this->statistics['hits']++;
        return $data['value'];
    }

    public function set(string $key, $value, int $ttl = 0): bool
    {
        $filePath = $this->getFilePath($key);
        $this->ensureDirectory(dirname($filePath));

        $data = [
            'value' => $value,
            'created_at' => time(),
            'ttl' => $ttl,
            'expires_at' => $ttl > 0 ? time() + $ttl : null,
        ];

        $serialized = serialize($data);

        // Use atomic write to prevent partial files
        $tempFile = $filePath . '.tmp.' . uniqid();

        if (file_put_contents($tempFile, $serialized, LOCK_EX) !== false) {
            if (rename($tempFile, $filePath)) {
                chmod($filePath, $this->filePermissions);
                $this->statistics['sets']++;
                return true;
            }
            @unlink($tempFile);
        }

        return false;
    }

    public function has(string $key): bool
    {
        $filePath = $this->getFilePath($key);

        if (!file_exists($filePath)) {
            return false;
        }

        $data = $this->readCacheFile($filePath);

        if ($data === null || $this->isExpired($data)) {
            $this->delete($key);
            return false;
        }

        return true;
    }

    public function delete(string $key): bool
    {
        $filePath = $this->getFilePath($key);

        if (file_exists($filePath)) {
            if (unlink($filePath)) {
                $this->statistics['deletes']++;
                return true;
            }
        }

        return false;
    }

    public function clear(): bool
    {
        $deleted = 0;
        $pattern = $this->cacheDir . '/*' . $this->extension;

        $files = glob($pattern);
        if ($files !== false) {
            foreach ($files as $file) {
                if (is_file($file) && unlink($file)) {
                    $deleted++;
                }
            }
        }

        // Also clear subdirectories
        $this->clearDirectory($this->cacheDir);

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
        $ttlToSet = $ttl !== false && $ttl !== -1 ? $ttl : 0;

        if ($this->set($key, $newValue, $ttlToSet)) {
            return $newValue;
        }

        return false;
    }

    public function decrement(string $key, int $step = 1)
    {
        return $this->increment($key, -$step);
    }

    public function getStatistics(): array
    {
        $hits = (int)$this->statistics['hits'];
        $misses = (int)$this->statistics['misses'];
        $total = $hits + $misses;
        $hitRate = $total > 0 ? ($hits / $total) * 100 : 0;

        return array_merge($this->statistics, [
            'total_requests' => $total,
            'hit_rate_percentage' => round($hitRate, 2),
            'disk_usage_bytes' => $this->calculateDiskUsage(),
            'file_count' => $this->countCacheFiles(),
            'cache_directory' => $this->cacheDir,
        ]);
    }

    public function getDriverInfo(): array
    {
        return [
            'driver' => 'file',
            'version' => '1.0.0',
            'description' => 'File-based cache driver',
            'persistent' => true,
            'supports_expiration' => true,
            'supports_increment' => true,
            'supports_patterns' => true,
            'configuration' => [
                'cache_directory' => $this->cacheDir,
                'file_extension' => $this->extension,
                'dir_permissions' => decoct($this->dirPermissions),
                'file_permissions' => decoct($this->filePermissions),
                'writable' => is_writable($this->cacheDir),
            ],
        ];
    }

    public function isHealthy(): bool
    {
        if (!is_dir($this->cacheDir)) {
            return false;
        }

        if (!is_writable($this->cacheDir)) {
            return false;
        }

        // Test write/read operation
        $testKey = '__health_check__';
        $testValue = time();

        if (!$this->set($testKey, $testValue, 1)) {
            return false;
        }

        $retrieved = $this->get($testKey);
        $this->delete($testKey);

        return $retrieved === $testValue;
    }

    public function getTtl(string $key)
    {
        $filePath = $this->getFilePath($key);

        if (!file_exists($filePath)) {
            return false;
        }

        $data = $this->readCacheFile($filePath);

        if ($data === null) {
            return false;
        }

        if ($data['expires_at'] === null) {
            return -1; // No expiration
        }

        $remaining = $data['expires_at'] - time();
        return max(0, (int) $remaining);
    }

    public function touch(string $key, int $ttl): bool
    {
        $filePath = $this->getFilePath($key);

        if (!file_exists($filePath)) {
            return false;
        }

        $data = $this->readCacheFile($filePath);

        if ($data === null || $this->isExpired($data)) {
            return false;
        }

        $data['ttl'] = $ttl;
        $data['expires_at'] = $ttl > 0 ? time() + $ttl : null;

        $serialized = serialize($data);

        if (file_put_contents($filePath, $serialized, LOCK_EX) !== false) {
            return true;
        }

        return false;
    }

    public function getKeys(string $pattern = '*'): array
    {
        $keys = [];
        $searchPattern = $this->cacheDir . '/' . str_replace('*', '*', $pattern) . $this->extension;

        $files = glob($searchPattern);
        if ($files !== false) {
            foreach ($files as $file) {
                if (is_file($file)) {
                    $key = $this->getKeyFromFilePath($file);

                    // Check if file is not expired
                    $data = $this->readCacheFile($file);
                    if ($data !== null && !$this->isExpired($data)) {
                        $keys[] = $key;
                    }
                }
            }
        }

        return $keys;
    }

    public function getSize(string $key)
    {
        $filePath = $this->getFilePath($key);

        if (!file_exists($filePath)) {
            return false;
        }

        return filesize($filePath);
    }

    public function flushExpired(): int
    {
        $expired = 0;
        $pattern = $this->cacheDir . '/*' . $this->extension;

        $files = glob($pattern);
        if ($files !== false) {
            foreach ($files as $file) {
                if (is_file($file)) {
                    $data = $this->readCacheFile($file);

                    if ($data === null || $this->isExpired($data)) {
                        if (unlink($file)) {
                            $expired++;
                        }
                    }
                }
            }
        }

        return $expired;
    }

    /**
     * Get file path for a cache key
     */
    private function getFilePath(string $key): string
    {
        // Create safe filename from key
        $safeKey = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $key);
        $hash = hash('sha256', $key);

        // Use subdirectory based on first 2 characters of hash for better performance
        $subDir = substr($hash, 0, 2);

        return $this->cacheDir . '/' . $subDir . '/' . $safeKey . '_' . substr($hash, 2, 8) . $this->extension;
    }

    /**
     * Get cache key from file path
     */
    private function getKeyFromFilePath(string $filePath): string
    {
        $basename = basename($filePath, $this->extension);

        // Extract original key (everything before the last underscore and 8-char hash)
        $matches = [];
        if (preg_match('/^(.+)_[a-f0-9]{8}$/', $basename, $matches)) {
            return str_replace('_', '', $matches[1]); // This is simplified; in practice, you'd need to store the original key
        }

        return $basename;
    }

    /**
     * Read and unserialize cache file
     */
    private function readCacheFile(string $filePath): ?array
    {
        $content = @file_get_contents($filePath);

        if ($content === false) {
            return null;
        }

        $data = @unserialize($content);

        if ($data === false || !is_array($data)) {
            // File is corrupted, remove it
            @unlink($filePath);
            return null;
        }

        return $data;
    }

    /**
     * Check if cache data is expired
     */
    private function isExpired(array $data): bool
    {
        return $data['expires_at'] !== null && $data['expires_at'] <= time();
    }

    /**
     * Ensure cache directory exists
     */
    private function ensureCacheDirectory(): void
    {
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, $this->dirPermissions, true);
        }
    }

    /**
     * Ensure directory exists
     */
    private function ensureDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            mkdir($dir, $this->dirPermissions, true);
        }
    }

    /**
     * Clear directory recursively
     */
    private function clearDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = glob($dir . '/*');
        
        if ($files !== false) {
            foreach ($files as $file) {
                if (is_dir($file)) {
                    $this->clearDirectory($file);
                    @rmdir($file);
                } elseif (is_file($file) && strpos($file, $this->extension) !== false) {
                    @unlink($file);
                }
            }
        }
    }

    /**
     * Calculate total disk usage of cache files
     */
    private function calculateDiskUsage(): int
    {
        $size = 0;
        $pattern = $this->cacheDir . '/*' . $this->extension;

        $files = glob($pattern);
        if ($files !== false) {
            foreach ($files as $file) {
                if (is_file($file)) {
                    $fileSize = filesize($file);
                    if ($fileSize !== false) {
                        $size += $fileSize;
                    }
                }
            }
        }

        return $size;
    }

    /**
     * Count cache files
     */
    private function countCacheFiles(): int
    {
        $pattern = $this->cacheDir . '/*' . $this->extension;
        $files = glob($pattern);
        return $files !== false ? count($files) : 0;
    }
}
