<?php

declare(strict_types=1);

namespace Wioex\SDK\Export;

use Wioex\SDK\Enums\ExportFormat;
use Wioex\SDK\Enums\Environment;
use Wioex\SDK\Logging\Logger;
use Wioex\SDK\Http\Response;

class ExportManager
{
    private array $exporters = [];
    private array $config;
    private bool $enabled = true;
    private ?Logger $logger = null;
    private array $statistics = [];

    public function __construct(array $config = [], Logger $logger = null)
    {
        $this->config = array_merge($this->getDefaultConfig(), $config);
        $this->enabled = $this->config['enabled'] ?? true;
        $this->logger = $logger;

        $this->registerDefaultExporters();
    }

    public static function create(array $config = []): self
    {
        return new self($config);
    }

    public static function forEnvironment(Environment $environment, array $config = []): self
    {
        $defaultConfig = [
            'enabled' => true,
            'default_format' => $environment->getDefaultExportFormat(),
            'max_export_size' => $environment->getMaxExportSize(),
            'temp_directory' => $environment->getTempDirectory(),
        ];

        return new self(array_merge($defaultConfig, $config));
    }

    public function export(array $data, ExportFormat $format, array $options = []): string
    {
        if (!$this->enabled) {
            throw new \RuntimeException('Export manager is disabled');
        }

        $exporter = $this->getExporter($format);
        $startTime = microtime(true);

        try {
            $result = $exporter->exportToString($data, $options);
            $this->recordExportStatistics($format, 'string', $data, $options, microtime(true) - $startTime, strlen($result));
            return $result;
        } catch (\Throwable $e) {
            $this->logError('Export failed', $e, $format, $data, $options);
            throw $e;
        }
    }

    public function exportToFile(array $data, ExportFormat $format, string $filename, array $options = []): bool
    {
        if (!$this->enabled) {
            throw new \RuntimeException('Export manager is disabled');
        }

        $exporter = $this->getExporter($format);
        $startTime = microtime(true);

        try {
            $result = $exporter->exportToFile($data, $filename, $options);
            if ($result) {
                $fileSize = file_exists($filename) ? filesize($filename) : 0;
                $this->recordExportStatistics($format, 'file', $data, $options, microtime(true) - $startTime, $fileSize, $filename);
            }
            return $result;
        } catch (\Throwable $e) {
            $this->logError('Export to file failed', $e, $format, $data, $options, $filename);
            throw $e;
        }
    }

    public function exportToDownload(array $data, ExportFormat $format, string $filename, array $options = []): void
    {
        if (!$this->enabled) {
            throw new \RuntimeException('Export manager is disabled');
        }

        $exporter = $this->getExporter($format);
        $startTime = microtime(true);

        try {
            $exporter->exportToDownload($data, $filename, $options);
            $this->recordExportStatistics($format, 'download', $data, $options, microtime(true) - $startTime, 0, $filename);
        } catch (\Throwable $e) {
            $this->logError('Export to download failed', $e, $format, $data, $options, $filename);
            throw $e;
        }
    }

    public function exportResponse(Response $response, ExportFormat $format, string $filename = null, array $options = []): string
    {
        $data = $response->data();
        $filename = $filename ?: $this->generateFilename($format);

        return $this->export($data, $format, $options);
    }

    public function exportResponseToFile(Response $response, ExportFormat $format, string $filename, array $options = []): bool
    {
        $data = $response->data();
        return $this->exportToFile($data, $format, $filename, $options);
    }

    public function exportResponseToDownload(Response $response, ExportFormat $format, string $filename = null, array $options = []): void
    {
        $data = $response->data();
        $filename = $filename ?: $this->generateFilename($format);

        $this->exportToDownload($data, $format, $filename, $options);
    }

    public function exportMultipleFormats(array $data, array $formats, string $baseFilename, array $options = []): array
    {
        $results = [];

        foreach ($formats as $format) {
            if (is_string($format)) {
                $format = ExportFormat::fromString($format);
            }

            $filename = $this->buildFilename($baseFilename, $format);

            try {
                $success = $this->exportToFile($data, $format, $filename, $options);
                $results[$format->value] = [
                    'success' => $success,
                    'filename' => $filename,
                    'format' => $format,
                    'file_size' => $success && file_exists($filename) ? filesize($filename) : 0,
                ];
            } catch (\Throwable $e) {
                $results[$format->value] = [
                    'success' => false,
                    'filename' => $filename,
                    'format' => $format,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    public function estimateExportSize(array $data, ExportFormat $format, array $options = []): int
    {
        $exporter = $this->getExporter($format);
        return $exporter->estimateSize($data, $options);
    }

    public function validateExport(array $data, ExportFormat $format, array $options = []): array
    {
        $validation = [
            'is_valid' => true,
            'warnings' => [],
            'errors' => [],
            'estimated_size' => 0,
            'estimated_duration' => 0,
        ];

        try {
            // Check if exporter is available
            $exporter = $this->getExporter($format);
            if (!$exporter->isAvailable()) {
                $validation['is_valid'] = false;
                $validation['errors'][] = "Exporter for {$format->value} is not available";
                $requiredLibraries = $exporter->getRequiredLibraries();
                if (($requiredLibraries !== null && $requiredLibraries !== '' && $requiredLibraries !== [])) {
                    $validation['errors'][] = "Required libraries: " . implode(', ', $requiredLibraries);
                }
            }

            // Estimate size
            $estimatedSize = $this->estimateExportSize($data, $format, $options);
            $validation['estimated_size'] = $estimatedSize;

            // Check size limits
            $maxSize = $this->config['max_export_size'] ?? (50 * 1024 * 1024); // 50MB default
            if ($estimatedSize > $maxSize) {
                $validation['warnings'][] = "Estimated export size ({$this->formatBytes($estimatedSize)}) exceeds recommended limit ({$this->formatBytes($maxSize)})";
            }

            // Estimate duration based on size and format performance
            $validation['estimated_duration'] = $this->estimateExportDuration($estimatedSize, $format);

            // Check data structure
            if (($data === null || $data === '' || $data === [])) {
                $validation['warnings'][] = "Export data is empty";
            } elseif (count($data) > 100000) {
                $validation['warnings'][] = "Large dataset detected (" . count($data) . " rows). Consider using chunked export.";
            }

            // Validate options
            try {
                $exporter->validateOptions($options);
            } catch (\Exception $e) {
                $validation['is_valid'] = false;
                $validation['errors'][] = "Invalid options: " . $e->getMessage();
            }
        } catch (\Throwable $e) {
            $validation['is_valid'] = false;
            $validation['errors'][] = $e->getMessage();
        }

        return $validation;
    }

    public function registerExporter(ExporterInterface $exporter): self
    {
        $this->exporters[$exporter->getFormat()->value] = $exporter;
        return $this;
    }

    public function getExporter(ExportFormat $format): ExporterInterface
    {
        if (!isset($this->exporters[$format->value])) {
            throw new \InvalidArgumentException("No exporter registered for format: {$format->value}");
        }

        return $this->exporters[$format->value];
    }

    public function hasExporter(ExportFormat $format): bool
    {
        return isset($this->exporters[$format->value]);
    }

    public function getAvailableFormats(): array
    {
        $available = [];

        foreach ($this->exporters as $format => $exporter) {
            if ($exporter->isAvailable()) {
                $available[] = ExportFormat::fromString($format);
            }
        }

        return $available;
    }

    public function getAllFormats(): array
    {
        return array_keys($this->exporters);
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function enable(): self
    {
        $this->enabled = true;
        return $this;
    }

    public function disable(): self
    {
        $this->enabled = false;
        return $this;
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function setConfig(array $config): self
    {
        $this->config = array_merge($this->config, $config);
        return $this;
    }

    public function getStatistics(): array
    {
        return [
            'enabled' => $this->enabled,
            'total_exports' => count($this->statistics),
            'exports_by_format' => $this->getExportsByFormat(),
            'exports_by_type' => $this->getExportsByType(),
            'average_export_time' => $this->getAverageExportTime(),
            'total_bytes_exported' => $this->getTotalBytesExported(),
            'recent_exports' => array_slice($this->statistics, -10),
            'available_formats' => $this->getAvailableFormats(),
            'config' => $this->config,
        ];
    }

    public function clearStatistics(): self
    {
        $this->statistics = [];
        return $this;
    }

    private function registerDefaultExporters(): void
    {
        // Register built-in exporters
        $this->registerExporter(new CsvExporter($this->config['csv'] ?? [], $this->logger));
        $this->registerExporter(new JsonExporter($this->config['json'] ?? [], $this->logger));

        // Register other exporters if their dependencies are available
        $this->registerConditionalExporters();
    }

    private function registerConditionalExporters(): void
    {
        // Excel exporter (requires PhpSpreadsheet)
        if (class_exists('PhpOffice\\PhpSpreadsheet\\Spreadsheet')) {
            // $this->registerExporter(new ExcelExporter($this->config['excel'] ?? [], $this->logger));
        }

        // XML exporter (built-in SimpleXML)
        if (extension_loaded('simplexml')) {
            // $this->registerExporter(new XmlExporter($this->config['xml'] ?? [], $this->logger));
        }

        // YAML exporter (requires yaml extension or symfony/yaml)
        if (extension_loaded('yaml') || class_exists('Symfony\\Component\\Yaml\\Yaml')) {
            // $this->registerExporter(new YamlExporter($this->config['yaml'] ?? [], $this->logger));
        }
    }

    private function generateFilename(ExportFormat $format, string $prefix = 'export'): string
    {
        $timestamp = date('Y-m-d_H-i-s');
        return "{$prefix}_{$timestamp}.{$format->getFileExtension()}";
    }

    private function buildFilename(string $baseFilename, ExportFormat $format): string
    {
        $pathInfo = pathinfo($baseFilename);
        $directory = $pathInfo['dirname'] ?? '.';
        $filename = $pathInfo['filename'] ?? 'export';

        return $directory . DIRECTORY_SEPARATOR . $filename . '.' . $format->getFileExtension();
    }

    private function estimateExportDuration(int $sizeBytes, ExportFormat $format): float
    {
        // Rough estimation based on format performance and size
        $baseSpeed = match ($format->getPerformanceRating()) {
            'excellent' => 10 * 1024 * 1024, // 10MB/s
            'good' => 5 * 1024 * 1024,       // 5MB/s
            'fair' => 2 * 1024 * 1024,       // 2MB/s
            'moderate' => 1 * 1024 * 1024,   // 1MB/s
            'slow' => 0.5 * 1024 * 1024,     // 0.5MB/s
            default => 1 * 1024 * 1024,
        };

        return $sizeBytes / $baseSpeed;
    }

    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }

    private function recordExportStatistics(ExportFormat $format, string $type, array $data, array $options, float $duration, int $size, string $filename = null): void
    {
        $this->statistics[] = [
            'timestamp' => time(),
            'format' => $format->value,
            'type' => $type,
            'row_count' => count($data),
            'duration_seconds' => $duration,
            'size_bytes' => $size,
            'filename' => $filename,
            'options' => $options,
        ];

        // Keep only last 1000 records
        if (count($this->statistics) > 1000) {
            $this->statistics = array_slice($this->statistics, -500);
        }
    }

    private function getExportsByFormat(): array
    {
        $byFormat = [];
        foreach ($this->statistics as $stat) {
            $format = $stat['format'];
            $byFormat[$format] = ($byFormat[$format] ?? 0) + 1;
        }
        return $byFormat;
    }

    private function getExportsByType(): array
    {
        $byType = [];
        foreach ($this->statistics as $stat) {
            $type = $stat['type'];
            $byType[$type] = ($byType[$type] ?? 0) + 1;
        }
        return $byType;
    }

    private function getAverageExportTime(): float
    {
        if (($this->statistics === null || $this->statistics === '' || $this->statistics === [])) {
            return 0.0;
        }

        $totalTime = array_sum(array_column($this->statistics, 'duration_seconds'));
        return $totalTime / count($this->statistics);
    }

    private function getTotalBytesExported(): int
    {
        return array_sum(array_column($this->statistics, 'size_bytes'));
    }

    private function logError(string $message, \Throwable $error, ExportFormat $format, array $data, array $options, string $filename = null): void
    {
        if ($this->logger === null) {
            return;
        }

        $this->logger->error($message, [
            'format' => $format->value,
            'filename' => $filename,
            'row_count' => count($data),
            'error' => $error->getMessage(),
            'error_file' => $error->getFile(),
            'error_line' => $error->getLine(),
            'options' => $options,
        ]);
    }

    private function getDefaultConfig(): array
    {
        return [
            'enabled' => true,
            'max_export_size' => 50 * 1024 * 1024, // 50MB
            'temp_directory' => sys_get_temp_dir(),
            'default_format' => 'csv',
            'chunk_size' => 1000,
        ];
    }

    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled,
            'config' => $this->config,
            'available_formats' => array_map(fn($f) => $f->value, $this->getAvailableFormats()),
            'all_formats' => $this->getAllFormats(),
            'statistics' => $this->getStatistics(),
            'exporters' => array_map(fn($e) => $e->getMetadata(), $this->exporters),
        ];
    }
}
