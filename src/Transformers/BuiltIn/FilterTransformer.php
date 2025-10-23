<?php

declare(strict_types=1);

namespace Wioex\SDK\Transformers\BuiltIn;

use Wioex\SDK\Transformers\AbstractTransformer;

class FilterTransformer extends AbstractTransformer
{
    protected array $defaultOptions = [
        'fields' => [],
        'exclude_fields' => [],
        'include_empty' => false,
        'include_null' => false,
        'filters' => [],
        'mode' => 'whitelist', // whitelist, blacklist, custom
    ];

    public function transform(array $data, array $context = []): array
    {
        $mode = $this->getOption('mode', 'whitelist');

        return match ($mode) {
            'whitelist' => $this->applyWhitelist($data),
            'blacklist' => $this->applyBlacklist($data),
            'custom' => $this->applyCustomFilters($data),
            default => $data,
        };
    }

    public function getName(): string
    {
        return 'filter';
    }

    public function getDescription(): string
    {
        return 'Filters data based on field inclusion/exclusion rules and custom filter criteria';
    }

    private function applyWhitelist(array $data): array
    {
        $fields = $this->getOption('fields', []);

        if (empty($fields)) {
            return $data;
        }

        return $this->filterFields($data, $fields, true);
    }

    private function applyBlacklist(array $data): array
    {
        $excludeFields = $this->getOption('exclude_fields', []);

        if (empty($excludeFields)) {
            return $data;
        }

        return $this->filterFields($data, $excludeFields, false);
    }

    private function applyCustomFilters(array $data): array
    {
        $filters = $this->getOption('filters', []);

        foreach ($filters as $filter) {
            if (is_callable($filter)) {
                $data = $filter($data);
            }
        }

        return $data;
    }

    private function filterFields(array $data, array $fields, bool $include): array
    {
        $result = [];
        $includeEmpty = $this->getOption('include_empty', false);
        $includeNull = $this->getOption('include_null', false);

        foreach ($data as $key => $value) {
            $shouldInclude = $include ? in_array($key, $fields, true) : !in_array($key, $fields, true);

            if ($shouldInclude) {
                // Apply empty/null filters
                if (!$includeEmpty && $value === '') {
                    continue;
                }

                if (!$includeNull && $value === null) {
                    continue;
                }

                // Recursively filter nested arrays
                if (is_array($value)) {
                    $result[$key] = $this->filterFields($value, $fields, $include);
                } else {
                    $result[$key] = $value;
                }
            }
        }

        return $result;
    }

    public function validate(array $data, array $context = []): bool
    {
        $mode = $this->getOption('mode', 'whitelist');

        switch ($mode) {
            case 'whitelist':
                return is_array($this->getOption('fields', []));

            case 'blacklist':
                return is_array($this->getOption('exclude_fields', []));

            case 'custom':
                $filters = $this->getOption('filters', []);
                return is_array($filters) && !empty($filters);

            default:
                return false;
        }
    }

    public function supports(array $data, array $context = []): bool
    {
        return !empty($data);
    }
}
