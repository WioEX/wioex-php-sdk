<?php

declare(strict_types=1);

namespace Wioex\SDK\Tests\Unit\Features;

use PHPUnit\Framework\TestCase;
use Wioex\SDK\Export\ExportManager;
use Wioex\SDK\Enums\ExportFormat;

class ExportManagerTest extends TestCase
{
    private string $tempDir;
    private ExportManager $manager;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/wioex_export_test_' . uniqid();
        mkdir($this->tempDir, 0777, true);
        
        $this->manager = new ExportManager();
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    public function testManagerInitialization(): void
    {
        $this->assertInstanceOf(ExportManager::class, $this->manager);
    }

    public function testExportToString(): void
    {
        $data = [
            ['symbol' => 'AAPL', 'price' => 150.25],
            ['symbol' => 'GOOGL', 'price' => 2750.00]
        ];

        $result = $this->manager->export($data, ExportFormat::JSON);
        
        $this->assertIsString($result);
        $this->assertStringContains('AAPL', $result);
        $this->assertStringContains('GOOGL', $result);
        
        $decoded = json_decode($result, true);
        $this->assertEquals($data, $decoded);
    }

    public function testExportToFile(): void
    {
        $data = [
            ['symbol' => 'AAPL', 'price' => 150.25],
            ['symbol' => 'GOOGL', 'price' => 2750.00]
        ];

        $outputPath = $this->tempDir . '/test_export.json';
        
        $result = $this->manager->exportToFile($data, ExportFormat::JSON, $outputPath);
        
        $this->assertTrue($result);
        $this->assertFileExists($outputPath);
        
        $exportedData = json_decode(file_get_contents($outputPath), true);
        $this->assertEquals($data, $exportedData);
    }

    public function testCsvExportToFile(): void
    {
        $data = [
            ['symbol' => 'AAPL', 'price' => 150.25],
            ['symbol' => 'GOOGL', 'price' => 2750.00]
        ];

        $outputPath = $this->tempDir . '/test_export.csv';
        
        $result = $this->manager->exportToFile($data, ExportFormat::CSV, $outputPath);
        
        $this->assertTrue($result);
        $this->assertFileExists($outputPath);
        
        $csvContent = file_get_contents($outputPath);
        $this->assertStringContains('symbol', $csvContent);
        $this->assertStringContains('AAPL', $csvContent);
        $this->assertStringContains('GOOGL', $csvContent);
    }

    public function testExportWithOptions(): void
    {
        $data = [
            ['name' => 'John Doe', 'age' => 30],
            ['name' => 'Jane Smith', 'age' => 25]
        ];

        $options = [
            'pretty_print' => true
        ];
        
        $result = $this->manager->export($data, ExportFormat::JSON, $options);
        
        $this->assertIsString($result);
        $this->assertStringContains('John Doe', $result);
        $this->assertStringContains('Jane Smith', $result);
    }

    public function testExportMultipleFormats(): void
    {
        $data = [
            ['symbol' => 'AAPL', 'price' => 150.25],
            ['symbol' => 'GOOGL', 'price' => 2750.00]
        ];

        $formats = [ExportFormat::JSON, ExportFormat::CSV];
        $baseFilename = $this->tempDir . '/multi_format_export';
        
        $results = $this->manager->exportMultipleFormats($data, $formats, $baseFilename);
        
        $this->assertIsArray($results);
        $this->assertCount(2, $results);
        $this->assertArrayHasKey('json', $results);
        $this->assertArrayHasKey('csv', $results);
        
        $this->assertTrue($results['json']['success']);
        $this->assertTrue($results['csv']['success']);
        
        $this->assertFileExists($results['json']['filename']);
        $this->assertFileExists($results['csv']['filename']);
    }

    public function testExportEmptyData(): void
    {
        $emptyData = [];
        
        $result = $this->manager->export($emptyData, ExportFormat::JSON);
        
        $this->assertEquals('[]', $result);
    }

    public function testExportLargeDataset(): void
    {
        $largeData = array_fill(0, 1000, ['id' => 1, 'value' => 'test']);
        
        $result = $this->manager->export($largeData, ExportFormat::JSON);
        
        $this->assertIsString($result);
        $this->assertGreaterThan(1000, strlen($result));
        
        $decoded = json_decode($result, true);
        $this->assertCount(1000, $decoded);
    }

    public function testCreateStaticMethod(): void
    {
        $manager = ExportManager::create(['enabled' => true]);
        
        $this->assertInstanceOf(ExportManager::class, $manager);
    }

    public function testDisabledExportManager(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Export manager is disabled');
        
        $disabledManager = new ExportManager(['enabled' => false]);
        $disabledManager->export([], ExportFormat::JSON);
    }
}