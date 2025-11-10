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
     * Version 2.12.0 - Unified NewsManager Architecture:
     * - NEW MAJOR FEATURE: Unified NewsManager with intelligent source routing
     * - NEW: Provider-based architecture (WioexProvider, PerplexityProvider, SocialProvider)
     * - NEW: Automatic provider selection based on content type
     * - NEW: Multi-source data aggregation and comparison
     * - NEW: Advanced fallback mechanisms and error handling
     * - NEW: Comprehensive caching and performance optimization
     * - NEW: Provider health monitoring and capabilities management
     * - ENHANCED: Unified API interface with backwards compatibility
     * - ENHANCED: Advanced filtering and options for all providers
     * - ARCHITECTURE: Scalable and extensible provider system
     * 
     * Previous features (2.11.1):
     * - NewsAnalysis clarity improvements and naming consistency
     * - External Finance API integration and sentiment analysis
     * - Cache system fixes and console cleanup
     */
    public const CURRENT = '2.12.0';

    /**
     * Version components
     */
    public const MAJOR = 2;
    public const MINOR = 12;
    public const PATCH = 0;

    /**
     * Version metadata
     */
    public const RELEASE_DATE = '2025-11-10';
    public const CODENAME = 'Unified NewsManager Architecture';

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
                'unified_news_manager_architecture',
                'intelligent_provider_routing',
                'multi_source_news_aggregation',
                'provider_based_architecture',
                'automatic_provider_selection',
                'advanced_fallback_mechanisms',
                'provider_health_monitoring',
                'comprehensive_caching_optimization',
                'news_analysis_api',
                'financial_news_sentiment_analysis', 
                'news_impact_level_detection',
                'cookie_based_session_management',
                'external_finance_api_integration',
                'real_time_financial_news_events',
                'news_event_type_normalization',
                'batch_news_analysis_requests',
                'critical_cache_system_fix',
                'graceful_cache_degradation',
                'auto_driver_detection_fix',
                'cache_error_handling',
                'production_ready_console_cleanup',
                'background_curl_error_reporting',
                'integrated_error_handling',
                'dead_code_cleanup',
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