<?php

declare(strict_types=1);

namespace Wioex\SDK\Enums;

/**
 * Trading signal types for buy/sell recommendations
 *
 * Represents different types of trading signals based on technical analysis:
 * - BUY: Basic buy recommendation
 * - SELL: Basic sell recommendation
 * - HOLD: Hold current position
 * - STRONG_BUY: Strong buy recommendation (high confidence)
 * - STRONG_SELL: Strong sell recommendation (high confidence)
 */
enum SignalType: string
{
    case BUY = 'BUY';
    case SELL = 'SELL';
    case HOLD = 'HOLD';
    case STRONG_BUY = 'STRONG_BUY';
    case STRONG_SELL = 'STRONG_SELL';

    /**
     * Get human-readable description
     */
    public function getDescription(): string
    {
        return match ($this) {
            self::BUY => 'Buy recommendation - Positive outlook',
            self::SELL => 'Sell recommendation - Negative outlook',
            self::HOLD => 'Hold position - Neutral outlook',
            self::STRONG_BUY => 'Strong Buy - Very positive outlook, high confidence',
            self::STRONG_SELL => 'Strong Sell - Very negative outlook, high confidence',
        };
    }

    /**
     * Get signal strength (1-5 scale)
     */
    public function getStrength(): int
    {
        return match ($this) {
            self::STRONG_SELL => 1,
            self::SELL => 2,
            self::HOLD => 3,
            self::BUY => 4,
            self::STRONG_BUY => 5,
        };
    }

    /**
     * Get signal direction
     */
    public function getDirection(): string
    {
        return match ($this) {
            self::STRONG_BUY, self::BUY => 'bullish',
            self::STRONG_SELL, self::SELL => 'bearish',
            self::HOLD => 'neutral',
        };
    }

    /**
     * Check if signal is bullish (buy-oriented)
     */
    public function isBullish(): bool
    {
        return $this === self::BUY || $this === self::STRONG_BUY;
    }

    /**
     * Check if signal is bearish (sell-oriented)
     */
    public function isBearish(): bool
    {
        return $this === self::SELL || $this === self::STRONG_SELL;
    }

    /**
     * Check if signal is neutral
     */
    public function isNeutral(): bool
    {
        return $this === self::HOLD;
    }

    /**
     * Check if signal is strong (high confidence)
     */
    public function isStrong(): bool
    {
        return $this === self::STRONG_BUY || $this === self::STRONG_SELL;
    }

    /**
     * Get recommended action for this signal
     */
    public function getRecommendedAction(): string
    {
        return match ($this) {
            self::STRONG_BUY => 'Strong Buy - Consider significant position increase',
            self::BUY => 'Buy - Consider position increase or new position',
            self::HOLD => 'Hold - Maintain current position, monitor closely',
            self::SELL => 'Sell - Consider position decrease or exit',
            self::STRONG_SELL => 'Strong Sell - Consider significant position decrease or full exit',
        };
    }

    /**
     * Get risk level associated with this signal
     */
    public function getRiskLevel(): string
    {
        return match ($this) {
            self::STRONG_BUY => 'Medium-High (aggressive bullish position)',
            self::BUY => 'Medium (moderate bullish position)',
            self::HOLD => 'Low (maintaining status quo)',
            self::SELL => 'Medium (moderate bearish position)',
            self::STRONG_SELL => 'Medium-High (aggressive bearish position)',
        };
    }

    /**
     * Get typical confidence range for this signal type
     */
    public function getTypicalConfidenceRange(): string
    {
        return match ($this) {
            self::STRONG_BUY, self::STRONG_SELL => '80-100% (High confidence)',
            self::BUY, self::SELL => '60-80% (Medium confidence)',
            self::HOLD => '40-70% (Variable confidence)',
        };
    }

    /**
     * Create SignalType from string value
     *
     * @param string $value The signal type string
     * @return self
     * @throws \InvalidArgumentException If the value is not a valid signal type
     */
    public static function fromString(string $value): self
    {
        $normalizedValue = strtoupper($value);
        return self::tryFrom($normalizedValue)
            ?? throw new \InvalidArgumentException("Invalid signal type: {$value}");
    }

    /**
     * Get all bullish signals
     *
     * @return array<SignalType>
     */
    public static function getBullishSignals(): array
    {
        return [self::BUY, self::STRONG_BUY];
    }

    /**
     * Get all bearish signals
     *
     * @return array<SignalType>
     */
    public static function getBearishSignals(): array
    {
        return [self::SELL, self::STRONG_SELL];
    }

    /**
     * Get all strong signals (high confidence)
     *
     * @return array<SignalType>
     */
    public static function getStrongSignals(): array
    {
        return [self::STRONG_BUY, self::STRONG_SELL];
    }

    /**
     * Get emoji representation for UI display
     */
    public function getEmoji(): string
    {
        return match ($this) {
            self::STRONG_BUY => '🚀',    // Rocket for strong buy
            self::BUY => '📈',           // Chart up for buy
            self::HOLD => '⏸️',          // Pause for hold
            self::SELL => '📉',          // Chart down for sell
            self::STRONG_SELL => '🔻',   // Red triangle for strong sell
        };
    }

    /**
     * Get color code for UI display
     */
    public function getColorCode(): string
    {
        return match ($this) {
            self::STRONG_BUY => '#00C851',      // Strong green
            self::BUY => '#4CAF50',             // Green
            self::HOLD => '#FF9800',            // Orange
            self::SELL => '#F44336',            // Red
            self::STRONG_SELL => '#D32F2F',     // Dark red
        };
    }
}
