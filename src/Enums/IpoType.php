<?php

declare(strict_types=1);

namespace Wioex\SDK\Enums;

/**
 * IPO (Initial Public Offering) types for screening
 *
 * Represents different stages of the IPO process:
 * - RECENT: Recently completed IPOs (already trading)
 * - UPCOMING: Planned IPOs with announced dates
 * - FILINGS: Companies that have filed for IPO but not yet scheduled
 */
enum IpoType: string
{
    case RECENT = 'recent';
    case UPCOMING = 'upcoming';
    case FILINGS = 'filings';

    /**
     * Get human-readable description
     */
    public function getDescription(): string
    {
        return match ($this) {
            self::RECENT => 'Recently completed IPOs (now trading publicly)',
            self::UPCOMING => 'Scheduled upcoming IPOs with announced dates',
            self::FILINGS => 'Filed for IPO but not yet scheduled',
        };
    }

    /**
     * Get typical timeframe
     */
    public function getTimeframe(): string
    {
        return match ($this) {
            self::RECENT => 'Last 30-90 days',
            self::UPCOMING => 'Next 30-180 days',
            self::FILINGS => 'Filing to IPO: 3-12 months',
        };
    }

    /**
     * Get investment opportunity type
     */
    public function getInvestmentOpportunity(): string
    {
        return match ($this) {
            self::RECENT => 'Post-IPO trading opportunities, momentum plays',
            self::UPCOMING => 'Pre-IPO planning, allocation opportunities',
            self::FILINGS => 'Early research, long-term positioning',
        };
    }

    /**
     * Get risk level
     */
    public function getRiskLevel(): string
    {
        return match ($this) {
            self::RECENT => 'High - Post-IPO volatility, price discovery',
            self::UPCOMING => 'Medium-High - IPO pricing uncertainty',
            self::FILINGS => 'Medium - Filing to IPO execution risk',
        };
    }

    /**
     * Get typical investor focus
     */
    public function getInvestorFocus(): string
    {
        return match ($this) {
            self::RECENT => 'Short-term traders, momentum investors',
            self::UPCOMING => 'IPO specialists, institutional investors',
            self::FILINGS => 'Research analysts, long-term investors',
        };
    }

    /**
     * Get data availability characteristics
     */
    public function getDataAvailability(): string
    {
        return match ($this) {
            self::RECENT => 'Full trading data, price history, volume',
            self::UPCOMING => 'Prospectus data, estimated pricing',
            self::FILINGS => 'S-1 filing data, preliminary information',
        };
    }

    /**
     * Get recommended analysis approach
     */
    public function getAnalysisApproach(): string
    {
        return match ($this) {
            self::RECENT => 'Technical analysis, momentum indicators, volume analysis',
            self::UPCOMING => 'Fundamental analysis, comparable company analysis',
            self::FILINGS => 'Deep fundamental research, industry analysis',
        };
    }

    /**
     * Get typical volatility expectations
     */
    public function getVolatilityExpectation(): string
    {
        return match ($this) {
            self::RECENT => 'Very high - Post-IPO price discovery period',
            self::UPCOMING => 'Unknown - Depends on market conditions at launch',
            self::FILINGS => 'Speculative - No trading data available',
        };
    }

    /**
     * Check if IPO is already trading
     */
    public function isTrading(): bool
    {
        return $this === self::RECENT;
    }

    /**
     * Check if IPO is in planning phase
     */
    public function isPlanning(): bool
    {
        return $this === self::UPCOMING || $this === self::FILINGS;
    }

    /**
     * Check if detailed trading data is available
     */
    public function hasTradingData(): bool
    {
        return $this === self::RECENT;
    }

    /**
     * Get monitoring frequency recommendation
     */
    public function getMonitoringFrequency(): string
    {
        return match ($this) {
            self::RECENT => 'Daily - Active price monitoring',
            self::UPCOMING => 'Weekly - IPO date and pricing updates',
            self::FILINGS => 'Monthly - Filing updates and progress',
        };
    }

    /**
     * Create IpoType from string value
     *
     * @param string $value The IPO type string
     * @return self
     * @throws \InvalidArgumentException If the value is not a valid IPO type
     */
    public static function fromString(string $value): self
    {
        return self::tryFrom($value)
            ?? throw new \InvalidArgumentException("Invalid IPO type: {$value}");
    }

    /**
     * Get default IPO type
     */
    public static function default(): self
    {
        return self::RECENT;
    }

    /**
     * Get IPO types suitable for active trading
     *
     * @return array<IpoType>
     */
    public static function getTradingTypes(): array
    {
        return [self::RECENT];
    }

    /**
     * Get IPO types suitable for research
     *
     * @return array<IpoType>
     */
    public static function getResearchTypes(): array
    {
        return [self::UPCOMING, self::FILINGS];
    }

    /**
     * Get emoji representation for UI display
     */
    public function getEmoji(): string
    {
        return match ($this) {
            self::RECENT => '🔥',    // Fire for hot recent IPOs
            self::UPCOMING => '📅',  // Calendar for scheduled
            self::FILINGS => '📋',   // Clipboard for filings
        };
    }

    /**
     * Get color code for UI display
     */
    public function getColorCode(): string
    {
        return match ($this) {
            self::RECENT => '#FF5722',      // Orange-red for hot/recent
            self::UPCOMING => '#2196F3',    // Blue for upcoming
            self::FILINGS => '#9E9E9E',     // Gray for filings
        };
    }
}
