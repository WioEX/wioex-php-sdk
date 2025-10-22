<?php

declare(strict_types=1);

namespace Wioex\SDK\Enums;

/**
 * Sort order for API responses
 *
 * Defines the order in which data should be returned:
 * - ASCENDING: Oldest to newest (chronological order)
 * - DESCENDING: Newest to oldest (reverse chronological order)
 */
enum SortOrder: string
{
    case ASCENDING = 'ASC';
    case DESCENDING = 'DESC';

    /**
     * Get human-readable description
     */
    public function getDescription(): string
    {
        return match ($this) {
            self::ASCENDING => 'Ascending (oldest to newest)',
            self::DESCENDING => 'Descending (newest to oldest)',
        };
    }

    /**
     * Get the opposite sort order
     */
    public function getOpposite(): self
    {
        return match ($this) {
            self::ASCENDING => self::DESCENDING,
            self::DESCENDING => self::ASCENDING,
        };
    }

    /**
     * Check if this is ascending order
     */
    public function isAscending(): bool
    {
        return $this === self::ASCENDING;
    }

    /**
     * Check if this is descending order
     */
    public function isDescending(): bool
    {
        return $this === self::DESCENDING;
    }

    /**
     * Create SortOrder from string value
     *
     * @param string $value The sort order string ('ASC', 'DESC', 'asc', 'desc')
     * @return self
     * @throws \InvalidArgumentException If the value is not a valid sort order
     */
    public static function fromString(string $value): self
    {
        $normalizedValue = strtoupper($value);
        return self::tryFrom($normalizedValue)
            ?? throw new \InvalidArgumentException("Invalid sort order: {$value}. Must be 'ASC' or 'DESC'");
    }

    /**
     * Get default sort order (ascending)
     */
    public static function default(): self
    {
        return self::ASCENDING;
    }
}
