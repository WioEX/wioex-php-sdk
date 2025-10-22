<?php

declare(strict_types=1);

namespace Wioex\SDK\Tests\Unit\Enums;

use PHPUnit\Framework\TestCase;
use Wioex\SDK\Enums\Environment;
use Wioex\SDK\Enums\ExportFormat;
use Wioex\SDK\Enums\ValidationState;
use Wioex\SDK\Enums\ConfigurationSource;
use Wioex\SDK\Enums\ErrorReportingLevel;
use Wioex\SDK\Enums\CurrencyInterval;
use Wioex\SDK\Enums\AnalyticsPeriod;
use Wioex\SDK\Enums\MetricType;

class EnumsTest extends TestCase
{
    public function testEnvironmentEnum(): void
    {
        $this->assertEquals('development', Environment::DEVELOPMENT->value);
        $this->assertEquals('staging', Environment::STAGING->value);
        $this->assertEquals('production', Environment::PRODUCTION->value);
        
        $this->assertTrue(Environment::PRODUCTION->isProduction());
        $this->assertFalse(Environment::DEVELOPMENT->isProduction());
        $this->assertFalse(Environment::STAGING->isProduction());
        
        $this->assertTrue(Environment::DEVELOPMENT->isDevelopment());
        $this->assertFalse(Environment::PRODUCTION->isDevelopment());
        
        $this->assertTrue(Environment::STAGING->isStaging());
        $this->assertFalse(Environment::DEVELOPMENT->isStaging());
    }

    public function testEnvironmentFromString(): void
    {
        $this->assertEquals(Environment::DEVELOPMENT, Environment::fromString('development'));
        $this->assertEquals(Environment::STAGING, Environment::fromString('staging'));
        $this->assertEquals(Environment::PRODUCTION, Environment::fromString('production'));
        
        $this->expectException(\InvalidArgumentException::class);
        Environment::fromString('invalid');
    }

    public function testEnvironmentApiBaseUrl(): void
    {
        $this->assertEquals('https://dev-api.wioex.com', Environment::DEVELOPMENT->getApiBaseUrl());
        $this->assertEquals('https://staging-api.wioex.com', Environment::STAGING->getApiBaseUrl());
        $this->assertEquals('https://api.wioex.com', Environment::PRODUCTION->getApiBaseUrl());
    }

    public function testExportFormatEnum(): void
    {
        $this->assertEquals('json', ExportFormat::JSON->value);
        $this->assertEquals('csv', ExportFormat::CSV->value);
        $this->assertEquals('xml', ExportFormat::XML->value);
        $this->assertEquals('excel', ExportFormat::EXCEL->value);
        
        $this->assertEquals('application/json', ExportFormat::JSON->getMimeType());
        $this->assertEquals('text/csv', ExportFormat::CSV->getMimeType());
        $this->assertEquals('application/xml', ExportFormat::XML->getMimeType());
        $this->assertEquals('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', ExportFormat::EXCEL->getMimeType());
        
        $this->assertEquals('.json', ExportFormat::JSON->getFileExtension());
        $this->assertEquals('.csv', ExportFormat::CSV->getFileExtension());
        $this->assertEquals('.xml', ExportFormat::XML->getFileExtension());
        $this->assertEquals('.xlsx', ExportFormat::EXCEL->getFileExtension());
    }

    public function testExportFormatFromExtension(): void
    {
        $this->assertEquals(ExportFormat::JSON, ExportFormat::fromExtension('.json'));
        $this->assertEquals(ExportFormat::CSV, ExportFormat::fromExtension('.csv'));
        $this->assertEquals(ExportFormat::XML, ExportFormat::fromExtension('.xml'));
        $this->assertEquals(ExportFormat::EXCEL, ExportFormat::fromExtension('.xlsx'));
        
        $this->assertEquals(ExportFormat::JSON, ExportFormat::fromExtension('json'));
        $this->assertEquals(ExportFormat::CSV, ExportFormat::fromExtension('csv'));
        
        $this->expectException(\InvalidArgumentException::class);
        ExportFormat::fromExtension('.txt');
    }

    public function testValidationStateEnum(): void
    {
        $this->assertEquals('valid', ValidationState::VALID->value);
        $this->assertEquals('invalid', ValidationState::INVALID->value);
        $this->assertEquals('pending', ValidationState::PENDING->value);
        $this->assertEquals('unknown', ValidationState::UNKNOWN->value);
        
        $this->assertEquals('[VALID]', ValidationState::VALID->getIndicator());
        $this->assertEquals('[INVALID]', ValidationState::INVALID->getIndicator());
        $this->assertEquals('[PENDING]', ValidationState::PENDING->getIndicator());
        $this->assertEquals('[UNKNOWN]', ValidationState::UNKNOWN->getIndicator());
        
        $this->assertTrue(ValidationState::VALID->isValid());
        $this->assertFalse(ValidationState::INVALID->isValid());
        $this->assertFalse(ValidationState::PENDING->isValid());
        $this->assertFalse(ValidationState::UNKNOWN->isValid());
    }

    public function testConfigurationSourceEnum(): void
    {
        $this->assertEquals('array', ConfigurationSource::ARRAY->value);
        $this->assertEquals('env_file', ConfigurationSource::ENV_FILE->value);
        $this->assertEquals('php_file', ConfigurationSource::PHP_FILE->value);
        $this->assertEquals('json_file', ConfigurationSource::JSON_FILE->value);
        $this->assertEquals('yaml_file', ConfigurationSource::YAML_FILE->value);
        
        $this->assertTrue(ConfigurationSource::ARRAY->isMemoryBased());
        $this->assertFalse(ConfigurationSource::ENV_FILE->isMemoryBased());
        $this->assertFalse(ConfigurationSource::PHP_FILE->isMemoryBased());
        
        $this->assertTrue(ConfigurationSource::ENV_FILE->isFileBased());
        $this->assertTrue(ConfigurationSource::PHP_FILE->isFileBased());
        $this->assertTrue(ConfigurationSource::JSON_FILE->isFileBased());
        $this->assertTrue(ConfigurationSource::YAML_FILE->isFileBased());
        $this->assertFalse(ConfigurationSource::ARRAY->isFileBased());
    }

    public function testConfigurationSourceFromPath(): void
    {
        $this->assertEquals(ConfigurationSource::ENV_FILE, ConfigurationSource::fromPath('.env'));
        $this->assertEquals(ConfigurationSource::ENV_FILE, ConfigurationSource::fromPath('config/.env.local'));
        $this->assertEquals(ConfigurationSource::PHP_FILE, ConfigurationSource::fromPath('config.php'));
        $this->assertEquals(ConfigurationSource::JSON_FILE, ConfigurationSource::fromPath('settings.json'));
        $this->assertEquals(ConfigurationSource::YAML_FILE, ConfigurationSource::fromPath('config.yaml'));
        $this->assertEquals(ConfigurationSource::YAML_FILE, ConfigurationSource::fromPath('config.yml'));
        
        $this->expectException(\InvalidArgumentException::class);
        ConfigurationSource::fromPath('config.ini');
    }

    public function testErrorReportingLevelEnum(): void
    {
        $this->assertEquals('minimal', ErrorReportingLevel::MINIMAL->value);
        $this->assertEquals('standard', ErrorReportingLevel::STANDARD->value);
        $this->assertEquals('detailed', ErrorReportingLevel::DETAILED->value);
        
        $this->assertFalse(ErrorReportingLevel::MINIMAL->includesRequestData());
        $this->assertFalse(ErrorReportingLevel::STANDARD->includesRequestData());
        $this->assertTrue(ErrorReportingLevel::DETAILED->includesRequestData());
        
        $this->assertFalse(ErrorReportingLevel::MINIMAL->includesStackTrace());
        $this->assertFalse(ErrorReportingLevel::STANDARD->includesStackTrace());
        $this->assertTrue(ErrorReportingLevel::DETAILED->includesStackTrace());
        
        $this->assertEquals(ErrorReportingLevel::DETAILED, ErrorReportingLevel::default());
    }

    public function testCurrencyIntervalEnum(): void
    {
        $this->assertEquals('1m', CurrencyInterval::ONE_MINUTE->value);
        $this->assertEquals('5m', CurrencyInterval::FIVE_MINUTES->value);
        $this->assertEquals('15m', CurrencyInterval::FIFTEEN_MINUTES->value);
        $this->assertEquals('1h', CurrencyInterval::ONE_HOUR->value);
        $this->assertEquals('1d', CurrencyInterval::ONE_DAY->value);
        
        $this->assertEquals(60, CurrencyInterval::ONE_MINUTE->getSeconds());
        $this->assertEquals(300, CurrencyInterval::FIVE_MINUTES->getSeconds());
        $this->assertEquals(900, CurrencyInterval::FIFTEEN_MINUTES->getSeconds());
        $this->assertEquals(3600, CurrencyInterval::ONE_HOUR->getSeconds());
        $this->assertEquals(86400, CurrencyInterval::ONE_DAY->getSeconds());
        
        $this->assertTrue(CurrencyInterval::ONE_MINUTE->isIntraday());
        $this->assertTrue(CurrencyInterval::FIVE_MINUTES->isIntraday());
        $this->assertTrue(CurrencyInterval::ONE_HOUR->isIntraday());
        $this->assertFalse(CurrencyInterval::ONE_DAY->isIntraday());
    }

    public function testAnalyticsPeriodEnum(): void
    {
        $this->assertEquals('1d', AnalyticsPeriod::ONE_DAY->value);
        $this->assertEquals('1w', AnalyticsPeriod::ONE_WEEK->value);
        $this->assertEquals('1m', AnalyticsPeriod::ONE_MONTH->value);
        $this->assertEquals('3m', AnalyticsPeriod::THREE_MONTHS->value);
        $this->assertEquals('6m', AnalyticsPeriod::SIX_MONTHS->value);
        $this->assertEquals('1y', AnalyticsPeriod::ONE_YEAR->value);
        
        $this->assertEquals(1, AnalyticsPeriod::ONE_DAY->getDays());
        $this->assertEquals(7, AnalyticsPeriod::ONE_WEEK->getDays());
        $this->assertEquals(30, AnalyticsPeriod::ONE_MONTH->getDays());
        $this->assertEquals(90, AnalyticsPeriod::THREE_MONTHS->getDays());
        $this->assertEquals(180, AnalyticsPeriod::SIX_MONTHS->getDays());
        $this->assertEquals(365, AnalyticsPeriod::ONE_YEAR->getDays());
        
        $this->assertTrue(AnalyticsPeriod::ONE_DAY->isShortTerm());
        $this->assertTrue(AnalyticsPeriod::ONE_WEEK->isShortTerm());
        $this->assertFalse(AnalyticsPeriod::ONE_MONTH->isShortTerm());
        $this->assertFalse(AnalyticsPeriod::ONE_YEAR->isShortTerm());
        
        $this->assertFalse(AnalyticsPeriod::ONE_DAY->isLongTerm());
        $this->assertFalse(AnalyticsPeriod::ONE_WEEK->isLongTerm());
        $this->assertFalse(AnalyticsPeriod::ONE_MONTH->isLongTerm());
        $this->assertTrue(AnalyticsPeriod::SIX_MONTHS->isLongTerm());
        $this->assertTrue(AnalyticsPeriod::ONE_YEAR->isLongTerm());
    }

    public function testMetricTypeEnum(): void
    {
        $this->assertEquals('counter', MetricType::COUNTER->value);
        $this->assertEquals('gauge', MetricType::GAUGE->value);
        $this->assertEquals('histogram', MetricType::HISTOGRAM->value);
        $this->assertEquals('timer', MetricType::TIMER->value);
        
        $this->assertTrue(MetricType::COUNTER->isIncremental());
        $this->assertFalse(MetricType::GAUGE->isIncremental());
        $this->assertFalse(MetricType::HISTOGRAM->isIncremental());
        $this->assertFalse(MetricType::TIMER->isIncremental());
        
        $this->assertFalse(MetricType::COUNTER->isAbsolute());
        $this->assertTrue(MetricType::GAUGE->isAbsolute());
        $this->assertTrue(MetricType::HISTOGRAM->isAbsolute());
        $this->assertTrue(MetricType::TIMER->isAbsolute());
        
        $this->assertTrue(MetricType::TIMER->isTimeBased());
        $this->assertFalse(MetricType::COUNTER->isTimeBased());
        $this->assertFalse(MetricType::GAUGE->isTimeBased());
        $this->assertFalse(MetricType::HISTOGRAM->isTimeBased());
    }

    public function testEnumCases(): void
    {
        $this->assertCount(3, Environment::cases());
        $this->assertCount(4, ExportFormat::cases());
        $this->assertCount(4, ValidationState::cases());
        $this->assertCount(5, ConfigurationSource::cases());
        $this->assertCount(5, ErrorReportingLevel::cases());
        $this->assertCount(5, CurrencyInterval::cases());
        $this->assertCount(6, AnalyticsPeriod::cases());
        $this->assertCount(4, MetricType::cases());
    }

    public function testEnumValues(): void
    {
        $environmentValues = array_map(fn($case) => $case->value, Environment::cases());
        $this->assertContains('development', $environmentValues);
        $this->assertContains('staging', $environmentValues);
        $this->assertContains('production', $environmentValues);
        
        $formatValues = array_map(fn($case) => $case->value, ExportFormat::cases());
        $this->assertContains('json', $formatValues);
        $this->assertContains('csv', $formatValues);
        $this->assertContains('xml', $formatValues);
        $this->assertContains('excel', $formatValues);
    }
}