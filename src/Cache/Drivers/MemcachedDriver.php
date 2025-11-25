<?php

declare(strict_types=1);

namespace Wioex\SDK\Cache\Drivers;

use Wioex\SDK\Cache\CacheInterface;
use Wioex\SDK\Exceptions\InvalidArgumentException;
use Memcached;

class MemcachedDriver implements CacheInterface
{
    private ?Memcached $memcached = null;
    private array $config;
    private array $stats = [
        'hits' => 0,
        'misses' => 0,
        'sets' => 0,
        'deletes' => 0,
        'errors' => 0,
        'connections' => 0
    ];

    /**
     * @param array{
     *     servers?: array<array{host: string, port: int, weight?: int}>,
     *     persistent_id?: string,
     *     prefix?: string,
     *     compression?: bool,
     *     serialization?: bool,
     *     binary_protocol?: bool,
     *     connect_timeout?: int,
     *     retry_timeout?: int,
     *     send_timeout?: int,
     *     recv_timeout?: int
     * } $config
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'servers' => [['host' => '127.0.0.1', 'port' => 11211, 'weight' => 100]],
            'persistent_id' => 'wioex_cache',
            'prefix' => 'wioex:',
            'compression' => true,
            'serialization' => true,
            'binary_protocol' => true,
            'connect_timeout' => 1000,
            'retry_timeout' => 2,
            'send_timeout' => 1000000,
            'recv_timeout' => 1000000
        ], $config);

        if (!extension_loaded('memcached')) {
            throw new InvalidArgumentException('Memcached extension is not installed');
        }

        $this->connect();
    }

    private function connect(): void
    {
        try {
            $this->memcached = new Memcached($this->config['persistent_id']);

            // Check if servers are already added (for persistent connections)
            if (count($this->memcached->getServerList()) === 0) {
                $this->memcached->addServers($this->config['servers']);
            }

            // Configure options
            $this->memcached->setOptions([
                Memcached::OPT_COMPRESSION => $this->config['compression'],
                Memcached::OPT_SERIALIZER => $this->config['serialization'] ? Memcached::SERIALIZER_IGBINARY : Memcached::SERIALIZER_PHP,
                Memcached::OPT_BINARY_PROTOCOL => $this->config['binary_protocol'],
                Memcached::OPT_CONNECT_TIMEOUT => $this->config['connect_timeout'],
                Memcached::OPT_RETRY_TIMEOUT => $this->config['retry_timeout'],
                Memcached::OPT_SEND_TIMEOUT => $this->config['send_timeout'],
                Memcached::OPT_RECV_TIMEOUT => $this->config['recv_timeout'],
                Memcached::OPT_PREFIX_KEY => $this->config['prefix'],
                Memcached::OPT_LIBKETAMA_COMPATIBLE => true,
                Memcached::OPT_DISTRIBUTION => Memcached::DISTRIBUTION_CONSISTENT,
                Memcached::OPT_TCP_NODELAY => true,
            ]);

            // Test connection
            $version = $this->memcached->getVersion();
            if (($version === null || $version === '' || $version === [])) {
                throw new InvalidArgumentException('Failed to connect to Memcached servers');
            }

            $this->stats['connections']++;

        } catch (\Exception $e) {
            $this->stats['errors']++;
            throw new InvalidArgumentException('Memcached connection error: ' . $e->getMessage());
        }
    }

    public function get(string $key)
    {
        try {
            if ($this->memcached === null) {
                return null;
            }
            
            $value = $this->memcached->get($key);
            
            if ($this->memcached->getResultCode() === Memcached::RES_SUCCESS) {
                $this->stats['hits']++;
                return $value;
            }

            $this->stats['misses']++;
            return null;

        } catch (\Exception $e) {
            $this->stats['errors']++;
            throw new InvalidArgumentException('Memcached get error: ' . $e->getMessage());
        }
    }

    public function set(string $key, $value, int $ttl = 0): bool
    {
        try {
            if ($this->memcached === null) {
                return false;
            }
            
            $expiration = $ttl > 0 ? time() + $ttl : 0;
            $result = $this->memcached->set($key, $value, $expiration);
            
            if ($result) {
                $this->stats['sets']++;
            } else {
                $this->stats['errors']++;
            }

            return $result;

        } catch (\Exception $e) {
            $this->stats['errors']++;
            throw new InvalidArgumentException('Memcached set error: ' . $e->getMessage());
        }
    }

    public function has(string $key): bool
    {
        try {
            if ($this->memcached === null) {
                return false;
            }
            
            $this->memcached->get($key);
            return $this->memcached->getResultCode() === Memcached::RES_SUCCESS;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function delete(string $key): bool
    {
        try {
            if ($this->memcached === null) {
                return false;
            }
            
            $result = $this->memcached->delete($key);
            
            if ($result) {
                $this->stats['deletes']++;
            }

            return $result;

        } catch (\Exception $e) {
            $this->stats['errors']++;
            return false;
        }
    }

    public function clear(): bool
    {
        try {
            if ($this->memcached === null) {
                return false;
            }
            
            return $this->memcached->flush();
        } catch (\Exception $e) {
            $this->stats['errors']++;
            return false;
        }
    }

    public function getMultiple(array $keys): array
    {
        try {
            if ($this->memcached === null) {
                return [];
            }
            
            $values = $this->memcached->getMulti($keys);
            
            if ($this->memcached->getResultCode() === Memcached::RES_SUCCESS) {
                $valuesCount = is_array($values) ? count($values) : 0;
                $this->stats['hits'] += $valuesCount;
                $this->stats['misses'] += count($keys) - $valuesCount;
                return $values !== false ? $values : [];
            }

            $this->stats['misses'] += count($keys);
            return [];

        } catch (\Exception $e) {
            $this->stats['errors']++;
            return [];
        }
    }

    public function setMultiple(array $items, int $ttl = 0): bool
    {
        try {
            if ($this->memcached === null) {
                return false;
            }
            
            $expiration = $ttl > 0 ? time() + $ttl : 0;
            $result = $this->memcached->setMulti($items, $expiration);
            
            if ($result) {
                $this->stats['sets'] += count($items);
            } else {
                $this->stats['errors']++;
            }

            return $result;

        } catch (\Exception $e) {
            $this->stats['errors']++;
            return false;
        }
    }

    public function deleteMultiple(array $keys): bool
    {
        try {
            if ($this->memcached === null) {
                return false;
            }
            
            $results = $this->memcached->deleteMulti($keys);
            $deleted = array_filter($results);
            $this->stats['deletes'] += count($deleted);
            
            return count($deleted) === count($keys);

        } catch (\Exception $e) {
            $this->stats['errors']++;
            return false;
        }
    }

    public function increment(string $key, int $step = 1)
    {
        try {
            if ($this->memcached === null) {
                return false;
            }
            
            $result = $this->memcached->increment($key, $step);
            
            if ($result === false) {
                // Key doesn't exist, set it to the step value
                $this->set($key, $step);
                return $step;
            }

            return $result;

        } catch (\Exception $e) {
            $this->stats['errors']++;
            return false;
        }
    }

    public function decrement(string $key, int $step = 1)
    {
        try {
            if ($this->memcached === null) {
                return false;
            }
            
            $result = $this->memcached->decrement($key, $step);
            
            if ($result === false) {
                // Key doesn't exist, set it to 0
                $this->set($key, 0);
                return 0;
            }

            return $result;

        } catch (\Exception $e) {
            $this->stats['errors']++;
            return false;
        }
    }

    public function getStatistics(): array
    {
        $memcachedStats = [];
        
        try {
            if ($this->memcached === null) {
                return $this->stats;
            }
            
            $stats = $this->memcached->getStats();
            
            foreach ($stats as $server => $serverStats) {
                if (($serverStats !== null && $serverStats !== '' && $serverStats !== [])) {
                    $memcachedStats[$server] = [
                        'version' => $serverStats['version'] ?? 'unknown',
                        'uptime' => $serverStats['uptime'] ?? 0,
                        'get_hits' => $serverStats['get_hits'] ?? 0,
                        'get_misses' => $serverStats['get_misses'] ?? 0,
                        'cmd_get' => $serverStats['cmd_get'] ?? 0,
                        'cmd_set' => $serverStats['cmd_set'] ?? 0,
                        'bytes_read' => $serverStats['bytes_read'] ?? 0,
                        'bytes_written' => $serverStats['bytes_written'] ?? 0,
                        'curr_connections' => $serverStats['curr_connections'] ?? 0,
                        'total_connections' => $serverStats['total_connections'] ?? 0,
                        'bytes' => $serverStats['bytes'] ?? 0,
                        'limit_maxbytes' => $serverStats['limit_maxbytes'] ?? 0,
                    ];
                }
            }
        } catch (\Exception $e) {
            $memcachedStats['error'] = $e->getMessage();
        }

        return array_merge($this->stats, [
            'memcached_stats' => $memcachedStats,
            'hit_ratio' => $this->getHitRatio(),
            'total_operations' => $this->stats['hits'] + $this->stats['misses'] + $this->stats['sets'] + $this->stats['deletes'],
            'driver' => 'memcached',
            'configuration' => [
                'servers' => $this->config['servers'],
                'persistent_id' => $this->config['persistent_id'],
                'prefix' => $this->config['prefix'],
                'compression' => $this->config['compression'],
                'serialization' => $this->config['serialization'],
                'binary_protocol' => $this->config['binary_protocol']
            ]
        ]);
    }

    public function getDriverInfo(): array
    {
        $version = [];
        try {
            if ($this->memcached !== null) {
                $version = $this->memcached->getVersion();
            }
        } catch (\Exception $e) {
            $version = ['error' => $e->getMessage()];
        }

        return [
            'driver' => 'memcached',
            'version' => '1.0.0',
            'description' => 'Memcached cache driver with clustering and persistence support',
            'supports_serialization' => true,
            'supports_compression' => true,
            'supports_clustering' => true,
            'supports_persistent' => true,
            'server_versions' => $version,
            'configuration' => $this->config
        ];
    }

    public function isHealthy(): bool
    {
        try {
            if ($this->memcached === null) {
                return false;
            }
            
            $version = $this->memcached->getVersion();
            return ($version !== null && $version !== '' && $version !== []);
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getTtl(string $key)
    {
        // Memcached doesn't support TTL retrieval natively
        // We could implement this with additional metadata storage if needed
        return false;
    }

    public function touch(string $key, int $ttl): bool
    {
        try {
            if ($this->memcached === null) {
                return false;
            }
            
            return $this->memcached->touch($key, time() + $ttl);
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getKeys(string $pattern = '*'): array
    {
        // Memcached doesn't support key enumeration for security/performance reasons
        // This is a limitation of the protocol
        return [];
    }

    public function getSize(string $key)
    {
        // Get the value and calculate its size
        $value = $this->get($key);
        return $value !== null ? strlen(serialize($value)) : 0;
    }

    public function flushExpired(): int
    {
        // Memcached automatically handles expired keys
        return 0;
    }

    private function getHitRatio(): float
    {
        $total = (int) $this->stats['hits'] + (int) $this->stats['misses'];
        return $total > 0 ? round(((int) $this->stats['hits'] / $total) * 100, 2) : 0.0;
    }

    public function getResultCode(): int
    {
        return $this->memcached !== null ? $this->memcached->getResultCode() : Memcached::RES_FAILURE;
    }

    public function getResultMessage(): string
    {
        return $this->memcached !== null ? $this->memcached->getResultMessage() : 'Memcached not initialized';
    }

    public function getAllKeys(): array
    {
        // This is expensive and not recommended for production
        // Memcached doesn't natively support this operation
        return [];
    }

    public function getServerList(): array
    {
        if ($this->memcached === null) {
            return [];
        }
        
        try {
            return $this->memcached->getServerList();
        } catch (\Exception $e) {
            return [];
        }
    }

    public function addServer(string $host, int $port, int $weight = 100): bool
    {
        if ($this->memcached === null) {
            return false;
        }
        
        try {
            return $this->memcached->addServer($host, $port, $weight);
        } catch (\Exception $e) {
            return false;
        }
    }

    public function resetServerList(): bool
    {
        if ($this->memcached === null) {
            return false;
        }
        
        try {
            return $this->memcached->resetServerList();
        } catch (\Exception $e) {
            return false;
        }
    }

    public function quit(): bool
    {
        if ($this->memcached === null) {
            return false;
        }
        
        try {
            return $this->memcached->quit();
        } catch (\Exception $e) {
            return false;
        }
    }
}