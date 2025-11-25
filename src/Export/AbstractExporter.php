<?php

declare(strict_types=1);

namespace Wioex\SDK\Export;

use Wioex\SDK\Enums\ExportFormat;
use Wioex\SDK\Logging\Logger;

abstract class AbstractExporter implements ExporterInterface
{
    protected ExportFormat $format;
    protected array $config;
    protected ?Logger $logger = null;
    protected array $statistics = [];

    public function __construct(ExportFormat $format, array $config = [], Logger $logger = null)
    {
        $this->format = $format;
        $this->config = array_merge($this->getDefaultConfig(), $config);
        $this->logger = $logger;
    }

    public function getFormat(): ExportFormat
    {
        return $this->format;
    }

    public function exportToString(array $data, array $options = []): string
    {
        $options = $this->validateOptions(array_merge($this->getDefaultOptions(), $options));
        $startTime = microtime(true);

        try {
            $result = $this->doExportToString($data, $options);
            $this->recordExportStatistics('string', $data, $options, microtime(true) - $startTime, strlen($result));
            return $result;
        } catch (\Throwable $e) {
            $this->logError('Export to string failed', $e, $data, $options);
            throw $e;
        }
    }

    public function exportToFile(array $data, string $filename, array $options = []): bool
    {
        $options = $this->validateOptions(array_merge($this->getDefaultOptions(), $options));
        $startTime = microtime(true);

        try {
            $content = $this->doExportToString($data, $options);

            // Ensure directory exists
            $directory = dirname($filename);
            if (!is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            $result = file_put_contents($filename, $content);
            $success = $result !== false;

            if ($success) {
                $this->recordExportStatistics('file', $data, $options, microtime(true) - $startTime, $result, $filename);
                $this->logSuccess('Export to file completed', $filename, count($data), $result);
            }

            return $success;
        } catch (\Throwable $e) {
            $this->logError('Export to file failed', $e, $data, $options, $filename);
            throw $e;
        }
    }

    public function exportToDownload(array $data, string $filename, array $options = []): void
    {
        $options = $this->validateOptions(array_merge($this->getDefaultOptions(), $options));
        $startTime = microtime(true);

        try {
            $content = $this->doExportToString($data, $options);

            // Set headers for download
            $this->setDownloadHeaders($filename, strlen($content));

            echo $content;

            $this->recordExportStatistics('download', $data, $options, microtime(true) - $startTime, strlen($content), $filename);
            $this->logSuccess('Export to download completed', $filename, count($data), strlen($content));
        } catch (\Throwable $e) {
            $this->logError('Export to download failed', $e, $data, $options, $filename);
            throw $e;
        }
    }

    public function getDefaultOptions(): array
    {
        return $this->format->getDefaultOptions();
    }

    public function validateOptions(array $options): array
    {
        // Basic validation - subclasses can override for format-specific validation
        $defaultOptions = $this->getDefaultOptions();
        $validatedOptions = array_merge($defaultOptions, $options);

        return $this->validateOptionsCustom($validatedOptions);
    }

    public function isAvailable(): bool
    {
        $requiredLibraries = $this->getRequiredLibraries();

        foreach ($requiredLibraries as $library) {
            if (!$this->isLibraryAvailable($library)) {
                return false;
            }
        }

        return true;
    }

    public function getRequiredLibraries(): array
    {
        return $this->format->getRequiredLibraries();
    }

    public function estimateSize(array $data, array $options = []): int
    {
        if (($data === null || $data === '' || $data === [])) {
            return 0;
        }

        // Basic estimation - subclasses can provide more accurate estimates
        $sampleSize = min(100, count($data));
        $sample = array_slice($data, 0, $sampleSize);

        try {
            $sampleExport = $this->doExportToString($sample, $options);
            $sizePerRow = strlen($sampleExport) / $sampleSize;
            return (int) ($sizePerRow * count($data));
        } catch (\Throwable $e) {
            // Fallback estimation
            return count($data) * 100; // Assume 100 bytes per row
        }
    }

    public function getMetadata(): array
    {
        return [
            'format' => $this->format->toArray(),
            'config' => $this->config,
            'is_available' => $this->isAvailable(),
            'required_libraries' => $this->getRequiredLibraries(),
            'statistics' => $this->getStatistics(),
        ];
    }

    public function getStatistics(): array
    {
        return $this->statistics;
    }

    public function resetStatistics(): self
    {
        $this->statistics = [];
        return $this;
    }

    /**
     * Abstract method for actual export implementation
     */
    abstract protected function doExportToString(array $data, array $options): string;

    /**
     * Custom validation for format-specific options
     */
    protected function validateOptionsCustom(array $options): array
    {
        return $options;
    }

    /**
     * Get default configuration
     */
    protected function getDefaultConfig(): array
    {
        return [
            'memory_limit' => '256M',
            'time_limit' => 300,
            'chunk_size' => 1000,
        ];
    }

    /**
     * Prepare data for export (flatten, normalize, etc.)
     */
    protected function prepareData(array $data, array $options): array
    {
        if (($data === null || $data === '' || $data === [])) {
            return [];
        }

        // If data is not indexed array of arrays, try to normalize it
        if (!$this->isIndexedArrayOfArrays($data)) {
            $data = $this->normalizeData($data);
        }

        return $data;
    }

    /**
     * Check if data is indexed array of arrays (tabular data)
     */
    protected function isIndexedArrayOfArrays(array $data): bool
    {
        if (($data === null || $data === '' || $data === [])) {
            return true;
        }

        // Check if all keys are numeric and sequential
        $keys = array_keys($data);
        if ($keys !== range(0, count($data) - 1)) {
            return false;
        }

        // Check if first element is an array
        return is_array(reset($data));
    }

    /**
     * Normalize data to tabular format
     */
    protected function normalizeData(array $data): array
    {
        // If it's a single associative array, wrap it
        if ($this->isAssociativeArray($data)) {
            return [$data];
        }

        // If it's mixed data, try to flatten it
        $normalized = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $normalized[] = $value;
            } else {
                $normalized[] = [$key => $value];
            }
        }

        return $normalized;
    }

    /**
     * Check if array is associative
     */
    protected function isAssociativeArray(array $array): bool
    {
        return array_keys($array) !== range(0, count($array) - 1);
    }

    /**
     * Extract headers from data
     */
    protected function extractHeaders(array $data): array
    {
        if (($data === null || $data === '' || $data === [])) {
            return [];
        }

        $first = reset($data);
        if (!is_array($first)) {
            return ['value'];
        }

        return array_keys($first);
    }

    /**
     * Flatten nested arrays for simple formats
     */
    protected function flattenArray(array $array, string $separator = '_'): array
    {
        $result = [];

        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $flattened = $this->flattenArray($value, $separator);
                foreach ($flattened as $subKey => $subValue) {
                    $result[$key . $separator . $subKey] = $subValue;
                }
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * Convert value to string for export
     */
    protected function valueToString(mixed $value): string
    {
        if (is_null($value)) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_array($value) || is_object($value)) {
            return json_encode($value);
        }

        return (string) $value;
    }

    /**
     * Set download headers
     */
    protected function setDownloadHeaders(string $filename, int $contentLength): void
    {
        $mimeType = $this->format->getMimeType();

        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
        header('Content-Length: ' . $contentLength);
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
    }

    /**
     * Check if library is available
     */
    protected function isLibraryAvailable(string $library): bool
    {
        // Check if it's a class
        if (class_exists($library)) {
            return true;
        }

        // Check if it's a function
        if (function_exists($library)) {
            return true;
        }

        // Check common library patterns
        $libraryParts = explode('/', $library);
        if (count($libraryParts) === 2) {
            // Composer package format
            return class_exists($libraryParts[1]) || function_exists($libraryParts[1]);
        }

        return false;
    }

    /**
     * Record export statistics
     */
    protected function recordExportStatistics(string $type, array $data, array $options, float $duration, int $size, string $filename = null): void
    {
        $this->statistics[] = [
            'timestamp' => time(),
            'type' => $type,
            'format' => $this->format->value,
            'row_count' => count($data),
            'column_count' => ($data === null || $data === '' || $data === []) ? 0 : count($this->extractHeaders($data)),
            'duration_seconds' => $duration,
            'size_bytes' => $size,
            'filename' => $filename,
            'options' => $options,
        ];

        // Keep only last 100 records
        if (count($this->statistics) > 100) {
            $this->statistics = array_slice($this->statistics, -50);
        }
    }

    /**
     * Log successful export
     */
    protected function logSuccess(string $message, string $filename, int $rowCount, int $size): void
    {
        if ($this->logger === null) {
            return;
        }

        $this->logger->info($message, [
            'format' => $this->format->value,
            'filename' => $filename,
            'row_count' => $rowCount,
            'size_bytes' => $size,
        ]);
    }

    /**
     * Log export error
     */
    protected function logError(string $message, \Throwable $error, array $data, array $options, string $filename = null): void
    {
        if ($this->logger === null) {
            return;
        }

        $this->logger->error($message, [
            'format' => $this->format->value,
            'filename' => $filename,
            'row_count' => count($data),
            'error' => $error->getMessage(),
            'error_file' => $error->getFile(),
            'error_line' => $error->getLine(),
            'options' => $options,
        ]);
    }
}
