<?php

declare(strict_types=1);

namespace Wioex\SDK\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use Wioex\SDK\WioexClient;
use Wioex\SDK\Resources\Stocks;
use Wioex\SDK\Resources\Streaming;
use Wioex\SDK\Resources\Screens;
use Wioex\SDK\Resources\Signals;
use Wioex\SDK\Resources\Markets;
use Wioex\SDK\Resources\News;
use Wioex\SDK\Resources\Currency;
use Wioex\SDK\Resources\Account;

class WioexClientTest extends TestCase
{
    private WioexClient $client;

    protected function setUp(): void
    {
        $this->client = new WioexClient([
            'api_key' => 'test-api-key',
            'base_url' => 'https://test-api.wioex.com'
        ]);
    }

    public function testClientInitialization(): void
    {
        $this->assertInstanceOf(WioexClient::class, $this->client);
    }

    public function testClientInitializationWithoutApiKey(): void
    {
        $client = new WioexClient([
            'base_url' => 'https://test-api.wioex.com'
        ]);
        
        $this->assertInstanceOf(WioexClient::class, $client);
    }

    public function testStocksResourceMethod(): void
    {
        $stocks = $this->client->stocks();
        
        $this->assertInstanceOf(Stocks::class, $stocks);
        // Test lazy loading - should return same instance
        $this->assertSame($stocks, $this->client->stocks());
    }

    public function testStreamingResourceMethod(): void
    {
        $streaming = $this->client->streaming();
        
        $this->assertInstanceOf(Streaming::class, $streaming);
        // Test lazy loading
        $this->assertSame($streaming, $this->client->streaming());
    }

    public function testScreensResourceMethod(): void
    {
        $screens = $this->client->screens();
        
        $this->assertInstanceOf(Screens::class, $screens);
        $this->assertSame($screens, $this->client->screens());
    }

    public function testSignalsResourceMethod(): void
    {
        $signals = $this->client->signals();
        
        $this->assertInstanceOf(Signals::class, $signals);
        $this->assertSame($signals, $this->client->signals());
    }

    public function testMarketsResourceMethod(): void
    {
        $markets = $this->client->markets();
        
        $this->assertInstanceOf(Markets::class, $markets);
        $this->assertSame($markets, $this->client->markets());
    }

    public function testNewsResourceMethod(): void
    {
        $news = $this->client->news();
        
        $this->assertInstanceOf(News::class, $news);
        $this->assertSame($news, $this->client->news());
    }

    public function testCurrencyResourceMethod(): void
    {
        $currency = $this->client->currency();
        
        $this->assertInstanceOf(Currency::class, $currency);
        $this->assertSame($currency, $this->client->currency());
    }

    public function testAccountResourceMethod(): void
    {
        $account = $this->client->account();
        
        $this->assertInstanceOf(Account::class, $account);
        $this->assertSame($account, $this->client->account());
    }

    public function testGetVersionStatic(): void
    {
        $version = WioexClient::getVersion();
        
        $this->assertIsString($version);
        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', $version);
    }

    public function testHealthCheck(): void
    {
        $health = $this->client->healthCheck();
        
        $this->assertIsArray($health);
        $this->assertArrayHasKey('client', $health);
        $this->assertArrayHasKey('config', $health);
        $this->assertArrayHasKey('overall_status', $health);
        
        // Test specific health components
        $this->assertIsArray($health['client']);
        $this->assertIsArray($health['config']);
        $this->assertIsBool($health['overall_status']);
    }

    public function testAsyncClientCreation(): void
    {
        $asyncClient = $this->client->async();
        
        $this->assertInstanceOf(\Wioex\SDK\Async\AsyncClient::class, $asyncClient);
    }

    public function testCacheGetterNullByDefault(): void
    {
        $cache = $this->client->getCache();
        
        // Cache is null by default since caching is disabled
        $this->assertNull($cache);
    }

    public function testConfigurationMethods(): void
    {
        // Test fluent interface methods exist and return self
        $this->assertInstanceOf(WioexClient::class, $this->client->enableDebug());
        $this->assertInstanceOf(WioexClient::class, $this->client->disableDebug());
        $this->assertInstanceOf(WioexClient::class, $this->client->setTimeout(60));
        $this->assertInstanceOf(WioexClient::class, $this->client->setApiKey('new-key'));
        $this->assertInstanceOf(WioexClient::class, $this->client->setBaseUrl('https://new-url.com'));
    }

    public function testMethodChaining(): void
    {
        // Test method chaining
        $result = $this->client
            ->enableDebug()
            ->setTimeout(60)
            ->setApiKey('chained-key');
            
        $this->assertInstanceOf(WioexClient::class, $result);
        $this->assertSame($this->client, $result);
    }
}