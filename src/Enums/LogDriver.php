<?php

declare(strict_types=1);

namespace Wioex\SDK\Enums;

enum LogDriver: string
{
    case MONOLOG = 'monolog';
    case FILE = 'file';
    case SYSLOG = 'syslog';
    case ERROR_LOG = 'error_log';
    case NULL = 'null';
    case STACK = 'stack';
    case DAILY = 'daily';
    case SINGLE = 'single';
    case STDERR = 'stderr';
    case CUSTOM = 'custom';

    public function getDescription(): string
    {
        return match ($this) {
            self::MONOLOG => 'Monolog logger with configurable handlers',
            self::FILE => 'Simple file-based logging',
            self::SYSLOG => 'System log integration',
            self::ERROR_LOG => 'PHP error_log function',
            self::NULL => 'Null logger (discards all logs)',
            self::STACK => 'Stack of multiple loggers',
            self::DAILY => 'Daily rotating file logs',
            self::SINGLE => 'Single file logger',
            self::STDERR => 'Standard error output',
            self::CUSTOM => 'Custom logger implementation',
        };
    }

    public function requiresMonolog(): bool
    {
        return match ($this) {
            self::MONOLOG, self::STACK, self::DAILY => true,
            default => false,
        };
    }

    public function supportsBatching(): bool
    {
        return match ($this) {
            self::MONOLOG, self::FILE, self::DAILY, self::SINGLE => true,
            default => false,
        };
    }

    public function supportsRotation(): bool
    {
        return match ($this) {
            self::DAILY => true,
            default => false,
        };
    }

    public function isProduction(): bool
    {
        return match ($this) {
            self::SYSLOG, self::DAILY, self::MONOLOG => true,
            default => false,
        };
    }

    public function isDevelopment(): bool
    {
        return match ($this) {
            self::FILE, self::STDERR, self::ERROR_LOG => true,
            default => false,
        };
    }

    public function getDefaultPath(): string
    {
        return match ($this) {
            self::FILE, self::SINGLE => '/var/log/wioex.log',
            self::DAILY => '/var/log/wioex',
            self::STDERR => 'php://stderr',
            default => '',
        };
    }

    public function getDefaultLevel(): LogLevel
    {
        return match ($this) {
            self::NULL => LogLevel::EMERGENCY, // Will never log
            self::ERROR_LOG => LogLevel::ERROR,
            self::SYSLOG => LogLevel::WARNING,
            self::STDERR => LogLevel::DEBUG,
            default => LogLevel::INFO,
        };
    }

    public function getPerformanceImpact(): string
    {
        return match ($this) {
            self::NULL => 'none',
            self::ERROR_LOG, self::STDERR => 'low',
            self::FILE, self::SINGLE => 'medium',
            self::SYSLOG, self::DAILY, self::MONOLOG => 'high',
            self::STACK, self::CUSTOM => 'variable',
        };
    }

    public static function fromString(string $driver): self
    {
        $driver = strtolower(trim($driver));

        return match ($driver) {
            'monolog' => self::MONOLOG,
            'file' => self::FILE,
            'syslog' => self::SYSLOG,
            'error_log', 'errorlog' => self::ERROR_LOG,
            'null' => self::NULL,
            'stack' => self::STACK,
            'daily' => self::DAILY,
            'single' => self::SINGLE,
            'stderr' => self::STDERR,
            'custom' => self::CUSTOM,
            default => throw new \InvalidArgumentException("Invalid log driver: {$driver}"),
        };
    }

    public function getRequiredExtensions(): array
    {
        return match ($this) {
            self::SYSLOG => ['syslog'],
            self::MONOLOG => ['json'],
            default => [],
        };
    }

    public function getDefaultConfig(): array
    {
        return match ($this) {
            self::MONOLOG => [
                'handlers' => ['stream'],
                'processors' => ['introspection'],
                'formatters' => ['line'],
            ],
            self::FILE, self::SINGLE => [
                'path' => $this->getDefaultPath(),
                'permission' => 0644,
            ],
            self::DAILY => [
                'path' => $this->getDefaultPath(),
                'days' => 14,
                'permission' => 0644,
            ],
            self::SYSLOG => [
                'facility' => LOG_USER,
                'flags' => LOG_PID | LOG_PERROR,
            ],
            self::STACK => [
                'drivers' => ['file', 'syslog'],
            ],
            default => [],
        };
    }

    public function toArray(): array
    {
        return [
            'value' => $this->value,
            'name' => $this->name,
            'description' => $this->getDescription(),
            'requires_monolog' => $this->requiresMonolog(),
            'supports_batching' => $this->supportsBatching(),
            'supports_rotation' => $this->supportsRotation(),
            'is_production' => $this->isProduction(),
            'is_development' => $this->isDevelopment(),
            'default_path' => $this->getDefaultPath(),
            'default_level' => $this->getDefaultLevel()->value,
            'performance_impact' => $this->getPerformanceImpact(),
            'required_extensions' => $this->getRequiredExtensions(),
            'default_config' => $this->getDefaultConfig(),
        ];
    }
}
