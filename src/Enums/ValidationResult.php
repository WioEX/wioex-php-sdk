<?php

declare(strict_types=1);

namespace Wioex\SDK\Enums;

enum ValidationResult: string
{
    case VALID = 'valid';
    case INVALID = 'invalid';
    case PARTIAL = 'partial';
    case UNKNOWN = 'unknown';
    case SKIPPED = 'skipped';
    case ERROR = 'error';

    public function isValid(): bool
    {
        return $this === self::VALID;
    }

    public function isInvalid(): bool
    {
        return $this === self::INVALID;
    }

    public function isPartial(): bool
    {
        return $this === self::PARTIAL;
    }

    public function isError(): bool
    {
        return $this === self::ERROR;
    }

    public function isSkipped(): bool
    {
        return $this === self::SKIPPED;
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::VALID => 'Validation passed successfully',
            self::INVALID => 'Validation failed with errors',
            self::PARTIAL => 'Validation partially successful with warnings',
            self::UNKNOWN => 'Validation result unknown',
            self::SKIPPED => 'Validation was skipped',
            self::ERROR => 'Validation encountered an error',
        };
    }

    public function getSeverity(): string
    {
        return match ($this) {
            self::VALID => 'success',
            self::INVALID => 'error',
            self::PARTIAL => 'warning',
            self::UNKNOWN => 'info',
            self::SKIPPED => 'info',
            self::ERROR => 'critical',
        };
    }

    public function getExitCode(): int
    {
        return match ($this) {
            self::VALID => 0,
            self::INVALID => 1,
            self::PARTIAL => 2,
            self::UNKNOWN => 3,
            self::SKIPPED => 4,
            self::ERROR => 5,
        };
    }

    public static function fromBoolean(bool $isValid): self
    {
        return $isValid ? self::VALID : self::INVALID;
    }

    public static function fromException(\Throwable $exception): self
    {
        return self::ERROR;
    }

    public static function combine(array $results): self
    {
        if (empty($results)) {
            return self::UNKNOWN;
        }

        $hasValid = false;
        $hasInvalid = false;
        $hasError = false;
        $hasSkipped = false;

        foreach ($results as $result) {
            if (!$result instanceof self) {
                continue;
            }

            switch ($result) {
                case self::VALID:
                    $hasValid = true;
                    break;
                case self::INVALID:
                    $hasInvalid = true;
                    break;
                case self::ERROR:
                    $hasError = true;
                    break;
                case self::SKIPPED:
                    $hasSkipped = true;
                    break;
            }
        }

        if ($hasError) {
            return self::ERROR;
        }

        if ($hasInvalid && $hasValid) {
            return self::PARTIAL;
        }

        if ($hasInvalid) {
            return self::INVALID;
        }

        if ($hasValid) {
            return self::VALID;
        }

        if ($hasSkipped) {
            return self::SKIPPED;
        }

        return self::UNKNOWN;
    }

    public function toArray(): array
    {
        return [
            'value' => $this->value,
            'name' => $this->name,
            'description' => $this->getDescription(),
            'severity' => $this->getSeverity(),
            'exit_code' => $this->getExitCode(),
            'is_valid' => $this->isValid(),
            'is_invalid' => $this->isInvalid(),
            'is_partial' => $this->isPartial(),
            'is_error' => $this->isError(),
            'is_skipped' => $this->isSkipped(),
        ];
    }
}
