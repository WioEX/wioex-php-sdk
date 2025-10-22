<?php

declare(strict_types=1);

namespace Wioex\SDK\Enums;

/**
 * Trading session types for market data filtering
 *
 * Defines different trading sessions for US stock markets:
 * - ALL: Full 24-hour data (default)
 * - REGULAR: Standard market hours (9:30 AM - 4:00 PM EST)
 * - PRE_MARKET: Pre-market trading (4:00 AM - 9:30 AM EST)
 * - AFTER_HOURS: After-hours trading (4:00 PM - 8:00 PM EST)
 * - EXTENDED: All extended hours combined (4:00 AM - 8:00 PM EST)
 *
 * Note: Session filtering only applies to minute-level intervals (1min, 5min, 15min, 30min)
 */
enum TradingSession: string
{
    case ALL = 'all';
    case REGULAR = 'regular';
    case PRE_MARKET = 'pre_market';
    case AFTER_HOURS = 'after_hours';
    case EXTENDED = 'extended';

    /**
     * Get trading hours for this session in EST/EDT
     *
     * @return array{start: string, end: string} Start and end times in HH:MM format
     */
    public function getTradingHours(): array
    {
        return match ($this) {
            self::ALL => ['start' => '00:00', 'end' => '23:59'],
            self::REGULAR => ['start' => '09:30', 'end' => '16:00'],
            self::PRE_MARKET => ['start' => '04:00', 'end' => '09:30'],
            self::AFTER_HOURS => ['start' => '16:00', 'end' => '20:00'],
            self::EXTENDED => ['start' => '04:00', 'end' => '20:00'],
        };
    }

    /**
     * Get human-readable description
     */
    public function getDescription(): string
    {
        return match ($this) {
            self::ALL => 'All hours (24/7 data)',
            self::REGULAR => 'Regular market hours (9:30 AM - 4:00 PM EST)',
            self::PRE_MARKET => 'Pre-market hours (4:00 AM - 9:30 AM EST)',
            self::AFTER_HOURS => 'After-hours trading (4:00 PM - 8:00 PM EST)',
            self::EXTENDED => 'Extended hours (Pre + Regular + After)',
        };
    }

    /**
     * Get duration in hours for this session
     */
    public function getDurationHours(): float
    {
        return match ($this) {
            self::ALL => 24.0,
            self::REGULAR => 6.5,   // 9:30 AM to 4:00 PM
            self::PRE_MARKET => 5.5, // 4:00 AM to 9:30 AM
            self::AFTER_HOURS => 4.0, // 4:00 PM to 8:00 PM
            self::EXTENDED => 16.0,  // 4:00 AM to 8:00 PM
        };
    }

    /**
     * Check if this session includes regular market hours
     */
    public function includesRegularHours(): bool
    {
        return match ($this) {
            self::ALL, self::REGULAR, self::EXTENDED => true,
            self::PRE_MARKET, self::AFTER_HOURS => false,
        };
    }

    /**
     * Check if this session is extended hours only (no regular hours)
     */
    public function isExtendedHoursOnly(): bool
    {
        return match ($this) {
            self::PRE_MARKET, self::AFTER_HOURS => true,
            self::ALL, self::REGULAR, self::EXTENDED => false,
        };
    }

    /**
     * Get recommended use case for this session
     */
    public function getUseCase(): string
    {
        return match ($this) {
            self::ALL => 'Complete market analysis, 24-hour trading view',
            self::REGULAR => 'Standard trading, main market activity',
            self::PRE_MARKET => 'Early earnings reactions, news impact',
            self::AFTER_HOURS => 'Post-earnings analysis, late news reactions',
            self::EXTENDED => 'Full trading day analysis, volatility study',
        };
    }

    /**
     * Get typical volume characteristics
     */
    public function getVolumeCharacteristics(): string
    {
        return match ($this) {
            self::ALL => 'Mixed volume throughout 24 hours',
            self::REGULAR => 'Highest volume, most liquid',
            self::PRE_MARKET => 'Lower volume, higher spreads',
            self::AFTER_HOURS => 'Lower volume, potential gaps',
            self::EXTENDED => 'Variable volume, includes all sessions',
        };
    }

    /**
     * Create TradingSession from string value
     *
     * @param string $value The session string
     * @return self
     * @throws \InvalidArgumentException If the value is not a valid session
     */
    public static function fromString(string $value): self
    {
        return self::tryFrom($value)
            ?? throw new \InvalidArgumentException("Invalid trading session: {$value}");
    }

    /**
     * Get default trading session (all hours)
     */
    public static function default(): self
    {
        return self::ALL;
    }

    /**
     * Get all sessions that include extended hours
     *
     * @return array<TradingSession>
     */
    public static function getExtendedHoursSessions(): array
    {
        return [
            self::PRE_MARKET,
            self::AFTER_HOURS,
            self::EXTENDED,
        ];
    }

    /**
     * Get sessions suitable for day trading
     *
     * @return array<TradingSession>
     */
    public static function getDayTradingSessions(): array
    {
        return [
            self::REGULAR,
            self::EXTENDED,
        ];
    }
}
