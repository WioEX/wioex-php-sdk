<?php

declare(strict_types=1);

namespace Wioex\SDK\Export;

use Wioex\SDK\Enums\ExportFormat;
use Wioex\SDK\Logging\Logger;

class CsvExporter extends AbstractExporter
{
    public function __construct(array $config = [], Logger $logger = null)
    {
        parent::__construct(ExportFormat::CSV, $config, $logger);
    }

    protected function doExportToString(array $data, array $options): string
    {
        $data = $this->prepareData($data, $options);

        if (($data === null || $data === '' || $data === [])) {
            return '';
        }

        $delimiter = $options['delimiter'] ?? ',';
        $enclosure = $options['enclosure'] ?? '"';
        $escapeChar = $options['escape_char'] ?? '\\';
        $includeHeaders = $options['include_headers'] ?? true;
        $flattenArrays = $options['flatten_arrays'] ?? true;

        $output = fopen('php://temp', 'r+');
        if ($output === false) {
            throw new \RuntimeException('Unable to create temporary stream for CSV export');
        }

        try {
            // Prepare data and headers
            $processedData = $this->processDataForCsv($data, $flattenArrays);
            $headers = $this->extractHeaders($processedData);

            // Write headers
            if ($includeHeaders && ($headers !== null && $headers !== '' && $headers !== [])) {
                fputcsv($output, $headers, $delimiter, $enclosure, $escapeChar);
            }

            // Write data rows
            foreach ($processedData as $row) {
                $csvRow = [];
                foreach ($headers as $header) {
                    $value = $row[$header] ?? '';
                    $csvRow[] = $this->formatCsvValue($value, $options);
                }
                fputcsv($output, $csvRow, $delimiter, $enclosure, $escapeChar);
            }

            rewind($output);
            $result = stream_get_contents($output);

            return $result !== false ? $result : '';
        } finally {
            fclose($output);
        }
    }

    protected function validateOptionsCustom(array $options): array
    {
        // Validate delimiter
        if (isset($options['delimiter']) && strlen($options['delimiter']) !== 1) {
            throw new \InvalidArgumentException('CSV delimiter must be a single character');
        }

        // Validate enclosure
        if (isset($options['enclosure']) && strlen($options['enclosure']) !== 1) {
            throw new \InvalidArgumentException('CSV enclosure must be a single character');
        }

        // Validate escape character
        if (isset($options['escape_char']) && strlen($options['escape_char']) !== 1) {
            throw new \InvalidArgumentException('CSV escape character must be a single character');
        }

        return $options;
    }

    private function processDataForCsv(array $data, bool $flattenArrays): array
    {
        if (!$flattenArrays) {
            return $data;
        }

        $processed = [];
        foreach ($data as $row) {
            if (is_array($row)) {
                $processed[] = $this->flattenArray($row);
            } else {
                $processed[] = ['value' => $row];
            }
        }

        return $processed;
    }

    private function formatCsvValue(mixed $value, array $options): string
    {
        if (is_null($value)) {
            return $options['null_value'] ?? '';
        }

        if (is_bool($value)) {
            return $options['bool_format'] === 'numeric' ? ($value ? '1' : '0') : ($value ? 'true' : 'false');
        }

        if (is_numeric($value)) {
            if (is_float($value) && isset($options['decimal_places'])) {
                return number_format($value, $options['decimal_places'], '.', '');
            }
            return (string) $value;
        }

        if (is_array($value) || is_object($value)) {
            return $options['complex_value_format'] === 'json' ? json_encode($value) : serialize($value);
        }

        $stringValue = (string) $value;

        // Handle line breaks
        if (isset($options['line_break_replacement'])) {
            $stringValue = str_replace(["\r\n", "\n", "\r"], $options['line_break_replacement'], $stringValue);
        }

        return $stringValue;
    }

    public function exportToStream($data, $stream, array $options = []): bool
    {
        $options = $this->validateOptions(array_merge($this->getDefaultOptions(), $options));
        $data = $this->prepareData($data, $options);

        if (($data === null || $data === '' || $data === [])) {
            return true;
        }

        $delimiter = $options['delimiter'] ?? ',';
        $enclosure = $options['enclosure'] ?? '"';
        $escapeChar = $options['escape_char'] ?? '\\';
        $includeHeaders = $options['include_headers'] ?? true;
        $flattenArrays = $options['flatten_arrays'] ?? true;

        try {
            $processedData = $this->processDataForCsv($data, $flattenArrays);
            $headers = $this->extractHeaders($processedData);

            // Write headers
            if ($includeHeaders && ($headers !== null && $headers !== '' && $headers !== [])) {
                fputcsv($stream, $headers, $delimiter, $enclosure, $escapeChar);
            }

            // Write data rows
            foreach ($processedData as $row) {
                $csvRow = [];
                foreach ($headers as $header) {
                    $value = $row[$header] ?? '';
                    $csvRow[] = $this->formatCsvValue($value, $options);
                }
                fputcsv($stream, $csvRow, $delimiter, $enclosure, $escapeChar);
            }

            return true;
        } catch (\Throwable $e) {
            $this->logError('Export to stream failed', $e, $data, $options);
            return false;
        }
    }

    public function exportChunked(array $data, string $filename, array $options = []): bool
    {
        $options = $this->validateOptions(array_merge($this->getDefaultOptions(), $options));
        $chunkSize = $options['chunk_size'] ?? 1000;

        if (($data === null || $data === '' || $data === [])) {
            return $this->exportToFile([], $filename, $options);
        }

        $file = fopen($filename, 'w');
        if ($file === false) {
            throw new \RuntimeException("Unable to open file for writing: {$filename}");
        }

        try {
            $delimiter = $options['delimiter'] ?? ',';
            $enclosure = $options['enclosure'] ?? '"';
            $escapeChar = $options['escape_char'] ?? '\\';
            $includeHeaders = $options['include_headers'] ?? true;
            $flattenArrays = $options['flatten_arrays'] ?? true;

            // Process first chunk to get headers
            $firstChunk = array_slice($data, 0, min($chunkSize, count($data)));
            $processedChunk = $this->processDataForCsv($firstChunk, $flattenArrays);
            $headers = $this->extractHeaders($processedChunk);

            // Write headers
            if ($includeHeaders && ($headers !== null && $headers !== '' && $headers !== [])) {
                fputcsv($file, $headers, $delimiter, $enclosure, $escapeChar);
            }

            // Process and write chunks
            for ($offset = 0; $offset < count($data); $offset += $chunkSize) {
                $chunk = array_slice($data, $offset, $chunkSize);
                $processedChunk = $this->processDataForCsv($chunk, $flattenArrays);

                foreach ($processedChunk as $row) {
                    $csvRow = [];
                    foreach ($headers as $header) {
                        $value = $row[$header] ?? '';
                        $csvRow[] = $this->formatCsvValue($value, $options);
                    }
                    fputcsv($file, $csvRow, $delimiter, $enclosure, $escapeChar);
                }

                // Clear memory
                unset($chunk, $processedChunk);
            }

            return true;
        } finally {
            fclose($file);
        }
    }

    protected function getDefaultConfig(): array
    {
        return array_merge(parent::getDefaultConfig(), [
            'max_field_length' => 10000,
            'auto_detect_encoding' => true,
            'output_encoding' => 'UTF-8',
        ]);
    }

    public function getDefaultOptions(): array
    {
        return array_merge(parent::getDefaultOptions(), [
            'flatten_arrays' => true,
            'null_value' => '',
            'bool_format' => 'text', // 'text' or 'numeric'
            'complex_value_format' => 'json', // 'json' or 'serialize'
            'decimal_places' => null,
            'line_break_replacement' => ' ',
            'chunk_size' => 1000,
        ]);
    }

    public function estimateSize(array $data, array $options = []): int
    {
        if (($data === null || $data === '' || $data === [])) {
            return 0;
        }

        // More accurate CSV size estimation
        $options = array_merge($this->getDefaultOptions(), $options);
        $sampleSize = min(10, count($data));
        $sample = array_slice($data, 0, $sampleSize);

        try {
            $sampleExport = $this->doExportToString($sample, $options);
            $avgRowSize = strlen($sampleExport) / $sampleSize;

            // Add overhead for headers if included
            $headerSize = 0;
            if ($options['include_headers'] ?? true) {
                $headers = $this->extractHeaders($this->prepareData($data, $options));
                $headerSize = strlen(implode($options['delimiter'] ?? ',', $headers)) + 1;
            }

            return (int) ($headerSize + ($avgRowSize * count($data)));
        } catch (\Throwable $e) {
            // Fallback estimation
            $headers = $this->extractHeaders($this->prepareData($data, $options));
            $avgFieldLength = 20; // Estimated average field length
            $rowSize = count($headers) * $avgFieldLength;
            return $rowSize * count($data);
        }
    }
}
