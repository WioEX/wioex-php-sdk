<?php

declare(strict_types=1);

namespace Wioex\SDK\Enums;

/**
 * Analytics period types for detailed account insights
 *
 * Represents different time periods for comprehensive analytics:
 * - WEEK: Weekly analytics and insights
 * - MONTH: Monthly performance analysis
 * - QUARTER: Quarterly business review data
 * - YEAR: Annual usage and performance metrics
 */
enum AnalyticsPeriod: string
{
    case WEEK = 'week';
    case MONTH = 'month';
    case QUARTER = 'quarter';
    case YEAR = 'year';

    /**
     * Get human-readable description
     */
    public function getDescription(): string
    {
        return match ($this) {
            self::WEEK => 'Weekly analytics',
            self::MONTH => 'Monthly performance analysis',
            self::QUARTER => 'Quarterly business review',
            self::YEAR => 'Annual metrics and trends',
        };
    }

    /**
     * Get period duration in days (approximate)
     */
    public function getDurationDays(): int
    {
        return match ($this) {
            self::WEEK => 7,
            self::MONTH => 30,
            self::QUARTER => 90,
            self::YEAR => 365,
        };
    }

    /**
     * Get analysis depth level
     */
    public function getAnalysisDepth(): string
    {
        return match ($this) {
            self::WEEK => 'Tactical - Short-term patterns and adjustments',
            self::MONTH => 'Operational - Monthly performance and optimization',
            self::QUARTER => 'Strategic - Business quarter review and planning',
            self::YEAR => 'Strategic - Annual performance and long-term trends',
        };
    }

    /**
     * Get recommended business use case
     */
    public function getBusinessUseCase(): string
    {
        return match ($this) {
            self::WEEK => 'Daily operations monitoring, sprint reviews',
            self::MONTH => 'Monthly reports, budget reviews, optimization',
            self::QUARTER => 'Quarterly business reviews, strategic planning',
            self::YEAR => 'Annual reporting, budget planning, contract renewal',
        };
    }

    /**
     * Get data aggregation level
     */
    public function getDataAggregation(): string
    {
        return match ($this) {
            self::WEEK => 'Daily aggregation with hourly peaks',
            self::MONTH => 'Daily aggregation with weekly trends',
            self::QUARTER => 'Weekly aggregation with monthly summaries',
            self::YEAR => 'Monthly aggregation with quarterly comparisons',
        };
    }

    /**
     * Get metrics focus
     */
    public function getMetricsFocus(): array
    {
        return match ($this) {
            self::WEEK => [
                'Daily API call patterns',
                'Peak usage hours',
                'Error rate fluctuations',
                'Response time variations'
            ],
            self::MONTH => [
                'Monthly usage trends',
                'Endpoint popularity',
                'Credit consumption patterns',
                'Performance benchmarks'
            ],
            self::QUARTER => [
                'Business growth metrics',
                'Usage scalability',
                'Cost efficiency analysis',
                'Feature adoption rates'
            ],
            self::YEAR => [
                'Annual growth trends',
                'ROI analysis',
                'Long-term usage patterns',
                'Strategic insights'
            ],
        };
    }

    /**
     * Check if this is a short-term period
     */
    public function isShortTerm(): bool
    {
        return $this === self::WEEK || $this === self::MONTH;
    }

    /**
     * Check if this is a long-term period
     */
    public function isLongTerm(): bool
    {
        return $this === self::QUARTER || $this === self::YEAR;
    }

    /**
     * Check if this period is suitable for operational decisions
     */
    public function isOperational(): bool
    {
        return $this === self::WEEK || $this === self::MONTH;
    }

    /**
     * Check if this period is suitable for strategic decisions
     */
    public function isStrategic(): bool
    {
        return $this === self::QUARTER || $this === self::YEAR;
    }

    /**
     * Get cache TTL for analytics data
     */
    public function getCacheTTL(): int
    {
        return match ($this) {
            self::WEEK => 1800,     // 30 minutes (recent data)
            self::MONTH => 3600,    // 1 hour (monthly data)
            self::QUARTER => 7200,  // 2 hours (quarterly data)
            self::YEAR => 14400,    // 4 hours (annual data is stable)
        };
    }

    /**
     * Get report generation frequency
     */
    public function getReportFrequency(): string
    {
        return match ($this) {
            self::WEEK => 'Generated daily, covers last 7 days',
            self::MONTH => 'Generated weekly, covers last 30 days',
            self::QUARTER => 'Generated monthly, covers last 90 days',
            self::YEAR => 'Generated quarterly, covers last 365 days',
        };
    }

    /**
     * Create AnalyticsPeriod from string value
     *
     * @param string $value The period string
     * @return self
     * @throws \InvalidArgumentException If the value is not a valid analytics period
     */
    public static function fromString(string $value): self
    {
        return self::tryFrom($value)
            ?? throw new \InvalidArgumentException("Invalid analytics period: {$value}");
    }

    /**
     * Get default analytics period
     */
    public static function default(): self
    {
        return self::MONTH;
    }

    /**
     * Get all available periods with descriptions
     *
     * @return array<string, string> Array of period value => description
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
     * Get periods suitable for operational analysis
     *
     * @return array<AnalyticsPeriod>
     */
    public static function getOperationalPeriods(): array
    {
        return [self::WEEK, self::MONTH];
    }

    /**
     * Get periods suitable for strategic analysis
     *
     * @return array<AnalyticsPeriod>
     */
    public static function getStrategicPeriods(): array
    {
        return [self::QUARTER, self::YEAR];
    }

    /**
     * Get periods suitable for executive reporting
     *
     * @return array<AnalyticsPeriod>
     */
    public static function getExecutiveReportingPeriods(): array
    {
        return [self::QUARTER, self::YEAR];
    }
}
