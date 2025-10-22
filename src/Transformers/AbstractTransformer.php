<?php

declare(strict_types=1);

namespace Wioex\SDK\Transformers;

abstract class AbstractTransformer implements TransformerInterface
{
    protected array $options = [];
    protected array $defaultOptions = [];

    public function __construct(array $options = [])
    {
        $this->options = array_merge($this->defaultOptions, $options);
    }

    public function supports(array $data, array $context = []): bool
    {
        return true;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function setOptions(array $options): TransformerInterface
    {
        $this->options = array_merge($this->options, $options);
        return $this;
    }

    public function getOption(string $key, $default = null)
    {
        return $this->options[$key] ?? $default;
    }

    public function setOption(string $key, $value): self
    {
        $this->options[$key] = $value;
        return $this;
    }

    public function validate(array $data, array $context = []): bool
    {
        return true;
    }

    protected function validateRequired(array $data, array $requiredFields): bool
    {
        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                return false;
            }
        }
        return true;
    }

    protected function applyToNestedArray(array $data, callable $callback): array
    {
        $result = [];
        
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $result[$key] = $this->applyToNestedArray($value, $callback);
            } else {
                $result[$key] = $callback($value, $key, $data);
            }
        }
        
        return $result;
    }

    protected function filterByPath(array $data, string $path)
    {
        $keys = explode('.', $path);
        $current = $data;
        
        foreach ($keys as $key) {
            if (!is_array($current) || !isset($current[$key])) {
                return null;
            }
            $current = $current[$key];
        }
        
        return $current;
    }

    protected function setByPath(array &$data, string $path, $value): void
    {
        $keys = explode('.', $path);
        $current = &$data;
        
        foreach ($keys as $i => $key) {
            if ($i === count($keys) - 1) {
                $current[$key] = $value;
            } else {
                if (!isset($current[$key]) || !is_array($current[$key])) {
                    $current[$key] = [];
                }
                $current = &$current[$key];
            }
        }
    }

    protected function removeByPath(array &$data, string $path): void
    {
        $keys = explode('.', $path);
        $current = &$data;
        
        for ($i = 0; $i < count($keys) - 1; $i++) {
            if (!is_array($current) || !isset($current[$keys[$i]])) {
                return;
            }
            $current = &$current[$keys[$i]];
        }
        
        if (is_array($current)) {
            unset($current[end($keys)]);
        }
    }
}