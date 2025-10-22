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

    public function shouldEnableMetrics(): bool
    {
        return !$this->isTesting();
    }

    public function getMetricsInterval(): int
    {
        return match ($this) {
            self::DEVELOPMENT, self::DEV, self::LOCAL => 30,  // 30 seconds
            self::STAGING => 60,                              // 1 minute
            self::PRODUCTION, self::PROD => 300,              // 5 minutes
            self::TESTING, self::TEST => 10,                 // 10 seconds
        };
    }

    public function getMetricsRetention(): int
    {
        return match ($this) {
            self::DEVELOPMENT, self::DEV, self::LOCAL => 3600,    // 1 hour
            self::STAGING => 86400,                               // 24 hours
            self::PRODUCTION, self::PROD => 604800,               // 7 days
            self::TESTING, self::TEST => 300,                    // 5 minutes
        };
    }

    public function shouldExportMetrics(): bool
    {
        return $this->isProduction() || $this->isStaging();
    }

    public function shouldEnableValidation(): bool
    {
        return !$this->isTesting();
    }

    public function shouldEnableMiddleware(): bool
    {
        return true; // Middleware is enabled in all environments
    }

    public function shouldEnableConnectionPooling(): bool
    {
        return !$this->isTesting();
    }

    public function getMinConnections(): int
    {
        return match ($this) {
            self::DEVELOPMENT, self::DEV, self::LOCAL => 1,
            self::STAGING => 2,
            self::PRODUCTION, self::PROD => 3,
            self::TESTING, self::TEST => 1,
        };
    }

    public function getMaxConnections(): int
    {
        return match ($this) {
            self::DEVELOPMENT, self::DEV, self::LOCAL => 5,
            self::STAGING => 10,
            self::PRODUCTION, self::PROD => 20,
            self::TESTING, self::TEST => 2,
        };
    }

    public function getDefaultPoolStrategy(): string
    {
        return match ($this) {
            self::DEVELOPMENT, self::DEV, self::LOCAL => 'round_robin',
            self::STAGING => 'least_connections',
            self::PRODUCTION, self::PROD => 'adaptive',
            self::TESTING, self::TEST => 'fifo',
        };
    }

    public function getCleanupInterval(): int
    {
        return match ($this) {
            self::DEVELOPMENT, self::DEV, self::LOCAL => 120,  // 2 minutes
            self::STAGING => 180,                              // 3 minutes
            self::PRODUCTION, self::PROD => 300,               // 5 minutes
            self::TESTING, self::TEST => 60,                  // 1 minute
        };
    }

    public function getDefaultExportFormat(): string
    {
        return match ($this) {
            self::DEVELOPMENT, self::DEV, self::LOCAL => 'json',
            self::STAGING => 'csv',
            self::PRODUCTION, self::PROD => 'csv',
            self::TESTING, self::TEST => 'json',
        };
    }

    public function getMaxExportSize(): int
    {
        return match ($this) {
            self::DEVELOPMENT, self::DEV, self::LOCAL => 10 * 1024 * 1024,  // 10MB
            self::STAGING => 25 * 1024 * 1024,                              // 25MB
            self::PRODUCTION, self::PROD => 100 * 1024 * 1024,              // 100MB
            self::TESTING, self::TEST => 5 * 1024 * 1024,                   // 5MB
        };
    }

    public function getTempDirectory(): string
    {
        return match ($this) {
            self::DEVELOPMENT, self::DEV, self::LOCAL => sys_get_temp_dir() . '/wioex_dev',
            self::STAGING => sys_get_temp_dir() . '/wioex_staging',
            self::PRODUCTION, self::PROD => sys_get_temp_dir() . '/wioex_prod',
            self::TESTING, self::TEST => sys_get_temp_dir() . '/wioex_test',
        };
    }

    public function getMonitoringLevel(): string
    {
        return match ($this) {
            self::DEVELOPMENT, self::DEV, self::LOCAL => 'verbose',
            self::STAGING => 'detailed',
            self::PRODUCTION, self::PROD => 'essential',
            self::TESTING, self::TEST => 'minimal',
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
        };
    }
}
