<?php

declare(strict_types=1);

namespace Wioex\SDK\Cache;

class PrefixedCache implements CacheInterface
{
    private CacheInterface $cache;
    private string $prefix;

    public function __construct(CacheInterface $cache, string $prefix)
    {
        $this->cache = $cache;
        $this->prefix = rtrim($prefix, ':') . ':';
    }

    public function get(string $key)
    {
        return $this->cache->get($this->prefixedKey($key));
    }

    public function set(string $key, $value, int $ttl = 0): bool
    {
        return $this->cache->set($this->prefixedKey($key), $value, $ttl);
    }

    public function has(string $key): bool
    {
        return $this->cache->has($this->prefixedKey($key));
    }

    public function delete(string $key): bool
    {
        return $this->cache->delete($this->prefixedKey($key));
    }

    public function clear(): bool
    {
        return $this->flush();
    }

    public function getMultiple(array $keys): array
    {
        $prefixedKeys = array_map([$this, 'prefixedKey'], $keys);
        $results = $this->cache->getMultiple($prefixedKeys);
        
        // Map back to original keys
        $mappedResults = [];
        foreach ($keys as $originalKey) {
            $prefixedKey = $this->prefixedKey($originalKey);
            $mappedResults[$originalKey] = $results[$prefixedKey] ?? null;
        }
        
        return $mappedResults;
    }

    public function setMultiple(array $items, int $ttl = 0): bool
    {
        $prefixedItems = [];
        
        foreach ($items as $key => $value) {
            $prefixedItems[$this->prefixedKey($key)] = $value;
        }
        
        return $this->cache->setMultiple($prefixedItems, $ttl);
    }

    public function deleteMultiple(array $keys): bool
    {
        $prefixedKeys = array_map([$this, 'prefixedKey'], $keys);
        return $this->cache->deleteMultiple($prefixedKeys);
    }

    public function increment(string $key, int $step = 1)
    {
        return $this->cache->increment($this->prefixedKey($key), $step);
    }

    public function decrement(string $key, int $step = 1)
    {
        return $this->cache->decrement($this->prefixedKey($key), $step);
    }

    public function getStatistics(): array
    {
        $stats = $this->cache->getStatistics();
        
        return array_merge($stats, [
            'prefixed_cache' => true,
            'prefix' => $this->prefix,
            'prefixed_items' => $this->countPrefixedItems(),
        ]);
    }

    public function getDriverInfo(): array
    {
        $info = $this->cache->getDriverInfo();
        
        return array_merge($info, [
            'prefixed_cache' => true,
            'prefix' => $this->prefix,
        ]);
    }

    public function isHealthy(): bool
    {
        return $this->cache->isHealthy();
    }

    public function getTtl(string $key)
    {
        return $this->cache->getTtl($this->prefixedKey($key));
    }

    public function touch(string $key, int $ttl): bool
    {
        return $this->cache->touch($this->prefixedKey($key), $ttl);
    }

    public function getKeys(string $pattern = '*'): array
    {
        $prefixedPattern = $this->prefixedKey($pattern);
        $keys = $this->cache->getKeys($prefixedPattern);
        
        // Remove prefix from results and filter only keys with our prefix
        return array_map(
            [$this, 'unprefixedKey'],
            array_filter($keys, [$this, 'hasPrefix'])
        );
    }

    public function getSize(string $key)
    {
        return $this->cache->getSize($this->prefixedKey($key));
    }

    public function flushExpired(): int
    {
        return $this->cache->flushExpired();
    }

    public function flush(): bool
    {
        $keys = $this->getAllPrefixedKeys();
        
        if (empty($keys)) {
            return true;
        }
        
        return $this->cache->deleteMultiple($keys);
    }

    public function flushPrefix(): bool
    {
        return $this->flush();
    }

    public function getPrefix(): string
    {
        return $this->prefix;
    }

    public function withPrefix(string $additionalPrefix): self
    {
        $newPrefix = $this->prefix . rtrim($additionalPrefix, ':') . ':';
        return new self($this->cache, $newPrefix);
    }

    public function namespace(string $namespace): self
    {
        return $this->withPrefix($namespace);
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

    public function tags(array $tags): TaggedCache
    {
        return new TaggedCache($this, $tags);
    }

    private function prefixedKey(string $key): string
    {
        return $this->prefix . $key;
    }

    private function unprefixedKey(string $prefixedKey): string
    {
        if ($this->hasPrefix($prefixedKey)) {
            return substr($prefixedKey, strlen($this->prefix));
        }
        
        return $prefixedKey;
    }

    private function hasPrefix(string $key): bool
    {
        return strpos($key, $this->prefix) === 0;
    }

    private function getAllPrefixedKeys(): array
    {
        $allKeys = $this->cache->getKeys('*');
        
        return array_filter($allKeys, [$this, 'hasPrefix']);
    }

    private function countPrefixedItems(): int
    {
        return count($this->getAllPrefixedKeys());
    }

    public function getUnderlyingCache(): CacheInterface
    {
        return $this->cache;
    }

    public function bulk(): BulkPrefixedCache
    {
        return new BulkPrefixedCache($this);
    }

    public function atomic(): AtomicPrefixedCache
    {
        return new AtomicPrefixedCache($this);
    }
}

class BulkPrefixedCache
{
    private PrefixedCache $cache;
    private array $operations = [];

    public function __construct(PrefixedCache $cache)
    {
        $this->cache = $cache;
    }

    public function set(string $key, $value, int $ttl = 0): self
    {
        $this->operations[] = ['set', $key, $value, $ttl];
        return $this;
    }

    public function delete(string $key): self
    {
        $this->operations[] = ['delete', $key];
        return $this;
    }

    public function increment(string $key, int $step = 1): self
    {
        $this->operations[] = ['increment', $key, $step];
        return $this;
    }

    public function decrement(string $key, int $step = 1): self
    {
        $this->operations[] = ['decrement', $key, $step];
        return $this;
    }

    public function execute(): array
    {
        $results = [];
        
        foreach ($this->operations as $operation) {
            [$method, $key] = $operation;
            
            switch ($method) {
                case 'set':
                    [, , $value, $ttl] = $operation;
                    $results[] = $this->cache->set($key, $value, $ttl);
                    break;
                    
                case 'delete':
                    $results[] = $this->cache->delete($key);
                    break;
                    
                case 'increment':
                    [, , $step] = $operation;
                    $results[] = $this->cache->increment($key, $step);
                    break;
                    
                case 'decrement':
                    [, , $step] = $operation;
                    $results[] = $this->cache->decrement($key, $step);
                    break;
            }
        }
        
        $this->operations = [];
        return $results;
    }

    public function clear(): self
    {
        $this->operations = [];
        return $this;
    }

    public function getOperations(): array
    {
        return $this->operations;
    }
}

class AtomicPrefixedCache
{
    private PrefixedCache $cache;
    private array $locks = [];

    public function __construct(PrefixedCache $cache)
    {
        $this->cache = $cache;
    }

    public function transaction(callable $callback): bool
    {
        try {
            $result = $callback($this->cache);
            return $result !== false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function lock(string $key, int $timeout = 10): bool
    {
        $lockKey = "lock:{$key}";
        $lockValue = uniqid('', true);
        
        if ($this->cache->set($lockKey, $lockValue, $timeout)) {
            $this->locks[$key] = $lockValue;
            return true;
        }
        
        return false;
    }

    public function unlock(string $key): bool
    {
        if (!isset($this->locks[$key])) {
            return false;
        }
        
        $lockKey = "lock:{$key}";
        $currentLock = $this->cache->get($lockKey);
        
        if ($currentLock === $this->locks[$key]) {
            unset($this->locks[$key]);
            return $this->cache->delete($lockKey);
        }
        
        return false;
    }

    public function withLock(string $key, callable $callback, int $timeout = 10)
    {
        if (!$this->lock($key, $timeout)) {
            throw new \RuntimeException("Could not acquire lock for key: {$key}");
        }
        
        try {
            return $callback($this->cache);
        } finally {
            $this->unlock($key);
        }
    }

    public function __destruct()
    {
        foreach (array_keys($this->locks) as $key) {
            $this->unlock($key);
        }
    }
}