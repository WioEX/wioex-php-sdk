<?php

declare(strict_types=1);

namespace Wioex\SDK\Cache;

use Wioex\SDK\Cache\Drivers\FileDriver;
use Wioex\SDK\Cache\Drivers\MemoryDriver;
use Wioex\SDK\Cache\Drivers\RedisDriver;
use Wioex\SDK\Cache\Drivers\MemcachedDriver;
use Wioex\SDK\Cache\Drivers\OpcacheDriver;
use Wioex\SDK\Exceptions\InvalidArgumentException;

class CacheManager implements CacheInterface
{
    private CacheInterface $driver;
    private string $defaultDriver;
    private array $drivers = [];
    private array $config;
    private array $macros = [];

    /**
     * @param array{
     *     default?: string,
     *     drivers?: array<string, array{driver: string, config?: array}>,
     *     memory?: array,
     *     file?: array,
     *     redis?: array
     * } $config
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;
        
        try {
            // Handle 'auto' driver detection
            $defaultDriver = $config['default'] ?? 'auto';
            if ($defaultDriver === 'auto') {
                $this->defaultDriver = $this->autoDetectBestDriver();
            } else {
                $this->defaultDriver = $defaultDriver;
            }

            $this->initializeDrivers();
            $this->driver = $this->getDriver($this->defaultDriver);
        } catch (\Exception $e) {
            // Graceful degradation: Fall back to memory driver on any error
            $this->defaultDriver = 'memory';
            $this->drivers = ['memory' => new MemoryDriver()];
            $this->driver = $this->drivers['memory'];
            
            // Report error if ErrorReporter is available
            if (class_exists('\Wioex\SDK\ErrorReporter')) {
                try {
                    (new \Wioex\SDK\ErrorReporter(new \Wioex\SDK\Config([])))->report($e, [
                        'context' => 'cache_initialization_error',
                        'requested_driver' => $config['default'] ?? 'auto',
                        'fallback_driver' => 'memory'
                    ]);
                } catch (\Exception $reportError) {
                    // Silent fail on error reporting
                }
            }
        }
    }

    private function initializeDrivers(): void
    {
        $driverConfigs = $this->config['drivers'] ?? [];

        // Auto-register available drivers if not explicitly configured
        $availableDrivers = $this->getAvailableDriverTypes();
        
        foreach ($availableDrivers as $driverName) {
            if (!isset($driverConfigs[$driverName])) {
                $driverConfigs[$driverName] = [
                    'driver' => $driverName,
                    'config' => $this->config[$driverName] ?? []
                ];
            }
        }

        foreach ($driverConfigs as $name => $driverConfig) {
            try {
                $this->drivers[$name] = $this->createDriver(
                    $driverConfig['driver'],
                    $driverConfig['config'] ?? []
                );
            } catch (\Exception $e) {
                // Skip failed drivers - continue with available ones
            }
        }
    }

    private function createDriver(string $driverType, array $config): CacheInterface
    {
        return match ($driverType) {
            'redis' => new RedisDriver($config),
            'memcached' => new MemcachedDriver($config),
            'opcache' => new OpcacheDriver($config),
            'memory' => new MemoryDriver(),
            'file' => new FileDriver($config),
            default => throw new InvalidArgumentException("Unsupported cache driver: {$driverType}")
        };
    }

    public function driver(?string $name = null): CacheInterface
    {
        $driverName = $name ?? $this->defaultDriver;

        if (!isset($this->drivers[$driverName])) {
            throw new InvalidArgumentException("Cache driver '{$driverName}' is not configured");
        }

        return $this->drivers[$driverName];
    }

    private function getDriver(string $name): CacheInterface
    {
        return $this->driver($name);
    }

    public function get(string $key)
    {
        return $this->driver->get($key);
    }

    public function set(string $key, $value, int $ttl = 0): bool
    {
        return $this->driver->set($key, $value, $ttl);
    }

    public function has(string $key): bool
    {
        return $this->driver->has($key);
    }

    public function delete(string $key): bool
    {
        return $this->driver->delete($key);
    }

    public function clear(): bool
    {
        return $this->driver->clear();
    }

    public function getMultiple(array $keys): array
    {
        return $this->driver->getMultiple($keys);
    }

    public function setMultiple(array $items, int $ttl = 0): bool
    {
        return $this->driver->setMultiple($items, $ttl);
    }

    public function deleteMultiple(array $keys): bool
    {
        return $this->driver->deleteMultiple($keys);
    }

    public function increment(string $key, int $step = 1)
    {
        return $this->driver->increment($key, $step);
    }

    public function decrement(string $key, int $step = 1)
    {
        return $this->driver->decrement($key, $step);
    }

    public function getStatistics(): array
    {
        $allStats = [];

        foreach ($this->drivers as $name => $driver) {
            $stats = $driver->getStatistics();
            $stats['driver_name'] = $name;
            $allStats[$name] = $stats;
        }

        return [
            'default_driver' => $this->defaultDriver,
            'available_drivers' => array_keys($this->drivers),
            'drivers' => $allStats,
            'manager_stats' => $this->getManagerStatistics(),
        ];
    }

    public function getDriverInfo(): array
    {
        return [
            'manager' => 'cache_manager',
            'version' => '1.0.0',
            'description' => 'Multi-driver cache manager',
            'default_driver' => $this->defaultDriver,
            'available_drivers' => array_keys($this->drivers),
            'drivers_info' => array_map(
                fn($driver) => $driver->getDriverInfo(),
                $this->drivers
            ),
        ];
    }

    public function isHealthy(): bool
    {
        return $this->driver->isHealthy();
    }

    public function getTtl(string $key)
    {
        return $this->driver->getTtl($key);
    }

    public function touch(string $key, int $ttl): bool
    {
        return $this->driver->touch($key, $ttl);
    }

    public function getKeys(string $pattern = '*'): array
    {
        return $this->driver->getKeys($pattern);
    }

    public function getSize(string $key)
    {
        return $this->driver->getSize($key);
    }

    public function flushExpired(): int
    {
        return $this->driver->flushExpired();
    }

    public function remember(string $key, callable $callback, int $ttl = 0): mixed
    {
        $value = $this->get($key);

        if ($value !== null) {
            return $value;
        }

        $value = $callback();
        $this->set($key, $value, $ttl);

        return $value;
    }

    public function rememberForever(string $key, callable $callback): mixed
    {
        return $this->remember($key, $callback, 0);
    }

    public function pull(string $key): mixed
    {
        $value = $this->get($key);
        $this->delete($key);
        return $value;
    }

    public function put(string $key, mixed $value, int $ttl = 0): bool
    {
        return $this->set($key, $value, $ttl);
    }

    public function forget(string $key): bool
    {
        return $this->delete($key);
    }

    public function flush(): bool
    {
        return $this->clear();
    }

    public function tags(array $tags): TaggedCache
    {
        return new TaggedCache($this->driver, $tags);
    }

    public function prefix(string $prefix): PrefixedCache
    {
        return new PrefixedCache($this->driver, $prefix);
    }

    public function namespace(string $namespace): PrefixedCache
    {
        return $this->prefix($namespace . ':');
    }

    public function many(array $keys): array
    {
        return $this->getMultiple($keys);
    }

    public function putMany(array $items, int $ttl = 0): bool
    {
        return $this->setMultiple($items, $ttl);
    }

    public function forgetMany(array $keys): bool
    {
        return $this->deleteMultiple($keys);
    }

    public function getAllDriversHealth(): array
    {
        $health = [];

        foreach ($this->drivers as $name => $driver) {
            $health[$name] = [
                'healthy' => $driver->isHealthy(),
                'info' => $driver->getDriverInfo(),
                'statistics' => $driver->getStatistics(),
            ];
        }

        return $health;
    }

    public function flushAllDrivers(): array
    {
        $results = [];

        foreach ($this->drivers as $name => $driver) {
            $results[$name] = $driver->clear();
        }

        return $results;
    }

    public function flushExpiredAllDrivers(): array
    {
        $results = [];

        foreach ($this->drivers as $name => $driver) {
            $results[$name] = $driver->flushExpired();
        }

        return $results;
    }

    public function setDefaultDriver(string $driver): void
    {
        if (!isset($this->drivers[$driver])) {
            throw new InvalidArgumentException("Driver '{$driver}' is not configured");
        }

        $this->defaultDriver = $driver;
        $this->driver = $this->drivers[$driver];
    }

    public function getDefaultDriver(): string
    {
        return $this->defaultDriver;
    }

    public function getAvailableDrivers(): array
    {
        return array_keys($this->drivers);
    }

    public function addDriver(string $name, CacheInterface $driver): void
    {
        $this->drivers[$name] = $driver;
    }

    public function removeDriver(string $name): bool
    {
        if ($name === $this->defaultDriver) {
            throw new InvalidArgumentException("Cannot remove the default driver '{$name}'");
        }

        if (isset($this->drivers[$name])) {
            unset($this->drivers[$name]);
            return true;
        }

        return false;
    }

    public function extend(string $driver, callable $resolver): void
    {
        $this->drivers[$driver] = $resolver();
    }

    private function getManagerStatistics(): array
    {
        return [
            'total_drivers' => count($this->drivers),
            'healthy_drivers' => count(array_filter(
                $this->drivers,
                fn($driver) => $driver->isHealthy()
            )),
            'configuration' => [
                'default_driver' => $this->defaultDriver,
                'available_drivers' => array_keys($this->drivers),
            ]
        ];
    }

    public function macro(string $name, callable $macro): void
    {
        if (!property_exists($this, 'macros')) {
            $this->macros = [];
        }

        $this->macros[$name] = $macro;
    }

    public function __call(string $method, array $parameters): mixed
    {
        if (isset($this->macros[$method])) {
            return call_user_func_array($this->macros[$method], $parameters);
        }

        throw new InvalidArgumentException("Method '{$method}' does not exist on CacheManager");
    }

    /**
     * Auto-detect the best available cache driver
     */
    private function autoDetectBestDriver(): string
    {
        // Priority order: Redis > Memcached > OPcache > Memory > File
        $priority = ['redis', 'memcached', 'opcache', 'memory', 'file'];
        
        foreach ($priority as $driver) {
            if ($this->isDriverAvailable($driver)) {
                return $driver;
            }
        }

        return 'memory'; // Fallback
    }

    /**
     * Get all available driver types on this system
     */
    private function getAvailableDriverTypes(): array
    {
        $drivers = [];
        $allDrivers = ['redis', 'memcached', 'opcache', 'memory', 'file'];
        
        foreach ($allDrivers as $driver) {
            if ($this->isDriverAvailable($driver)) {
                $drivers[] = $driver;
            }
        }

        return $drivers;
    }

    /**
     * Check if a driver is available on this system
     */
    private function isDriverAvailable(string $driver): bool
    {
        return match ($driver) {
            'redis' => extension_loaded('redis'),
            'memcached' => extension_loaded('memcached'),
            'opcache' => function_exists('opcache_compile_file') && (bool) ini_get('opcache.enable'),
            'memory' => true, // Always available
            'file' => true, // Always available
            default => false
        };
    }

    /**
     * Get system cache recommendations
     */
    public function getSystemRecommendations(): array
    {
        $recommendations = [];
        
        // Check Redis
        if (extension_loaded('redis')) {
            $recommendations['redis'] = [
                'available' => true,
                'recommended' => true,
                'reason' => 'High performance, clustering support, persistence'
            ];
        } else {
            $recommendations['redis'] = [
                'available' => false,
                'recommended' => true,
                'reason' => 'Install php-redis extension for best performance'
            ];
        }

        // Check Memcached
        if (extension_loaded('memcached')) {
            $recommendations['memcached'] = [
                'available' => true,
                'recommended' => true,
                'reason' => 'Good performance, distributed caching'
            ];
        } else {
            $recommendations['memcached'] = [
                'available' => false,
                'recommended' => true,
                'reason' => 'Install php-memcached extension for distributed caching'
            ];
        }

        // Check OPcache
        if (function_exists('opcache_compile_file') && (bool) ini_get('opcache.enable')) {
            $recommendations['opcache'] = [
                'available' => true,
                'recommended' => true,
                'reason' => 'File-based caching with opcode compilation'
            ];
        } else {
            $recommendations['opcache'] = [
                'available' => false,
                'recommended' => false,
                'reason' => 'Enable OPcache extension for file-based caching'
            ];
        }

        $recommendations['memory'] = [
            'available' => true,
            'recommended' => false,
            'reason' => 'Fast but lost on process restart'
        ];

        $recommendations['file'] = [
            'available' => true,
            'recommended' => false,
            'reason' => 'Persistent but slower than memory-based drivers'
        ];

        return [
            'current_driver' => $this->defaultDriver,
            'available_drivers' => $this->getAvailableDriverTypes(),
            'recommendations' => $recommendations,
            'system_info' => [
                'php_version' => PHP_VERSION,
                'loaded_extensions' => get_loaded_extensions(),
                'memory_limit' => ini_get('memory_limit'),
                'opcache_enabled' => (bool) ini_get('opcache.enable') ? 'Yes' : 'No'
            ]
        ];
    }

    /**
     * Configure cache with smart defaults based on use case
     */
    public function configureForUseCase(string $useCase): array
    {
        $config = [];

        switch ($useCase) {
            case 'api_responses':
                $config = [
                    'default' => 'redis',
                    'ttl' => [
                        'market_data' => 60,
                        'static_data' => 3600,
                        'user_data' => 300
                    ]
                ];
                break;

            case 'session_storage':
                $config = [
                    'default' => 'redis',
                    'persistent' => true,
                    'ttl' => [
                        'session' => 1440, // 24 minutes
                        'remember_token' => 43200 // 30 days
                    ]
                ];
                break;

            case 'file_caching':
                $config = [
                    'default' => 'opcache',
                    'ttl' => [
                        'compiled_templates' => 86400,
                        'configuration' => 3600
                    ]
                ];
                break;

            case 'high_frequency':
                $config = [
                    'default' => 'memory',
                    'ttl' => [
                        'counters' => 300,
                        'rate_limits' => 60
                    ]
                ];
                break;

            default:
                $config = [
                    'default' => $this->autoDetectBestDriver(),
                    'ttl' => [
                        'default' => 3600
                    ]
                ];
        }

        return $config;
    }
}
