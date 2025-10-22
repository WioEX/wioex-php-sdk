<?php

declare(strict_types=1);

namespace Wioex\SDK;

use Wioex\SDK\Http\Client;
use Wioex\SDK\Resources\Account;
use Wioex\SDK\Resources\Currency;
use Wioex\SDK\Resources\Markets;
use Wioex\SDK\Resources\News;
use Wioex\SDK\Resources\Screens;
use Wioex\SDK\Resources\Signals;
use Wioex\SDK\Resources\Stocks;
use Wioex\SDK\Resources\Streaming;

class WioexClient
{
    private Config $config;
    private Client $httpClient;

    private ?Stocks $stocks = null;
    private ?Screens $screens = null;
    private ?Signals $signals = null;
    private ?Markets $markets = null;
    private ?News $news = null;
    private ?Currency $currency = null;
    private ?Account $account = null;
    private ?Streaming $streaming = null;

    /**
     * Create a new WioEX API client instance
     *
     * @param array{
     *     api_key?: string,
     *     base_url?: string,
     *     timeout?: int,
     *     connect_timeout?: int,
     *     retry?: array,
     *     headers?: array
     * } $options Configuration options:
     *   - api_key: string (required) Your WioEX API key
     *   - base_url: string (optional) API base URL, defaults to https://api.wioex.com
     *   - timeout: int (optional) Request timeout in seconds, defaults to 30
     *   - connect_timeout: int (optional) Connection timeout in seconds, defaults to 10
     *   - retry: array (optional) Retry configuration:
     *       - times: int (default: 3) Number of retry attempts
     *       - delay: int (default: 100) Initial delay in milliseconds
     *       - multiplier: int (default: 2) Exponential backoff multiplier
     *       - max_delay: int (default: 5000) Maximum delay in milliseconds
     *   - headers: array (optional) Additional HTTP headers
     *
     * @throws \InvalidArgumentException If required options are missing or invalid
     *
     * @example
     * ```php
     * $client = new WioexClient([
     *     'api_key' => 'your-api-key-here',
     *     'timeout' => 30,
     *     'retry' => ['times' => 3]
     * ]);
     * ```
     */
    public function __construct(array $options)
    {
        $this->config = new Config($options);
        $this->httpClient = new Client($this->config);
    }

    /**
     * Access stocks-related endpoints
     *
     * @return Stocks
     *
     * @example
     * ```php
     * $client->stocks()->search('AAPL');
     * $client->stocks()->quote('AAPL');
     * $client->stocks()->info('AAPL');
     * ```
     */
    public function stocks(): Stocks
    {
        if ($this->stocks === null) {
            $this->stocks = new Stocks($this->httpClient);
        }

        return $this->stocks;
    }

    /**
     * Access stock screening and market movers endpoints
     *
     * @return Screens
     *
     * @example
     * ```php
     * $client->screens()->gainers();
     * $client->screens()->losers();
     * $client->screens()->active(50);
     * ```
     */
    public function screens(): Screens
    {
        if ($this->screens === null) {
            $this->screens = new Screens($this->httpClient);
        }

        return $this->screens;
    }

    /**
     * Access trading signals endpoints
     *
     * @return Signals
     *
     * @example
     * ```php
     * $client->signals()->active(['symbol' => 'AAPL']);
     * $client->signals()->history(['days' => 7]);
     * ```
     */
    public function signals(): Signals
    {
        if ($this->signals === null) {
            $this->signals = new Signals($this->httpClient);
        }

        return $this->signals;
    }

    /**
     * Access market status and trading hours endpoints
     *
     * @return Markets
     *
     * @example
     * ```php
     * $status = $client->markets()->status();
     * echo "NYSE is " . ($status['markets']['nyse']['is_open'] ? 'open' : 'closed');
     * ```
     */
    public function markets(): Markets
    {
        if ($this->markets === null) {
            $this->markets = new Markets($this->httpClient);
        }

        return $this->markets;
    }

    /**
     * Access news-related endpoints
     *
     * @return News
     *
     * @example
     * ```php
     * $client->news()->latest('AAPL');
     * $client->news()->companyAnalysis('AAPL');
     * ```
     */
    public function news(): News
    {
        if ($this->news === null) {
            $this->news = new News($this->httpClient);
        }

        return $this->news;
    }

    /**
     * Access currency exchange rate endpoints
     *
     * @return Currency
     *
     * @example
     * ```php
     * $client->currency()->baseUsd();
     * $client->currency()->graph('USD', 'EUR', '1d');
     * $client->currency()->calculator('USD', 'EUR', 100);
     * ```
     */
    public function currency(): Currency
    {
        if ($this->currency === null) {
            $this->currency = new Currency($this->httpClient);
        }

        return $this->currency;
    }

    /**
     * Access account management endpoints
     *
     * @return Account
     *
     * @example
     * ```php
     * $client->account()->balance();
     * $client->account()->usage(30);
     * $client->account()->analytics('month');
     * ```
     */
    public function account(): Account
    {
        if ($this->account === null) {
            $this->account = new Account($this->httpClient);
        }

        return $this->account;
    }

    /**
     * Access WebSocket streaming endpoints
     *
     * @return Streaming
     *
     * @example
     * ```php
     * $token = $client->streaming()->getToken();
     * if ($token->successful()) {
     *     $auth = $token['token'];
     *     $wsUrl = $token['websocket_url'];
     * }
     * ```
     */
    public function streaming(): Streaming
    {
        if ($this->streaming === null) {
            $this->streaming = new Streaming($this->httpClient);
        }

        return $this->streaming;
    }

    /**
     * Get the configuration instance
     *
     * @return Config
     */
    public function getConfig(): Config
    {
        return $this->config;
    }

    /**
     * Get SDK version
     *
     * @return string
     */
    public static function getVersion(): string
    {
        return '1.3.0';
    }
}
