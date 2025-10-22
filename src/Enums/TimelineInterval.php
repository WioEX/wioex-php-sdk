<?php

declare(strict_types=1);

namespace Wioex\SDK\Enums;

/**
 * Timeline interval types for historical stock data
 *
 * Supports 17 different interval types with period-based optimization:
 * - Minute intervals for high-frequency trading and day trading
 * - Hour intervals for swing trading and trend analysis
 * - Daily/Weekly/Monthly intervals for long-term analysis
 * - Period-based intervals with automatic optimization for specific timeframes
 */
enum TimelineInterval: string
{
    // Minute intervals (high frequency, short cache)
    case ONE_MINUTE = '1min';
    case FIVE_MINUTES = '5min';
    case FIFTEEN_MINUTES = '15min';
    case THIRTY_MINUTES = '30min';

    // Hour intervals (medium frequency)
    case ONE_HOUR = '1hour';
    case FIVE_HOURS = '5hour';

    // Daily/Weekly/Monthly intervals (low frequency)
    case ONE_DAY = '1day';
    case ONE_WEEK = '1week';
    case ONE_MONTH = '1month';

    // Period-based intervals (optimized for specific timeframes)
    case PERIOD_1D = '1d';      // 1 day period with 5-minute intervals
    case PERIOD_1W = '1w';      // 1 week period with 30-minute intervals
    case PERIOD_1M = '1m';      // 1 month period with 5-hour intervals
    case PERIOD_3M = '3m';      // 3 months period with daily intervals
    case PERIOD_6M = '6m';      // 6 months period with daily intervals
    case PERIOD_1Y = '1y';      // 1 year period with weekly intervals
    case PERIOD_5Y = '5y';      // 5 years period with monthly intervals
    case PERIOD_MAX = 'max';    // Maximum available data with monthly intervals

    /**
     * Get all minute-level intervals
     *
     * @return array<TimelineInterval>
     */
    public static function getMinuteIntervals(): array
    {
        return [
            self::ONE_MINUTE,
            self::FIVE_MINUTES,
            self::FIFTEEN_MINUTES,
            self::THIRTY_MINUTES,
        ];
    }

    /**
     * Get all hour-level intervals
     *
     * @return array<TimelineInterval>
     */
    public static function getHourIntervals(): array
    {
        return [
            self::ONE_HOUR,
            self::FIVE_HOURS,
        ];
    }

    /**
     * Get all daily/weekly/monthly intervals
     *
     * @return array<TimelineInterval>
     */
    public static function getStandardIntervals(): array
    {
        return [
            self::ONE_DAY,
            self::ONE_WEEK,
            self::ONE_MONTH,
        ];
    }

    /**
     * Get all period-based intervals
     *
     * @return array<TimelineInterval>
     */
    public static function getPeriodBasedIntervals(): array
    {
        return [
            self::PERIOD_1D,
            self::PERIOD_1W,
            self::PERIOD_1M,
            self::PERIOD_3M,
            self::PERIOD_6M,
            self::PERIOD_1Y,
            self::PERIOD_5Y,
            self::PERIOD_MAX,
        ];
    }

    /**
     * Check if interval supports trading session filtering
     *
     * Session filtering only applies to minute-level intervals
     */
    public function supportsSessionFiltering(): bool
    {
        return in_array($this, self::getMinuteIntervals(), true) ||
               $this === self::PERIOD_1D; // 1d period uses 5min intervals
    }

    /**
     * Get cache TTL in seconds for this interval
     *
     * Returns optimal cache duration based on interval frequency
     */
    public function getCacheTTL(): int
    {
        return match ($this) {
            // High frequency intervals - shorter cache
            self::ONE_MINUTE => 60,        // 1 minute
            self::FIVE_MINUTES => 300,     // 5 minutes
            self::FIFTEEN_MINUTES => 900,  // 15 minutes
            self::THIRTY_MINUTES => 1800,  // 30 minutes

            // Medium frequency intervals
            self::ONE_HOUR => 3600,        // 1 hour
            self::FIVE_HOURS => 3600,      // 1 hour

            // Low frequency intervals - longer cache
            self::ONE_DAY => 3600,         // 1 hour
            self::ONE_WEEK => 7200,        // 2 hours
            self::ONE_MONTH => 14400,      // 4 hours

            // Period-based intervals - optimized per period
            self::PERIOD_1D => 300,        // 5 minutes (intraday)
            self::PERIOD_1W => 1800,       // 30 minutes
            self::PERIOD_1M => 3600,       // 1 hour
            self::PERIOD_3M => 7200,       // 2 hours
            self::PERIOD_6M => 14400,      // 4 hours
            self::PERIOD_1Y => 28800,      // 8 hours
            self::PERIOD_5Y => 86400,      // 24 hours
            self::PERIOD_MAX => 172800,    // 48 hours
        };
    }

    /**
     * Get trading strategy recommendation for this interval
     */
    public function getTradingStrategy(): string
    {
        return match ($this) {
            self::ONE_MINUTE, self::FIVE_MINUTES => 'Day Trading / Scalping',
            self::FIFTEEN_MINUTES, self::THIRTY_MINUTES => 'Day Trading / Short-term',
            self::ONE_HOUR, self::FIVE_HOURS => 'Swing Trading',
            self::ONE_DAY => 'Medium-term Analysis',
            self::ONE_WEEK, self::ONE_MONTH => 'Long-term Analysis',
            self::PERIOD_1D => 'Intraday Analysis',
            self::PERIOD_1W => 'Weekly Analysis',
            self::PERIOD_1M, self::PERIOD_3M => 'Short-term Investing',
            self::PERIOD_6M, self::PERIOD_1Y => 'Medium-term Investing',
            self::PERIOD_5Y, self::PERIOD_MAX => 'Long-term Investing',
        };
    }

    /**
     * Get human-readable description
     */
    public function getDescription(): string
    {
        return match ($this) {
            self::ONE_MINUTE => 'Every minute (highest frequency)',
            self::FIVE_MINUTES => 'Every 5 minutes (high frequency)',
            self::FIFTEEN_MINUTES => 'Every 15 minutes (medium-high frequency)',
            self::THIRTY_MINUTES => 'Every 30 minutes (medium frequency)',
            self::ONE_HOUR => 'Every hour',
            self::FIVE_HOURS => 'Every 5 hours',
            self::ONE_DAY => 'Daily intervals',
            self::ONE_WEEK => 'Weekly intervals',
            self::ONE_MONTH => 'Monthly intervals',
            self::PERIOD_1D => '1 day period with 5-minute intervals',
            self::PERIOD_1W => '1 week period with 30-minute intervals',
            self::PERIOD_1M => '1 month period with 5-hour intervals',
            self::PERIOD_3M => '3 months period with daily intervals',
            self::PERIOD_6M => '6 months period with daily intervals',
            self::PERIOD_1Y => '1 year period with weekly intervals',
            self::PERIOD_5Y => '5 years period with monthly intervals',
            self::PERIOD_MAX => 'Maximum available data with monthly intervals',
        };
    }

    /**
     * Create TimelineInterval from string value
     *
     * @param string $value The interval string
     * @return self
     * @throws \InvalidArgumentException If the value is not a valid interval
     */
    public static function fromString(string $value): self
    {
        return self::tryFrom($value)
            ?? throw new \InvalidArgumentException("Invalid timeline interval: {$value}");
    }

    /**
     * Get all available intervals as array
     *
     * @return array<string, string> Array of interval value => description
     */
    public static function getAllIntervals(): array
    {
        $intervals = [];
        foreach (self::cases() as $interval) {
            $intervals[$interval->value] = $interval->getDescription();
        }
        return $intervals;
    }
}
