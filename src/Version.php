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
     * Version 2.4.1 - Critical Bug Fix Release:
     * - FIXED: AAPL stock lookup issue - customers can now find AAPL and all stocks
     * - FIXED: Autoload configuration to properly load environment variables
     * - FIXED: Missing Functions class dependency from src/app directory
     * - FIXED: TipRanks provider instantiation failures
     * - IMPROVED: Error handling and dependency loading
     * - All ticker analysis features from 2.4.0 maintained
     */
    public const CURRENT = '2.4.1';

    /**
     * Version components
     */
    public const MAJOR = 2;
    public const MINOR = 4;
    public const PATCH = 1;

    /**
     * Version metadata
     */
    public const RELEASE_DATE = '2025-10-24';
    public const CODENAME = 'Stock Lookup Fix';

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
                'comprehensive_ticker_analysis',
                'analyst_ratings_integration',
                'earnings_insights_analysis',
                'insider_activity_tracking',
                'news_sentiment_analysis',
                'options_analysis_system',
                'price_movement_analytics',
                'financial_metrics_evaluation',
                'investment_research_platform',
                'professional_validation_schemas',
                'unified_response_template',
                'multi_provider_architecture',
                'source_masking_compliance',
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