<?php

declare(strict_types=1);

namespace Wioex\SDK;

use InvalidArgumentException;

class Config
{
    private string $apiKey;
    private string $baseUrl;
    private int $timeout;
    private int $connectTimeout;
    /** @var array{times: int, delay: int, multiplier: int, max_delay: int} */
    private array $retryConfig;
    private array $headers;

    /**
     * @param array{api_key?: string, base_url?: string, timeout?: int, connect_timeout?: int, retry?: array, headers?: array} $options
     */
    public function __construct(array $options = [])
    {
        if (!isset($options['api_key']) || $options['api_key'] === '') {
            throw new InvalidArgumentException('API key is required');
        }

        $this->apiKey = $options['api_key'];
        $this->baseUrl = $options['base_url'] ?? 'https://api.wioex.com';
        $this->timeout = $options['timeout'] ?? 30;
        $this->connectTimeout = $options['connect_timeout'] ?? 10;

        $retry = $options['retry'] ?? [];
        $this->retryConfig = [
            'times' => $retry['times'] ?? 3,
            'delay' => $retry['delay'] ?? 100, // milliseconds
            'multiplier' => $retry['multiplier'] ?? 2,
            'max_delay' => $retry['max_delay'] ?? 5000 // max 5 seconds
        ];

        $this->headers = array_merge([
            'Accept' => 'application/json',
            'User-Agent' => 'WioEX-PHP-SDK/1.0',
        ], $options['headers'] ?? []);

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
    }

    public function getApiKey(): string
    {
        return $this->apiKey;
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

    public function toArray(): array
    {
        return [
            'api_key' => $this->apiKey,
            'base_url' => $this->baseUrl,
            'timeout' => $this->timeout,
            'connect_timeout' => $this->connectTimeout,
            'retry' => $this->retryConfig,
            'headers' => $this->headers,
        ];
    }
}
