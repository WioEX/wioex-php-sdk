<?php

declare(strict_types=1);

namespace Wioex\SDK\Enums;

enum SchemaType: string
{
    case JSON_SCHEMA = 'json_schema';
    case ARRAY_STRUCTURE = 'array_structure';
    case FIELD_TYPES = 'field_types';
    case REQUIRED_FIELDS = 'required_fields';
    case REGEX_PATTERN = 'regex_pattern';
    case CUSTOM_VALIDATOR = 'custom_validator';
    case RANGE_VALIDATION = 'range_validation';
    case ENUM_VALIDATION = 'enum_validation';
    case DATE_FORMAT = 'date_format';
    case EMAIL_FORMAT = 'email_format';
    case URL_FORMAT = 'url_format';
    case NUMERIC_FORMAT = 'numeric_format';

    public function getDescription(): string
    {
        return match ($this) {
            self::JSON_SCHEMA => 'JSON Schema validation (RFC 7159)',
            self::ARRAY_STRUCTURE => 'Array structure and key validation',
            self::FIELD_TYPES => 'Field type validation (string, int, array, etc.)',
            self::REQUIRED_FIELDS => 'Required field presence validation',
            self::REGEX_PATTERN => 'Regular expression pattern matching',
            self::CUSTOM_VALIDATOR => 'Custom validation function',
            self::RANGE_VALIDATION => 'Numeric range validation (min/max)',
            self::ENUM_VALIDATION => 'Enumerated value validation',
            self::DATE_FORMAT => 'Date format validation',
            self::EMAIL_FORMAT => 'Email format validation',
            self::URL_FORMAT => 'URL format validation',
            self::NUMERIC_FORMAT => 'Numeric format validation',
        };
    }

    public function getComplexity(): string
    {
        return match ($this) {
            self::JSON_SCHEMA => 'high',
            self::ARRAY_STRUCTURE => 'medium',
            self::FIELD_TYPES => 'medium',
            self::REQUIRED_FIELDS => 'low',
            self::REGEX_PATTERN => 'medium',
            self::CUSTOM_VALIDATOR => 'variable',
            self::RANGE_VALIDATION => 'low',
            self::ENUM_VALIDATION => 'low',
            self::DATE_FORMAT => 'medium',
            self::EMAIL_FORMAT => 'low',
            self::URL_FORMAT => 'low',
            self::NUMERIC_FORMAT => 'low',
        };
    }

    public function getPerformanceImpact(): string
    {
        return match ($this) {
            self::JSON_SCHEMA => 'high',
            self::ARRAY_STRUCTURE => 'medium',
            self::FIELD_TYPES => 'low',
            self::REQUIRED_FIELDS => 'minimal',
            self::REGEX_PATTERN => 'medium',
            self::CUSTOM_VALIDATOR => 'variable',
            self::RANGE_VALIDATION => 'minimal',
            self::ENUM_VALIDATION => 'minimal',
            self::DATE_FORMAT => 'low',
            self::EMAIL_FORMAT => 'low',
            self::URL_FORMAT => 'low',
            self::NUMERIC_FORMAT => 'minimal',
        };
    }

    public function requiresExternalLibrary(): bool
    {
        return match ($this) {
            self::JSON_SCHEMA => true,
            default => false,
        };
    }

    public function getValidationPriority(): int
    {
        return match ($this) {
            self::REQUIRED_FIELDS => 1,
            self::FIELD_TYPES => 2,
            self::ARRAY_STRUCTURE => 3,
            self::RANGE_VALIDATION => 4,
            self::ENUM_VALIDATION => 4,
            self::REGEX_PATTERN => 5,
            self::DATE_FORMAT => 5,
            self::EMAIL_FORMAT => 5,
            self::URL_FORMAT => 5,
            self::NUMERIC_FORMAT => 5,
            self::JSON_SCHEMA => 6,
            self::CUSTOM_VALIDATOR => 7,
        };
    }

    public function isFormatValidator(): bool
    {
        return match ($this) {
            self::DATE_FORMAT, self::EMAIL_FORMAT, self::URL_FORMAT, self::NUMERIC_FORMAT, self::REGEX_PATTERN => true,
            default => false,
        };
    }

    public function isStructuralValidator(): bool
    {
        return match ($this) {
            self::JSON_SCHEMA, self::ARRAY_STRUCTURE, self::REQUIRED_FIELDS => true,
            default => false,
        };
    }

    public function isTypeValidator(): bool
    {
        return match ($this) {
            self::FIELD_TYPES, self::RANGE_VALIDATION, self::ENUM_VALIDATION => true,
            default => false,
        };
    }

    public static function getByComplexity(string $complexity): array
    {
        return array_filter(
            self::cases(),
            fn(self $type) => $type->getComplexity() === $complexity
        );
    }

    public static function getByPerformanceImpact(string $impact): array
    {
        return array_filter(
            self::cases(),
            fn(self $type) => $type->getPerformanceImpact() === $impact
        );
    }

    public static function getFormatValidators(): array
    {
        return array_filter(
            self::cases(),
            fn(self $type) => $type->isFormatValidator()
        );
    }

    public static function getStructuralValidators(): array
    {
        return array_filter(
            self::cases(),
            fn(self $type) => $type->isStructuralValidator()
        );
    }

    public static function getTypeValidators(): array
    {
        return array_filter(
            self::cases(),
            fn(self $type) => $type->isTypeValidator()
        );
    }

    public static function fromString(string $type): self
    {
        $type = strtolower(str_replace(['-', '_', ' '], '_', $type));

        return match ($type) {
            'json_schema', 'jsonschema' => self::JSON_SCHEMA,
            'array_structure', 'arraystructure' => self::ARRAY_STRUCTURE,
            'field_types', 'fieldtypes' => self::FIELD_TYPES,
            'required_fields', 'requiredfields' => self::REQUIRED_FIELDS,
            'regex_pattern', 'regexpattern', 'regex' => self::REGEX_PATTERN,
            'custom_validator', 'customvalidator', 'custom' => self::CUSTOM_VALIDATOR,
            'range_validation', 'rangevalidation', 'range' => self::RANGE_VALIDATION,
            'enum_validation', 'enumvalidation', 'enum' => self::ENUM_VALIDATION,
            'date_format', 'dateformat', 'date' => self::DATE_FORMAT,
            'email_format', 'emailformat', 'email' => self::EMAIL_FORMAT,
            'url_format', 'urlformat', 'url' => self::URL_FORMAT,
            'numeric_format', 'numericformat', 'numeric' => self::NUMERIC_FORMAT,
            default => throw new \InvalidArgumentException("Invalid schema type: {$type}"),
        };
    }

    public function toArray(): array
    {
        return [
            'value' => $this->value,
            'name' => $this->name,
            'description' => $this->getDescription(),
            'complexity' => $this->getComplexity(),
            'performance_impact' => $this->getPerformanceImpact(),
            'validation_priority' => $this->getValidationPriority(),
            'requires_external_library' => $this->requiresExternalLibrary(),
            'is_format_validator' => $this->isFormatValidator(),
            'is_structural_validator' => $this->isStructuralValidator(),
            'is_type_validator' => $this->isTypeValidator(),
        ];
    }
}
