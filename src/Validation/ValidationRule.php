<?php

declare(strict_types=1);

namespace Wioex\SDK\Validation;

use Wioex\SDK\Enums\SchemaType;
use Wioex\SDK\Enums\ValidationResult;

class ValidationRule
{
    private string $field;
    private SchemaType $type;
    private mixed $rule;
    private bool $required;
    private string $message;
    private array $options;
    private mixed $customValidator = null;

    public function __construct(
        string $field,
        SchemaType $type,
        mixed $rule,
        bool $required = false,
        string $message = '',
        array $options = []
    ) {
        $this->field = $field;
        $this->type = $type;
        $this->rule = $rule;
        $this->required = $required;
        $this->message = $message ?: $this->getDefaultMessage();
        $this->options = $options;
    }

    public static function required(string $field, string $message = ''): self
    {
        return new self($field, SchemaType::REQUIRED_FIELDS, true, true, $message);
    }

    public static function type(string $field, string $expectedType, bool $required = false, string $message = ''): self
    {
        return new self($field, SchemaType::FIELD_TYPES, $expectedType, $required, $message);
    }

    public static function range(string $field, float $min, float $max, bool $required = false, string $message = ''): self
    {
        return new self($field, SchemaType::RANGE_VALIDATION, ['min' => $min, 'max' => $max], $required, $message);
    }

    public static function enum(string $field, array $allowedValues, bool $required = false, string $message = ''): self
    {
        return new self($field, SchemaType::ENUM_VALIDATION, $allowedValues, $required, $message);
    }

    public static function regex(string $field, string $pattern, bool $required = false, string $message = ''): self
    {
        return new self($field, SchemaType::REGEX_PATTERN, $pattern, $required, $message);
    }

    public static function email(string $field, bool $required = false, string $message = ''): self
    {
        return new self($field, SchemaType::EMAIL_FORMAT, true, $required, $message);
    }

    public static function url(string $field, bool $required = false, string $message = ''): self
    {
        return new self($field, SchemaType::URL_FORMAT, true, $required, $message);
    }

    public static function date(string $field, string $format = 'Y-m-d', bool $required = false, string $message = ''): self
    {
        return new self($field, SchemaType::DATE_FORMAT, $format, $required, $message);
    }

    public static function numeric(string $field, bool $required = false, string $message = ''): self
    {
        return new self($field, SchemaType::NUMERIC_FORMAT, true, $required, $message);
    }

    public static function arrayStructure(string $field, array $structure, bool $required = false, string $message = ''): self
    {
        return new self($field, SchemaType::ARRAY_STRUCTURE, $structure, $required, $message);
    }

    public static function jsonSchema(string $field, array $schema, bool $required = false, string $message = ''): self
    {
        return new self($field, SchemaType::JSON_SCHEMA, $schema, $required, $message);
    }

    public static function custom(string $field, callable $validator, bool $required = false, string $message = ''): self
    {
        $rule = new self($field, SchemaType::CUSTOM_VALIDATOR, null, $required, $message);
        $rule->customValidator = $validator;
        return $rule;
    }

    public function validate(array $data): ValidationResult
    {
        try {
            // Check if field exists
            $fieldExists = $this->fieldExists($data, $this->field);

            // Handle required field validation
            if ($this->required && !$fieldExists) {
                return ValidationResult::INVALID;
            }

            // Skip validation if field doesn't exist and is not required
            if (!$fieldExists && !$this->required) {
                return ValidationResult::SKIPPED;
            }

            $value = $this->getFieldValue($data, $this->field);

            return match ($this->type) {
                SchemaType::REQUIRED_FIELDS => $this->validateRequired($value),
                SchemaType::FIELD_TYPES => $this->validateType($value),
                SchemaType::RANGE_VALIDATION => $this->validateRange($value),
                SchemaType::ENUM_VALIDATION => $this->validateEnum($value),
                SchemaType::REGEX_PATTERN => $this->validateRegex($value),
                SchemaType::EMAIL_FORMAT => $this->validateEmail($value),
                SchemaType::URL_FORMAT => $this->validateUrl($value),
                SchemaType::DATE_FORMAT => $this->validateDate($value),
                SchemaType::NUMERIC_FORMAT => $this->validateNumeric($value),
                SchemaType::ARRAY_STRUCTURE => $this->validateArrayStructure($value),
                SchemaType::JSON_SCHEMA => $this->validateJsonSchema($value),
                SchemaType::CUSTOM_VALIDATOR => $this->validateCustom($value),
            };
        } catch (\Throwable $e) {
            return ValidationResult::ERROR;
        }
    }

    public function getField(): string
    {
        return $this->field;
    }

    public function getType(): SchemaType
    {
        return $this->type;
    }

    public function getRule(): mixed
    {
        return $this->rule;
    }

    public function isRequired(): bool
    {
        return $this->required;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function setMessage(string $message): self
    {
        $this->message = $message;
        return $this;
    }

    public function setOptions(array $options): self
    {
        $this->options = array_merge($this->options, $options);
        return $this;
    }

    private function fieldExists(array $data, string $field): bool
    {
        if (strpos($field, '.') === false) {
            return array_key_exists($field, $data);
        }

        $keys = explode('.', $field);
        $current = $data;

        foreach ($keys as $key) {
            if (!is_array($current) || !array_key_exists($key, $current)) {
                return false;
            }
            $current = $current[$key];
        }

        return true;
    }

    private function getFieldValue(array $data, string $field): mixed
    {
        if (strpos($field, '.') === false) {
            return $data[$field] ?? null;
        }

        $keys = explode('.', $field);
        $current = $data;

        foreach ($keys as $key) {
            if (!is_array($current) || !array_key_exists($key, $current)) {
                return null;
            }
            $current = $current[$key];
        }

        return $current;
    }

    private function validateRequired(mixed $value): ValidationResult
    {
        return ValidationResult::fromBoolean($value !== null && $value !== '');
    }

    private function validateType(mixed $value): ValidationResult
    {
        $expectedType = (string) $this->rule;

        return ValidationResult::fromBoolean(match ($expectedType) {
            'string' => is_string($value),
            'int', 'integer' => is_int($value),
            'float', 'double' => is_float($value),
            'bool', 'boolean' => is_bool($value),
            'array' => is_array($value),
            'object' => is_object($value),
            'null' => is_null($value),
            'numeric' => is_numeric($value),
            'scalar' => is_scalar($value),
            default => false,
        });
    }

    private function validateRange(mixed $value): ValidationResult
    {
        if (!is_numeric($value)) {
            return ValidationResult::INVALID;
        }

        $numValue = (float) $value;
        $rule = (array) $this->rule;
        $min = $rule['min'] ?? PHP_FLOAT_MIN;
        $max = $rule['max'] ?? PHP_FLOAT_MAX;

        return ValidationResult::fromBoolean($numValue >= $min && $numValue <= $max);
    }

    private function validateEnum(mixed $value): ValidationResult
    {
        $allowedValues = (array) $this->rule;
        return ValidationResult::fromBoolean(in_array($value, $allowedValues, true));
    }

    private function validateRegex(mixed $value): ValidationResult
    {
        if (!is_string($value)) {
            return ValidationResult::INVALID;
        }

        $pattern = (string) $this->rule;
        return ValidationResult::fromBoolean(preg_match($pattern, $value) === 1);
    }

    private function validateEmail(mixed $value): ValidationResult
    {
        if (!is_string($value)) {
            return ValidationResult::INVALID;
        }

        return ValidationResult::fromBoolean(filter_var($value, FILTER_VALIDATE_EMAIL) !== false);
    }

    private function validateUrl(mixed $value): ValidationResult
    {
        if (!is_string($value)) {
            return ValidationResult::INVALID;
        }

        return ValidationResult::fromBoolean(filter_var($value, FILTER_VALIDATE_URL) !== false);
    }

    private function validateDate(mixed $value): ValidationResult
    {
        if (!is_string($value)) {
            return ValidationResult::INVALID;
        }

        $format = (string) $this->rule;
        $date = \DateTime::createFromFormat($format, $value);

        return ValidationResult::fromBoolean(
            $date !== false && $date->format($format) === $value
        );
    }

    private function validateNumeric(mixed $value): ValidationResult
    {
        return ValidationResult::fromBoolean(is_numeric($value));
    }

    private function validateArrayStructure(mixed $value): ValidationResult
    {
        if (!is_array($value)) {
            return ValidationResult::INVALID;
        }

        $structure = (array) $this->rule;

        foreach ($structure as $key => $expectedType) {
            if (!array_key_exists($key, $value)) {
                return ValidationResult::INVALID;
            }

            if (!$this->validateFieldType($value[$key], $expectedType)) {
                return ValidationResult::INVALID;
            }
        }

        return ValidationResult::VALID;
    }

    private function validateJsonSchema(mixed $value): ValidationResult
    {
        // This would require a JSON Schema library like justinrainbow/json-schema
        // For now, return SKIPPED to indicate this validation is not implemented
        return ValidationResult::SKIPPED;
    }

    private function validateCustom(mixed $value): ValidationResult
    {
        if ($this->customValidator === null) {
            return ValidationResult::ERROR;
        }

        try {
            $result = ($this->customValidator)($value, $this->field, $this->options);
            return is_bool($result) ? ValidationResult::fromBoolean($result) : $result;
        } catch (\Throwable $e) {
            return ValidationResult::ERROR;
        }
    }

    private function validateFieldType(mixed $value, string $expectedType): bool
    {
        return match ($expectedType) {
            'string' => is_string($value),
            'int', 'integer' => is_int($value),
            'float', 'double' => is_float($value),
            'bool', 'boolean' => is_bool($value),
            'array' => is_array($value),
            'object' => is_object($value),
            'null' => is_null($value),
            'numeric' => is_numeric($value),
            'scalar' => is_scalar($value),
            default => false,
        };
    }

    private function getDefaultMessage(): string
    {
        return match ($this->type) {
            SchemaType::REQUIRED_FIELDS => "Field '{$this->field}' is required",
            SchemaType::FIELD_TYPES => "Field '{$this->field}' must be of type {$this->rule}",
            SchemaType::RANGE_VALIDATION => "Field '{$this->field}' must be within specified range",
            SchemaType::ENUM_VALIDATION => "Field '{$this->field}' must be one of the allowed values",
            SchemaType::REGEX_PATTERN => "Field '{$this->field}' does not match required pattern",
            SchemaType::EMAIL_FORMAT => "Field '{$this->field}' must be a valid email address",
            SchemaType::URL_FORMAT => "Field '{$this->field}' must be a valid URL",
            SchemaType::DATE_FORMAT => "Field '{$this->field}' must be a valid date in format {$this->rule}",
            SchemaType::NUMERIC_FORMAT => "Field '{$this->field}' must be numeric",
            SchemaType::ARRAY_STRUCTURE => "Field '{$this->field}' must have required array structure",
            SchemaType::JSON_SCHEMA => "Field '{$this->field}' does not match JSON schema",
            SchemaType::CUSTOM_VALIDATOR => "Field '{$this->field}' failed custom validation",
        };
    }

    public function toArray(): array
    {
        return [
            'field' => $this->field,
            'type' => $this->type->toArray(),
            'rule' => $this->rule,
            'required' => $this->required,
            'message' => $this->message,
            'options' => $this->options,
        ];
    }
}
