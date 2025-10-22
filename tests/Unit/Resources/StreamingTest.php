<?php

declare(strict_types=1);

namespace Wioex\SDK\Tests\Unit\Resources;

use PHPUnit\Framework\TestCase;
use Wioex\SDK\Resources\Streaming;
use Wioex\SDK\Http\Client;
use Wioex\SDK\Http\Response;
use Wioex\SDK\Config;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response as GuzzleResponse;

class StreamingTest extends TestCase
{
    private MockHandler $mockHandler;
    private Client $httpClient;
    private Streaming $streaming;

    protected function setUp(): void
    {
        $config = new Config([
            'api_key' => 'test-api-key',
            'base_url' => 'https://test-api.wioex.com'
        ]);

        $this->mockHandler = new MockHandler();
        $handlerStack = HandlerStack::create($this->mockHandler);
        $guzzleClient = new GuzzleClient(['handler' => $handlerStack]);
        
        $this->httpClient = new Client($config, $guzzleClient);
        $this->streaming = new Streaming($this->httpClient);
    }

    public function testResourceInitialization(): void
    {
        $this->assertInstanceOf(Streaming::class, $this->streaming);
    }

    public function testGetToken(): void
    {
        $responseData = [
            'token' => 'ws_token_123456789',
            'websocket_url' => 'wss://ws.wioex.com/v1/stream',
            'expires_at' => time() + 3600,
            'permissions' => ['quotes', 'trades', 'news']
        ];

        $this->mockHandler->append(
            new GuzzleResponse(200, [], json_encode($responseData))
        );

        $response = $this->streaming->getToken();

        $this->assertInstanceOf(Response::class, $response);
        $this->assertTrue($response->successful());
        $this->assertEquals('ws_token_123456789', $response->data('token'));
        $this->assertEquals('wss://ws.wioex.com/v1/stream', $response->data('websocket_url'));
        $this->assertIsArray($response->data('permissions'));
    }

    public function testGetTokenWithScopes(): void
    {
        $scopes = ['quotes', 'trades'];
        $responseData = [
            'token' => 'ws_token_scoped_123',
            'websocket_url' => 'wss://ws.wioex.com/v1/stream',
            'expires_at' => time() + 3600,
            'permissions' => $scopes
        ];

        $this->mockHandler->append(
            new GuzzleResponse(200, [], json_encode($responseData))
        );

        $response = $this->streaming->getToken($scopes);

        $this->assertTrue($response->successful());
        $this->assertEquals($scopes, $response->data('permissions'));
    }

    public function testSubscribe(): void
    {
        $responseData = [
            'subscription_id' => 'sub_123456',
            'symbol' => 'AAPL',
            'channels' => ['quotes', 'trades'],
            'status' => 'active'
        ];

        $this->mockHandler->append(
            new GuzzleResponse(200, [], json_encode($responseData))
        );

        $response = $this->streaming->subscribe('AAPL', ['quotes', 'trades']);

        $this->assertTrue($response->successful());
        $this->assertEquals('sub_123456', $response->data('subscription_id'));
        $this->assertEquals('AAPL', $response->data('symbol'));
        $this->assertEquals('active', $response->data('status'));
    }

    public function testUnsubscribe(): void
    {
        $subscriptionId = 'sub_123456';
        $responseData = [
            'subscription_id' => $subscriptionId,
            'status' => 'cancelled',
            'message' => 'Successfully unsubscribed'
        ];

        $this->mockHandler->append(
            new GuzzleResponse(200, [], json_encode($responseData))
        );

        $response = $this->streaming->unsubscribe($subscriptionId);

        $this->assertTrue($response->successful());
        $this->assertEquals('cancelled', $response->data('status'));
        $this->assertEquals('Successfully unsubscribed', $response->data('message'));
    }

    public function testGetSubscriptions(): void
    {
        $responseData = [
            'subscriptions' => [
                [
                    'subscription_id' => 'sub_123456',
                    'symbol' => 'AAPL',
                    'channels' => ['quotes'],
                    'status' => 'active'
                ],
                [
                    'subscription_id' => 'sub_789012',
                    'symbol' => 'GOOGL',
                    'channels' => ['trades'],
                    'status' => 'active'
                ]
            ],
            'total' => 2
        ];

        $this->mockHandler->append(
            new GuzzleResponse(200, [], json_encode($responseData))
        );

        $response = $this->streaming->getSubscriptions();

        $this->assertTrue($response->successful());
        $this->assertCount(2, $response->data('subscriptions'));
        $this->assertEquals(2, $response->data('total'));
        $this->assertEquals('AAPL', $response->data('subscriptions.0.symbol'));
    }

    public function testGetChannels(): void
    {
        $responseData = [
            'channels' => [
                [
                    'name' => 'quotes',
                    'description' => 'Real-time stock quotes',
                    'rate_limit' => 100,
                    'requires_permission' => true
                ],
                [
                    'name' => 'trades',
                    'description' => 'Real-time trade data',
                    'rate_limit' => 50,
                    'requires_permission' => true
                ],
                [
                    'name' => 'news',
                    'description' => 'Breaking news updates',
                    'rate_limit' => 10,
                    'requires_permission' => false
                ]
            ]
        ];

        $this->mockHandler->append(
            new GuzzleResponse(200, [], json_encode($responseData))
        );

        $response = $this->streaming->getChannels();

        $this->assertTrue($response->successful());
        $this->assertCount(3, $response->data('channels'));
        $this->assertEquals('quotes', $response->data('channels.0.name'));
        $this->assertEquals(100, $response->data('channels.0.rate_limit'));
    }

    public function testRefreshToken(): void
    {
        $oldToken = 'old_token_123';
        $responseData = [
            'token' => 'new_token_456',
            'websocket_url' => 'wss://ws.wioex.com/v1/stream',
            'expires_at' => time() + 3600,
            'previous_token' => $oldToken
        ];

        $this->mockHandler->append(
            new GuzzleResponse(200, [], json_encode($responseData))
        );

        $response = $this->streaming->refreshToken($oldToken);

        $this->assertTrue($response->successful());
        $this->assertEquals('new_token_456', $response->data('token'));
        $this->assertEquals($oldToken, $response->data('previous_token'));
    }

    public function testRevokeToken(): void
    {
        $token = 'token_to_revoke_123';
        $responseData = [
            'token' => $token,
            'status' => 'revoked',
            'message' => 'Token successfully revoked'
        ];

        $this->mockHandler->append(
            new GuzzleResponse(200, [], json_encode($responseData))
        );

        $response = $this->streaming->revokeToken($token);

        $this->assertTrue($response->successful());
        $this->assertEquals('revoked', $response->data('status'));
        $this->assertEquals('Token successfully revoked', $response->data('message'));
    }

    public function testGetConnectionInfo(): void
    {
        $responseData = [
            'websocket_url' => 'wss://ws.wioex.com/v1/stream',
            'max_connections' => 5,
            'heartbeat_interval' => 30,
            'supported_protocols' => ['websocket'],
            'compression' => ['gzip', 'deflate']
        ];

        $this->mockHandler->append(
            new GuzzleResponse(200, [], json_encode($responseData))
        );

        $response = $this->streaming->getConnectionInfo();

        $this->assertTrue($response->successful());
        $this->assertEquals('wss://ws.wioex.com/v1/stream', $response->data('websocket_url'));
        $this->assertEquals(5, $response->data('max_connections'));
        $this->assertEquals(30, $response->data('heartbeat_interval'));
    }

    public function testValidateToken(): void
    {
        $token = 'token_to_validate_123';
        $responseData = [
            'valid' => true,
            'expires_at' => time() + 1800,
            'permissions' => ['quotes', 'trades'],
            'remaining_time' => 1800
        ];

        $this->mockHandler->append(
            new GuzzleResponse(200, [], json_encode($responseData))
        );

        $response = $this->streaming->validateToken($token);

        $this->assertTrue($response->successful());
        $this->assertTrue($response->data('valid'));
        $this->assertEquals(1800, $response->data('remaining_time'));
        $this->assertIsArray($response->data('permissions'));
    }

    public function testGetUsageStats(): void
    {
        $responseData = [
            'current_connections' => 2,
            'max_connections' => 5,
            'messages_sent' => 15420,
            'messages_received' => 189654,
            'bytes_transferred' => 45231890,
            'uptime' => 3650,
            'active_subscriptions' => 8
        ];

        $this->mockHandler->append(
            new GuzzleResponse(200, [], json_encode($responseData))
        );

        $response = $this->streaming->getUsageStats();

        $this->assertTrue($response->successful());
        $this->assertEquals(2, $response->data('current_connections'));
        $this->assertEquals(15420, $response->data('messages_sent'));
        $this->assertEquals(8, $response->data('active_subscriptions'));
    }

    public function testErrorHandling(): void
    {
        $errorData = ['error' => 'Invalid channel', 'code' => 'INVALID_CHANNEL'];
        
        $this->mockHandler->append(
            new GuzzleResponse(400, [], json_encode($errorData))
        );

        $response = $this->streaming->subscribe('AAPL', ['invalid_channel']);

        $this->assertFalse($response->successful());
        $this->assertEquals(400, $response->status());
        $this->assertEquals('Invalid channel', $response->data('error'));
    }

    public function testUnauthorizedAccess(): void
    {
        $errorData = ['error' => 'Unauthorized', 'code' => 'UNAUTHORIZED'];
        
        $this->mockHandler->append(
            new GuzzleResponse(401, [], json_encode($errorData))
        );

        $response = $this->streaming->getToken();

        $this->assertFalse($response->successful());
        $this->assertEquals(401, $response->status());
        $this->assertEquals('Unauthorized', $response->data('error'));
    }

    public function testServiceUnavailable(): void
    {
        $errorData = ['error' => 'Service temporarily unavailable', 'retry_after' => 300];
        
        $this->mockHandler->append(
            new GuzzleResponse(503, ['Retry-After' => '300'], json_encode($errorData))
        );

        $response = $this->streaming->getToken();

        $this->assertFalse($response->successful());
        $this->assertEquals(503, $response->status());
        $this->assertEquals('300', $response->header('Retry-After'));
    }

    public function testBatchSubscribe(): void
    {
        $symbols = ['AAPL', 'GOOGL', 'MSFT'];
        $channels = ['quotes'];
        
        $responseData = [
            'subscriptions' => [
                ['subscription_id' => 'sub_1', 'symbol' => 'AAPL', 'status' => 'active'],
                ['subscription_id' => 'sub_2', 'symbol' => 'GOOGL', 'status' => 'active'],
                ['subscription_id' => 'sub_3', 'symbol' => 'MSFT', 'status' => 'active']
            ],
            'successful' => 3,
            'failed' => 0
        ];

        $this->mockHandler->append(
            new GuzzleResponse(200, [], json_encode($responseData))
        );

        $response = $this->streaming->batchSubscribe($symbols, $channels);

        $this->assertTrue($response->successful());
        $this->assertCount(3, $response->data('subscriptions'));
        $this->assertEquals(3, $response->data('successful'));
        $this->assertEquals(0, $response->data('failed'));
    }
}