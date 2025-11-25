<?php

declare(strict_types=1);

namespace Wioex\SDK\Cache\Drivers;

use Wioex\SDK\Cache\CacheInterface;
use Wioex\SDK\Exceptions\InvalidArgumentException;
use Redis;
use RedisException;

class RedisDriver implements CacheInterface
{
    private ?Redis $redis = null;
    private array $config;
    private array $stats = [
        'hits' => 0,
        'misses' => 0,
        'sets' => 0,
        'deletes' => 0,
        'errors' => 0,
        'connections' => 0,
        'reconnections' => 0
    ];

    /**
     * @param array{
     *     host?: string,
     *     port?: int,
     *     password?: string,
     *     database?: int,
     *     timeout?: float,
     *     read_timeout?: float,
     *     persistent?: bool,
     *     prefix?: string,
     *     serialization?: string,
     *     compression?: bool,
     *     retry_attempts?: int
     * } $config
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'host' => '127.0.0.1',
            'port' => 6379,
            'password' => null,
            'database' => 0,
            'timeout' => 5.0,
            'read_timeout' => 5.0,
            'persistent' => true,
            'prefix' => 'wioex:',
            'serialization' => 'igbinary', // json, igbinary, msgpack
            'compression' => true,
            'retry_attempts' => 3
        ], $config);

        if (!extension_loaded('redis')) {
            throw new InvalidArgumentException('Redis extension is not installed');
        }

        $this->connect();
    }

    private function connect(): void
    {
        try {
            $this->redis = new Redis();

            $connected = $this->config['persistent']
                ? $this->redis->pconnect(
                    $this->config['host'],
                    $this->config['port'],
                    $this->config['timeout'],
                    $this->config['prefix']
                )
                : $this->redis->connect(
                    $this->config['host'],
                    $this->config['port'],
                    $this->config['timeout']
                );

            if (!$connected) {
                throw new InvalidArgumentException('Failed to connect to Redis');
            }

            if ($this->config['password']) {
                $this->redis->auth($this->config['password']);
            }

            if ($this->config['database'] > 0) {
                $this->redis->select($this->config['database']);
            }

            // Set serialization mode
            $this->setSerializationMode();

            // Enable compression if supported and configured
            if ($this->config['compression'] && $this->redis->getOption(Redis::OPT_COMPRESSION)) {
                $this->redis->setOption(Redis::OPT_COMPRESSION, Redis::COMPRESSION_LZF);
            }

            // Set key prefix
            if ($this->config['prefix']) {
                $this->redis->setOption(Redis::OPT_PREFIX, $this->config['prefix']);
            }

            $this->stats['connections']++;

        } catch (RedisException $e) {
            $this->stats['errors']++;
            throw new InvalidArgumentException('Redis connection error: ' . $e->getMessage());
        }
    }

    private function setSerializationMode(): void
    {
        $mode = match ($this->config['serialization']) {
            'json' => Redis::SERIALIZER_JSON,
            'igbinary' => Redis::SERIALIZER_IGBINARY,
            'msgpack' => Redis::SERIALIZER_MSGPACK,
            default => Redis::SERIALIZER_PHP
        };

        $this->redis->setOption(Redis::OPT_SERIALIZER, $mode);
    }

    private function executeWithRetry(callable $operation): mixed
    {
        $attempts = 0;
        $maxAttempts = $this->config['retry_attempts'];

        while ($attempts < $maxAttempts) {
            try {
                if ($this->redis === null || !$this->redis->ping()) {
                    $this->reconnect();
                }
                
                return $operation();
                
            } catch (RedisException $e) {
                $attempts++;
                $this->stats['errors']++;

                if ($attempts >= $maxAttempts) {
                    throw new InvalidArgumentException('Redis operation failed: ' . $e->getMessage());
                }

                $this->reconnect();
            }
        }
        
        throw new InvalidArgumentException('Redis operation failed after maximum retry attempts');
    }

    private function reconnect(): void
    {
        try {
            $this->redis->close();
        } catch (RedisException $e) {
            // Ignore close errors
        }

        $this->connect();
        $this->stats['reconnections']++;
    }

    public function get(string $key): mixed
    {
        return $this->executeWithRetry(function () use ($key) {
            if ($this->redis === null) {
                throw new RedisException('Redis connection is null');
            }
            
            $value = $this->redis->get($key);
            
            if ($value === false) {
                $this->stats['misses']++;
                return null;
            }

            $this->stats['hits']++;
            return $value;
        });
    }

    public function set(string $key, mixed $value, int $ttl = 0): bool
    {
        return $this->executeWithRetry(function () use ($key, $value, $ttl) {
            if ($this->redis === null) {
                throw new RedisException('Redis connection is null');
            }
            
            $result = $ttl > 0 
                ? $this->redis->setex($key, $ttl, $value)
                : $this->redis->set($key, $value);

            if ($result) {
                $this->stats['sets']++;
            }

            return $result;
        });
    }

    public function has(string $key): bool
    {
        return $this->executeWithRetry(function () use ($key) {
            if ($this->redis === null) {
                throw new RedisException('Redis connection is null');
            }
            
            $exists = $this->redis->exists($key);
            return is_int($exists) && $exists > 0;
        });
    }

    public function delete(string $key): bool
    {
        return $this->executeWithRetry(function () use ($key) {
            if ($this->redis === null) {
                throw new RedisException('Redis connection is null');
            }
            
            $delResult = $this->redis->del($key);
            $result = is_int($delResult) && $delResult > 0;
            
            if ($result) {
                $this->stats['deletes']++;
            }

            return $result;
        });
    }

    public function clear(): bool
    {
        return $this->executeWithRetry(function () {
            if ($this->redis === null) {
                throw new RedisException('Redis connection is null');
            }
            
            return $this->redis->flushDB();
        });
    }

    public function getMultiple(array $keys): array
    {
        return $this->executeWithRetry(function () use ($keys) {
            if ($this->redis === null) {
                throw new RedisException('Redis connection is null');
            }
            
            $values = $this->redis->mGet($keys);
            $result = [];

            foreach ($keys as $index => $key) {
                if ($values[$index] !== false) {
                    $result[$key] = $values[$index];
                    $this->stats['hits']++;
                } else {
                    $this->stats['misses']++;
                }
            }

            return $result;
        });
    }

    public function setMultiple(array $items, int $ttl = 0): bool
    {
        return $this->executeWithRetry(function () use ($items, $ttl) {
            if ($ttl > 0) {
                $pipeline = $this->redis->pipeline();
                foreach ($items as $key => $value) {
                    $pipeline->setex($key, $ttl, $value);
                }
                $results = $pipeline->exec();
                $this->stats['sets'] += count($items);
                return !in_array(false, $results, true);
            } else {
                $result = $this->redis->mSet($items);
                if ($result) {
                    $this->stats['sets'] += count($items);
                }
                return $result;
            }
        });
    }

    public function deleteMultiple(array $keys): bool
    {
        return $this->executeWithRetry(function () use ($keys) {
            $deleted = $this->redis->del(...$keys);
            $this->stats['deletes'] += $deleted;
            return $deleted === count($keys);
        });
    }

    public function increment(string $key, int $step = 1)
    {
        return $this->executeWithRetry(function () use ($key, $step) {
            return $this->redis->incrBy($key, $step);
        });
    }

    public function decrement(string $key, int $step = 1)
    {
        return $this->executeWithRetry(function () use ($key, $step) {
            return $this->redis->decrBy($key, $step);
        });
    }

    public function getStatistics(): array
    {
        $redisInfo = [];
        
        try {
            $info = $this->redis->info();
            $redisInfo = [
                'redis_version' => $info['redis_version'] ?? 'unknown',
                'used_memory' => $info['used_memory_human'] ?? 'unknown',
                'connected_clients' => $info['connected_clients'] ?? 'unknown',
                'total_connections_received' => $info['total_connections_received'] ?? 'unknown',
                'total_commands_processed' => $info['total_commands_processed'] ?? 'unknown',
                'keyspace_hits' => $info['keyspace_hits'] ?? 'unknown',
                'keyspace_misses' => $info['keyspace_misses'] ?? 'unknown',
            ];
        } catch (RedisException $e) {
            $redisInfo['error'] = $e->getMessage();
        }

        return array_merge($this->stats, [
            'redis_info' => $redisInfo,
            'hit_ratio' => $this->getHitRatio(),
            'total_operations' => $this->stats['hits'] + $this->stats['misses'] + $this->stats['sets'] + $this->stats['deletes'],
            'driver' => 'redis',
            'configuration' => [
                'host' => $this->config['host'],
                'port' => $this->config['port'],
                'database' => $this->config['database'],
                'persistent' => $this->config['persistent'],
                'prefix' => $this->config['prefix'],
                'serialization' => $this->config['serialization'],
                'compression' => $this->config['compression']
            ]
        ]);
    }

    public function getDriverInfo(): array
    {
        return [
            'driver' => 'redis',
            'version' => '1.0.0',
            'description' => 'Redis cache driver with persistence and clustering support',
            'supports_serialization' => true,
            'supports_compression' => true,
            'supports_transactions' => true,
            'supports_clustering' => true,
            'configuration' => $this->config
        ];
    }

    public function isHealthy(): bool
    {
        try {
            return $this->redis && $this->redis->ping();
        } catch (RedisException $e) {
            return false;
        }
    }

    public function getTtl(string $key)
    {
        return $this->executeWithRetry(function () use ($key) {
            $ttl = $this->redis->ttl($key);
            return $ttl === -1 ? null : $ttl;
        });
    }

    public function touch(string $key, int $ttl): bool
    {
        return $this->executeWithRetry(function () use ($key, $ttl) {
            return $this->redis->expire($key, $ttl);
        });
    }

    public function getKeys(string $pattern = '*'): array
    {
        return $this->executeWithRetry(function () use ($pattern) {
            return $this->redis->keys($pattern);
        });
    }

    public function getSize(string $key)
    {
        return $this->executeWithRetry(function () use ($key) {
            return strlen(serialize($this->redis->get($key)));
        });
    }

    public function flushExpired(): int
    {
        // Redis automatically handles expired keys
        return 0;
    }

    private function getHitRatio(): float
    {
        $total = $this->stats['hits'] + $this->stats['misses'];
        return $total > 0 ? round(($this->stats['hits'] / $total) * 100, 2) : 0.0;
    }

    public function pipeline(): RedisPipeline
    {
        return new RedisPipeline($this->redis);
    }

    public function transaction(): RedisTransaction
    {
        return new RedisTransaction($this->redis);
    }

    public function pubsub(): RedisPubSub
    {
        return new RedisPubSub($this->redis);
    }

    public function __destruct()
    {
        if ($this->redis && !$this->config['persistent']) {
            try {
                $this->redis->close();
            } catch (RedisException $e) {
                // Ignore close errors
            }
        }
    }
}

class RedisPipeline
{
    private Redis $redis;
    private $pipeline;

    public function __construct(Redis $redis)
    {
        $this->redis = $redis;
        $this->pipeline = $redis->pipeline();
    }

    public function set(string $key, $value, int $ttl = 0): self
    {
        if ($ttl > 0) {
            $this->pipeline->setex($key, $ttl, $value);
        } else {
            $this->pipeline->set($key, $value);
        }
        return $this;
    }

    public function get(string $key): self
    {
        $this->pipeline->get($key);
        return $this;
    }

    public function delete(string $key): self
    {
        $this->pipeline->del($key);
        return $this;
    }

    public function execute(): array
    {
        return $this->pipeline->exec();
    }
}

class RedisTransaction
{
    private Redis $redis;

    public function __construct(Redis $redis)
    {
        $this->redis = $redis;
        $this->redis->multi();
    }

    public function set(string $key, $value, int $ttl = 0): self
    {
        if ($ttl > 0) {
            $this->redis->setex($key, $ttl, $value);
        } else {
            $this->redis->set($key, $value);
        }
        return $this;
    }

    public function get(string $key): self
    {
        $this->redis->get($key);
        return $this;
    }

    public function delete(string $key): self
    {
        $this->redis->del($key);
        return $this;
    }

    public function execute(): array
    {
        return $this->redis->exec();
    }

    public function discard(): bool
    {
        return $this->redis->discard();
    }
}

class RedisPubSub
{
    private Redis $redis;

    public function __construct(Redis $redis)
    {
        $this->redis = $redis;
    }

    public function publish(string $channel, string $message): int
    {
        return $this->redis->publish($channel, $message);
    }

    public function subscribe(array $channels, callable $callback): void
    {
        $this->redis->subscribe($channels, $callback);
    }

    public function psubscribe(array $patterns, callable $callback): void
    {
        $this->redis->psubscribe($patterns, $callback);
    }
}