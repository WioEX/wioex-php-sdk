<?php

declare(strict_types=1);

namespace Wioex\SDK;

use InvalidArgumentException;
use Wioex\SDK\Enums\ErrorReportingLevel;

class Config
{
    private ?string $apiKey;
    private string $baseUrl;
    private int $timeout;
    private int $connectTimeout;
    /** @var array{times: int, delay: int, multiplier: int, max_delay: int} */
    private array $retryConfig;
    private array $headers;
    private bool $errorReporting;
    private string $errorReportingEndpoint;
    private bool $includeStackTrace;
    private ErrorReportingLevel $errorReportingLevel;
    private bool $includeRequestData;
    private bool $includeResponseData;
    /** @var array{enabled: bool, requests: int, window: int, strategy: string, burst_allowance: int} */
    private array $rateLimitConfig;
    /** @var array{enabled: bool, attempts: int, backoff: string, base_delay: int, max_delay: int, jitter: bool, exponential_base: float} */
    private array $enhancedRetryConfig;
    /** @var array{enabled: bool, auto_report_errors: bool, performance_tracking: bool, privacy_mode: string, sampling_rate: float, endpoint: string, flush_interval: int, max_queue_size: int, filters: array} */
    private array $telemetryConfig;

    /**
     * @param array{
     *     api_key?: string,
     *     base_url?: string,
     *     timeout?: int,
     *     connect_timeout?: int,
     *     retry?: array,
     *     headers?: array,
     *     error_reporting?: bool,
     *     error_reporting_endpoint?: string,
     *     include_stack_trace?: bool,
     *     error_reporting_level?: string|ErrorReportingLevel,
     *     include_request_data?: bool,
     *     include_response_data?: bool,
     *     rate_limit?: array,
     *     enhanced_retry?: array,
     *     telemetry?: array
     * } $options
     */
    public function __construct(array $options = [])
    {
        // API key is optional for public endpoints
        $this->apiKey = isset($options['api_key']) && $options['api_key'] !== ''
            ? $options['api_key']
            : null;
        $this->baseUrl = $options['base_url'] ?? 'https://api.wioex.com';
        $this->timeout = $options['timeout'] ?? 30;
        $this->connectTimeout = $options['connect_timeout'] ?? 10;

        // Legacy retry configuration (maintained for backward compatibility)
        $retry = $options['retry'] ?? [];
        $this->retryConfig = [
            'times' => (int)($retry['times'] ?? 3),
            'delay' => (int)($retry['delay'] ?? 100), // milliseconds
            'multiplier' => (int)($retry['multiplier'] ?? 2),
            'max_delay' => (int)($retry['max_delay'] ?? 5000) // max 5 seconds
        ];

        // Enhanced rate limiting configuration
        $rateLimit = $options['rate_limit'] ?? [];
        $this->rateLimitConfig = [
            'enabled' => (bool)($rateLimit['enabled'] ?? false),
            'requests' => (int)($rateLimit['requests'] ?? 100), // requests per window
            'window' => (int)($rateLimit['window'] ?? 60), // window in seconds
            'strategy' => $rateLimit['strategy'] ?? 'sliding_window', // sliding_window, fixed_window, token_bucket
            'burst_allowance' => (int)($rateLimit['burst_allowance'] ?? 10) // extra requests for burst
        ];

        // Enhanced retry configuration with intelligent backoff
        $enhancedRetry = $options['enhanced_retry'] ?? [];
        $this->enhancedRetryConfig = [
            'enabled' => (bool)($enhancedRetry['enabled'] ?? false),
            'attempts' => (int)($enhancedRetry['attempts'] ?? 5),
            'backoff' => $enhancedRetry['backoff'] ?? 'exponential', // exponential, linear, fixed
            'base_delay' => (int)($enhancedRetry['base_delay'] ?? 100), // base delay in ms
            'max_delay' => (int)($enhancedRetry['max_delay'] ?? 30000), // max delay in ms (30 seconds)
            'jitter' => (bool)($enhancedRetry['jitter'] ?? true), // add randomization
            'exponential_base' => (float)($enhancedRetry['exponential_base'] ?? 2.0) // exponential multiplier
        ];

        // Telemetry configuration for comprehensive SDK monitoring
        $telemetry = $options['telemetry'] ?? [];
        $this->telemetryConfig = [
            'enabled' => (bool)($telemetry['enabled'] ?? false), // Opt-in by default
            'auto_report_errors' => (bool)($telemetry['auto_report_errors'] ?? true),
            'performance_tracking' => (bool)($telemetry['performance_tracking'] ?? true),
            'privacy_mode' => $telemetry['privacy_mode'] ?? 'production', // production, development, debug
            'sampling_rate' => (float)($telemetry['sampling_rate'] ?? 0.1), // 10% sampling by default
            'endpoint' => $telemetry['endpoint'] ?? 'https://api.wioex.com/v2/sdk/telemetry',
            'flush_interval' => (int)($telemetry['flush_interval'] ?? 30), // seconds
            'max_queue_size' => (int)($telemetry['max_queue_size'] ?? 100),
            'filters' => array_merge([
                'exclude_sensitive_headers' => true,
                'anonymize_ip' => true,
                'sanitize_stack_traces' => true
            ], $telemetry['filters'] ?? [])
        ];

        $this->headers = array_merge([
            'Accept' => 'application/json',
            'User-Agent' => 'WioEX-PHP-SDK/2.0.0',
        ], $options['headers'] ?? []);

        // Error reporting configuration
        $this->errorReporting = $options['error_reporting'] ?? false;
        $this->errorReportingEndpoint = $options['error_reporting_endpoint']
            ?? 'https://api.wioex.com/v2/sdk/error-report';
        $this->includeStackTrace = $options['include_stack_trace'] ?? false;

        // Error reporting levels: 'minimal', 'standard', 'detailed'
        // Default is 'detailed' for better debugging and customer support
        $errorLevel = $options['error_reporting_level'] ?? ErrorReportingLevel::DETAILED;
        $this->errorReportingLevel = $errorLevel instanceof ErrorReportingLevel
            ? $errorLevel
            : ErrorReportingLevel::fromString($errorLevel);
        $this->includeRequestData = $options['include_request_data'] ?? false;
        $this->includeResponseData = $options['include_response_data'] ?? false;

        $this->validate();
        $this->validateRateLimitConfig();
        $this->validateEnhancedRetryConfig();
        $this->validateTelemetryConfig();
    }

    private function validate(): void
    {
        if ($this->timeout < 1) {
            throw new InvalidArgumentException('Timeout must be at least 1 second');
        }

        if ($this->connectTimeout < 1) {
            throw new InvalidArgumentException('Connect timeout must be at least 1 second');
        }

        if (filter_var($this->baseUrl, FILTER_VALIDATE_URL) === false) {
            throw new InvalidArgumentException('Invalid base URL');
        }

        // ErrorReportingLevel ENUM validation is handled in fromString() method
    }

    private function validateRateLimitConfig(): void
    {
        if ($this->rateLimitConfig['requests'] < 1) {
            throw new InvalidArgumentException('Rate limit requests must be at least 1');
        }

        if ($this->rateLimitConfig['window'] < 1) {
            throw new InvalidArgumentException('Rate limit window must be at least 1 second');
        }

        $validStrategies = ['sliding_window', 'fixed_window', 'token_bucket'];
        if (!in_array($this->rateLimitConfig['strategy'], $validStrategies, true)) {
            throw new InvalidArgumentException(
                'Invalid rate limit strategy. Must be one of: ' . implode(', ', $validStrategies)
            );
        }

        if ($this->rateLimitConfig['burst_allowance'] < 0) {
            throw new InvalidArgumentException('Rate limit burst allowance cannot be negative');
        }
    }

    private function validateEnhancedRetryConfig(): void
    {
        if ($this->enhancedRetryConfig['attempts'] < 1) {
            throw new InvalidArgumentException('Enhanced retry attempts must be at least 1');
        }

        if ($this->enhancedRetryConfig['attempts'] > 10) {
            throw new InvalidArgumentException('Enhanced retry attempts cannot exceed 10');
        }

        $validBackoffStrategies = ['exponential', 'linear', 'fixed'];
        if (!in_array($this->enhancedRetryConfig['backoff'], $validBackoffStrategies, true)) {
            throw new InvalidArgumentException(
                'Invalid retry backoff strategy. Must be one of: ' . implode(', ', $validBackoffStrategies)
            );
        }

        if ($this->enhancedRetryConfig['base_delay'] < 1) {
            throw new InvalidArgumentException('Enhanced retry base delay must be at least 1ms');
        }

        if ($this->enhancedRetryConfig['max_delay'] < $this->enhancedRetryConfig['base_delay']) {
            throw new InvalidArgumentException('Enhanced retry max delay must be greater than or equal to base delay');
        }

        if ($this->enhancedRetryConfig['exponential_base'] <= 1.0) {
            throw new InvalidArgumentException('Enhanced retry exponential base must be greater than 1.0');
        }
    }

    private function validateTelemetryConfig(): void
    {
        if ($this->telemetryConfig['sampling_rate'] < 0.0 || $this->telemetryConfig['sampling_rate'] > 1.0) {
            throw new InvalidArgumentException('Telemetry sampling rate must be between 0.0 and 1.0');
        }

        $validPrivacyModes = ['production', 'development', 'debug'];
        if (!in_array($this->telemetryConfig['privacy_mode'], $validPrivacyModes, true)) {
            throw new InvalidArgumentException(
                'Invalid telemetry privacy mode. Must be one of: ' . implode(', ', $validPrivacyModes)
            );
        }

        if ($this->telemetryConfig['flush_interval'] < 1) {
            throw new InvalidArgumentException('Telemetry flush interval must be at least 1 second');
        }

        if ($this->telemetryConfig['max_queue_size'] < 1) {
            throw new InvalidArgumentException('Telemetry max queue size must be at least 1');
        }

        if (filter_var($this->telemetryConfig['endpoint'], FILTER_VALIDATE_URL) === false) {
            throw new InvalidArgumentException('Invalid telemetry endpoint URL');
        }
    }

    public function getApiKey(): ?string
    {
        return $this->apiKey;
    }

    public function hasApiKey(): bool
    {
        return $this->apiKey !== null;
    }

    public function getBaseUrl(): string
    {
        return rtrim($this->baseUrl, '/');
    }

    public function getTimeout(): int
    {
        return $this->timeout;
    }

    public function getConnectTimeout(): int
    {
        return $this->connectTimeout;
    }

    /**
     * @return array{times: int, delay: int, multiplier: int, max_delay: int}
     */
    public function getRetryConfig(): array
    {
        return $this->retryConfig;
    }

    /**
     * Get rate limiting configuration
     *
     * @return array{enabled: bool, requests: int, window: int, strategy: string, burst_allowance: int}
     */
    public function getRateLimitConfig(): array
    {
        return $this->rateLimitConfig;
    }

    /**
     * Get enhanced retry configuration
     *
     * @return array{enabled: bool, attempts: int, backoff: string, base_delay: int, max_delay: int, jitter: bool, exponential_base: float}
     */
    public function getEnhancedRetryConfig(): array
    {
        return $this->enhancedRetryConfig;
    }

    /**
     * Check if rate limiting is enabled
     */
    public function isRateLimitingEnabled(): bool
    {
        return $this->rateLimitConfig['enabled'];
    }

    /**
     * Check if enhanced retry is enabled
     */
    public function isEnhancedRetryEnabled(): bool
    {
        return $this->enhancedRetryConfig['enabled'];
    }

    /**
     * Get telemetry configuration
     *
     * @return array{enabled: bool, auto_report_errors: bool, performance_tracking: bool, privacy_mode: string, sampling_rate: float, endpoint: string, flush_interval: int, max_queue_size: int, filters: array}
     */
    public function getTelemetryConfig(): array
    {
        return $this->telemetryConfig;
    }

    /**
     * Check if telemetry is enabled
     */
    public function isTelemetryEnabled(): bool
    {
        return $this->telemetryConfig['enabled'];
    }

    /**
     * Get telemetry endpoint URL
     */
    public function getTelemetryEndpoint(): string
    {
        return $this->telemetryConfig['endpoint'];
    }

    /**
     * Get SDK version
     */
    public function getSdkVersion(): string
    {
        return '2.0.0';
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function isErrorReportingEnabled(): bool
    {
        return $this->errorReporting;
    }

    public function getErrorReportingEndpoint(): string
    {
        return $this->errorReportingEndpoint;
    }

    public function shouldIncludeStackTrace(): bool
    {
        return $this->includeStackTrace;
    }

    public function getErrorReportingLevel(): ErrorReportingLevel
    {
        return $this->errorReportingLevel;
    }

    public function shouldIncludeRequestData(): bool
    {
        return $this->includeRequestData;
    }

    public function shouldIncludeResponseData(): bool
    {
        return $this->includeResponseData;
    }

    /**
     * Get API key identification for error reporting
     * Returns hashed version for privacy while allowing customer identification
     */
    public function getApiKeyIdentification(): ?string
    {
        if ($this->apiKey === null) {
            return null;
        }
        // Use first 8 chars of SHA256 hash for identification
        return substr(hash('sha256', $this->apiKey), 0, 16);
    }

    public function toArray(): array
    {
        return [
            'api_key' => $this->apiKey,
            'base_url' => $this->baseUrl,
            'timeout' => $this->timeout,
            'connect_timeout' => $this->connectTimeout,
            'retry' => $this->retryConfig,
            'headers' => $this->headers,
            'error_reporting' => $this->errorReporting,
            'error_reporting_endpoint' => $this->errorReportingEndpoint,
            'include_stack_trace' => $this->includeStackTrace,
            'error_reporting_level' => $this->errorReportingLevel->value,
            'include_request_data' => $this->includeRequestData,
            'include_response_data' => $this->includeResponseData,
            'rate_limit' => $this->rateLimitConfig,
            'enhanced_retry' => $this->enhancedRetryConfig,
            'telemetry' => $this->telemetryConfig,
        ];
    }

    /**
     * Check if caching is enabled
     */
    public function isCacheEnabled(): bool
    {
        return false; // Default disabled, can be enabled via configuration
    }

    /**
     * Get cache configuration
     */
    public function getCacheConfig(): array
    {
        return [
            'driver' => 'memory',
            'ttl' => 300,
        ];
    }

    /**
     * Check if monitoring is enabled
     */
    public function isMonitoringEnabled(): bool
    {
        return false; // Default disabled, can be enabled via configuration
    }

    /**
     * Get monitoring configuration
     */
    public function getMonitoringConfig(): array
    {
        return [
            'enabled' => false,
            'metrics_interval' => 60,
        ];
    }

    /**
     * Get environment for configuration
     */
    public function getEnvironment(): \Wioex\SDK\Enums\Environment
    {
        return \Wioex\SDK\Enums\Environment::PRODUCTION;
    }
}
