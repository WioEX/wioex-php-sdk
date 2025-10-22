<?php

declare(strict_types=1);

namespace Wioex\SDK\Export;

use Wioex\SDK\Enums\ExportFormat;

interface ExporterInterface
{
    /**
     * Get the supported export format
     */
    public function getFormat(): ExportFormat;

    /**
     * Export data to string
     */
    public function exportToString(array $data, array $options = []): string;

    /**
     * Export data to file
     */
    public function exportToFile(array $data, string $filename, array $options = []): bool;

    /**
     * Export data and force download
     */
    public function exportToDownload(array $data, string $filename, array $options = []): void;

    /**
     * Get default export options
     */
    public function getDefaultOptions(): array;

    /**
     * Validate export options
     */
    public function validateOptions(array $options): array;

    /**
     * Check if the exporter is available (required libraries installed)
     */
    public function isAvailable(): bool;

    /**
     * Get required libraries for this exporter
     */
    public function getRequiredLibraries(): array;

    /**
     * Estimate export size in bytes
     */
    public function estimateSize(array $data, array $options = []): int;

    /**
     * Get export metadata
     */
    public function getMetadata(): array;
}
