<?php

declare(strict_types=1);

namespace Wioex\SDK\Cache;

class TaggedCache implements CacheInterface
{
    private CacheInterface $cache;
    private array $tags;
    private string $tagPrefix = 'tag:';

    public function __construct(CacheInterface $cache, array $tags)
    {
        $this->cache = $cache;
        $this->tags = array_unique($tags);
    }

    public function get(string $key)
    {
        if (!$this->isValidTaggedKey($key)) {
            return null;
        }

        return $this->cache->get($this->taggedKey($key));
    }

    public function set(string $key, $value, int $ttl = 0): bool
    {
        $taggedKey = $this->taggedKey($key);
        
        // Store the main cache item
        $result = $this->cache->set($taggedKey, $value, $ttl);
        
        if ($result) {
            // Update tag indexes
            $this->updateTagIndexes($key, $ttl);
        }
        
        return $result;
    }

    public function has(string $key): bool
    {
        if (!$this->isValidTaggedKey($key)) {
            return false;
        }

        return $this->cache->has($this->taggedKey($key));
    }

    public function delete(string $key): bool
    {
        $taggedKey = $this->taggedKey($key);
        $result = $this->cache->delete($taggedKey);
        
        if ($result) {
            $this->removeFromTagIndexes($key);
        }
        
        return $result;
    }

    public function clear(): bool
    {
        return $this->flush();
    }

    public function getMultiple(array $keys): array
    {
        $validKeys = array_filter($keys, [$this, 'isValidTaggedKey']);
        $taggedKeys = array_map([$this, 'taggedKey'], $validKeys);
        
        $results = $this->cache->getMultiple($taggedKeys);
        
        // Map back to original keys
        $mappedResults = [];
        foreach ($validKeys as $originalKey) {
            $taggedKey = $this->taggedKey($originalKey);
            $mappedResults[$originalKey] = $results[$taggedKey] ?? null;
        }
        
        return $mappedResults;
    }

    public function setMultiple(array $items, int $ttl = 0): bool
    {
        $taggedItems = [];
        
        foreach ($items as $key => $value) {
            $taggedKey = $this->taggedKey($key);
            $taggedItems[$taggedKey] = $value;
        }
        
        $result = $this->cache->setMultiple($taggedItems, $ttl);
        
        if ($result) {
            foreach (array_keys($items) as $key) {
                $this->updateTagIndexes($key, $ttl);
            }
        }
        
        return $result;
    }

    public function deleteMultiple(array $keys): bool
    {
        $validKeys = array_filter($keys, [$this, 'isValidTaggedKey']);
        $taggedKeys = array_map([$this, 'taggedKey'], $validKeys);
        
        $result = $this->cache->deleteMultiple($taggedKeys);
        
        if ($result) {
            foreach ($validKeys as $key) {
                $this->removeFromTagIndexes($key);
            }
        }
        
        return $result;
    }

    public function increment(string $key, int $step = 1)
    {
        if (!$this->isValidTaggedKey($key)) {
            return false;
        }

        return $this->cache->increment($this->taggedKey($key), $step);
    }

    public function decrement(string $key, int $step = 1)
    {
        if (!$this->isValidTaggedKey($key)) {
            return false;
        }

        return $this->cache->decrement($this->taggedKey($key), $step);
    }

    public function getStatistics(): array
    {
        $stats = $this->cache->getStatistics();
        
        return array_merge($stats, [
            'tagged_cache' => true,
            'tags' => $this->tags,
            'tag_count' => count($this->tags),
            'items_in_tags' => $this->countItemsInTags(),
        ]);
    }

    public function getDriverInfo(): array
    {
        $info = $this->cache->getDriverInfo();
        
        return array_merge($info, [
            'tagged_cache' => true,
            'tags' => $this->tags,
            'tag_prefix' => $this->tagPrefix,
        ]);
    }

    public function isHealthy(): bool
    {
        return $this->cache->isHealthy();
    }

    public function getTtl(string $key)
    {
        if (!$this->isValidTaggedKey($key)) {
            return false;
        }

        return $this->cache->getTtl($this->taggedKey($key));
    }

    public function touch(string $key, int $ttl): bool
    {
        if (!$this->isValidTaggedKey($key)) {
            return false;
        }

        return $this->cache->touch($this->taggedKey($key), $ttl);
    }

    public function getKeys(string $pattern = '*'): array
    {
        $taggedPattern = $this->taggedKey($pattern);
        $keys = $this->cache->getKeys($taggedPattern);
        
        // Remove tag prefix from results
        return array_map([$this, 'untaggedKey'], $keys);
    }

    public function getSize(string $key)
    {
        if (!$this->isValidTaggedKey($key)) {
            return false;
        }

        return $this->cache->getSize($this->taggedKey($key));
    }

    public function flushExpired(): int
    {
        return $this->cache->flushExpired();
    }

    public function flush(): bool
    {
        $flushed = 0;
        
        foreach ($this->tags as $tag) {
            $tagKey = $this->getTagKey($tag);
            $items = $this->cache->get($tagKey) ?? [];
            
            if (is_array($items)) {
                foreach ($items as $item) {
                    if ($this->cache->delete($item)) {
                        $flushed++;
                    }
                }
            }
            
            $this->cache->delete($tagKey);
        }
        
        return $flushed > 0;
    }

    public function flushTag(string $tag): bool
    {
        if (!in_array($tag, $this->tags, true)) {
            return false;
        }
        
        $tagKey = $this->getTagKey($tag);
        $items = $this->cache->get($tagKey) ?? [];
        
        if (is_array($items)) {
            foreach ($items as $item) {
                $this->cache->delete($item);
            }
        }
        
        return $this->cache->delete($tagKey);
    }

    public function getTags(): array
    {
        return $this->tags;
    }

    public function addTag(string $tag): self
    {
        if (!in_array($tag, $this->tags, true)) {
            $this->tags[] = $tag;
        }
        
        return $this;
    }

    public function removeTag(string $tag): self
    {
        $this->tags = array_values(array_filter(
            $this->tags,
            fn($t) => $t !== $tag
        ));
        
        return $this;
    }

    public function hasTag(string $tag): bool
    {
        return in_array($tag, $this->tags, true);
    }

    private function taggedKey(string $key): string
    {
        $tagHash = md5(implode('|', $this->tags));
        return "tagged:{$tagHash}:{$key}";
    }

    private function untaggedKey(string $taggedKey): string
    {
        $tagHash = md5(implode('|', $this->tags));
        $prefix = "tagged:{$tagHash}:";
        
        if (strpos($taggedKey, $prefix) === 0) {
            return substr($taggedKey, strlen($prefix));
        }
        
        return $taggedKey;
    }

    private function getTagKey(string $tag): string
    {
        return $this->tagPrefix . $tag;
    }

    private function isValidTaggedKey(string $key): bool
    {
        foreach ($this->tags as $tag) {
            $tagKey = $this->getTagKey($tag);
            $items = $this->cache->get($tagKey) ?? [];
            
            if (is_array($items) && in_array($this->taggedKey($key), $items, true)) {
                return true;
            }
        }
        
        return true; // Allow new items to be cached
    }

    private function updateTagIndexes(string $key, int $ttl): void
    {
        $taggedKey = $this->taggedKey($key);
        
        foreach ($this->tags as $tag) {
            $tagKey = $this->getTagKey($tag);
            $items = $this->cache->get($tagKey) ?? [];
            
            if (!is_array($items)) {
                $items = [];
            }
            
            if (!in_array($taggedKey, $items, true)) {
                $items[] = $taggedKey;
                
                // Set tag index TTL to be longer than item TTL
                $tagTtl = $ttl > 0 ? $ttl + 3600 : 0;
                $this->cache->set($tagKey, $items, $tagTtl);
            }
        }
    }

    private function removeFromTagIndexes(string $key): void
    {
        $taggedKey = $this->taggedKey($key);
        
        foreach ($this->tags as $tag) {
            $tagKey = $this->getTagKey($tag);
            $items = $this->cache->get($tagKey) ?? [];
            
            if (is_array($items)) {
                $items = array_values(array_filter(
                    $items,
                    fn($item) => $item !== $taggedKey
                ));
                
                if (empty($items)) {
                    $this->cache->delete($tagKey);
                } else {
                    $this->cache->set($tagKey, $items);
                }
            }
        }
    }

    private function countItemsInTags(): array
    {
        $counts = [];
        
        foreach ($this->tags as $tag) {
            $tagKey = $this->getTagKey($tag);
            $items = $this->cache->get($tagKey) ?? [];
            $counts[$tag] = is_array($items) ? count($items) : 0;
        }
        
        return $counts;
    }
}