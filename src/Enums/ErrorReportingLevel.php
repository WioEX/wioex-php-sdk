<?php

declare(strict_types=1);

namespace Wioex\SDK\Enums;

/**
 * Error reporting levels for SDK error telemetry
 *
 * Represents different levels of detail for error reporting:
 * - MINIMAL: Basic error information only
 * - STANDARD: Standard error details with context
 * - DETAILED: Comprehensive error information with full context
 */
enum ErrorReportingLevel: string
{
    case MINIMAL = 'minimal';
    case STANDARD = 'standard';
    case DETAILED = 'detailed';

    /**
     * Get human-readable description
     */
    public function getDescription(): string
    {
        return match ($this) {
            self::MINIMAL => 'Minimal error reporting - Basic error information only',
            self::STANDARD => 'Standard error reporting - Error details with context',
            self::DETAILED => 'Detailed error reporting - Comprehensive information',
        };
    }

    /**
     * Get included data types for this reporting level
     */
    public function getIncludedDataTypes(): array
    {
        return match ($this) {
            self::MINIMAL => [
                'Error message',
                'Error type',
                'Timestamp',
                'SDK version'
            ],
            self::STANDARD => [
                'Error message',
                'Error type',
                'HTTP status code',
                'Request endpoint',
                'Timestamp',
                'SDK version',
                'API key identification'
            ],
            self::DETAILED => [
                'Error message',
                'Error type',
                'HTTP status code',
                'Full stack trace',
                'Request endpoint',
                'Request parameters',
                'Response data',
                'Timestamp',
                'SDK version',
                'PHP version',
                'API key identification',
                'System information'
            ],
        };
    }

    /**
     * Check if this level includes request data
     */
    public function includesRequestData(): bool
    {
        return $this === self::DETAILED;
    }

    /**
     * Check if this level includes response data
     */
    public function includesResponseData(): bool
    {
        return $this === self::DETAILED;
    }

    /**
     * Check if this level includes stack traces
     */
    public function includesStackTrace(): bool
    {
        return $this === self::DETAILED;
    }

    /**
     * Check if this level includes system information
     */
    public function includesSystemInfo(): bool
    {
        return $this === self::DETAILED;
    }

    /**
     * Get privacy level for this reporting level
     */
    public function getPrivacyLevel(): string
    {
        return match ($this) {
            self::MINIMAL => 'High privacy - No sensitive data included',
            self::STANDARD => 'Medium privacy - Limited context data',
            self::DETAILED => 'Low privacy - Comprehensive data for debugging',
        };
    }

    /**
     * Get recommended use case
     */
    public function getRecommendedUseCase(): string
    {
        return match ($this) {
            self::MINIMAL => 'Production - Privacy-focused, basic monitoring',
            self::STANDARD => 'Production - Balanced monitoring with context',
            self::DETAILED => 'Development/Staging - Full debugging information',
        };
    }

    /**
     * Get data retention considerations
     */
    public function getDataRetention(): string
    {
        return match ($this) {
            self::MINIMAL => 'Long-term retention safe - Minimal sensitive data',
            self::STANDARD => 'Medium-term retention - Some contextual data',
            self::DETAILED => 'Short-term retention - May contain sensitive data',
        };
    }

    /**
     * Get performance impact
     */
    public function getPerformanceImpact(): string
    {
        return match ($this) {
            self::MINIMAL => 'Minimal - Very low overhead',
            self::STANDARD => 'Low - Small overhead for context gathering',
            self::DETAILED => 'Medium - Higher overhead for comprehensive data',
        };
    }

    /**
     * Get compliance considerations
     */
    public function getComplianceNotes(): string
    {
        return match ($this) {
            self::MINIMAL => 'GDPR/CCPA friendly - No personal data',
            self::STANDARD => 'Review for compliance - Limited personal data',
            self::DETAILED => 'Compliance review required - May contain personal data',
        };
    }

    /**
     * Create ErrorReportingLevel from string value
     *
     * @param string $value The reporting level string
     * @return self
     * @throws \InvalidArgumentException If the value is not a valid reporting level
     */
    public static function fromString(string $value): self
    {
        return self::tryFrom($value) 
            ?? throw new \InvalidArgumentException("Invalid error reporting level: {$value}");
    }

    /**
     * Get default error reporting level
     */
    public static function default(): self
    {
        return self::DETAILED;
    }

    /**
     * Get production-safe reporting levels
     *
     * @return array<ErrorReportingLevel>
     */
    public static function getProductionSafeLevels(): array
    {
        return [self::MINIMAL, self::STANDARD];
    }

    /**
     * Get development-friendly reporting levels
     *
     * @return array<ErrorReportingLevel>
     */
    public static function getDevelopmentLevels(): array
    {
        return [self::STANDARD, self::DETAILED];
    }

    /**
     * Get privacy-focused reporting levels
     *
     * @return array<ErrorReportingLevel>
     */
    public static function getPrivacyFocusedLevels(): array
    {
        return [self::MINIMAL];
    }

    /**
     * Get all available levels with descriptions
     *
     * @return array<string, string> Array of level value => description
     */
    public static function getAllLevels(): array
    {
        $levels = [];
        foreach (self::cases() as $level) {
            $levels[$level->value] = $level->getDescription();
        }
        return $levels;
    }
}