<?php

declare(strict_types=1);

namespace Wioex\SDK\Cache;

use Wioex\SDK\Cache\Drivers\FileDriver;
use Wioex\SDK\Cache\Drivers\MemoryDriver;
use Wioex\SDK\Exceptions\InvalidArgumentException;

class CacheManager implements CacheInterface
{
    private CacheInterface $driver;
    private string $defaultDriver;
    private array $drivers = [];
    private array $config;

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
        $this->defaultDriver = $config['default'] ?? 'memory';

        $this->initializeDrivers();
        $this->driver = $this->getDriver($this->defaultDriver);
    }

    private function initializeDrivers(): void
    {
        $driverConfigs = $this->config['drivers'] ?? [];

        // Register default drivers if not explicitly configured
        if (!isset($driverConfigs['memory'])) {
            $driverConfigs['memory'] = [
                'driver' => 'memory',
                'config' => $this->config['memory'] ?? []
            ];
        }

        if (!isset($driverConfigs['file'])) {
            $driverConfigs['file'] = [
                'driver' => 'file',
                'config' => $this->config['file'] ?? []
            ];
        }

        foreach ($driverConfigs as $name => $driverConfig) {
            $this->drivers[$name] = $this->createDriver(
                $driverConfig['driver'],
                $driverConfig['config'] ?? []
            );
        }
    }

    private function createDriver(string $driverType, array $config): CacheInterface
    {
        return match ($driverType) {
            'memory' => new MemoryDriver(),
            'file' => new FileDriver($config),
            default => throw new InvalidArgumentException("Unsupported cache driver: {$driverType}")
        };
    }

    public function driver(string $name = null): CacheInterface
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

    public function remember(string $key, callable $callback, int $ttl = 0)
    {
        $value = $this->get($key);

        if ($value !== null) {
            return $value;
        }

        $value = $callback();
        $this->set($key, $value, $ttl);

        return $value;
    }

    public function rememberForever(string $key, callable $callback)
    {
        return $this->remember($key, $callback, 0);
    }

    public function pull(string $key)
    {
        $value = $this->get($key);
        $this->delete($key);
        return $value;
    }

    public function put(string $key, $value, int $ttl = 0): bool
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

    public function __call(string $method, array $parameters)
    {
        if (isset($this->macros[$method])) {
            return call_user_func_array($this->macros[$method], $parameters);
        }

        throw new InvalidArgumentException("Method '{$method}' does not exist on CacheManager");
    }
}
