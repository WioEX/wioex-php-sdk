<?php

declare(strict_types=1);

namespace Wioex\SDK\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use Wioex\SDK\Config;
use Wioex\SDK\Enums\ErrorReportingLevel;
use Wioex\SDK\Enums\Environment;

class ConfigTest extends TestCase
{
    public function testDefaultConfiguration(): void
    {
        $config = new Config();
        
        $this->assertNull($config->getApiKey());
        $this->assertEquals('https://api.wioex.com', $config->getBaseUrl());
        $this->assertEquals(30, $config->getTimeout());
        $this->assertEquals(10, $config->getConnectTimeout());
        $this->assertFalse($config->isCacheEnabled());
        $this->assertFalse($config->isMonitoringEnabled());
    }

    public function testConfigurationWithApiKey(): void
    {
        $config = new Config(['api_key' => 'test-key']);
        
        $this->assertEquals('test-key', $config->getApiKey());
        $this->assertTrue($config->hasApiKey());
    }

    public function testConfigurationWithoutApiKey(): void
    {
        $config = new Config();
        
        $this->assertNull($config->getApiKey());
        $this->assertFalse($config->hasApiKey());
    }

    public function testCustomBaseUrl(): void
    {
        $config = new Config(['base_url' => 'https://custom.api.com']);
        
        $this->assertEquals('https://custom.api.com', $config->getBaseUrl());
    }

    public function testCustomTimeouts(): void
    {
        $config = new Config([
            'timeout' => 60,
            'connect_timeout' => 20
        ]);
        
        $this->assertEquals(60, $config->getTimeout());
        $this->assertEquals(20, $config->getConnectTimeout());
    }

    public function testRetryConfiguration(): void
    {
        $retryConfig = [
            'times' => 5,
            'delay' => 2000,
            'multiplier' => 3,
            'max_delay' => 30000
        ];
        
        $config = new Config(['retry' => $retryConfig]);
        
        $this->assertEquals($retryConfig, $config->getRetryConfig());
    }

    public function testErrorReportingConfiguration(): void
    {
        $config = new Config([
            'error_reporting' => true,
            'error_reporting_level' => ErrorReportingLevel::DETAILED,
            'include_stack_trace' => true,
            'include_request_data' => true,
            'include_response_data' => false
        ]);
        
        $this->assertTrue($config->isErrorReportingEnabled());
        $this->assertEquals(ErrorReportingLevel::DETAILED, $config->getErrorReportingLevel());
        $this->assertTrue($config->shouldIncludeStackTrace());
        $this->assertTrue($config->shouldIncludeRequestData());
        $this->assertFalse($config->shouldIncludeResponseData());
    }

    public function testErrorReportingLevelFromString(): void
    {
        $config = new Config(['error_reporting_level' => 'standard']);
        
        $this->assertEquals(ErrorReportingLevel::STANDARD, $config->getErrorReportingLevel());
    }

    public function testRateLimitConfiguration(): void
    {
        $rateLimitConfig = [
            'enabled' => true,
            'requests' => 100,
            'window' => 60,
            'strategy' => 'sliding_window',
            'burst_allowance' => 10
        ];
        
        $config = new Config(['rate_limit' => $rateLimitConfig]);
        
        $this->assertTrue($config->isRateLimitingEnabled());
        $this->assertEquals($rateLimitConfig, $config->getRateLimitConfig());
    }

    public function testEnhancedRetryConfiguration(): void
    {
        $enhancedRetryConfig = [
            'enabled' => true,
            'attempts' => 5,
            'backoff' => 'exponential',
            'base_delay' => 1000,
            'max_delay' => 30000,
            'jitter' => true,
            'exponential_base' => 2.5
        ];
        
        $config = new Config(['enhanced_retry' => $enhancedRetryConfig]);
        
        $this->assertEquals($enhancedRetryConfig, $config->getEnhancedRetryConfig());
    }

    public function testCustomHeaders(): void
    {
        $headers = [
            'User-Agent' => 'Custom SDK/1.0',
            'X-Custom-Header' => 'custom-value'
        ];
        
        $config = new Config(['headers' => $headers]);
        $configHeaders = $config->getHeaders();
        
        $this->assertEquals('Custom SDK/1.0', $configHeaders['User-Agent']);
        $this->assertEquals('custom-value', $configHeaders['X-Custom-Header']);
        $this->assertArrayHasKey('Accept', $configHeaders); // Default header added by Config
    }

    public function testApiKeyIdentification(): void
    {
        $config = new Config(['api_key' => 'test-api-key-123']);
        
        $identification = $config->getApiKeyIdentification();
        
        $this->assertIsString($identification);
        $this->assertNotEquals('test-api-key-123', $identification);
        $this->assertEquals(16, strlen($identification));
    }

    public function testApiKeyIdentificationWithoutKey(): void
    {
        $config = new Config();
        
        $identification = $config->getApiKeyIdentification();
        
        $this->assertNull($identification);
    }

    public function testToArray(): void
    {
        $options = [
            'api_key' => 'test-key',
            'base_url' => 'https://test.api.com',
            'timeout' => 45,
            'error_reporting' => true
        ];
        
        $config = new Config($options);
        $configArray = $config->toArray();
        
        $this->assertIsArray($configArray);
        $this->assertEquals('test-key', $configArray['api_key']);
        $this->assertEquals('https://test.api.com', $configArray['base_url']);
        $this->assertEquals(45, $configArray['timeout']);
        $this->assertTrue($configArray['error_reporting']);
    }

    public function testCacheConfiguration(): void
    {
        $config = new Config();
        
        // Default cache configuration
        $this->assertFalse($config->isCacheEnabled());
        $this->assertIsArray($config->getCacheConfig());
        
        $cacheConfig = $config->getCacheConfig();
        $this->assertEquals('memory', $cacheConfig['driver']);
        $this->assertEquals(300, $cacheConfig['ttl']);
    }

    public function testMonitoringConfiguration(): void
    {
        $config = new Config();
        
        // Default monitoring configuration
        $this->assertFalse($config->isMonitoringEnabled());
        $this->assertIsArray($config->getMonitoringConfig());
        
        $monitoringConfig = $config->getMonitoringConfig();
        $this->assertFalse($monitoringConfig['enabled']);
        $this->assertEquals(60, $monitoringConfig['metrics_interval']);
    }

    public function testEnvironmentConfiguration(): void
    {
        $config = new Config();
        
        $environment = $config->getEnvironment();
        
        $this->assertInstanceOf(Environment::class, $environment);
        $this->assertEquals(Environment::PRODUCTION, $environment);
    }

    public function testInvalidTimeoutThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        
        new Config(['timeout' => -1]);
    }

    public function testInvalidConnectTimeoutThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        
        new Config(['connect_timeout' => 0]);
    }
}