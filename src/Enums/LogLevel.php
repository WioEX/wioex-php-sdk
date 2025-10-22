<?php

declare(strict_types=1);

namespace Wioex\SDK\Enums;

enum LogLevel: string
{
    case EMERGENCY = 'emergency';
    case ALERT = 'alert';
    case CRITICAL = 'critical';
    case ERROR = 'error';
    case WARNING = 'warning';
    case NOTICE = 'notice';
    case INFO = 'info';
    case DEBUG = 'debug';

    public function getNumericLevel(): int
    {
        return match ($this) {
            self::EMERGENCY => 800,
            self::ALERT => 700,
            self::CRITICAL => 600,
            self::ERROR => 500,
            self::WARNING => 400,
            self::NOTICE => 300,
            self::INFO => 200,
            self::DEBUG => 100,
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::EMERGENCY => 'System is unusable',
            self::ALERT => 'Action must be taken immediately',
            self::CRITICAL => 'Critical conditions',
            self::ERROR => 'Error conditions',
            self::WARNING => 'Warning conditions',
            self::NOTICE => 'Normal but significant condition',
            self::INFO => 'Informational messages',
            self::DEBUG => 'Debug-level messages',
        };
    }

    public function getConsoleColor(): string
    {
        return match ($this) {
            self::EMERGENCY => "\033[1;41m", // Bold white on red
            self::ALERT => "\033[1;45m",     // Bold white on magenta
            self::CRITICAL => "\033[1;41m",  // Bold white on red
            self::ERROR => "\033[1;31m",     // Bold red
            self::WARNING => "\033[1;33m",   // Bold yellow
            self::NOTICE => "\033[1;36m",    // Bold cyan
            self::INFO => "\033[1;32m",      // Bold green
            self::DEBUG => "\033[0;37m",     // Light gray
        };
    }

    public function shouldLog(self $minimumLevel): bool
    {
        return $this->getNumericLevel() >= $minimumLevel->getNumericLevel();
    }

    public function isErrorLevel(): bool
    {
        return match ($this) {
            self::EMERGENCY, self::ALERT, self::CRITICAL, self::ERROR => true,
            default => false,
        };
    }

    public function isWarningLevel(): bool
    {
        return $this === self::WARNING;
    }

    public function isInfoLevel(): bool
    {
        return match ($this) {
            self::NOTICE, self::INFO => true,
            default => false,
        };
    }

    public function isDebugLevel(): bool
    {
        return $this === self::DEBUG;
    }

    public static function fromString(string $level): self
    {
        $level = strtolower(trim($level));

        return match ($level) {
            'emergency', 'emerg' => self::EMERGENCY,
            'alert' => self::ALERT,
            'critical', 'crit' => self::CRITICAL,
            'error', 'err' => self::ERROR,
            'warning', 'warn' => self::WARNING,
            'notice' => self::NOTICE,
            'info' => self::INFO,
            'debug' => self::DEBUG,
            default => throw new \InvalidArgumentException("Invalid log level: {$level}"),
        };
    }

    public static function fromNumericLevel(int $level): self
    {
        return match (true) {
            $level >= 800 => self::EMERGENCY,
            $level >= 700 => self::ALERT,
            $level >= 600 => self::CRITICAL,
            $level >= 500 => self::ERROR,
            $level >= 400 => self::WARNING,
            $level >= 300 => self::NOTICE,
            $level >= 200 => self::INFO,
            default => self::DEBUG,
        };
    }

    public function getMonologLevel(): int
    {
        return $this->getNumericLevel();
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::EMERGENCY => '🚨',
            self::ALERT => '🔴',
            self::CRITICAL => '💥',
            self::ERROR => '❌',
            self::WARNING => '⚠️',
            self::NOTICE => '📢',
            self::INFO => 'ℹ️',
            self::DEBUG => '🐛',
        };
    }

    public function getShortName(): string
    {
        return match ($this) {
            self::EMERGENCY => 'EMRG',
            self::ALERT => 'ALRT',
            self::CRITICAL => 'CRIT',
            self::ERROR => 'ERRO',
            self::WARNING => 'WARN',
            self::NOTICE => 'NOTE',
            self::INFO => 'INFO',
            self::DEBUG => 'DEBG',
        };
    }

    public static function getAllLevels(): array
    {
        return [
            'error_levels' => [self::EMERGENCY, self::ALERT, self::CRITICAL, self::ERROR],
            'warning_levels' => [self::WARNING],
            'info_levels' => [self::NOTICE, self::INFO],
            'debug_levels' => [self::DEBUG],
        ];
    }

    public function toArray(): array
    {
        return [
            'value' => $this->value,
            'name' => $this->name,
            'numeric_level' => $this->getNumericLevel(),
            'description' => $this->getDescription(),
            'console_color' => $this->getConsoleColor(),
            'icon' => $this->getIcon(),
            'short_name' => $this->getShortName(),
            'is_error_level' => $this->isErrorLevel(),
            'is_warning_level' => $this->isWarningLevel(),
            'is_info_level' => $this->isInfoLevel(),
            'is_debug_level' => $this->isDebugLevel(),
        ];
    }
}
