<?php

declare(strict_types=1);

namespace Wioex\SDK\Validation;

use Wioex\SDK\Enums\ValidationResult;

class ValidationReport
{
    private ValidationResult $result;
    private array $errors;
    private array $data;
    private array $metadata;
    private array $warnings = [];
    private array $suggestions = [];

    public function __construct(
        ValidationResult $result,
        array $errors = [],
        array $data = [],
        array $metadata = []
    ) {
        $this->result = $result;
        $this->errors = $errors;
        $this->data = $data;
        $this->metadata = $metadata;

        $this->analyzeErrors();
    }

    public function isValid(): bool
    {
        return $this->result->isValid();
    }

    public function isInvalid(): bool
    {
        return $this->result->isInvalid();
    }

    public function isPartial(): bool
    {
        return $this->result->isPartial();
    }

    public function hasErrors(): bool
    {
        return count($this->errors) > 0;
    }

    public function hasWarnings(): bool
    {
        return count($this->warnings) > 0;
    }

    public function getResult(): ValidationResult
    {
        return $this->result;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getWarnings(): array
    {
        return $this->warnings;
    }

    public function getSuggestions(): array
    {
        return $this->suggestions;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function getErrorsCount(): int
    {
        return count($this->errors);
    }

    public function getWarningsCount(): int
    {
        return count($this->warnings);
    }

    public function getErrorsByField(): array
    {
        $errorsByField = [];

        foreach ($this->errors as $error) {
            $field = $error['field'] ?? 'unknown';
            if (!isset($errorsByField[$field])) {
                $errorsByField[$field] = [];
            }
            $errorsByField[$field][] = $error;
        }

        return $errorsByField;
    }

    public function getErrorsByType(): array
    {
        $errorsByType = [];

        foreach ($this->errors as $error) {
            $type = $error['type'] ?? 'unknown';
            if (!isset($errorsByType[$type])) {
                $errorsByType[$type] = [];
            }
            $errorsByType[$type][] = $error;
        }

        return $errorsByType;
    }

    public function getFirstError(): ?array
    {
        return $this->errors[0] ?? null;
    }

    public function getLastError(): ?array
    {
        return end($this->errors) !== false ? end($this->errors) : null;
    }

    public function getErrorsForField(string $field): array
    {
        return array_filter(
            $this->errors,
            fn($error) => ($error['field'] ?? '') === $field
        );
    }

    public function hasErrorForField(string $field): bool
    {
        return count($this->getErrorsForField($field)) > 0;
    }

    public function getFormattedErrors(): array
    {
        return array_map(function ($error) {
            return sprintf(
                '[%s] %s (Type: %s)',
                $error['field'] ?? 'unknown',
                $error['message'] ?? 'Unknown error',
                $error['type'] ?? 'unknown'
            );
        }, $this->errors);
    }

    public function getFormattedWarnings(): array
    {
        return array_map(function ($warning) {
            return sprintf(
                '[%s] %s',
                $warning['field'] ?? 'general',
                $warning['message'] ?? 'Unknown warning'
            );
        }, $this->warnings);
    }

    public function getSummary(): array
    {
        return [
            'result' => $this->result->value,
            'is_valid' => $this->isValid(),
            'errors_count' => $this->getErrorsCount(),
            'warnings_count' => $this->getWarningsCount(),
            'data_fields_count' => count($this->data),
            'duration_ms' => $this->metadata['validation_duration_ms'] ?? 0,
            'schema_name' => $this->metadata['schema_name'] ?? 'default',
        ];
    }

    public function getDetailedReport(): array
    {
        return [
            'summary' => $this->getSummary(),
            'result_details' => $this->result->toArray(),
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'suggestions' => $this->suggestions,
            'errors_by_field' => $this->getErrorsByField(),
            'errors_by_type' => $this->getErrorsByType(),
            'metadata' => $this->metadata,
        ];
    }

    public function toJson(): string
    {
        $result = json_encode($this->toArray(), JSON_PRETTY_PRINT);
        if ($result === false) {
            throw new \RuntimeException('Failed to encode validation report to JSON: ' . json_last_error_msg());
        }
        return $result;
    }

    public function toArray(): array
    {
        return [
            'result' => $this->result->value,
            'is_valid' => $this->isValid(),
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'suggestions' => $this->suggestions,
            'summary' => $this->getSummary(),
            'metadata' => $this->metadata,
        ];
    }

    public function throwIfInvalid(string $message = 'Validation failed'): void
    {
        if ($this->isInvalid()) {
            $errorMessages = array_column($this->errors, 'message');
            $fullMessage = $message . ': ' . implode(', ', $errorMessages);
            throw new \InvalidArgumentException($fullMessage);
        }
    }

    public function merge(ValidationReport $other): self
    {
        $mergedErrors = array_merge($this->errors, $other->getErrors());
        $mergedWarnings = array_merge($this->warnings, $other->getWarnings());
        $mergedSuggestions = array_merge($this->suggestions, $other->getSuggestions());

        $results = [$this->result, $other->getResult()];
        $combinedResult = ValidationResult::combine($results);

        $mergedMetadata = array_merge($this->metadata, $other->getMetadata());

        $merged = new self($combinedResult, $mergedErrors, $this->data, $mergedMetadata);
        $merged->warnings = $mergedWarnings;
        $merged->suggestions = $mergedSuggestions;

        return $merged;
    }

    public function filterErrorsByType(string $type): array
    {
        return array_filter(
            $this->errors,
            fn($error) => ($error['type'] ?? '') === $type
        );
    }

    public function filterErrorsBySeverity(string $severity): array
    {
        return array_filter($this->errors, function ($error) use ($severity) {
            $result = $error['result'] ?? null;
            if ($result instanceof ValidationResult) {
                return $result->getSeverity() === $severity;
            }
            return false;
        });
    }

    public function addWarning(string $field, string $message): self
    {
        $this->warnings[] = [
            'field' => $field,
            'message' => $message,
            'type' => 'warning',
        ];

        return $this;
    }

    public function addSuggestion(string $suggestion): self
    {
        $this->suggestions[] = $suggestion;
        return $this;
    }

    private function analyzeErrors(): void
    {
        if (count($this->errors) === 0) {
            return;
        }

        // Generate suggestions based on common error patterns
        $errorTypes = array_column($this->errors, 'type');
        $errorFields = array_column($this->errors, 'field');

        // Missing required fields suggestion
        $requiredFieldErrors = array_filter($errorTypes, fn($type) => $type === 'required_fields');
        if (count($requiredFieldErrors) > 0) {
            $this->addSuggestion('Consider providing all required fields in your request data');
        }

        // Type errors suggestion
        $typeErrors = array_filter($errorTypes, fn($type) => $type === 'field_types');
        if (count($typeErrors) > 3) {
            $this->addSuggestion('Multiple type errors detected - review your data structure');
        }

        // Range validation errors
        $rangeErrors = array_filter($errorTypes, fn($type) => $type === 'range_validation');
        if (count($rangeErrors) > 0) {
            $this->addSuggestion('Ensure numeric values are within expected ranges');
        }

        // Email/URL format errors
        $formatErrors = array_filter($errorTypes, fn($type) => in_array($type, ['email_format', 'url_format'], true));
        if (count($formatErrors) > 0) {
            $this->addSuggestion('Verify that email addresses and URLs are properly formatted');
        }

        // Add warnings for performance concerns
        if (count($this->errors) > 10) {
            $this->addWarning('performance', 'Large number of validation errors may indicate data quality issues');
        }

        $validationDuration = $this->metadata['validation_duration_ms'] ?? 0;
        if ($validationDuration > 100) {
            $this->addWarning('performance', 'Validation took longer than expected - consider optimizing rules');
        }
    }

    public function __toString(): string
    {
        if ($this->isValid()) {
            return 'Validation passed successfully';
        }

        $summary = $this->getSummary();
        return sprintf(
            'Validation failed: %d error(s), %d warning(s) in %s schema',
            $summary['errors_count'],
            $summary['warnings_count'],
            $summary['schema_name']
        );
    }
}
