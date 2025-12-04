<?php

declare(strict_types=1);

namespace Wioex\SDK;

use InvalidArgumentException;
use Wioex\SDK\Enums\ErrorReportingLevel;
use Wioex\SDK\Version;

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
    
    /** @var array Configuration data for dot notation access */
    private readonly array $configData;
    
    /** @var array Dynamic configuration for runtime changes */
    private array $dynamicConfig = [];

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
        // Store complete configuration data for dot notation access
        $this->configData = $options;
        
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
            'enabled' => false, // Permanently disabled for maximum performance
            'requests' => (int)($rateLimit['requests'] ?? 100), // requests per window
            'window' => (int)($rateLimit['window'] ?? 60), // window in seconds
            'strategy' => $rateLimit['strategy'] ?? 'sliding_window', // sliding_window, fixed_window, token_bucket
            'burst_allowance' => (int)($rateLimit['burst_allowance'] ?? 10) // extra requests for burst
        ];

        // Enhanced retry configuration with intelligent backoff for server errors
        $enhancedRetry = $options['enhanced_retry'] ?? [];
        $this->enhancedRetryConfig = [
            'enabled' => (bool)($enhancedRetry['enabled'] ?? true), // Enable by default for server error handling
            'attempts' => (int)($enhancedRetry['attempts'] ?? 5),
            'backoff' => $enhancedRetry['backoff'] ?? 'exponential', // exponential, linear, fixed
            'base_delay' => (int)($enhancedRetry['base_delay'] ?? 500), // Higher base delay for server errors
            'max_delay' => (int)($enhancedRetry['max_delay'] ?? 30000), // max delay in ms (30 seconds)
            'jitter' => (bool)($enhancedRetry['jitter'] ?? true), // add randomization
            'exponential_base' => (float)($enhancedRetry['exponential_base'] ?? 2.0), // exponential multiplier
            'retry_on_server_errors' => (bool)($enhancedRetry['retry_on_server_errors'] ?? true), // Retry on all 5xx errors
            'retry_on_connection_errors' => (bool)($enhancedRetry['retry_on_connection_errors'] ?? true), // Network/timeout errors
            'retry_on_rate_limit' => (bool)($enhancedRetry['retry_on_rate_limit'] ?? true), // 429 errors
            'circuit_breaker_enabled' => (bool)($enhancedRetry['circuit_breaker_enabled'] ?? true)
        ];

        // Telemetry configuration for comprehensive SDK monitoring
        $telemetry = $options['telemetry'] ?? [];
        $this->telemetryConfig = [
            'enabled' => (bool)($telemetry['enabled'] ?? false), // Opt-in by default
            'auto_report_errors' => (bool)($telemetry['auto_report_errors'] ?? true),
            'performance_tracking' => (bool)($telemetry['performance_tracking'] ?? true),
            'privacy_mode' => $telemetry['privacy_mode'] ?? 'production', // production, development, debug
            'sampling_rate' => (float)($telemetry['sampling_rate'] ?? 0.1), // 10% sampling by default
            'endpoint' => $telemetry['endpoint'] ?? 'https://api.wioex.com/sdk/telemetry',
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
            'User-Agent' => Version::userAgent(),
        ], $options['headers'] ?? []);

        // Error reporting configuration
        $this->errorReporting = $options['error_reporting'] ?? false;
        $this->errorReportingEndpoint = $options['error_reporting_endpoint']
            ?? 'https://api.wioex.com/sdk/error-report';
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

    /**
     * Get configuration value by key
     */
    public function get(string $key, mixed $default = null): mixed
    {
        // Handle dot notation for nested keys
        $keys = explode('.', $key);
        $value = $this->getConfigValue($keys);
        
        return $value !== null ? $value : $default;
    }

    /**
     * Set configuration value by key
     */
    public function set(string $key, mixed $value): void
    {
        // Handle dot notation for nested keys
        $keys = explode('.', $key);
        $this->setConfigValue($keys, $value);
    }

    /**
     * Check if configuration key exists
     */
    public function has(string $key): bool
    {
        $keys = explode('.', $key);
        return $this->getConfigValue($keys) !== null;
    }

    /**
     * Get all configuration as array
     */
    public function all(): array
    {
        return array_merge($this->toArray(), $this->dynamicConfig);
    }

    /**
     * Get configuration value from nested array structure
     */
    private function getConfigValue(array $keys): mixed
    {
        $firstKey = array_shift($keys);
        
        // Check built-in properties first
        $value = match ($firstKey) {
            'api_key' => $this->apiKey,
            'base_url' => $this->baseUrl,
            'timeout' => $this->timeout,
            'connect_timeout' => $this->connectTimeout,
            'retry' => $this->retryConfig,
            'headers' => $this->headers,
            'error_reporting' => $this->errorReporting,
            'error_reporting_endpoint' => $this->errorReportingEndpoint,
            'include_stack_trace' => $this->includeStackTrace,
            'error_reporting_level' => $this->errorReportingLevel,
            'include_request_data' => $this->includeRequestData,
            'include_response_data' => $this->includeResponseData,
            'rate_limit', 'rate_limiting' => $this->rateLimitConfig,
            'enhanced_retry' => $this->enhancedRetryConfig,
            'telemetry' => $this->telemetryConfig,
            'cache' => $this->getCacheConfig(),
            'monitoring' => $this->getMonitoringConfig(),
            default => $this->dynamicConfig[$firstKey] ?? null
        };

        // If we have more keys, traverse deeper
        if ($keys !== '' && is_array($value)) {
            return $this->traverseArray($value, $keys);
        }

        return $value;
    }

    /**
     * Set configuration value in nested array structure
     */
    private function setConfigValue(array $keys, mixed $value): void
    {
        $firstKey = array_shift($keys);
        
        // For built-in properties, update them directly if it's a top-level set
        if (empty($keys)) {
            match ($firstKey) {
                'api_key' => $this->apiKey = $value,
                'base_url' => $this->baseUrl = $value,
                'timeout' => $this->timeout = (int) $value,
                'connect_timeout' => $this->connectTimeout = (int) $value,
                'error_reporting' => $this->errorReporting = (bool) $value,
                'include_stack_trace' => $this->includeStackTrace = (bool) $value,
                'include_request_data' => $this->includeRequestData = (bool) $value,
                'include_response_data' => $this->includeResponseData = (bool) $value,
                default => $this->dynamicConfig[$firstKey] = $value
            };
            return;
        }

        // For nested values, store in dynamic config
        if (!isset($this->dynamicConfig[$firstKey])) {
            $this->dynamicConfig[$firstKey] = [];
        }

        $this->setNestedValue($this->dynamicConfig[$firstKey], $keys, $value);
    }

    /**
     * Traverse array with keys
     */
    private function traverseArray(array $array, array $keys): mixed
    {
        $current = $array;
        
        foreach ($keys as $key) {
            if (!is_array($current) || !isset($current[$key])) {
                return null;
            }
            $current = $current[$key];
        }
        
        return $current;
    }

    /**
     * Set nested value in array
     */
    private function setNestedValue(array &$array, array $keys, mixed $value): void
    {
        $key = array_shift($keys);
        
        if (($keys === null || $keys === '' || $keys === [])) {
            $array[$key] = $value;
            return;
        }
        
        if (!isset($array[$key]) || !is_array($array[$key])) {
            $array[$key] = [];
        }
        
        $this->setNestedValue($array[$key], $keys, $value);
    }

    /**
     * Remove configuration key
     */
    public function remove(string $key): void
    {
        $keys = explode('.', $key);
        $firstKey = array_shift($keys);
        
        if (($keys === null || $keys === '' || $keys === [])) {
            unset($this->dynamicConfig[$firstKey]);
        } else {
            $this->removeNestedValue($this->dynamicConfig[$firstKey] ?? [], $keys);
        }
    }

    /**
     * Remove nested value from array
     */
    private function removeNestedValue(array &$array, array $keys): void
    {
        $key = array_shift($keys);
        
        if (($keys === null || $keys === '' || $keys === [])) {
            unset($array[$key]);
            return;
        }
        
        if (isset($array[$key]) && is_array($array[$key])) {
            $this->removeNestedValue($array[$key], $keys);
        }
    }

    /**
     * Merge configuration with another array
     *
     * SECURITY FIX: Validates input against whitelist and type checks
     * to prevent configuration injection attacks
     */
    public function merge(array $config): self
    {
        // Whitelist of allowed configuration keys
        $allowedKeys = [
            'api_key', 'base_url', 'timeout', 'connect_timeout',
            'retry', 'headers', 'error_reporting', 'error_reporting_endpoint',
            'include_stack_trace', 'error_reporting_level', 'include_request_data',
            'include_response_data', 'rate_limit', 'enhanced_retry', 'telemetry'
        ];

        // Validate and sanitize input
        $validated = $this->validateMergeConfig($config, $allowedKeys);

        // Perform merge with validated configuration
        $this->dynamicConfig = array_merge_recursive($this->dynamicConfig, $validated);
        return $this;
    }

    /**
     * Validate configuration array for merge operation
     */
    private function validateMergeConfig(array $config, array $allowedKeys): array
    {
        $validated = [];

        foreach ($config as $key => $value) {
            // Check if key is in whitelist
            if (!in_array($key, $allowedKeys, true)) {
                throw new InvalidArgumentException(
                    "Invalid configuration key: '{$key}'. Allowed keys: " . implode(', ', $allowedKeys)
                );
            }

            // Type validation based on key
            switch ($key) {
                case 'api_key':
                case 'base_url':
                case 'error_reporting_endpoint':
                case 'error_reporting_level':
                    if (!is_string($value) && $value !== null) {
                        throw new InvalidArgumentException("Configuration key '{$key}' must be a string or null");
                    }
                    break;

                case 'timeout':
                case 'connect_timeout':
                    if (!is_int($value) || $value < 0) {
                        throw new InvalidArgumentException("Configuration key '{$key}' must be a positive integer");
                    }
                    break;

                case 'error_reporting':
                case 'include_stack_trace':
                case 'include_request_data':
                case 'include_response_data':
                    if (!is_bool($value)) {
                        throw new InvalidArgumentException("Configuration key '{$key}' must be a boolean");
                    }
                    break;

                case 'retry':
                case 'rate_limit':
                case 'enhanced_retry':
                case 'telemetry':
                case 'headers':
                    if (!is_array($value)) {
                        throw new InvalidArgumentException("Configuration key '{$key}' must be an array");
                    }
                    $validated[$key] = $this->validateNestedConfig($key, $value);
                    continue 2;
            }

            $validated[$key] = $value;
        }

        return $validated;
    }

    /**
     * Validate nested configuration arrays
     */
    private function validateNestedConfig(string $parentKey, array $config): array
    {
        $nestedWhitelists = [
            'retry' => ['times', 'delay', 'multiplier', 'max_delay'],
            'rate_limit' => ['enabled', 'requests', 'window', 'strategy', 'burst_allowance'],
            'enhanced_retry' => [
                'enabled', 'attempts', 'backoff', 'base_delay', 'max_delay',
                'jitter', 'exponential_base', 'retry_on_server_errors',
                'retry_on_connection_errors', 'retry_on_rate_limit', 'circuit_breaker_enabled'
            ],
            'telemetry' => [
                'enabled', 'auto_report_errors', 'performance_tracking', 'privacy_mode',
                'sampling_rate', 'endpoint', 'flush_interval', 'max_queue_size', 'filters'
            ],
            'headers' => null
        ];

        $validated = [];

        if ($parentKey === 'headers') {
            foreach ($config as $headerKey => $headerValue) {
                if (!is_string($headerKey) || (!is_string($headerValue) && !is_numeric($headerValue))) {
                    throw new InvalidArgumentException("Header keys and values must be strings");
                }
                $validated[$headerKey] = (string) $headerValue;
            }
            return $validated;
        }

        $allowedNestedKeys = $nestedWhitelists[$parentKey] ?? [];

        foreach ($config as $nestedKey => $nestedValue) {
            if (!in_array($nestedKey, $allowedNestedKeys, true)) {
                throw new InvalidArgumentException(
                    "Invalid configuration key '{$parentKey}.{$nestedKey}'. Allowed keys: " .
                    implode(', ', $allowedNestedKeys)
                );
            }

            if (str_ends_with($nestedKey, '_enabled') ||
                in_array($nestedKey, ['enabled', 'jitter', 'auto_report_errors', 'performance_tracking'], true)) {
                if (!is_bool($nestedValue)) {
                    throw new InvalidArgumentException("Configuration key '{$parentKey}.{$nestedKey}' must be a boolean");
                }
            } elseif (in_array($nestedKey, ['times', 'delay', 'multiplier', 'max_delay', 'requests',
                                             'window', 'burst_allowance', 'attempts', 'base_delay',
                                             'flush_interval', 'max_queue_size'], true)) {
                if (!is_int($nestedValue) || $nestedValue < 0) {
                    throw new InvalidArgumentException("Configuration key '{$parentKey}.{$nestedKey}' must be a positive integer");
                }
            }

            $validated[$nestedKey] = $nestedValue;
        }

        return $validated;
    }
}
