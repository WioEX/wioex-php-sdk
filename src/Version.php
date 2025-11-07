<?php

declare(strict_types=1);

namespace Wioex\SDK;

/**
 * SDK Version Management
 * 
 * Centralized version control for WioEX PHP SDK.
 * All version references should use this class.
 */
final class Version
{
    /**
     * Current SDK version
     * 
     * Version 2.9.0 - Configuration & Type Safety Release:
     * - ENHANCED: Configuration management with dot notation support
     * - IMPROVED: PHPStan Level 8 compliance with comprehensive type safety
     * - ADDED: Cache management enhancements and macro support
     * - FIXED: 300+ null safety and type declaration issues
     * - OPTIMIZED: Strict comparison operators and validation improvements
     * - MAINTAINED: Full backward compatibility
     * 
     * Previous features (2.8.x):
     * - API route fixes and endpoint optimizations
     * - Comprehensive ticker analysis platform
     * - Investment research grade functionality
     * - Professional validation schemas
     */
    public const CURRENT = '2.9.0';

    /**
     * Version components
     */
    public const MAJOR = 2;
    public const MINOR = 9;
    public const PATCH = 0;

    /**
     * Version metadata
     */
    public const RELEASE_DATE = '2025-11-07';
    public const CODENAME = 'Configuration & Type Safety Release';

    /**
     * Get current version string
     */
    public static function current(): string
    {
        return self::CURRENT;
    }

    /**
     * Get version components as array
     */
    public static function components(): array
    {
        return [
            'major' => self::MAJOR,
            'minor' => self::MINOR,
            'patch' => self::PATCH
        ];
    }

    /**
     * Get full version information
     */
    public static function info(): array
    {
        return [
            'version' => self::CURRENT,
            'major' => self::MAJOR,
            'minor' => self::MINOR,
            'patch' => self::PATCH,
            'release_date' => self::RELEASE_DATE,
            'codename' => self::CODENAME,
            'php_version' => PHP_VERSION,
            'features' => [
                'enhanced_configuration_management',
                'dot_notation_config_support',
                'phpstan_level8_compliance',
                'comprehensive_type_safety',
                'null_safety_improvements',
                'cache_management_enhancements',
                'macro_support_caching',
                'strict_comparison_optimization',
                'production_stream_tokens',
                'websocket_authentication_fix',
                'comprehensive_ticker_analysis',
                'analyst_ratings_integration',
                'earnings_insights_analysis',
                'insider_activity_tracking',
                'news_sentiment_analysis',
                'options_analysis_system',
                'investment_research_platform',
                'professional_validation_schemas',
                'unified_response_template',
                'enhanced_stock_data',
                'backward_compatibility',
                'php_8_4_support',
                'psr_4_compliance'
            ]
        ];
    }

    /**
     * Check if current version is compatible with minimum required version
     */
    public static function isCompatible(string $minVersion): bool
    {
        return version_compare(self::CURRENT, $minVersion, '>=');
    }

    /**
     * Get user agent string for API requests
     */
    public static function userAgent(): string
    {
        return sprintf(
            'WioEX-PHP-SDK/%s (PHP/%s)',
            self::CURRENT,
            PHP_VERSION
        );
    }

    /**
     * Prevent instantiation
     */
    private function __construct() {}
}