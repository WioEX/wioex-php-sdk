<?php

declare(strict_types=1);

namespace Wioex\SDK\Http;

use ArrayAccess;
use Countable;
use Iterator;
use JsonSerializable;

/**
 * @implements ArrayAccess<string|int, mixed>
 * @implements Iterator<string|int, mixed>
 */
class ResponseCollection implements ArrayAccess, Countable, Iterator, JsonSerializable
{
    private array $items;
    private int $position = 0;

    public function __construct(array $items = [])
    {
        $this->items = $items;
    }

    public function all(): array
    {
        return $this->items;
    }

    public function first()
    {
        return reset($this->items) ?: null;
    }

    public function last()
    {
        return end($this->items) ?: null;
    }

    public function get($key, $default = null)
    {
        return $this->items[$key] ?? $default;
    }

    public function has($key): bool
    {
        return isset($this->items[$key]);
    }

    public function put($key, $value): self
    {
        $this->items[$key] = $value;
        return $this;
    }

    public function push($value): self
    {
        $this->items[] = $value;
        return $this;
    }

    public function pull($key, $default = null)
    {
        $value = $this->get($key, $default);
        unset($this->items[$key]);
        return $value;
    }

    public function forget($key): self
    {
        unset($this->items[$key]);
        return $this;
    }

    public function only(array $keys): self
    {
        return new self(array_intersect_key($this->items, array_flip($keys)));
    }

    public function except(array $keys): self
    {
        return new self(array_diff_key($this->items, array_flip($keys)));
    }

    public function filter(callable $callback = null): self
    {
        if ($callback === null) {
            return new self(array_filter($this->items));
        }

        return new self(array_filter($this->items, $callback, ARRAY_FILTER_USE_BOTH));
    }

    public function map(callable $callback): self
    {
        return new self(array_map($callback, $this->items));
    }

    public function transform(callable $callback): self
    {
        $this->items = array_map($callback, $this->items);
        return $this;
    }

    public function reduce(callable $callback, $initial = null)
    {
        return array_reduce($this->items, $callback, $initial);
    }

    public function pluck(string $key): self
    {
        $result = [];

        foreach ($this->items as $item) {
            if (is_array($item) && isset($item[$key])) {
                $result[] = $item[$key];
            } elseif (is_object($item) && property_exists($item, $key)) {
                $result[] = $item->$key;
            }
        }

        return new self($result);
    }

    public function where(string $key, $operator, $value = null): self
    {
        // If only 2 arguments, assume equality
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        return $this->filter(function ($item) use ($key, $operator, $value) {
            $itemValue = is_array($item) ? ($item[$key] ?? null) : (is_object($item) ? ($item->$key ?? null) : null);

            return match ($operator) {
                '=' => $itemValue == $value,
                '==' => $itemValue == $value,
                '===' => $itemValue === $value,
                '!=' => $itemValue != $value,
                '!==' => $itemValue !== $value,
                '>' => $itemValue > $value,
                '>=' => $itemValue >= $value,
                '<' => $itemValue < $value,
                '<=' => $itemValue <= $value,
                'in' => is_array($value) && in_array($itemValue, $value, true),
                'not_in' => is_array($value) && !in_array($itemValue, $value, true),
                'like' => is_string($itemValue) && is_string($value) && str_contains($itemValue, $value),
                'not_like' => is_string($itemValue) && is_string($value) && !str_contains($itemValue, $value),
                default => false,
            };
        });
    }

    public function whereIn(string $key, array $values): self
    {
        return $this->where($key, 'in', $values);
    }

    public function whereNotIn(string $key, array $values): self
    {
        return $this->where($key, 'not_in', $values);
    }

    public function whereLike(string $key, string $value): self
    {
        return $this->where($key, 'like', $value);
    }

    public function whereNotLike(string $key, string $value): self
    {
        return $this->where($key, 'not_like', $value);
    }

    public function sortBy(string $key, int $direction = SORT_ASC): self
    {
        $items = $this->items;

        uasort($items, function ($a, $b) use ($key, $direction) {
            $aValue = is_array($a) ? ($a[$key] ?? null) : (is_object($a) ? ($a->$key ?? null) : null);
            $bValue = is_array($b) ? ($b[$key] ?? null) : (is_object($b) ? ($b->$key ?? null) : null);

            $comparison = $aValue <=> $bValue;
            return $direction === SORT_DESC ? -$comparison : $comparison;
        });

        return new self($items);
    }

    public function sortByDesc(string $key): self
    {
        return $this->sortBy($key, SORT_DESC);
    }

    public function groupBy(string $key): self
    {
        $groups = [];

        foreach ($this->items as $item) {
            $groupKey = is_array($item) ? ($item[$key] ?? 'null') : (is_object($item) ? ($item->$key ?? 'null') : 'null');

            if (!isset($groups[$groupKey])) {
                $groups[$groupKey] = [];
            }

            $groups[$groupKey][] = $item;
        }

        return new self($groups);
    }

    public function unique(string $key = null): self
    {
        if ($key === null) {
            return new self(array_unique($this->items, SORT_REGULAR));
        }

        $unique = [];
        $seen = [];

        foreach ($this->items as $item) {
            $value = is_array($item) ? ($item[$key] ?? null) : (is_object($item) ? ($item->$key ?? null) : null);

            if (!in_array($value, $seen, true)) {
                $seen[] = $value;
                $unique[] = $item;
            }
        }

        return new self($unique);
    }

    public function slice(int $offset, int $length = null): self
    {
        return new self(array_slice($this->items, $offset, $length, true));
    }

    public function take(int $limit): self
    {
        return $this->slice(0, $limit);
    }

    public function skip(int $offset): self
    {
        return $this->slice($offset);
    }

    public function chunk(int $size): self
    {
        $chunks = array_chunk($this->items, $size, true);
        return new self(array_map(fn($chunk) => new self($chunk), $chunks));
    }

    public function flatten(int $depth = 1): self
    {
        $items = $this->items;

        while ($depth > 0 && $this->hasNestedArrays($items)) {
            $items = $this->flattenOnce($items);
            $depth--;
        }

        return new self($items);
    }

    public function reverse(): self
    {
        return new self(array_reverse($this->items, true));
    }

    public function shuffle(): self
    {
        $items = $this->items;
        shuffle($items);
        return new self($items);
    }

    public function merge(self $other): self
    {
        return new self(array_merge($this->items, $other->items));
    }

    public function union(self $other): self
    {
        return new self($this->items + $other->items);
    }

    public function intersect(self $other): self
    {
        return new self(array_intersect($this->items, $other->items));
    }

    public function diff(self $other): self
    {
        return new self(array_diff($this->items, $other->items));
    }

    public function values(): self
    {
        return new self(array_values($this->items));
    }

    public function keys(): self
    {
        return new self(array_keys($this->items));
    }

    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    public function isNotEmpty(): bool
    {
        return !$this->isEmpty();
    }

    public function contains($value, string $key = null): bool
    {
        if ($key === null) {
            return in_array($value, $this->items, true);
        }

        foreach ($this->items as $item) {
            $itemValue = is_array($item) ? ($item[$key] ?? null) : (is_object($item) ? ($item->$key ?? null) : null);
            if ($itemValue === $value) {
                return true;
            }
        }

        return false;
    }

    public function sum(string $key = null): float
    {
        if ($key === null) {
            return array_sum(array_filter($this->items, 'is_numeric'));
        }

        return $this->pluck($key)->sum();
    }

    public function avg(string $key = null): float
    {
        $count = $this->count();
        return $count > 0 ? $this->sum($key) / $count : 0;
    }

    public function min(string $key = null)
    {
        if ($key === null) {
            return min($this->items);
        }

        return min($this->pluck($key)->all());
    }

    public function max(string $key = null)
    {
        if ($key === null) {
            return max($this->items);
        }

        return max($this->pluck($key)->all());
    }

    // ArrayAccess implementation
    public function offsetExists(mixed $offset): bool
    {
        return isset($this->items[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->items[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($offset === null) {
            $this->items[] = $value;
        } else {
            $this->items[$offset] = $value;
        }
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->items[$offset]);
    }

    // Countable implementation
    public function count(): int
    {
        return count($this->items);
    }

    // Iterator implementation
    public function current(): mixed
    {
        return $this->items[array_keys($this->items)[$this->position]] ?? null;
    }

    public function key(): mixed
    {
        return array_keys($this->items)[$this->position] ?? null;
    }

    public function next(): void
    {
        $this->position++;
    }

    public function rewind(): void
    {
        $this->position = 0;
    }

    public function valid(): bool
    {
        $keys = array_keys($this->items);
        return isset($keys[$this->position]);
    }

    // JsonSerializable implementation
    public function jsonSerialize(): array
    {
        return $this->items;
    }

    public function toArray(): array
    {
        return $this->items;
    }

    public function toJson(): string
    {
        return json_encode($this->items, JSON_THROW_ON_ERROR);
    }

    public function __toString(): string
    {
        return $this->toJson();
    }

    private function hasNestedArrays(array $items): bool
    {
        foreach ($items as $item) {
            if (is_array($item)) {
                return true;
            }
        }
        return false;
    }

    private function flattenOnce(array $items): array
    {
        $result = [];

        foreach ($items as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $subValue) {
                    $result[] = $subValue;
                }
            } else {
                $result[] = $value;
            }
        }

        return $result;
    }
}
