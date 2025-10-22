<?php

declare(strict_types=1);

namespace Wioex\SDK\Enums;

/**
 * Currency chart interval types for historical exchange rate data
 *
 * Represents different time intervals for currency exchange rate charts.
 * Optimized for forex market analysis and currency trend visualization.
 */
enum CurrencyInterval: string
{
    case ONE_DAY = '1d';
    case ONE_WEEK = '1w';
    case ONE_MONTH = '1m';
    case THREE_MONTHS = '3m';
    case SIX_MONTHS = '6m';
    case ONE_YEAR = '1y';

    /**
     * Get human-readable description
     */
    public function getDescription(): string
    {
        return match ($this) {
            self::ONE_DAY => '1 day - Intraday currency movements',
            self::ONE_WEEK => '1 week - Short-term currency trends',
            self::ONE_MONTH => '1 month - Monthly currency analysis',
            self::THREE_MONTHS => '3 months - Quarterly currency trends',
            self::SIX_MONTHS => '6 months - Medium-term currency analysis',
            self::ONE_YEAR => '1 year - Annual currency performance',
        };
    }

    /**
     * Get period duration in days
     */
    public function getDurationDays(): int
    {
        return match ($this) {
            self::ONE_DAY => 1,
            self::ONE_WEEK => 7,
            self::ONE_MONTH => 30,
            self::THREE_MONTHS => 90,
            self::SIX_MONTHS => 180,
            self::ONE_YEAR => 365,
        };
    }

    /**
     * Get recommended data point frequency
     */
    public function getDataPointFrequency(): string
    {
        return match ($this) {
            self::ONE_DAY => 'Hourly - Detailed intraday movements',
            self::ONE_WEEK => '4-hour intervals - Short-term trends',
            self::ONE_MONTH => 'Daily - Monthly trend analysis',
            self::THREE_MONTHS => 'Daily - Quarterly performance',
            self::SIX_MONTHS => 'Weekly - Medium-term trends',
            self::ONE_YEAR => 'Weekly - Annual performance overview',
        };
    }

    /**
     * Get typical use case for this interval
     */
    public function getUseCase(): string
    {
        return match ($this) {
            self::ONE_DAY => 'Day trading, intraday volatility analysis',
            self::ONE_WEEK => 'Short-term trading, weekly pattern analysis',
            self::ONE_MONTH => 'Monthly performance review, trend identification',
            self::THREE_MONTHS => 'Quarterly analysis, seasonal patterns',
            self::SIX_MONTHS => 'Medium-term investment planning, semi-annual review',
            self::ONE_YEAR => 'Annual performance, long-term trend analysis',
        };
    }

    /**
     * Get forex trading style recommendation
     */
    public function getTradingStyleRecommendation(): string
    {
        return match ($this) {
            self::ONE_DAY => 'Scalping, day trading',
            self::ONE_WEEK => 'Short-term swing trading',
            self::ONE_MONTH => 'Medium-term position trading',
            self::THREE_MONTHS => 'Quarterly position adjustments',
            self::SIX_MONTHS => 'Medium-term investment strategy',
            self::ONE_YEAR => 'Long-term currency investment',
        };
    }

    /**
     * Get volatility analysis focus
     */
    public function getVolatilityFocus(): string
    {
        return match ($this) {
            self::ONE_DAY => 'Intraday volatility spikes, news reactions',
            self::ONE_WEEK => 'Weekly volatility patterns, event impact',
            self::ONE_MONTH => 'Monthly volatility cycles, economic data impact',
            self::THREE_MONTHS => 'Quarterly volatility trends, policy changes',
            self::SIX_MONTHS => 'Semi-annual volatility patterns, economic cycles',
            self::ONE_YEAR => 'Annual volatility overview, major economic shifts',
        };
    }

    /**
     * Check if interval is suitable for day trading
     */
    public function isDayTradingSuitable(): bool
    {
        return $this === self::ONE_DAY;
    }

    /**
     * Check if interval is suitable for swing trading
     */
    public function isSwingTradingSuitable(): bool
    {
        return in_array($this, [self::ONE_WEEK, self::ONE_MONTH], true);
    }

    /**
     * Check if interval is suitable for position trading
     */
    public function isPositionTradingSuitable(): bool
    {
        return in_array($this, [self::THREE_MONTHS, self::SIX_MONTHS, self::ONE_YEAR], true);
    }

    /**
     * Get cache TTL for this interval's data
     */
    public function getCacheTTL(): int
    {
        return match ($this) {
            self::ONE_DAY => 300,      // 5 minutes (active intraday data)
            self::ONE_WEEK => 900,     // 15 minutes (short-term data)
            self::ONE_MONTH => 1800,   // 30 minutes (monthly data)
            self::THREE_MONTHS => 3600, // 1 hour (quarterly data)
            self::SIX_MONTHS => 7200,  // 2 hours (semi-annual data)
            self::ONE_YEAR => 14400,   // 4 hours (annual data)
        };
    }

    /**
     * Get economic factors most relevant for this timeframe
     */
    public function getRelevantEconomicFactors(): array
    {
        return match ($this) {
            self::ONE_DAY => [
                'Economic news releases',
                'Central bank announcements',
                'Market sentiment shifts',
                'Technical support/resistance levels'
            ],
            self::ONE_WEEK => [
                'Weekly economic data',
                'Central bank communications',
                'Political developments',
                'Technical pattern formations'
            ],
            self::ONE_MONTH => [
                'Monthly economic indicators',
                'Inflation data',
                'Employment statistics',
                'Interest rate expectations'
            ],
            self::THREE_MONTHS => [
                'Quarterly GDP data',
                'Central bank policy meetings',
                'Trade balance trends',
                'Political stability factors'
            ],
            self::SIX_MONTHS => [
                'Semi-annual economic trends',
                'Interest rate cycles',
                'Commodity price trends',
                'Geopolitical developments'
            ],
            self::ONE_YEAR => [
                'Annual economic growth',
                'Long-term monetary policy',
                'Structural economic changes',
                'Multi-year trend analysis'
            ],
        };
    }

    /**
     * Create CurrencyInterval from string value
     *
     * @param string $value The interval string
     * @return self
     * @throws \InvalidArgumentException If the value is not a valid currency interval
     */
    public static function fromString(string $value): self
    {
        return self::tryFrom($value)
            ?? throw new \InvalidArgumentException("Invalid currency interval: {$value}");
    }

    /**
     * Get default currency interval
     */
    public static function default(): self
    {
        return self::ONE_MONTH;
    }

    /**
     * Get short-term intervals (suitable for active trading)
     *
     * @return array<CurrencyInterval>
     */
    public static function getShortTermIntervals(): array
    {
        return [self::ONE_DAY, self::ONE_WEEK];
    }

    /**
     * Get medium-term intervals (suitable for swing trading)
     *
     * @return array<CurrencyInterval>
     */
    public static function getMediumTermIntervals(): array
    {
        return [self::ONE_MONTH, self::THREE_MONTHS];
    }

    /**
     * Get long-term intervals (suitable for position trading)
     *
     * @return array<CurrencyInterval>
     */
    public static function getLongTermIntervals(): array
    {
        return [self::SIX_MONTHS, self::ONE_YEAR];
    }

    /**
     * Get all available intervals with descriptions
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
