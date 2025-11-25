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
use Wioex\SDK\Enums\ErrorCategory;
use Wioex\SDK\ErrorReporter;
use Wioex\SDK\Http\RateLimitingMiddleware;
use Wioex\SDK\Monitoring\PerformanceMonitor;
use Wioex\SDK\Cache\CacheInterface;
use Wioex\SDK\Cache\CacheManager;
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
    private ?PerformanceMonitor $performanceMonitor = null;
    private ?CacheInterface $cache = null;

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->client = $this->createGuzzleClient();
        $this->errorReporter = new ErrorReporter($config);

        // Initialize performance monitoring if enabled
        if ($config->isMonitoringEnabled()) {
            $this->performanceMonitor = PerformanceMonitor::forEnvironment(
                $config->getEnvironment(),
                $config->getMonitoringConfig()
            );
        }

        // Initialize cache if enabled
        if ($config->isCacheEnabled()) {
            $this->cache = CacheManager::create($config->getCacheConfig());
        }
    }

    private function createGuzzleClient(): GuzzleClient
    {
        $stack = HandlerStack::create();

        // Add rate limiting middleware (if enabled)
        if ($this->config->isRateLimitingEnabled()) {
            $rateLimitingMiddleware = new RateLimitingMiddleware($this->config->getRateLimitConfig());
            $stack->push($rateLimitingMiddleware, 'rate_limiting');
        }

        // Add retry middleware with enhanced configuration support
        $enhancedRetryConfig = $this->config->isEnhancedRetryEnabled()
            ? $this->config->getEnhancedRetryConfig()
            : null;
        $retryHandler = new RetryHandler($this->config->getRetryConfig(), $enhancedRetryConfig);
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
        $requestId = uniqid('req_', true);
        $startTime = microtime(true);

        // Start monitoring request
        $this->performanceMonitor?->startRequest($requestId, $path, $method, [
            'options' => $this->sanitizeOptionsForLogging($options)
        ]);

        try {
            $response = $this->client->request($method, $path, $options);
            $duration = (microtime(true) - $startTime) * 1000; // milliseconds
            $statusCode = $response->getStatusCode();
            $responseSize = strlen((string) $response->getBody());

            // Handle HTTP errors
            $this->handleResponseErrors($response, $method, $path, $options);

            // End monitoring request (success)
            $this->performanceMonitor?->endRequest($requestId, $statusCode, $responseSize, [
                'duration_ms' => $duration,
                'cached' => false, // TODO: Track if response was cached
            ]);

            return new Response($response);
        } catch (ConnectException $e) {
            $duration = (microtime(true) - $startTime) * 1000;

            // End monitoring request (connection error)
            $this->performanceMonitor?->endRequest($requestId, 0, 0, [
                'duration_ms' => $duration,
                'error_type' => 'connection_error',
            ]);

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
            $duration = (microtime(true) - $startTime) * 1000;
            $response = $e->getResponse();
            $statusCode = $response !== null ? $response->getStatusCode() : 0;
            $responseSize = $response !== null ? strlen((string) $response->getBody()) : 0;

            // End monitoring request (network error)
            $this->performanceMonitor?->endRequest($requestId, $statusCode, $responseSize, [
                'duration_ms' => $duration,
                'error_type' => 'network_error',
            ]);

            if ($response !== null) {
                $this->handleResponseErrors($response, $method, $path, $options);
            }

            $exception = RequestException::networkError($e->getMessage());

            // Report error to WioEX if enabled
            $context = [
                'method' => $method,
                'path' => $path,
                'error_type' => 'network_error',
                'status_code' => $statusCode,
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
            } elseif (isset($data['error']) && is_string($data['error'])) {
                // Legacy error format: { "error": "message" } or { "message": "message" }
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
            if ($statusCode === 503) {
                $exception = ServerException::serviceUnavailable();
                $this->reportError($exception, $response, $method, $path, $options, [
                    'service_unavailable_detected' => true,
                    'original_message' => $errorMessage
                ]);
                throw $exception;
            }
            
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
            'error_category' => $this->categorizeError($statusCode)->value,
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
    private function categorizeError(int $statusCode): ErrorCategory
    {
        return ErrorCategory::fromHttpStatusCode($statusCode);
    }

    public function getConfig(): Config
    {
        return $this->config;
    }

    public function getCache(): ?CacheInterface
    {
        return $this->cache;
    }

    public function getPerformanceMonitor(): ?PerformanceMonitor
    {
        return $this->performanceMonitor;
    }

    /**
     * Sanitize request options for logging (remove sensitive data)
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function sanitizeOptionsForLogging(array $options): array
    {
        $sanitized = $options;

        // Remove or mask sensitive query parameters
        if (isset($sanitized['query']) && is_array($sanitized['query'])) {
            if (isset($sanitized['query']['api_key'])) {
                $sanitized['query']['api_key'] = $this->maskSensitiveValue($sanitized['query']['api_key']);
            }
        }

        // Remove or mask sensitive headers
        if (isset($sanitized['headers']) && is_array($sanitized['headers'])) {
            foreach (['Authorization', 'X-API-Key', 'Bearer'] as $header) {
                if (isset($sanitized['headers'][$header])) {
                    $sanitized['headers'][$header] = $this->maskSensitiveValue($sanitized['headers'][$header]);
                }
            }
        }

        // Remove or mask sensitive form parameters
        if (isset($sanitized['form_params']) && is_array($sanitized['form_params'])) {
            foreach (['password', 'token', 'secret', 'key'] as $param) {
                if (isset($sanitized['form_params'][$param])) {
                    $sanitized['form_params'][$param] = $this->maskSensitiveValue($sanitized['form_params'][$param]);
                }
            }
        }

        // Remove or mask sensitive JSON data
        if (isset($sanitized['json']) && is_array($sanitized['json'])) {
            foreach (['password', 'token', 'secret', 'key', 'api_key'] as $param) {
                if (isset($sanitized['json'][$param])) {
                    $sanitized['json'][$param] = $this->maskSensitiveValue($sanitized['json'][$param]);
                }
            }
        }

        return $sanitized;
    }

    /**
     * Mask sensitive values for logging
     */
    private function maskSensitiveValue(mixed $value): string
    {
        if (!is_string($value)) {
            return '[REDACTED]';
        }

        $length = strlen($value);
        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        return substr($value, 0, 2) . str_repeat('*', $length - 4) . substr($value, -2);
    }

    public function sendAsync(\Psr\Http\Message\RequestInterface $request): \GuzzleHttp\Promise\PromiseInterface
    {
        return $this->client->sendAsync($request);
    }

    public function getGuzzleClient(): GuzzleClient
    {
        return $this->client;
    }
}
