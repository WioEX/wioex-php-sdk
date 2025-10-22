<?php

declare(strict_types=1);

namespace Wioex\SDK\Http;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException as GuzzleRequestException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Wioex\SDK\Config;
use Wioex\SDK\ErrorReporter;
use Wioex\SDK\Exceptions\AuthenticationException;
use Wioex\SDK\Exceptions\RateLimitException;
use Wioex\SDK\Exceptions\RequestException;
use Wioex\SDK\Exceptions\ServerException;
use Wioex\SDK\Exceptions\ValidationException;

class Client
{
    private Config $config;
    private GuzzleClient $client;
    private ErrorReporter $errorReporter;

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->client = $this->createGuzzleClient();
        $this->errorReporter = new ErrorReporter($config);
    }

    private function createGuzzleClient(): GuzzleClient
    {
        $stack = HandlerStack::create();

        // Add retry middleware
        $retryHandler = new RetryHandler($this->config->getRetryConfig());
        /** @psalm-suppress MixedArgumentTypeCoercion */
        $stack->push(Middleware::retry(
            function (
                int $retries,
                RequestInterface $request,
                ?ResponseInterface $response,
                ?\Exception $exception
            ) use ($retryHandler) {
                return $retryHandler($retries, $request, $response, $exception);
            }
        ));

        return new GuzzleClient([
            'base_uri' => $this->config->getBaseUrl(),
            'timeout' => $this->config->getTimeout(),
            'connect_timeout' => $this->config->getConnectTimeout(),
            'headers' => $this->config->getHeaders(),
            'handler' => $stack,
            'http_errors' => false, // We handle errors manually
        ]);
    }

    public function get(string $path, array $query = []): Response
    {
        // Only add API key if it's set (for authenticated endpoints)
        if ($this->config->hasApiKey()) {
            $query['api_key'] = $this->config->getApiKey();
        }

        return $this->request('GET', $path, ['query' => $query]);
    }

    public function post(string $path, array $data = []): Response
    {
        $options = ['json' => $data];

        // Only add API key if it's set (for authenticated endpoints)
        if ($this->config->hasApiKey()) {
            $options['query'] = ['api_key' => $this->config->getApiKey()];
        }

        return $this->request('POST', $path, $options);
    }

    public function put(string $path, array $data = []): Response
    {
        $options = ['json' => $data];

        // Only add API key if it's set (for authenticated endpoints)
        if ($this->config->hasApiKey()) {
            $options['query'] = ['api_key' => $this->config->getApiKey()];
        }

        return $this->request('PUT', $path, $options);
    }

    public function delete(string $path, array $query = []): Response
    {
        // Only add API key if it's set (for authenticated endpoints)
        if ($this->config->hasApiKey()) {
            $query['api_key'] = $this->config->getApiKey();
        }

        return $this->request('DELETE', $path, ['query' => $query]);
    }

    private function request(string $method, string $path, array $options = []): Response
    {
        try {
            $response = $this->client->request($method, $path, $options);

            // Handle HTTP errors
            $this->handleResponseErrors($response, $method, $path, $options);

            return new Response($response);
        } catch (ConnectException $e) {
            $exception = RequestException::connectionFailed($e->getMessage());

            // Report error to WioEX if enabled
            $context = [
                'method' => $method,
                'path' => $path,
                'error_type' => 'connection_error',
                'category' => $this->extractCategory($path),
            ];

            // Add request data if configured
            if ($this->config->shouldIncludeRequestData()) {
                $context['request_data'] = $this->extractRequestData($method, $path, $options);
            }

            $this->errorReporter->report($exception, $context);

            throw $exception;
        } catch (GuzzleRequestException $e) {
            $response = $e->getResponse();
            if ($response !== null) {
                $this->handleResponseErrors($response, $method, $path, $options);
            }

            $exception = RequestException::networkError($e->getMessage());

            // Report error to WioEX if enabled
            $context = [
                'method' => $method,
                'path' => $path,
                'error_type' => 'network_error',
                'status_code' => $response !== null ? $response->getStatusCode() : null,
                'category' => $this->extractCategory($path),
            ];

            // Add request/response data if configured
            if ($this->config->shouldIncludeRequestData()) {
                $context['request_data'] = $this->extractRequestData($method, $path, $options);
            }

            if ($response !== null && $this->config->shouldIncludeResponseData()) {
                $context['response_data'] = [
                    'status' => $response->getStatusCode(),
                    'body' => (string) $response->getBody(),
                    'headers' => $response->getHeaders(),
                ];
            }

            $this->errorReporter->report($exception, $context);

            throw $exception;
        }
    }

    private function handleResponseErrors(
        \Psr\Http\Message\ResponseInterface $response,
        string $method = '',
        string $path = '',
        array $options = []
    ): void {
        $statusCode = $response->getStatusCode();
        $body = (string) $response->getBody();
        /** @var mixed $data */
        $data = json_decode($body, true);

        // Extract error message with type safety (supports both old and new error formats)
        $errorMessage = 'Unknown error occurred';
        if (is_array($data)) {
            // New error format: { "error": { "message": "...", "code": "...", ... } }
            if (isset($data['error']) && is_array($data['error'])) {
                if (isset($data['error']['message']) && is_string($data['error']['message'])) {
                    $errorMessage = $data['error']['message'];
                } elseif (isset($data['error']['title']) && is_string($data['error']['title'])) {
                    $errorMessage = $data['error']['title'];
                }
            }
            // Legacy error format: { "error": "message" } or { "message": "message" }
            elseif (isset($data['error']) && is_string($data['error'])) {
                $errorMessage = $data['error'];
            } elseif (isset($data['message']) && is_string($data['message'])) {
                $errorMessage = $data['message'];
            }
        }

        // Success - no error
        if ($statusCode >= 200 && $statusCode < 300) {
            return;
        }

        // 401 Unauthorized
        if ($statusCode === 401) {
            $exception = AuthenticationException::unauthorized($errorMessage);
            $this->reportError($exception, $response, $method, $path, $options);
            throw $exception;
        }

        // 403 Forbidden
        if ($statusCode === 403) {
            $exception = AuthenticationException::unauthorized($errorMessage);
            $this->reportError($exception, $response, $method, $path, $options);
            throw $exception;
        }

        // 400 Bad Request
        if ($statusCode === 400) {
            $exception = ValidationException::fromResponse($errorMessage);
            $this->reportError($exception, $response, $method, $path, $options);
            throw $exception;
        }

        // 422 Unprocessable Entity
        if ($statusCode === 422) {
            $exception = ValidationException::fromResponse($errorMessage);
            $this->reportError($exception, $response, $method, $path, $options);
            throw $exception;
        }

        // 429 Too Many Requests
        if ($statusCode === 429) {
            $retryAfter = $response->hasHeader('Retry-After')
                ? (int) $response->getHeaderLine('Retry-After')
                : null;

            $exception = RateLimitException::exceeded($retryAfter);
            $this->reportError($exception, $response, $method, $path, $options, ['retry_after' => $retryAfter]);
            throw $exception;
        }

        // 500+ Server Errors
        if ($statusCode >= 500) {
            $exception = ServerException::internalError($errorMessage);
            $this->reportError($exception, $response, $method, $path, $options);
            throw $exception;
        }

        // Other client errors (404, etc.)
        if ($statusCode >= 400) {
            $exception = ValidationException::fromResponse($errorMessage);
            $this->reportError($exception, $response, $method, $path, $options);
            throw $exception;
        }
    }

    /**
     * Report error to WioEX if error reporting is enabled
     *
     * @param \Throwable $exception
     * @param \Psr\Http\Message\ResponseInterface $response
     * @param string $method
     * @param string $path
     * @param array<string, mixed> $options
     * @param array<string, mixed> $additionalContext
     */
    private function reportError(
        \Throwable $exception,
        \Psr\Http\Message\ResponseInterface $response,
        string $method,
        string $path,
        array $options,
        array $additionalContext = []
    ): void {
        $statusCode = $response->getStatusCode();

        $context = array_merge([
            'http_status_code' => $statusCode,
            'error_category' => $this->categorizeError($statusCode),
            'method' => $method,
            'path' => $path,
            'category' => $this->extractCategory($path),
        ], $additionalContext);

        // Add request data if configured
        if ($this->config->shouldIncludeRequestData()) {
            $context['request_data'] = $this->extractRequestData($method, $path, $options);
        }

        // Add response data if configured
        if ($this->config->shouldIncludeResponseData()) {
            $context['response_data'] = [
                'status' => $statusCode,
                'body' => (string) $response->getBody(),
                'headers' => $response->getHeaders(),
            ];
        }

        $this->errorReporter->report($exception, $context);
    }

    /**
     * Extract request data for error reporting
     *
     * @param string $method
     * @param string $path
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function extractRequestData(string $method, string $path, array $options): array
    {
        $requestData = [
            'method' => $method,
            'path' => $path,
        ];

        // Add query parameters (API key will be sanitized by ErrorReporter)
        if (isset($options['query'])) {
            $requestData['query'] = $options['query'];
        }

        // Add JSON body
        if (isset($options['json'])) {
            $requestData['body'] = $options['json'];
        }

        // Add form data
        if (isset($options['form_params'])) {
            $requestData['form_params'] = $options['form_params'];
        }

        // Add headers (sensitive ones will be sanitized)
        if (isset($options['headers'])) {
            $requestData['headers'] = $options['headers'];
        }

        return $requestData;
    }

    /**
     * Extract category from API path
     * Examples: /v2/stocks/get -> stocks, /v2/currency/convert -> currency
     */
    private function extractCategory(string $path): string
    {
        // Extract category from path pattern: /v2/{category}/...
        if (preg_match('#^/v2/([^/]+)/#', $path, $matches) === 1) {
            return $matches[1]; // stocks, currency, news, crypto, account
        }

        // Default to 'unknown' if pattern doesn't match
        return 'unknown';
    }

    /**
     * Categorize error by HTTP status code
     */
    private function categorizeError(int $statusCode): string
    {
        if ($statusCode === 401 || $statusCode === 403) {
            return 'authentication';
        }

        if ($statusCode === 429) {
            return 'rate_limit';
        }

        if ($statusCode >= 400 && $statusCode < 500) {
            return 'client_error';
        }

        if ($statusCode >= 500) {
            return 'server_error';
        }

        return 'unknown';
    }

    public function getConfig(): Config
    {
        return $this->config;
    }
}
