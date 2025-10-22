<?php

declare(strict_types=1);

namespace Wioex\SDK\Enums;

enum Environment: string
{
    case DEVELOPMENT = 'development';
    case DEV = 'dev';
    case STAGING = 'staging';
    case PRODUCTION = 'production';
    case PROD = 'prod';
    case TESTING = 'testing';
    case TEST = 'test';
    case LOCAL = 'local';

    public function isDevelopment(): bool
    {
        return $this === self::DEVELOPMENT || $this === self::DEV || $this === self::LOCAL;
    }

    public function isStaging(): bool
    {
        return $this === self::STAGING;
    }

    public function isProduction(): bool
    {
        return $this === self::PRODUCTION || $this === self::PROD;
    }

    public function isTesting(): bool
    {
        return $this === self::TESTING || $this === self::TEST;
    }

    public function getBaseUrl(): string
    {
        return match ($this) {
            self::DEVELOPMENT, self::DEV, self::LOCAL => 'https://dev-api.wioex.com',
            self::STAGING => 'https://staging-api.wioex.com',
            self::PRODUCTION, self::PROD => 'https://api.wioex.com',
            self::TESTING, self::TEST => 'https://test-api.wioex.com',
        };
    }

    public function getDefaultTimeout(): int
    {
        return match ($this) {
            self::DEVELOPMENT, self::DEV, self::LOCAL => 60,
            self::STAGING => 45,
            self::PRODUCTION, self::PROD => 30,
            self::TESTING, self::TEST => 10,
        };
    }

    public function shouldEnableDebug(): bool
    {
        return $this->isDevelopment() || $this->isTesting();
    }

    public function shouldEnableLogging(): bool
    {
        return !$this->isTesting();
    }

    public function getLogLevel(): string
    {
        return match ($this) {
            self::DEVELOPMENT, self::DEV, self::LOCAL => 'debug',
            self::STAGING => 'info',
            self::PRODUCTION, self::PROD => 'warning',
            self::TESTING, self::TEST => 'error',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::DEVELOPMENT, self::DEV => 'Development environment with debug features',
            self::LOCAL => 'Local development environment',
            self::STAGING => 'Staging environment for testing',
            self::PRODUCTION, self::PROD => 'Production environment',
            self::TESTING, self::TEST => 'Testing environment for unit/integration tests',
        };
    }

    public static function fromString(string $env): self
    {
        $env = strtolower(trim($env));
        
        return match ($env) {
            'development', 'dev' => self::DEV,
            'staging', 'stage' => self::STAGING,
            'production', 'prod', 'live' => self::PRODUCTION,
            'testing', 'test' => self::TEST,
            'local' => self::LOCAL,
            default => throw new \InvalidArgumentException("Invalid environment: {$env}"),
        };
    }

    public function normalize(): self
    {
        return match ($this) {
            self::DEVELOPMENT => self::DEV,
            self::PRODUCTION => self::PROD,
            self::TESTING => self::TEST,
            default => $this,
        };
    }

    public function toArray(): array
    {
        return [
            'value' => $this->value,
            'name' => $this->name,
            'description' => $this->getDescription(),
            'base_url' => $this->getBaseUrl(),
            'default_timeout' => $this->getDefaultTimeout(),
            'debug_enabled' => $this->shouldEnableDebug(),
            'logging_enabled' => $this->shouldEnableLogging(),
            'log_level' => $this->getLogLevel(),
            'is_development' => $this->isDevelopment(),
            'is_staging' => $this->isStaging(),
            'is_production' => $this->isProduction(),
            'is_testing' => $this->isTesting(),
        ];
    }

    public static function getAllEnvironments(): array
    {
        return [
            'development' => [self::DEV, self::DEVELOPMENT, self::LOCAL],
            'staging' => [self::STAGING],
            'production' => [self::PRODUCTION, self::PROD],
            'testing' => [self::TEST, self::TESTING],
        ];
    }

    public function getSecurityLevel(): string
    {
        return match ($this) {
            self::DEVELOPMENT, self::DEV, self::LOCAL => 'low',
            self::STAGING, self::TESTING, self::TEST => 'medium',
            self::PRODUCTION, self::PROD => 'high',
        };
    }

    public function getPerformanceProfile(): array
    {
        return match ($this) {
            self::DEVELOPMENT, self::DEV, self::LOCAL => [
                'cache_ttl' => 60,
                'rate_limit' => 1000,
                'retry_attempts' => 5,
                'connection_timeout' => 30,
            ],
            self::STAGING => [
                'cache_ttl' => 300,
                'rate_limit' => 500,
                'retry_attempts' => 3,
                'connection_timeout' => 20,
            ],
            self::PRODUCTION, self::PROD => [
                'cache_ttl' => 600,
                'rate_limit' => 100,
                'retry_attempts' => 3,
                'connection_timeout' => 10,
            ],
            self::TESTING, self::TEST => [
                'cache_ttl' => 30,
                'rate_limit' => 10000,
                'retry_attempts' => 1,
                'connection_timeout' => 5,
            ],
        ];
    }
}