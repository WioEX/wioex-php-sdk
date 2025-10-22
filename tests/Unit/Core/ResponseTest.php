<?php

declare(strict_types=1);

namespace Wioex\SDK\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use Wioex\SDK\Http\Response;
use GuzzleHttp\Psr7\Response as GuzzleResponse;

class ResponseTest extends TestCase
{
    private function createGuzzleResponse(int $status = 200, array $data = []): GuzzleResponse
    {
        return new GuzzleResponse($status, [], json_encode($data));
    }

    public function testSuccessfulResponse(): void
    {
        $data = ['symbol' => 'AAPL', 'price' => 150.25];
        $guzzleResponse = $this->createGuzzleResponse(200, $data);
        
        $response = new Response($guzzleResponse);
        
        $this->assertTrue($response->successful());
        $this->assertFalse($response->failed());
        $this->assertEquals(200, $response->status());
        $this->assertEquals($data, $response->data());
        $this->assertEquals('AAPL', $response->data('symbol'));
        $this->assertEquals(150.25, $response->data('price'));
    }

    public function testFailedResponse(): void
    {
        $errorData = ['error' => 'Not Found', 'code' => 404];
        $guzzleResponse = $this->createGuzzleResponse(404, $errorData);
        
        $response = new Response($guzzleResponse);
        
        $this->assertFalse($response->successful());
        $this->assertTrue($response->failed());
        $this->assertEquals(404, $response->status());
        $this->assertEquals($errorData, $response->data());
    }

    public function testDataWithDefaultValue(): void
    {
        $data = ['existing_key' => 'value'];
        $guzzleResponse = $this->createGuzzleResponse(200, $data);
        
        $response = new Response($guzzleResponse);
        
        $this->assertEquals('value', $response->data('existing_key'));
        $this->assertEquals('default', $response->data('non_existing_key', 'default'));
        $this->assertNull($response->data('non_existing_key'));
    }

    public function testJsonResponse(): void
    {
        $data = ['test' => 'value', 'number' => 42];
        $guzzleResponse = $this->createGuzzleResponse(200, $data);
        
        $response = new Response($guzzleResponse);
        
        $json = $response->json();
        $this->assertJson($json);
        $this->assertEquals(json_encode($data), $json);
    }

    public function testBodyResponse(): void
    {
        $data = ['test' => 'value'];
        $guzzleResponse = $this->createGuzzleResponse(200, $data);
        
        $response = new Response($guzzleResponse);
        
        $body = $response->body();
        $this->assertIsString($body);
        $this->assertEquals(json_encode($data), $body);
    }

    public function testHeadersResponse(): void
    {
        $headers = ['Content-Type' => 'application/json', 'X-Rate-Limit' => '100'];
        $guzzleResponse = new GuzzleResponse(200, $headers, '{}');
        
        $response = new Response($guzzleResponse);
        
        $responseHeaders = $response->headers();
        $this->assertIsArray($responseHeaders);
        $this->assertEquals(['application/json'], $responseHeaders['Content-Type']);
        $this->assertEquals(['100'], $responseHeaders['X-Rate-Limit']);
    }

    public function testSpecificHeader(): void
    {
        $headers = ['X-Rate-Limit-Remaining' => '99'];
        $guzzleResponse = new GuzzleResponse(200, $headers, '{}');
        
        $response = new Response($guzzleResponse);
        
        $rateLimitHeader = $response->header('X-Rate-Limit-Remaining');
        $this->assertEquals('99', $rateLimitHeader);
        
        $nonExistentHeader = $response->header('Non-Existent-Header');
        $this->assertNull($nonExistentHeader);
    }

    public function testStatusChecks(): void
    {
        // Test 2xx success
        $response200 = new Response($this->createGuzzleResponse(200));
        $this->assertTrue($response200->successful());
        $this->assertFalse($response200->failed());

        $response201 = new Response($this->createGuzzleResponse(201));
        $this->assertTrue($response201->successful());
        $this->assertFalse($response201->failed());

        // Test 4xx client error
        $response400 = new Response($this->createGuzzleResponse(400));
        $this->assertFalse($response400->successful());
        $this->assertTrue($response400->failed());

        $response404 = new Response($this->createGuzzleResponse(404));
        $this->assertFalse($response404->successful());
        $this->assertTrue($response404->failed());

        // Test 5xx server error
        $response500 = new Response($this->createGuzzleResponse(500));
        $this->assertFalse($response500->successful());
        $this->assertTrue($response500->failed());
    }

    public function testEmptyResponse(): void
    {
        $guzzleResponse = new GuzzleResponse(204, [], '');
        
        $response = new Response($guzzleResponse);
        
        $this->assertTrue($response->successful());
        $this->assertEquals(204, $response->status());
        $this->assertEquals('', $response->body());
        $this->assertNull($response->data());
    }

    public function testInvalidJsonResponse(): void
    {
        $guzzleResponse = new GuzzleResponse(200, [], 'invalid-json{');
        
        $response = new Response($guzzleResponse);
        
        $this->assertTrue($response->successful());
        $this->assertNull($response->data());
        $this->assertEquals('invalid-json{', $response->body());
    }

    public function testResponseWithArrayData(): void
    {
        $data = [
            'data' => [
                ['id' => 1, 'name' => 'Item 1'],
                ['id' => 2, 'name' => 'Item 2']
            ],
            'meta' => ['total' => 2]
        ];
        $guzzleResponse = $this->createGuzzleResponse(200, $data);
        
        $response = new Response($guzzleResponse);
        
        $this->assertEquals($data, $response->data());
        $this->assertEquals($data['data'], $response->data('data'));
        $this->assertEquals($data['meta'], $response->data('meta'));
        $this->assertEquals(2, $response->data('meta.total'));
    }

    public function testResponseWithNestedData(): void
    {
        $data = [
            'user' => [
                'profile' => [
                    'name' => 'John Doe',
                    'settings' => [
                        'theme' => 'dark'
                    ]
                ]
            ]
        ];
        $guzzleResponse = $this->createGuzzleResponse(200, $data);
        
        $response = new Response($guzzleResponse);
        
        $this->assertEquals('John Doe', $response->data('user.profile.name'));
        $this->assertEquals('dark', $response->data('user.profile.settings.theme'));
        $this->assertNull($response->data('user.profile.settings.nonexistent'));
    }

    public function testToString(): void
    {
        $data = ['test' => 'value'];
        $guzzleResponse = $this->createGuzzleResponse(200, $data);
        
        $response = new Response($guzzleResponse);
        
        $this->assertEquals(json_encode($data), (string) $response);
    }
}