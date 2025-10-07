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
    private array $retryConfig;
    private array $headers;

    public function __construct(array $options = [])
    {
        if (empty($options['api_key'])) {
            throw new InvalidArgumentException('API key is required');
        }

        $this->apiKey = $options['api_key'];
        $this->baseUrl = $options['base_url'] ?? 'https://api.wioex.com';
        $this->timeout = $options['timeout'] ?? 30;
        $this->connectTimeout = $options['connect_timeout'] ?? 10;

        $this->retryConfig = $options['retry'] ?? [
            'times' => 3,
            'delay' => 100, // milliseconds
            'multiplier' => 2,
            'max_delay' => 5000 // max 5 seconds
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

        if (!filter_var($this->baseUrl, FILTER_VALIDATE_URL)) {
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
