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
     * Version 2.7.1 - API Route Fix Release:
     * - CRITICAL: Fixed all API endpoints to use correct /api/ prefix
     * - FIXED: Account, Stocks, News, Currency, Logos endpoints now working
     * - MAINTAINED: Stream endpoints use direct /stream/ paths
     * - TESTED: All major endpoints verified working
     * - STABLE: Production-ready with proper route handling
     * 
     * Previous features (2.5.0):
     * - Stock logo access functionality (6,840+ logos)
     * - Comprehensive ticker analysis platform
     * - Professional validation schemas
     */
    public const CURRENT = '2.6.0';

    /**
     * Version components
     */
    public const MAJOR = 2;
    public const MINOR = 7;
    public const PATCH = 1;

    /**
     * Version metadata
     */
    public const RELEASE_DATE = '2025-10-30';
    public const CODENAME = 'API Route Fix Release';

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
                'production_stream_tokens',
                'websocket_authentication_fix',
                'post_body_api_key_support',
                'stock_logo_access',
                'logo_batch_operations',
                'logo_metadata_info',
                'logo_existence_validation',
                'logo_download_save',
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