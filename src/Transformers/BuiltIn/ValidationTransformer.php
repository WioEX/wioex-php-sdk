<?php

declare(strict_types=1);

namespace Wioex\SDK\Transformers\BuiltIn;

use Wioex\SDK\Transformers\AbstractTransformer;
use Wioex\SDK\Exceptions\TransformationException;

class ValidationTransformer extends AbstractTransformer
{
    protected array $defaultOptions = [
        'rules' => [],
        'required_fields' => [],
        'optional_fields' => [],
        'throw_on_validation_error' => true,
        'sanitize_data' => false,
        'default_values' => [],
        'custom_validators' => [],
    ];

    public function transform(array $data, array $context = []): array
    {
        $result = $data;

        // Apply default values
        $result = $this->applyDefaults($result);

        // Sanitize data if enabled
        if ($this->getOption('sanitize_data', false)) {
            $result = $this->sanitizeData($result);
        }

        // Validate data
        $validationResult = $this->validateData($result);

        if (!$validationResult['valid'] && $this->getOption('throw_on_validation_error', true)) {
            throw TransformationException::validationFailed(
                $this->getName(),
                implode(', ', $validationResult['errors'])
            );
        }

        return $result;
    }

    public function getName(): string
    {
        return 'validation';
    }

    public function getDescription(): string
    {
        return 'Validates data against specified rules and optionally sanitizes values';
    }

    private function applyDefaults(array $data): array
    {
        $defaults = $this->getOption('default_values', []);

        foreach ($defaults as $field => $defaultValue) {
            if (!isset($data[$field])) {
                $data[$field] = $defaultValue;
            }
        }

        return $data;
    }

    private function sanitizeData(array $data): array
    {
        return $this->applyToNestedArray($data, function ($value) {
            if (is_string($value)) {
                return trim(htmlspecialchars($value, ENT_QUOTES, 'UTF-8'));
            }
            return $value;
        });
    }

    private function validateData(array $data): array
    {
        $errors = [];
        $rules = $this->getOption('rules', []);
        $requiredFields = $this->getOption('required_fields', []);
        $customValidators = $this->getOption('custom_validators', []);

        // Check required fields
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || $data[$field] === null || $data[$field] === '') {
                $errors[] = "Required field '{$field}' is missing or empty";
            }
        }

        // Apply validation rules
        foreach ($rules as $field => $fieldRules) {
            if (!isset($data[$field])) {
                continue;
            }

            $value = $data[$field];
            $fieldErrors = $this->validateField($field, $value, $fieldRules);
            $errors = array_merge($errors, $fieldErrors);
        }

        // Apply custom validators
        foreach ($customValidators as $validator) {
            if (is_callable($validator)) {
                $result = $validator($data);
                if ($result !== true) {
                    $errors[] = is_string($result) ? $result : 'Custom validation failed';
                }
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    private function validateField(string $field, $value, array $rules): array
    {
        $errors = [];

        foreach ($rules as $rule => $parameter) {
            $error = $this->validateRule($field, $value, $rule, $parameter);
            if ($error !== null) {
                $errors[] = $error;
            }
        }

        return $errors;
    }

    private function validateRule(string $field, $value, string $rule, $parameter): ?string
    {
        switch ($rule) {
            case 'type':
                if (!$this->validateType($value, $parameter)) {
                    return "Field '{$field}' must be of type {$parameter}";
                }
                break;

            case 'min_length':
                if (is_string($value) && strlen($value) < $parameter) {
                    return "Field '{$field}' must be at least {$parameter} characters long";
                }
                break;

            case 'max_length':
                if (is_string($value) && strlen($value) > $parameter) {
                    return "Field '{$field}' must not exceed {$parameter} characters";
                }
                break;

            case 'min_value':
                if (is_numeric($value) && $value < $parameter) {
                    return "Field '{$field}' must be at least {$parameter}";
                }
                break;

            case 'max_value':
                if (is_numeric($value) && $value > $parameter) {
                    return "Field '{$field}' must not exceed {$parameter}";
                }
                break;

            case 'pattern':
                if (is_string($value) && !preg_match($parameter, $value)) {
                    return "Field '{$field}' does not match required pattern";
                }
                break;

            case 'in':
                if (!in_array($value, $parameter, true)) {
                    $allowed = implode(', ', $parameter);
                    return "Field '{$field}' must be one of: {$allowed}";
                }
                break;

            case 'not_in':
                if (in_array($value, $parameter, true)) {
                    $forbidden = implode(', ', $parameter);
                    return "Field '{$field}' must not be one of: {$forbidden}";
                }
                break;

            case 'email':
                if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    return "Field '{$field}' must be a valid email address";
                }
                break;

            case 'url':
                if (!filter_var($value, FILTER_VALIDATE_URL)) {
                    return "Field '{$field}' must be a valid URL";
                }
                break;

            case 'date':
                if (!$this->validateDate($value, $parameter)) {
                    return "Field '{$field}' must be a valid date in format {$parameter}";
                }
                break;
        }

        return null;
    }

    private function validateType($value, string $expectedType): bool
    {
        return match ($expectedType) {
            'string' => is_string($value),
            'integer', 'int' => is_int($value),
            'float', 'double' => is_float($value),
            'boolean', 'bool' => is_bool($value),
            'array' => is_array($value),
            'numeric' => is_numeric($value),
            'null' => $value === null,
            default => false,
        };
    }

    private function validateDate($value, string $format): bool
    {
        if (!is_string($value)) {
            return false;
        }

        $date = \DateTime::createFromFormat($format, $value);
        return $date && $date->format($format) === $value;
    }

    public function validate(array $data, array $context = []): bool
    {
        return true; // This transformer validates the data itself
    }

    public function supports(array $data, array $context = []): bool
    {
        return is_array($data) ? count($data) > 0 : $data !== null && $data !== '';
    }

    public function addRule(string $field, string $rule, mixed $parameter = null): self
    {
        $rules = $this->getOption('rules', []);
        if (!isset($rules[$field])) {
            $rules[$field] = [];
        }
        $rules[$field][$rule] = $parameter;
        $this->setOption('rules', $rules);

        return $this;
    }

    public function addRequired(string $field): self
    {
        $required = $this->getOption('required_fields', []);
        if (!in_array($field, $required, true)) {
            $required[] = $field;
            $this->setOption('required_fields', $required);
        }

        return $this;
    }

    public function addDefault(string $field, mixed $value): self
    {
        $defaults = $this->getOption('default_values', []);
        $defaults[$field] = $value;
        $this->setOption('default_values', $defaults);

        return $this;
    }

    public function addCustomValidator(callable $validator): self
    {
        $validators = $this->getOption('custom_validators', []);
        $validators[] = $validator;
        $this->setOption('custom_validators', $validators);

        return $this;
    }
}
