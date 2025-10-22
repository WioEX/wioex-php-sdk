<?php

declare(strict_types=1);

namespace Wioex\SDK\Tests\Unit\Resources;

use PHPUnit\Framework\TestCase;
use Wioex\SDK\Resources\Stocks;
use Wioex\SDK\Http\Client;
use Wioex\SDK\Http\Response;
use Wioex\SDK\Config;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response as GuzzleResponse;

class StocksTest extends TestCase
{
    private MockHandler $mockHandler;
    private Client $httpClient;
    private Stocks $stocks;

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
        $this->stocks = new Stocks($this->httpClient);
    }

    public function testResourceInitialization(): void
    {
        $this->assertInstanceOf(Stocks::class, $this->stocks);
    }

    public function testSearch(): void
    {
        $responseData = [
            'results' => [
                ['symbol' => 'AAPL', 'name' => 'Apple Inc.'],
                ['symbol' => 'AMZN', 'name' => 'Amazon.com Inc.']
            ]
        ];

        $this->mockHandler->append(
            new GuzzleResponse(200, [], json_encode($responseData))
        );

        $response = $this->stocks->search('APP');

        $this->assertInstanceOf(Response::class, $response);
        $this->assertTrue($response->successful());
        $this->assertEquals($responseData, $response->data());
        $this->assertCount(2, $response->data('results'));
    }

    public function testQuote(): void
    {
        $responseData = [
            'symbol' => 'AAPL',
            'price' => 150.25,
            'change' => 2.50,
            'change_percent' => 1.69,
            'volume' => 45623000
        ];

        $this->mockHandler->append(
            new GuzzleResponse(200, [], json_encode($responseData))
        );

        $response = $this->stocks->quote('AAPL');

        $this->assertTrue($response->successful());
        $this->assertEquals('AAPL', $response->data('symbol'));
        $this->assertEquals(150.25, $response->data('price'));
        $this->assertEquals(2.50, $response->data('change'));
    }

    public function testQuoteMultiple(): void
    {
        $symbols = ['AAPL', 'GOOGL', 'MSFT'];
        $responseData = [
            'quotes' => [
                ['symbol' => 'AAPL', 'price' => 150.25],
                ['symbol' => 'GOOGL', 'price' => 2750.00],
                ['symbol' => 'MSFT', 'price' => 310.50]
            ]
        ];

        $this->mockHandler->append(
            new GuzzleResponse(200, [], json_encode($responseData))
        );

        $response = $this->stocks->quote($symbols);

        $this->assertTrue($response->successful());
        $this->assertCount(3, $response->data('quotes'));
        $this->assertEquals('AAPL', $response->data('quotes.0.symbol'));
    }

    public function testInfo(): void
    {
        $responseData = [
            'symbol' => 'AAPL',
            'company_name' => 'Apple Inc.',
            'sector' => 'Technology',
            'industry' => 'Consumer Electronics',
            'market_cap' => 2450000000000,
            'employees' => 154000
        ];

        $this->mockHandler->append(
            new GuzzleResponse(200, [], json_encode($responseData))
        );

        $response = $this->stocks->info('AAPL');

        $this->assertTrue($response->successful());
        $this->assertEquals('Apple Inc.', $response->data('company_name'));
        $this->assertEquals('Technology', $response->data('sector'));
        $this->assertEquals(154000, $response->data('employees'));
    }

    public function testHistorical(): void
    {
        $responseData = [
            'symbol' => 'AAPL',
            'interval' => '1d',
            'data' => [
                ['date' => '2023-01-01', 'open' => 149.50, 'high' => 151.25, 'low' => 148.75, 'close' => 150.25, 'volume' => 50000000],
                ['date' => '2023-01-02', 'open' => 150.25, 'high' => 152.00, 'low' => 149.50, 'close' => 151.75, 'volume' => 45000000]
            ]
        ];

        $this->mockHandler->append(
            new GuzzleResponse(200, [], json_encode($responseData))
        );

        $response = $this->stocks->historical('AAPL', '1d', '2023-01-01', '2023-01-02');

        $this->assertTrue($response->successful());
        $this->assertEquals('AAPL', $response->data('symbol'));
        $this->assertEquals('1d', $response->data('interval'));
        $this->assertCount(2, $response->data('data'));
    }

    public function testRealtime(): void
    {
        $responseData = [
            'symbol' => 'AAPL',
            'price' => 150.25,
            'timestamp' => time(),
            'bid' => 150.20,
            'ask' => 150.30,
            'volume' => 1000
        ];

        $this->mockHandler->append(
            new GuzzleResponse(200, [], json_encode($responseData))
        );

        $response = $this->stocks->realtime('AAPL');

        $this->assertTrue($response->successful());
        $this->assertEquals('AAPL', $response->data('symbol'));
        $this->assertEquals(150.25, $response->data('price'));
        $this->assertArrayHasKey('bid', $response->data());
        $this->assertArrayHasKey('ask', $response->data());
    }

    public function testNews(): void
    {
        $responseData = [
            'symbol' => 'AAPL',
            'news' => [
                [
                    'title' => 'Apple Reports Strong Q4 Earnings',
                    'summary' => 'Apple exceeded expectations...',
                    'published_at' => '2023-10-26T16:30:00Z',
                    'source' => 'Reuters'
                ]
            ]
        ];

        $this->mockHandler->append(
            new GuzzleResponse(200, [], json_encode($responseData))
        );

        $response = $this->stocks->news('AAPL');

        $this->assertTrue($response->successful());
        $this->assertEquals('AAPL', $response->data('symbol'));
        $this->assertCount(1, $response->data('news'));
        $this->assertEquals('Apple Reports Strong Q4 Earnings', $response->data('news.0.title'));
    }

    public function testAnalytics(): void
    {
        $responseData = [
            'symbol' => 'AAPL',
            'technical_indicators' => [
                'rsi' => 65.4,
                'macd' => 1.25,
                'moving_averages' => [
                    'sma_20' => 149.75,
                    'sma_50' => 147.50,
                    'ema_12' => 150.10
                ]
            ],
            'analyst_ratings' => [
                'strong_buy' => 15,
                'buy' => 12,
                'hold' => 8,
                'sell' => 2,
                'strong_sell' => 0
            ]
        ];

        $this->mockHandler->append(
            new GuzzleResponse(200, [], json_encode($responseData))
        );

        $response = $this->stocks->analytics('AAPL');

        $this->assertTrue($response->successful());
        $this->assertEquals(65.4, $response->data('technical_indicators.rsi'));
        $this->assertEquals(15, $response->data('analyst_ratings.strong_buy'));
    }

    public function testFinancials(): void
    {
        $responseData = [
            'symbol' => 'AAPL',
            'financials' => [
                'revenue' => 394328000000,
                'net_income' => 99803000000,
                'total_assets' => 352755000000,
                'total_debt' => 123749000000,
                'cash_and_equivalents' => 29965000000
            ]
        ];

        $this->mockHandler->append(
            new GuzzleResponse(200, [], json_encode($responseData))
        );

        $response = $this->stocks->financials('AAPL');

        $this->assertTrue($response->successful());
        $this->assertEquals(394328000000, $response->data('financials.revenue'));
        $this->assertEquals(99803000000, $response->data('financials.net_income'));
    }

    public function testErrorHandling(): void
    {
        $errorData = ['error' => 'Symbol not found', 'code' => 'SYMBOL_NOT_FOUND'];
        
        $this->mockHandler->append(
            new GuzzleResponse(404, [], json_encode($errorData))
        );

        $response = $this->stocks->quote('INVALID');

        $this->assertFalse($response->successful());
        $this->assertEquals(404, $response->status());
        $this->assertEquals('Symbol not found', $response->data('error'));
    }

    public function testRateLimitHandling(): void
    {
        $errorData = ['error' => 'Rate limit exceeded', 'retry_after' => 60];
        
        $this->mockHandler->append(
            new GuzzleResponse(429, ['Retry-After' => '60'], json_encode($errorData))
        );

        $response = $this->stocks->quote('AAPL');

        $this->assertFalse($response->successful());
        $this->assertEquals(429, $response->status());
        $this->assertEquals('60', $response->header('Retry-After'));
    }

    public function testParameterValidation(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        
        $this->stocks->historical('AAPL', 'invalid-interval');
    }

    public function testBatchQuotes(): void
    {
        $symbols = ['AAPL', 'GOOGL', 'MSFT', 'AMZN', 'TSLA'];
        $responseData = [
            'quotes' => array_map(function($symbol) {
                return ['symbol' => $symbol, 'price' => rand(100, 300)];
            }, $symbols)
        ];

        $this->mockHandler->append(
            new GuzzleResponse(200, [], json_encode($responseData))
        );

        $response = $this->stocks->batchQuotes($symbols);

        $this->assertTrue($response->successful());
        $this->assertCount(5, $response->data('quotes'));
    }
}