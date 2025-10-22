<?php

declare(strict_types=1);

namespace Wioex\SDK\Enums;

/**
 * Signal trigger types for tracking signal outcomes
 *
 * Represents different ways a trading signal can be resolved:
 * - ENTRY: Signal was triggered at entry price
 * - TARGET: Signal reached target price (profitable outcome)
 * - STOP_LOSS: Signal hit stop loss (loss mitigation)
 * - EXPIRED: Signal expired without being triggered
 */
enum TriggerType: string
{
    case ENTRY = 'entry';
    case TARGET = 'target';
    case STOP_LOSS = 'stop_loss';
    case EXPIRED = 'expired';

    /**
     * Get human-readable description
     */
    public function getDescription(): string
    {
        return match ($this) {
            self::ENTRY => 'Entry triggered - Position opened at entry price',
            self::TARGET => 'Target reached - Profitable exit achieved',
            self::STOP_LOSS => 'Stop loss hit - Loss minimized at stop level',
            self::EXPIRED => 'Signal expired - No trigger within time limit',
        };
    }

    /**
     * Get outcome type
     */
    public function getOutcome(): string
    {
        return match ($this) {
            self::ENTRY => 'neutral',      // Position opened, outcome pending
            self::TARGET => 'positive',    // Profitable outcome
            self::STOP_LOSS => 'negative', // Loss outcome
            self::EXPIRED => 'neutral',    // No outcome
        };
    }

    /**
     * Check if trigger represents a profitable outcome
     */
    public function isProfitable(): bool
    {
        return $this === self::TARGET;
    }

    /**
     * Check if trigger represents a loss outcome
     */
    public function isLoss(): bool
    {
        return $this === self::STOP_LOSS;
    }

    /**
     * Check if trigger represents an active position
     */
    public function isActivePosition(): bool
    {
        return $this === self::ENTRY;
    }

    /**
     * Check if trigger represents a closed position
     */
    public function isClosedPosition(): bool
    {
        return $this === self::TARGET || $this === self::STOP_LOSS;
    }

    /**
     * Check if signal was never activated
     */
    public function wasNeverActivated(): bool
    {
        return $this === self::EXPIRED;
    }

    /**
     * Get recommended next action
     */
    public function getRecommendedNextAction(): string
    {
        return match ($this) {
            self::ENTRY => 'Monitor position, watch for target or stop loss levels',
            self::TARGET => 'Analyze success factors for future signals',
            self::STOP_LOSS => 'Review signal parameters and risk management',
            self::EXPIRED => 'Analyze why signal was not triggered, market conditions',
        };
    }

    /**
     * Get typical timeframe for this trigger
     */
    public function getTypicalTimeframe(): string
    {
        return match ($this) {
            self::ENTRY => 'Usually within 1-3 trading days',
            self::TARGET => 'Varies: minutes to weeks depending on strategy',
            self::STOP_LOSS => 'Can occur quickly (minutes) or over days',
            self::EXPIRED => 'Typically 1-30 days depending on signal validity period',
        };
    }

    /**
     * Get impact on signal accuracy metrics
     */
    public function getAccuracyImpact(): string
    {
        return match ($this) {
            self::ENTRY => 'Neutral - Position opened, pending outcome',
            self::TARGET => 'Positive - Adds to successful signals count',
            self::STOP_LOSS => 'Negative - Adds to failed signals count',
            self::EXPIRED => 'Neutral - Not counted in accuracy metrics',
        };
    }

    /**
     * Create TriggerType from string value
     *
     * @param string $value The trigger type string
     * @return self
     * @throws \InvalidArgumentException If the value is not a valid trigger type
     */
    public static function fromString(string $value): self
    {
        return self::tryFrom($value) 
            ?? throw new \InvalidArgumentException("Invalid trigger type: {$value}");
    }

    /**
     * Get triggers that represent completed signals
     *
     * @return array<TriggerType>
     */
    public static function getCompletedTriggers(): array
    {
        return [self::TARGET, self::STOP_LOSS, self::EXPIRED];
    }

    /**
     * Get triggers that represent active positions
     *
     * @return array<TriggerType>
     */
    public static function getActiveTriggers(): array
    {
        return [self::ENTRY];
    }

    /**
     * Get triggers that affect performance metrics
     *
     * @return array<TriggerType>
     */
    public static function getPerformanceAffectingTriggers(): array
    {
        return [self::TARGET, self::STOP_LOSS];
    }

    /**
     * Get emoji representation for UI display
     */
    public function getEmoji(): string
    {
        return match ($this) {
            self::ENTRY => '🎯',     // Target for entry
            self::TARGET => '✅',    // Check mark for success
            self::STOP_LOSS => '❌', // X mark for stop loss
            self::EXPIRED => '⏰',   // Clock for expired
        };
    }

    /**
     * Get color code for UI display
     */
    public function getColorCode(): string
    {
        return match ($this) {
            self::ENTRY => '#2196F3',       // Blue for entry
            self::TARGET => '#4CAF50',      // Green for success
            self::STOP_LOSS => '#F44336',   // Red for loss
            self::EXPIRED => '#9E9E9E',     // Gray for expired
        };
    }
}