<?php

declare(strict_types=1);

namespace Wioex\SDK\Enums;

enum ExportFormat: string
{
    case CSV = 'csv';
    case JSON = 'json';
    case EXCEL = 'excel';
    case XML = 'xml';
    case YAML = 'yaml';
    case TSV = 'tsv';
    case HTML = 'html';
    case PDF = 'pdf';
    case TXT = 'txt';
    case SQL = 'sql';
    case PARQUET = 'parquet';
    case ARROW = 'arrow';

    public function getDescription(): string
    {
        return match ($this) {
            self::CSV => 'Comma-separated values format',
            self::JSON => 'JavaScript Object Notation format',
            self::EXCEL => 'Microsoft Excel format (.xlsx)',
            self::XML => 'Extensible Markup Language format',
            self::YAML => 'YAML Ain\'t Markup Language format',
            self::TSV => 'Tab-separated values format',
            self::HTML => 'HyperText Markup Language format',
            self::PDF => 'Portable Document Format',
            self::TXT => 'Plain text format',
            self::SQL => 'Structured Query Language format',
            self::PARQUET => 'Apache Parquet columnar format',
            self::ARROW => 'Apache Arrow columnar format',
        };
    }

    public function getMimeType(): string
    {
        return match ($this) {
            self::CSV => 'text/csv',
            self::JSON => 'application/json',
            self::EXCEL => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            self::XML => 'application/xml',
            self::YAML => 'application/x-yaml',
            self::TSV => 'text/tab-separated-values',
            self::HTML => 'text/html',
            self::PDF => 'application/pdf',
            self::TXT => 'text/plain',
            self::SQL => 'application/sql',
            self::PARQUET => 'application/octet-stream',
            self::ARROW => 'application/octet-stream',
        };
    }

    public function getFileExtension(): string
    {
        return match ($this) {
            self::CSV => 'csv',
            self::JSON => 'json',
            self::EXCEL => 'xlsx',
            self::XML => 'xml',
            self::YAML => 'yaml',
            self::TSV => 'tsv',
            self::HTML => 'html',
            self::PDF => 'pdf',
            self::TXT => 'txt',
            self::SQL => 'sql',
            self::PARQUET => 'parquet',
            self::ARROW => 'arrow',
        };
    }

    public function isTextFormat(): bool
    {
        return match ($this) {
            self::CSV, self::JSON, self::XML, self::YAML, self::TSV, self::HTML, self::TXT, self::SQL => true,
            default => false,
        };
    }

    public function isBinaryFormat(): bool
    {
        return !$this->isTextFormat();
    }

    public function isTabularFormat(): bool
    {
        return match ($this) {
            self::CSV, self::EXCEL, self::TSV, self::HTML, self::PARQUET, self::ARROW => true,
            default => false,
        };
    }

    public function isStructuredFormat(): bool
    {
        return match ($this) {
            self::JSON, self::XML, self::YAML => true,
            default => false,
        };
    }

    public function supportsMultipleSheets(): bool
    {
        return match ($this) {
            self::EXCEL => true,
            default => false,
        };
    }

    public function supportsFormatting(): bool
    {
        return match ($this) {
            self::EXCEL, self::HTML, self::PDF => true,
            default => false,
        };
    }

    public function requiresExternalLibrary(): bool
    {
        return match ($this) {
            self::EXCEL, self::PDF, self::PARQUET, self::ARROW => true,
            default => false,
        };
    }

    public function getRequiredLibraries(): array
    {
        return match ($this) {
            self::EXCEL => ['phpspreadsheet/phpspreadsheet'],
            self::PDF => ['tecnickcom/tcpdf', 'dompdf/dompdf'],
            self::PARQUET => ['parquet-php/parquet'],
            self::ARROW => ['apache/arrow'],
            default => [],
        };
    }

    public function getPerformanceRating(): string
    {
        return match ($this) {
            self::CSV, self::TSV, self::TXT => 'excellent',
            self::JSON, self::YAML => 'good',
            self::XML, self::HTML => 'fair',
            self::EXCEL, self::SQL => 'moderate',
            self::PDF, self::PARQUET, self::ARROW => 'slow',
        };
    }

    public function getCompressionSupport(): array
    {
        return match ($this) {
            self::CSV, self::JSON, self::XML, self::YAML, self::TSV, self::HTML, self::TXT, self::SQL => ['gzip', 'zip'],
            self::EXCEL => ['zip'],
            self::PARQUET => ['snappy', 'gzip', 'lz4'],
            self::ARROW => ['lz4', 'snappy', 'gzip'],
            default => [],
        };
    }

    public function getDefaultOptions(): array
    {
        return match ($this) {
            self::CSV => [
                'delimiter' => ',',
                'enclosure' => '"',
                'escape_char' => '\\',
                'include_headers' => true,
            ],
            self::JSON => [
                'pretty_print' => true,
                'preserve_zero_fraction' => false,
                'escape_unicode' => false,
            ],
            self::EXCEL => [
                'include_headers' => true,
                'auto_size_columns' => true,
                'freeze_headers' => true,
                'sheet_name' => 'Data',
            ],
            self::XML => [
                'root_element' => 'data',
                'row_element' => 'row',
                'pretty_print' => true,
            ],
            self::YAML => [
                'inline_level' => 2,
                'indent' => 2,
                'flags' => 0,
            ],
            self::TSV => [
                'delimiter' => "\t",
                'enclosure' => '"',
                'escape_char' => '\\',
                'include_headers' => true,
            ],
            self::HTML => [
                'include_headers' => true,
                'table_class' => 'export-table',
                'striped_rows' => true,
            ],
            self::PDF => [
                'orientation' => 'P',
                'unit' => 'mm',
                'format' => 'A4',
            ],
            self::TXT => [
                'column_separator' => ' | ',
                'include_headers' => true,
            ],
            default => [],
        };
    }

    public function getUseCases(): array
    {
        return match ($this) {
            self::CSV => ['data_analysis', 'spreadsheet_import', 'simple_export'],
            self::JSON => ['api_integration', 'web_applications', 'configuration'],
            self::EXCEL => ['business_reports', 'data_analysis', 'presentations'],
            self::XML => ['data_exchange', 'configuration', 'structured_documents'],
            self::YAML => ['configuration', 'human_readable_data', 'documentation'],
            self::TSV => ['data_analysis', 'database_import', 'simple_export'],
            self::HTML => ['web_display', 'reports', 'documentation'],
            self::PDF => ['reports', 'documentation', 'printing'],
            self::TXT => ['simple_viewing', 'debugging', 'logs'],
            self::SQL => ['database_import', 'backup', 'migration'],
            self::PARQUET => ['big_data_analytics', 'data_warehousing', 'columnar_analysis'],
            self::ARROW => ['in_memory_analytics', 'cross_language_data', 'streaming'],
        };
    }

    public static function getByPerformance(string $rating): array
    {
        return array_filter(
            self::cases(),
            fn(self $format) => $format->getPerformanceRating() === $rating
        );
    }

    public static function getTextFormats(): array
    {
        return array_filter(
            self::cases(),
            fn(self $format) => $format->isTextFormat()
        );
    }

    public static function getBinaryFormats(): array
    {
        return array_filter(
            self::cases(),
            fn(self $format) => $format->isBinaryFormat()
        );
    }

    public static function getTabularFormats(): array
    {
        return array_filter(
            self::cases(),
            fn(self $format) => $format->isTabularFormat()
        );
    }

    public static function getStructuredFormats(): array
    {
        return array_filter(
            self::cases(),
            fn(self $format) => $format->isStructuredFormat()
        );
    }

    public static function fromString(string $format): self
    {
        $format = strtolower(trim($format));

        return match ($format) {
            'csv' => self::CSV,
            'json' => self::JSON,
            'excel', 'xlsx', 'xls' => self::EXCEL,
            'xml' => self::XML,
            'yaml', 'yml' => self::YAML,
            'tsv' => self::TSV,
            'html', 'htm' => self::HTML,
            'pdf' => self::PDF,
            'txt', 'text' => self::TXT,
            'sql' => self::SQL,
            'parquet' => self::PARQUET,
            'arrow' => self::ARROW,
            default => throw new \InvalidArgumentException("Invalid export format: {$format}"),
        };
    }

    public static function fromMimeType(string $mimeType): self
    {
        foreach (self::cases() as $format) {
            if ($format->getMimeType() === $mimeType) {
                return $format;
            }
        }

        throw new \InvalidArgumentException("Invalid MIME type: {$mimeType}");
    }

    public static function fromFileExtension(string $extension): self
    {
        $extension = ltrim(strtolower($extension), '.');

        foreach (self::cases() as $format) {
            if ($format->getFileExtension() === $extension) {
                return $format;
            }
        }

        throw new \InvalidArgumentException("Invalid file extension: {$extension}");
    }

    public function toArray(): array
    {
        return [
            'value' => $this->value,
            'name' => $this->name,
            'description' => $this->getDescription(),
            'mime_type' => $this->getMimeType(),
            'file_extension' => $this->getFileExtension(),
            'is_text_format' => $this->isTextFormat(),
            'is_binary_format' => $this->isBinaryFormat(),
            'is_tabular_format' => $this->isTabularFormat(),
            'is_structured_format' => $this->isStructuredFormat(),
            'supports_multiple_sheets' => $this->supportsMultipleSheets(),
            'supports_formatting' => $this->supportsFormatting(),
            'requires_external_library' => $this->requiresExternalLibrary(),
            'required_libraries' => $this->getRequiredLibraries(),
            'performance_rating' => $this->getPerformanceRating(),
            'compression_support' => $this->getCompressionSupport(),
            'default_options' => $this->getDefaultOptions(),
            'use_cases' => $this->getUseCases(),
        ];
    }
}
