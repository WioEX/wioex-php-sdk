<?php

declare(strict_types=1);

namespace Wioex\SDK\Export;

use Wioex\SDK\Enums\ExportFormat;
use Wioex\SDK\Logging\Logger;

class JsonExporter extends AbstractExporter
{
    public function __construct(array $config = [], Logger $logger = null)
    {
        parent::__construct(ExportFormat::JSON, $config, $logger);
    }

    protected function doExportToString(array $data, array $options): string
    {
        $data = $this->prepareDataForJson($data, $options);

        $jsonFlags = $this->buildJsonFlags($options);

        $result = json_encode($data, $jsonFlags);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('JSON encoding failed: ' . json_last_error_msg());
        }

        return $result;
    }

    protected function validateOptionsCustom(array $options): array
    {
        // Validate JSON depth
        if (isset($options['depth']) && (!is_int($options['depth']) || $options['depth'] < 1)) {
            throw new \InvalidArgumentException('JSON depth must be a positive integer');
        }

        // Validate date format
        if (isset($options['date_format']) && !is_string($options['date_format'])) {
            throw new \InvalidArgumentException('Date format must be a string');
        }

        return $options;
    }

    private function prepareDataForJson(array $data, array $options): array
    {
        $normalizeKeys = $options['normalize_keys'] ?? false;
        $convertDates = $options['convert_dates'] ?? false;
        $dateFormat = $options['date_format'] ?? 'Y-m-d H:i:s';
        $includeMetadata = $options['include_metadata'] ?? false;
        $rootElement = $options['root_element'] ?? null;

        // Process data
        $processedData = $this->processDataRecursively($data, [
            'normalize_keys' => $normalizeKeys,
            'convert_dates' => $convertDates,
            'date_format' => $dateFormat,
        ]);

        // Wrap in root element if specified
        if ($rootElement) {
            $processedData = [$rootElement => $processedData];
        }

        // Add metadata if requested
        if ($includeMetadata) {
            $metadata = [
                'exported_at' => date('c'),
                'row_count' => count($data),
                'format' => 'json',
                'version' => '1.0',
            ];

            if ($rootElement) {
                $processedData['metadata'] = $metadata;
            } else {
                $processedData = [
                    'data' => $processedData,
                    'metadata' => $metadata,
                ];
            }
        }

        return $processedData;
    }

    private function processDataRecursively(array $data, array $options): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            // Normalize key if requested
            if ($options['normalize_keys']) {
                $key = $this->normalizeKey($key);
            }

            if (is_array($value)) {
                $result[$key] = $this->processDataRecursively($value, $options);
            } elseif ($options['convert_dates'] && $this->isDateString($value)) {
                $result[$key] = $this->convertDateString($value, $options['date_format']);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    private function normalizeKey(string $key): string
    {
        // Convert to camelCase
        $key = preg_replace('/[^a-zA-Z0-9]+/', '_', $key);
        $key = lcfirst(str_replace('_', '', ucwords($key, '_')));

        return $key;
    }

    private function isDateString(mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }

        // Check common date patterns
        $datePatterns = [
            '/^\d{4}-\d{2}-\d{2}$/',                    // YYYY-MM-DD
            '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', // YYYY-MM-DD HH:MM:SS
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/',  // ISO 8601
        ];

        foreach ($datePatterns as $pattern) {
            if (preg_match($pattern, $value)) {
                return strtotime($value) !== false;
            }
        }

        return false;
    }

    private function convertDateString(string $dateString, string $format): string
    {
        $timestamp = strtotime($dateString);
        return $timestamp ? date($format, $timestamp) : $dateString;
    }

    private function buildJsonFlags(array $options): int
    {
        $flags = 0;

        if ($options['pretty_print'] ?? false) {
            $flags |= JSON_PRETTY_PRINT;
        }

        if ($options['preserve_zero_fraction'] ?? false) {
            $flags |= JSON_PRESERVE_ZERO_FRACTION;
        }

        if ($options['escape_unicode'] ?? false) {
            $flags |= JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP;
        } else {
            $flags |= JSON_UNESCAPED_UNICODE;
        }

        if ($options['escape_slashes'] ?? false) {
            // Default behavior (no flag needed)
        } else {
            $flags |= JSON_UNESCAPED_SLASHES;
        }

        if ($options['force_object'] ?? false) {
            $flags |= JSON_FORCE_OBJECT;
        }

        if ($options['numeric_check'] ?? false) {
            $flags |= JSON_NUMERIC_CHECK;
        }

        return $flags;
    }

    public function exportToStream($data, $stream, array $options = []): bool
    {
        try {
            $jsonString = $this->exportToString($data, $options);
            $result = fwrite($stream, $jsonString);
            return $result !== false;
        } catch (\Throwable $e) {
            $this->logError('Export to stream failed', $e, $data, $options);
            return false;
        }
    }

    public function exportChunked(array $data, string $filename, array $options = []): bool
    {
        $options = $this->validateOptions(array_merge($this->getDefaultOptions(), $options));
        $chunkSize = $options['chunk_size'] ?? 1000;

        if (empty($data)) {
            return $this->exportToFile([], $filename, $options);
        }

        $file = fopen($filename, 'w');
        if ($file === false) {
            throw new \RuntimeException("Unable to open file for writing: {$filename}");
        }

        try {
            $includeMetadata = $options['include_metadata'] ?? false;
            $rootElement = $options['root_element'] ?? null;

            // Start JSON structure
            if ($rootElement) {
                fwrite($file, '{"' . $rootElement . '": [');
            } else {
                if ($includeMetadata) {
                    fwrite($file, '{"data": [');
                } else {
                    fwrite($file, '[');
                }
            }

            // Process chunks
            $isFirst = true;
            for ($offset = 0; $offset < count($data); $offset += $chunkSize) {
                $chunk = array_slice($data, $offset, $chunkSize);
                $processedChunk = $this->processDataRecursively($chunk, [
                    'normalize_keys' => $options['normalize_keys'] ?? false,
                    'convert_dates' => $options['convert_dates'] ?? false,
                    'date_format' => $options['date_format'] ?? 'Y-m-d H:i:s',
                ]);

                foreach ($processedChunk as $item) {
                    if (!$isFirst) {
                        fwrite($file, ',');
                    }
                    fwrite($file, json_encode($item, $this->buildJsonFlags($options)));
                    $isFirst = false;
                }

                unset($chunk, $processedChunk);
            }

            // Close JSON structure
            if ($rootElement) {
                fwrite($file, ']}');
            } else {
                if ($includeMetadata) {
                    $metadata = [
                        'exported_at' => date('c'),
                        'row_count' => count($data),
                        'format' => 'json',
                        'version' => '1.0',
                    ];
                    fwrite($file, '], "metadata": ' . json_encode($metadata, $this->buildJsonFlags($options)) . '}');
                } else {
                    fwrite($file, ']');
                }
            }

            return true;
        } finally {
            fclose($file);
        }
    }

    public function exportAsJsonLines(array $data, string $filename, array $options = []): bool
    {
        $options = $this->validateOptions(array_merge($this->getDefaultOptions(), $options));

        $file = fopen($filename, 'w');
        if ($file === false) {
            throw new \RuntimeException("Unable to open file for writing: {$filename}");
        }

        try {
            $processedData = $this->processDataRecursively($data, [
                'normalize_keys' => $options['normalize_keys'] ?? false,
                'convert_dates' => $options['convert_dates'] ?? false,
                'date_format' => $options['date_format'] ?? 'Y-m-d H:i:s',
            ]);

            foreach ($processedData as $item) {
                $line = json_encode($item, $this->buildJsonFlags($options));
                fwrite($file, $line . "\n");
            }

            return true;
        } finally {
            fclose($file);
        }
    }

    public function getDefaultOptions(): array
    {
        return array_merge(parent::getDefaultOptions(), [
            'normalize_keys' => false,
            'convert_dates' => false,
            'date_format' => 'Y-m-d H:i:s',
            'include_metadata' => false,
            'root_element' => null,
            'escape_slashes' => false,
            'force_object' => false,
            'numeric_check' => false,
            'depth' => 512,
        ]);
    }

    public function estimateSize(array $data, array $options = []): int
    {
        if (empty($data)) {
            return 2; // Empty array: []
        }

        // JSON size estimation
        $options = array_merge($this->getDefaultOptions(), $options);
        $sampleSize = min(10, count($data));
        $sample = array_slice($data, 0, $sampleSize);

        try {
            $sampleExport = $this->doExportToString($sample, $options);
            $avgItemSize = strlen($sampleExport) / $sampleSize;

            // Account for JSON structure overhead
            $overhead = 50; // Estimated overhead for JSON structure
            if ($options['include_metadata']) {
                $overhead += 200; // Additional overhead for metadata
            }

            return (int) ($overhead + ($avgItemSize * count($data)));
        } catch (\Throwable $e) {
            // Fallback estimation - assume 100 bytes per item average
            return count($data) * 100;
        }
    }
}
