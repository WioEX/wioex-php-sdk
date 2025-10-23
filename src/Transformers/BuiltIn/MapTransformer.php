<?php

declare(strict_types=1);

namespace Wioex\SDK\Transformers\BuiltIn;

use Wioex\SDK\Transformers\AbstractTransformer;

class MapTransformer extends AbstractTransformer
{
    protected array $defaultOptions = [
        'field_mappings' => [],
        'value_mappings' => [],
        'transformations' => [],
        'preserve_unmapped' => true,
        'strict_mapping' => false,
    ];

    public function transform(array $data, array $context = []): array
    {
        $result = $this->getOption('preserve_unmapped', true) ? $data : [];

        // Apply field mappings (rename fields)
        $result = $this->applyFieldMappings($result, $data);

        // Apply value mappings (transform values)
        $result = $this->applyValueMappings($result);

        // Apply custom transformations
        $result = $this->applyTransformations($result);

        return $result;
    }

    public function getName(): string
    {
        return 'map';
    }

    public function getDescription(): string
    {
        return 'Maps field names and values according to specified transformation rules';
    }

    private function applyFieldMappings(array $result, array $data): array
    {
        $fieldMappings = $this->getOption('field_mappings', []);

        foreach ($fieldMappings as $oldField => $newField) {
            if (isset($data[$oldField])) {
                $result[$newField] = $data[$oldField];

                // Remove old field if not preserving unmapped
                if (!$this->getOption('preserve_unmapped', true)) {
                    unset($result[$oldField]);
                }
            }
        }

        return $result;
    }

    private function applyValueMappings(array $data): array
    {
        $valueMappings = $this->getOption('value_mappings', []);

        foreach ($valueMappings as $field => $mapping) {
            if (isset($data[$field])) {
                $value = $data[$field];

                if (is_array($mapping)) {
                    // Direct value mapping
                    $data[$field] = $mapping[$value] ?? $value;
                } elseif (is_callable($mapping)) {
                    // Callable transformation
                    $data[$field] = $mapping($value, $field, $data);
                }
            }
        }

        return $data;
    }

    private function applyTransformations(array $data): array
    {
        $transformations = $this->getOption('transformations', []);

        foreach ($transformations as $transformation) {
            if (is_callable($transformation)) {
                $data = $transformation($data);
            }
        }

        return $data;
    }

    public function validate(array $data, array $context = []): bool
    {
        $strictMapping = $this->getOption('strict_mapping', false);

        if ($strictMapping) {
            $fieldMappings = $this->getOption('field_mappings', []);

            // Check if all mapped fields exist in data
            foreach (array_keys($fieldMappings) as $field) {
                if (!isset($data[$field])) {
                    return false;
                }
            }
        }

        return true;
    }

    public function supports(array $data, array $context = []): bool
    {
        return !empty($data);
    }

    public function addFieldMapping(string $oldField, string $newField): self
    {
        $mappings = $this->getOption('field_mappings', []);
        $mappings[$oldField] = $newField;
        $this->setOption('field_mappings', $mappings);

        return $this;
    }

    public function addValueMapping(string $field, $mapping): self
    {
        $mappings = $this->getOption('value_mappings', []);
        $mappings[$field] = $mapping;
        $this->setOption('value_mappings', $mappings);

        return $this;
    }

    public function addTransformation(callable $transformation): self
    {
        $transformations = $this->getOption('transformations', []);
        $transformations[] = $transformation;
        $this->setOption('transformations', $transformations);

        return $this;
    }
}
