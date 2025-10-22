<?php

declare(strict_types=1);

namespace Wioex\SDK\Enums;

/**
 * Usage period types for account statistics
 *
 * Represents different time periods for API usage analytics:
 * - SEVEN_DAYS: Last 7 days of usage
 * - THIRTY_DAYS: Last 30 days of usage  
 * - NINETY_DAYS: Last 90 days of usage
 */
enum UsagePeriod: int
{
    case SEVEN_DAYS = 7;
    case THIRTY_DAYS = 30;
    case NINETY_DAYS = 90;

    /**
     * Get human-readable description
     */
    public function getDescription(): string
    {
        return match ($this) {
            self::SEVEN_DAYS => 'Last 7 days',
            self::THIRTY_DAYS => 'Last 30 days',
            self::NINETY_DAYS => 'Last 90 days',
        };
    }

    /**
     * Get period type classification
     */
    public function getPeriodType(): string
    {
        return match ($this) {
            self::SEVEN_DAYS => 'short_term',
            self::THIRTY_DAYS => 'medium_term',
            self::NINETY_DAYS => 'long_term',
        };
    }

    /**
     * Get recommended use case
     */
    public function getUseCase(): string
    {
        return match ($this) {
            self::SEVEN_DAYS => 'Recent activity monitoring, daily usage patterns',
            self::THIRTY_DAYS => 'Monthly usage analysis, billing period review',
            self::NINETY_DAYS => 'Quarterly analysis, long-term usage trends',
        };
    }

    /**
     * Get data granularity level
     */
    public function getDataGranularity(): string
    {
        return match ($this) {
            self::SEVEN_DAYS => 'hourly',  // Detailed hourly breakdown
            self::THIRTY_DAYS => 'daily',  // Daily aggregation
            self::NINETY_DAYS => 'weekly', // Weekly aggregation
        };
    }

    /**
     * Get expected data volume
     */
    public function getExpectedDataVolume(): string
    {
        return match ($this) {
            self::SEVEN_DAYS => 'Small - Detailed recent data',
            self::THIRTY_DAYS => 'Medium - Monthly aggregation',
            self::NINETY_DAYS => 'Large - Quarterly historical data',
        };
    }

    /**
     * Check if this is a short-term period
     */
    public function isShortTerm(): bool
    {
        return $this === self::SEVEN_DAYS;
    }

    /**
     * Check if this is a medium-term period
     */
    public function isMediumTerm(): bool
    {
        return $this === self::THIRTY_DAYS;
    }

    /**
     * Check if this is a long-term period
     */
    public function isLongTerm(): bool
    {
        return $this === self::NINETY_DAYS;
    }

    /**
     * Get period in weeks
     */
    public function getWeeks(): float
    {
        return round($this->value / 7, 1);
    }

    /**
     * Get period in months (approximate)
     */
    public function getMonths(): float
    {
        return round($this->value / 30, 1);
    }

    /**
     * Get cache TTL for this period's data
     */
    public function getCacheTTL(): int
    {
        return match ($this) {
            self::SEVEN_DAYS => 300,    // 5 minutes (recent data changes frequently)
            self::THIRTY_DAYS => 1800,  // 30 minutes (daily patterns are stable)
            self::NINETY_DAYS => 3600,  // 1 hour (historical data is stable)
        };
    }

    /**
     * Create UsagePeriod from integer value
     *
     * @param int $value The period in days
     * @return self
     * @throws \InvalidArgumentException If the value is not a valid usage period
     */
    public static function fromInt(int $value): self
    {
        return self::tryFrom($value) 
            ?? throw new \InvalidArgumentException("Invalid usage period: {$value} days. Valid periods: 7, 30, 90");
    }

    /**
     * Get default usage period
     */
    public static function default(): self
    {
        return self::THIRTY_DAYS;
    }

    /**
     * Get all available periods with descriptions
     *
     * @return array<int, string> Array of days => description
     */
    public static function getAllPeriods(): array
    {
        $periods = [];
        foreach (self::cases() as $period) {
            $periods[$period->value] = $period->getDescription();
        }
        return $periods;
    }

    /**
     * Get periods suitable for trend analysis
     *
     * @return array<UsagePeriod>
     */
    public static function getTrendAnalysisPeriods(): array
    {
        return [self::THIRTY_DAYS, self::NINETY_DAYS];
    }

    /**
     * Get periods suitable for recent activity monitoring
     *
     * @return array<UsagePeriod>
     */
    public static function getRecentActivityPeriods(): array
    {
        return [self::SEVEN_DAYS, self::THIRTY_DAYS];
    }
}