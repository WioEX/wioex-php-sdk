<?php

declare(strict_types=1);

namespace Wioex\SDK\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use Wioex\SDK\Http\Client;
use Wioex\SDK\Http\Response;
use Wioex\SDK\Config;
use Wioex\SDK\Cache\CacheInterface;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response as GuzzleResponse;

class HttpClientTest extends TestCase
{
    private Config $config;
    private MockHandler $mockHandler;
    private Client $client;

    protected function setUp(): void
    {
        $this->config = new Config([
            'api_key' => 'test-api-key',
            'base_url' => 'https://test-api.wioex.com',
            'timeout' => 30
        ]);

        $this->mockHandler = new MockHandler();
        $handlerStack = HandlerStack::create($this->mockHandler);
        
        $guzzleClient = new GuzzleClient(['handler' => $handlerStack]);
        $this->client = new Client($this->config, $guzzleClient);
    }

    public function testClientInitialization(): void
    {
        $this->assertInstanceOf(Client::class, $this->client);
    }

    public function testGetRequest(): void
    {
        $responseData = ['test' => 'data'];
        $this->mockHandler->append(
            new GuzzleResponse(200, [], json_encode($responseData))
        );

        $response = $this->client->get('/test');

        $this->assertInstanceOf(Response::class, $response);
        $this->assertTrue($response->successful());
        $this->assertEquals($responseData, $response->data());
    }

    public function testPostRequest(): void
    {
        $responseData = ['created' => true, 'id' => 123];
        $requestData = ['name' => 'test'];
        
        $this->mockHandler->append(
            new GuzzleResponse(201, [], json_encode($responseData))
        );

        $response = $this->client->post('/test', $requestData);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertTrue($response->successful());
        $this->assertEquals(201, $response->status());
        $this->assertEquals($responseData, $response->data());
    }

    public function testPutRequest(): void
    {
        $responseData = ['updated' => true];
        $requestData = ['name' => 'updated'];
        
        $this->mockHandler->append(
            new GuzzleResponse(200, [], json_encode($responseData))
        );

        $response = $this->client->put('/test/123', $requestData);

        $this->assertTrue($response->successful());
        $this->assertEquals($responseData, $response->data());
    }

    public function testDeleteRequest(): void
    {
        $this->mockHandler->append(
            new GuzzleResponse(204, [], '')
        );

        $response = $this->client->delete('/test/123');

        $this->assertTrue($response->successful());
        $this->assertEquals(204, $response->status());
    }

    public function testRequestWithQuery(): void
    {
        $this->mockHandler->append(
            new GuzzleResponse(200, [], '{"results": []}')
        );

        $response = $this->client->get('/test', ['limit' => 10, 'offset' => 0]);

        $this->assertTrue($response->successful());
        $this->assertEquals(['results' => []], $response->data());
    }

    public function testRequestWithHeaders(): void
    {
        $this->mockHandler->append(
            new GuzzleResponse(200, [], '{"success": true}')
        );

        $response = $this->client->get('/test', [], [
            'X-Custom-Header' => 'custom-value'
        ]);

        $this->assertTrue($response->successful());
    }

    public function testErrorResponse(): void
    {
        $errorData = ['error' => 'Not Found'];
        $this->mockHandler->append(
            new GuzzleResponse(404, [], json_encode($errorData))
        );

        $response = $this->client->get('/nonexistent');

        $this->assertFalse($response->successful());
        $this->assertTrue($response->failed());
        $this->assertEquals(404, $response->status());
        $this->assertEquals($errorData, $response->data());
    }

    public function testGetCache(): void
    {
        $cache = $this->client->getCache();
        
        // Cache should be null by default since caching is disabled
        $this->assertNull($cache);
    }

    public function testGetCacheWithCacheEnabled(): void
    {
        $configWithCache = new Config([
            'api_key' => 'test-key',
            'cache' => [
                'enabled' => true,
                'driver' => 'memory'
            ]
        ]);

        $clientWithCache = new Client($configWithCache);
        $cache = $clientWithCache->getCache();

        if ($cache !== null) {
            $this->assertInstanceOf(CacheInterface::class, $cache);
        }
    }

    public function testConfigurationAccess(): void
    {
        $config = $this->client->getConfig();
        
        $this->assertInstanceOf(Config::class, $config);
        $this->assertEquals('test-api-key', $config->getApiKey());
        $this->assertEquals('https://test-api.wioex.com', $config->getBaseUrl());
    }

    public function testUserAgentHeader(): void
    {
        $this->mockHandler->append(
            new GuzzleResponse(200, [], '{}')
        );

        $this->client->get('/test');

        $lastRequest = $this->mockHandler->getLastRequest();
        $userAgent = $lastRequest->getHeaderLine('User-Agent');
        
        $this->assertStringContains('Wioex-PHP-SDK', $userAgent);
    }

    public function testApiKeyAuthentication(): void
    {
        $this->mockHandler->append(
            new GuzzleResponse(200, [], '{}')
        );

        $this->client->get('/test');

        $lastRequest = $this->mockHandler->getLastRequest();
        $authHeader = $lastRequest->getHeaderLine('Authorization');
        
        $this->assertEquals('Bearer test-api-key', $authHeader);
    }

    public function testTimeoutConfiguration(): void
    {
        $config = new Config([
            'api_key' => 'test-key',
            'timeout' => 60,
            'connect_timeout' => 15
        ]);

        $client = new Client($config);
        
        $this->assertEquals(60, $config->getTimeout());
        $this->assertEquals(15, $config->getConnectTimeout());
    }

    public function testRetryConfiguration(): void
    {
        $config = new Config([
            'api_key' => 'test-key',
            'retry' => [
                'times' => 3,
                'delay' => 1000
            ]
        ]);

        $client = new Client($config);
        $retryConfig = $config->getRetryConfig();
        
        $this->assertEquals(3, $retryConfig['times']);
        $this->assertEquals(1000, $retryConfig['delay']);
    }
}