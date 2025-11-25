<?php

declare(strict_types=1);

namespace Wioex\SDK\Transformers\BuiltIn;

use Wioex\SDK\Transformers\AbstractTransformer;

class NormalizationTransformer extends AbstractTransformer
{
    protected array $defaultOptions = [
        'normalize_keys' => true,
        'key_case' => 'snake_case', // snake_case, camelCase, PascalCase, kebab-case
        'normalize_values' => true,
        'trim_strings' => true,
        'convert_numeric_strings' => true,
        'normalize_booleans' => true,
        'remove_empty_arrays' => false,
        'remove_null_values' => false,
        'flatten_single_arrays' => false,
        'date_format' => 'Y-m-d H:i:s',
        'timezone' => 'UTC',
    ];

    public function transform(array $data, array $context = []): array
    {
        $result = $data;

        if ($this->getOption('normalize_keys', true)) {
            $result = $this->normalizeKeys($result);
        }

        if ($this->getOption('normalize_values', true)) {
            $result = $this->normalizeValues($result);
        }

        if ($this->getOption('remove_empty_arrays', false)) {
            $result = $this->removeEmptyArrays($result);
        }

        if ($this->getOption('remove_null_values', false)) {
            $result = $this->removeNullValues($result);
        }

        if ($this->getOption('flatten_single_arrays', false)) {
            $result = $this->flattenSingleArrays($result);
        }

        return $result;
    }

    public function getName(): string
    {
        return 'normalization';
    }

    public function getDescription(): string
    {
        return 'Normalizes data structure, keys, and values according to specified conventions';
    }

    private function normalizeKeys(array $data): array
    {
        $result = [];
        $keyCase = $this->getOption('key_case', 'snake_case');

        foreach ($data as $key => $value) {
            $normalizedKey = $this->normalizeKeyCase($key, $keyCase);

            if (is_array($value)) {
                $result[$normalizedKey] = $this->normalizeKeys($value);
            } else {
                $result[$normalizedKey] = $value;
            }
        }

        return $result;
    }

    private function normalizeKeyCase(string $key, string $case): string
    {
        return match ($case) {
            'snake_case' => $this->toSnakeCase($key),
            'camelCase' => $this->toCamelCase($key),
            'PascalCase' => $this->toPascalCase($key),
            'kebab-case' => $this->toKebabCase($key),
            default => $key,
        };
    }

    private function toSnakeCase(string $string): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $string));
    }

    private function toCamelCase(string $string): string
    {
        $string = str_replace(['_', '-'], ' ', $string);
        $string = ucwords($string);
        $string = str_replace(' ', '', $string);
        return lcfirst($string);
    }

    private function toPascalCase(string $string): string
    {
        return ucfirst($this->toCamelCase($string));
    }

    private function toKebabCase(string $string): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $string));
    }

    private function normalizeValues(array $data): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $result[$key] = $this->normalizeValues($value);
            } else {
                $result[$key] = $this->normalizeValue($value);
            }
        }

        return $result;
    }

    private function normalizeValue($value)
    {
        // Trim strings
        if (is_string($value) && $this->getOption('trim_strings', true)) {
            $value = trim($value);
        }

        // Convert numeric strings
        if (is_string($value) && $this->getOption('convert_numeric_strings', true)) {
            if (is_numeric($value)) {
                $value = strpos($value, '.') !== false ? (float) $value : (int) $value;
            }
        }

        // Normalize booleans
        if ($this->getOption('normalize_booleans', true)) {
            $value = $this->normalizeBooleanValue($value);
        }

        // Normalize dates
        $value = $this->normalizeDateValue($value);

        return $value;
    }

    private function normalizeBooleanValue($value)
    {
        if (is_string($value)) {
            $lower = strtolower($value);
            if (in_array($lower, ['true', '1', 'yes', 'on'], true)) {
                return true;
            }
            if (in_array($lower, ['false', '0', 'no', 'off'], true)) {
                return false;
            }
        }

        return $value;
    }

    private function normalizeDateValue($value)
    {
        if (!is_string($value)) {
            return $value;
        }

        // Try to parse as date
        $timestamp = strtotime($value);
        if ($timestamp !== false) {
            $dateFormat = $this->getOption('date_format', 'Y-m-d H:i:s');
            $timezone = $this->getOption('timezone', 'UTC');

            try {
                $date = new \DateTime('@' . $timestamp);
                $date->setTimezone(new \DateTimeZone($timezone));
                return $date->format($dateFormat);
            } catch (\Exception $e) {
                // If date formatting fails, return original value
                return $value;
            }
        }

        return $value;
    }

    private function removeEmptyArrays(array $data): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $cleaned = $this->removeEmptyArrays($value);
                if (($cleaned !== null && $cleaned !== '' && $cleaned !== [])) {
                    $result[$key] = $cleaned;
                }
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    private function removeNullValues(array $data): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            if ($value !== null) {
                if (is_array($value)) {
                    $result[$key] = $this->removeNullValues($value);
                } else {
                    $result[$key] = $value;
                }
            }
        }

        return $result;
    }

    private function flattenSingleArrays(array $data): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                if (count($value) === 1 && isset($value[0])) {
                    // Single-element numeric array
                    $result[$key] = $value[0];
                } else {
                    $result[$key] = $this->flattenSingleArrays($value);
                }
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    public function validate(array $data, array $context = []): bool
    {
        return ($data !== null && $data !== '' && $data !== []);
    }

    public function supports(array $data, array $context = []): bool
    {
        return ($data !== null && $data !== '' && $data !== []);
    }

    public function setKeyCase(string $case): self
    {
        $validCases = ['snake_case', 'camelCase', 'PascalCase', 'kebab-case'];
        if (in_array($case, $validCases, true)) {
            $this->setOption('key_case', $case);
        }

        return $this;
    }

    public function setDateFormat(string $format, string $timezone = 'UTC'): self
    {
        $this->setOption('date_format', $format);
        $this->setOption('timezone', $timezone);

        return $this;
    }
}
