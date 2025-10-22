<?php

declare(strict_types=1);

namespace Wioex\SDK\Enums;

enum ValidationState: string
{
    case VALID = 'valid';
    case INVALID = 'invalid';
    case PENDING = 'pending';
    case PARTIAL = 'partial';
    case WARNING = 'warning';
    case ERROR = 'error';
    case UNKNOWN = 'unknown';

    public function isValid(): bool
    {
        return $this === self::VALID;
    }

    public function isInvalid(): bool
    {
        return $this === self::INVALID || $this === self::ERROR;
    }

    public function isPending(): bool
    {
        return $this === self::PENDING;
    }

    public function hasWarnings(): bool
    {
        return $this === self::WARNING || $this === self::PARTIAL;
    }

    public function hasErrors(): bool
    {
        return $this === self::ERROR || $this === self::INVALID;
    }

    public function isComplete(): bool
    {
        return match ($this) {
            self::VALID, self::INVALID, self::ERROR => true,
            default => false,
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::VALID => 'Validation passed successfully',
            self::INVALID => 'Validation failed with errors',
            self::PENDING => 'Validation is in progress',
            self::PARTIAL => 'Validation passed with warnings',
            self::WARNING => 'Validation completed with warnings',
            self::ERROR => 'Validation failed with critical errors',
            self::UNKNOWN => 'Validation state is unknown',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::VALID => '[VALID]',
            self::INVALID => '[INVALID]',
            self::PENDING => '[PENDING]',
            self::PARTIAL => '[PARTIAL]',
            self::WARNING => '[WARNING]',
            self::ERROR => '[ERROR]',
            self::UNKNOWN => '[UNKNOWN]',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::VALID => 'green',
            self::INVALID => 'red',
            self::PENDING => 'blue',
            self::PARTIAL => 'orange',
            self::WARNING => 'yellow',
            self::ERROR => 'darkred',
            self::UNKNOWN => 'gray',
        };
    }

    public function getPriority(): int
    {
        return match ($this) {
            self::ERROR => 10,      // Highest priority
            self::INVALID => 9,
            self::WARNING => 8,
            self::PARTIAL => 7,
            self::PENDING => 6,
            self::UNKNOWN => 5,
            self::VALID => 1,       // Lowest priority
        };
    }

    public function shouldLog(): bool
    {
        return match ($this) {
            self::VALID => false,
            default => true,
        };
    }

    public function getLogLevel(): LogLevel
    {
        return match ($this) {
            self::VALID => LogLevel::DEBUG,
            self::INVALID => LogLevel::ERROR,
            self::PENDING => LogLevel::INFO,
            self::PARTIAL => LogLevel::WARNING,
            self::WARNING => LogLevel::WARNING,
            self::ERROR => LogLevel::CRITICAL,
            self::UNKNOWN => LogLevel::NOTICE,
        };
    }

    public static function fromException(\Throwable $exception): self
    {
        return match (true) {
            $exception instanceof \InvalidArgumentException => self::INVALID,
            $exception instanceof \RuntimeException => self::ERROR,
            $exception instanceof \LogicException => self::ERROR,
            default => self::UNKNOWN,
        };
    }

    public static function fromValidationResults(array $results): self
    {
        $hasErrors = false;
        $hasWarnings = false;
        $hasPending = false;

        foreach ($results as $result) {
            if ($result === false || (is_array($result) && !empty($result['errors']))) {
                $hasErrors = true;
            } elseif (is_array($result) && !empty($result['warnings'])) {
                $hasWarnings = true;
            } elseif (is_null($result)) {
                $hasPending = true;
            }
        }

        if ($hasErrors) {
            return self::INVALID;
        }

        if ($hasPending) {
            return self::PENDING;
        }

        if ($hasWarnings) {
            return self::WARNING;
        }

        return self::VALID;
    }

    public static function merge(self ...$states): self
    {
        if (empty($states)) {
            return self::UNKNOWN;
        }

        // Sort by priority (highest first)
        usort($states, fn($a, $b) => $b->getPriority() <=> $a->getPriority());

        return $states[0];
    }

    public function canTransitionTo(self $newState): bool
    {
        return match ($this) {
            self::PENDING => true, // Can transition to any state
            self::UNKNOWN => $newState !== self::PENDING,
            self::PARTIAL => in_array($newState, [self::VALID, self::INVALID, self::WARNING, self::ERROR], true),
            self::WARNING => in_array($newState, [self::VALID, self::INVALID, self::ERROR], true),
            self::VALID, self::INVALID, self::ERROR => false, // Final states
        };
    }

    public function toArray(): array
    {
        return [
            'value' => $this->value,
            'name' => $this->name,
            'description' => $this->getDescription(),
            'icon' => $this->getIcon(),
            'color' => $this->getColor(),
            'priority' => $this->getPriority(),
            'is_valid' => $this->isValid(),
            'is_invalid' => $this->isInvalid(),
            'is_pending' => $this->isPending(),
            'has_warnings' => $this->hasWarnings(),
            'has_errors' => $this->hasErrors(),
            'is_complete' => $this->isComplete(),
            'should_log' => $this->shouldLog(),
            'log_level' => $this->getLogLevel()->value,
        ];
    }
}
