<?php

declare(strict_types=1);

namespace Wioex\SDK;

use InvalidArgumentException;

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
    private string $errorReportingLevel;
    private bool $includeRequestData;
    private bool $includeResponseData;

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
     *     error_reporting_level?: string,
     *     include_request_data?: bool,
     *     include_response_data?: bool
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

        $retry = $options['retry'] ?? [];
        $this->retryConfig = [
            'times' => (int)($retry['times'] ?? 3),
            'delay' => (int)($retry['delay'] ?? 100), // milliseconds
            'multiplier' => (int)($retry['multiplier'] ?? 2),
            'max_delay' => (int)($retry['max_delay'] ?? 5000) // max 5 seconds
        ];

        $this->headers = array_merge([
            'Accept' => 'application/json',
            'User-Agent' => 'WioEX-PHP-SDK/1.0',
        ], $options['headers'] ?? []);

        // Error reporting configuration
        $this->errorReporting = $options['error_reporting'] ?? false;
        $this->errorReportingEndpoint = $options['error_reporting_endpoint']
            ?? 'https://api.wioex.com/v2/sdk/error-report';
        $this->includeStackTrace = $options['include_stack_trace'] ?? false;

        // Error reporting levels: 'minimal', 'standard', 'detailed'
        // Default is 'detailed' for better debugging and customer support
        $this->errorReportingLevel = $options['error_reporting_level'] ?? 'detailed';
        $this->includeRequestData = $options['include_request_data'] ?? false;
        $this->includeResponseData = $options['include_response_data'] ?? false;

        $this->validate();
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

        $validLevels = ['minimal', 'standard', 'detailed'];
        if (!in_array($this->errorReportingLevel, $validLevels, true)) {
            throw new InvalidArgumentException(
                'Invalid error reporting level. Must be one of: ' . implode(', ', $validLevels)
            );
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

    public function getErrorReportingLevel(): string
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
            'error_reporting_level' => $this->errorReportingLevel,
            'include_request_data' => $this->includeRequestData,
            'include_response_data' => $this->includeResponseData,
        ];
    }
}
