<?php

declare(strict_types=1);

namespace Wioex\SDK\Cache\Drivers;

use Wioex\SDK\Cache\CacheInterface;
use Wioex\SDK\Exceptions\InvalidArgumentException;

class OpcacheDriver implements CacheInterface
{
    private array $config;
    private string $cacheDir;
    private array $stats = [
        'hits' => 0,
        'misses' => 0,
        'sets' => 0,
        'deletes' => 0,
        'errors' => 0
    ];

    /**
     * @param array{
     *     cache_dir?: string,
     *     prefix?: string,
     *     ttl_check_interval?: int,
     *     max_file_size?: int,
     *     file_permissions?: int
     * } $config
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'cache_dir' => sys_get_temp_dir() . '/wioex_opcache',
            'prefix' => 'wioex_opcache_',
            'ttl_check_interval' => 300, // 5 minutes
            'max_file_size' => 1024 * 1024, // 1MB
            'file_permissions' => 0644
        ], $config);

        $opcacheEnabled = ini_get('opcache.enable');
        if (!function_exists('opcache_compile_file') || !$opcacheEnabled) {
            throw new InvalidArgumentException('OPcache is not enabled');
        }

        $this->cacheDir = rtrim($this->config['cache_dir'], '/');
        $this->ensureCacheDirectory();
    }

    private function ensureCacheDirectory(): void
    {
        if (!is_dir($this->cacheDir)) {
            if (!mkdir($this->cacheDir, 0755, true)) {
                throw new InvalidArgumentException("Cannot create cache directory: {$this->cacheDir}");
            }
        }

        if (!is_writable($this->cacheDir)) {
            throw new InvalidArgumentException("Cache directory is not writable: {$this->cacheDir}");
        }
    }

    private function getFilePath(string $key): string
    {
        $hash = md5($this->config['prefix'] . $key);
        return $this->cacheDir . '/' . $hash . '.php';
    }

    private function createCacheFile(string $filePath, mixed $value, int $ttl = 0): bool
    {
        $expireTime = $ttl > 0 ? time() + $ttl : 0;
        
        $content = "<?php\n";
        $content .= "// Generated cache file - DO NOT EDIT\n";
        $content .= "// Expires: " . ($expireTime > 0 ? date('Y-m-d H:i:s', $expireTime) : 'Never') . "\n";
        $content .= "return [\n";
        $content .= "    'expires' => {$expireTime},\n";
        $content .= "    'data' => " . var_export($value, true) . "\n";
        $content .= "];\n";

        if (strlen($content) > $this->config['max_file_size']) {
            $this->stats['errors']++;
            return false;
        }

        $result = file_put_contents($filePath, $content, LOCK_EX);
        
        if ($result !== false) {
            chmod($filePath, $this->config['file_permissions']);
            
            // Compile the file into OPcache
            if (function_exists('opcache_compile_file')) {
                opcache_compile_file($filePath);
            }
            
            return true;
        }

        $this->stats['errors']++;
        return false;
    }

    private function readCacheFile(string $filePath): mixed
    {
        if (!file_exists($filePath)) {
            return null;
        }

        try {
            $data = include $filePath;
            
            if (!is_array($data) || !isset($data['expires'], $data['data'])) {
                return null;
            }

            // Check expiration
            if ($data['expires'] > 0 && time() > $data['expires']) {
                $this->deleteFile($filePath);
                return null;
            }

            return $data['data'];

        } catch (\Exception $e) {
            $this->stats['errors']++;
            return null;
        }
    }

    private function deleteFile(string $filePath): bool
    {
        if (file_exists($filePath)) {
            // Invalidate from OPcache first
            if (function_exists('opcache_invalidate')) {
                opcache_invalidate($filePath, true);
            }
            
            return unlink($filePath);
        }
        
        return true;
    }

    public function get(string $key)
    {
        $filePath = $this->getFilePath($key);
        $value = $this->readCacheFile($filePath);
        
        if ($value !== null) {
            $this->stats['hits']++;
            return $value;
        }

        $this->stats['misses']++;
        return null;
    }

    public function set(string $key, $value, int $ttl = 0): bool
    {
        $filePath = $this->getFilePath($key);
        $result = $this->createCacheFile($filePath, $value, $ttl);
        
        if ($result) {
            $this->stats['sets']++;
        }

        return $result;
    }

    public function has(string $key): bool
    {
        $filePath = $this->getFilePath($key);
        return $this->readCacheFile($filePath) !== null;
    }

    public function delete(string $key): bool
    {
        $filePath = $this->getFilePath($key);
        $result = $this->deleteFile($filePath);
        
        if ($result) {
            $this->stats['deletes']++;
        }

        return $result;
    }

    public function clear(): bool
    {
        $files = glob($this->cacheDir . '/*.php');
        if ($files === false) {
            return false;
        }
        
        $success = true;

        foreach ($files as $file) {
            if (!$this->deleteFile($file)) {
                $success = false;
            }
        }

        return $success;
    }

    public function getMultiple(array $keys): array
    {
        $result = [];

        foreach ($keys as $key) {
            $value = $this->get($key);
            if ($value !== null) {
                $result[$key] = $value;
            }
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
        $current = $this->get($key);
        
        if ($current === null) {
            $current = 0;
        }

        if (!is_numeric($current)) {
            return false;
        }

        $new = $current + $step;
        $this->set($key, $new);
        
        return $new;
    }

    public function decrement(string $key, int $step = 1)
    {
        return $this->increment($key, -$step);
    }

    public function getStatistics(): array
    {
        $opcacheStatus = [];
        
        if (function_exists('opcache_get_status')) {
            $status = opcache_get_status(false);
            $opcacheStatus = [
                'opcache_enabled' => $status['opcache_enabled'] ?? false,
                'cache_full' => $status['cache_full'] ?? false,
                'restart_pending' => $status['restart_pending'] ?? false,
                'restart_in_progress' => $status['restart_in_progress'] ?? false,
                'memory_usage' => $status['memory_usage'] ?? [],
                'opcache_statistics' => $status['opcache_statistics'] ?? []
            ];
        }

        $files = glob($this->cacheDir . '/*.php');
        if ($files === false) {
            $files = [];
        }
        
        $totalSize = 0;
        $expiredFiles = 0;
        
        foreach ($files as $file) {
            $filesize = filesize($file);
            if ($filesize !== false) {
                $totalSize += $filesize;
            }
            
            // Check if file is expired
            $data = include $file;
            if (is_array($data) && isset($data['expires']) && 
                $data['expires'] > 0 && time() > $data['expires']) {
                $expiredFiles++;
            }
        }

        return array_merge($this->stats, [
            'opcache_status' => $opcacheStatus,
            'total_files' => count($files),
            'expired_files' => $expiredFiles,
            'cache_size_bytes' => $totalSize,
            'cache_size_human' => $this->formatBytes($totalSize),
            'hit_ratio' => $this->getHitRatio(),
            'total_operations' => $this->stats['hits'] + $this->stats['misses'] + $this->stats['sets'] + $this->stats['deletes'],
            'driver' => 'opcache',
            'configuration' => $this->config
        ]);
    }

    public function getDriverInfo(): array
    {
        $opcacheConfig = [];
        
        if (function_exists('opcache_get_configuration')) {
            $config = opcache_get_configuration();
            $opcacheConfig = $config['directives'] ?? [];
        }

        return [
            'driver' => 'opcache',
            'version' => '1.0.0',
            'description' => 'OPcache-based file cache driver for ultra-fast compilation',
            'supports_compilation' => true,
            'supports_memory_storage' => true,
            'supports_file_storage' => true,
            'opcache_version' => phpversion('Zend OPcache'),
            'opcache_config' => $opcacheConfig,
            'configuration' => $this->config
        ];
    }

    public function isHealthy(): bool
    {
        $opcacheEnabled = ini_get('opcache.enable');
        return is_dir($this->cacheDir) && 
               is_writable($this->cacheDir) && 
               function_exists('opcache_compile_file') && 
               (bool)$opcacheEnabled;
    }

    public function getTtl(string $key): int|false
    {
        $filePath = $this->getFilePath($key);
        
        if (!file_exists($filePath)) {
            return false;
        }

        try {
            $data = include $filePath;
            
            if (!is_array($data) || !isset($data['expires'])) {
                return false;
            }

            if ($data['expires'] === 0) {
                return false; // No expiration
            }

            $remaining = $data['expires'] - time();
            return $remaining > 0 ? $remaining : false;

        } catch (\Exception $e) {
            return false;
        }
    }

    public function touch(string $key, int $ttl): bool
    {
        $value = $this->get($key);
        
        if ($value === null) {
            return false;
        }

        return $this->set($key, $value, $ttl);
    }

    public function getKeys(string $pattern = '*'): array
    {
        $files = glob($this->cacheDir . '/*.php');
        if ($files === false) {
            return [];
        }
        
        $keys = [];

        foreach ($files as $file) {
            $hash = basename($file, '.php');
            // We can't reverse the hash to get original key
            // This is a limitation of this approach
            $keys[] = $hash;
        }

        return $keys;
    }

    public function getSize(string $key)
    {
        $filePath = $this->getFilePath($key);
        
        if (file_exists($filePath)) {
            return filesize($filePath);
        }

        return 0;
    }

    public function flushExpired(): int
    {
        $files = glob($this->cacheDir . '/*.php');
        $deleted = 0;

        foreach ($files as $file) {
            try {
                $data = include $file;
                
                if (is_array($data) && isset($data['expires']) && 
                    $data['expires'] > 0 && time() > $data['expires']) {
                    if ($this->deleteFile($file)) {
                        $deleted++;
                    }
                }
            } catch (\Exception $e) {
                // Skip invalid files
            }
        }

        return $deleted;
    }

    private function getHitRatio(): float
    {
        $hits = (int)$this->stats['hits'];
        $misses = (int)$this->stats['misses'];
        $total = $hits + $misses;
        return $total > 0 ? round(($hits / $total) * 100, 2) : 0.0;
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . ($units[$i] ?? 'B');
    }

    public function getOpcacheStatus(): array
    {
        if (function_exists('opcache_get_status')) {
            return opcache_get_status() ?: [];
        }

        return [];
    }

    public function precompileFiles(): int
    {
        $files = glob($this->cacheDir . '/*.php');
        $compiled = 0;

        foreach ($files as $file) {
            if (function_exists('opcache_compile_file') && opcache_compile_file($file)) {
                $compiled++;
            }
        }

        return $compiled;
    }

    public function invalidateOpcache(): bool
    {
        if (function_exists('opcache_reset')) {
            return opcache_reset();
        }

        return false;
    }
}