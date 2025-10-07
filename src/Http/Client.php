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
        return $this->request('GET', $path, [
            'query' => array_merge($query, ['api_key' => $this->config->getApiKey()])
        ]);
    }

    public function post(string $path, array $data = []): Response
    {
        return $this->request('POST', $path, [
            'json' => $data,
            'query' => ['api_key' => $this->config->getApiKey()]
        ]);
    }

    public function put(string $path, array $data = []): Response
    {
        return $this->request('PUT', $path, [
            'json' => $data,
            'query' => ['api_key' => $this->config->getApiKey()]
        ]);
    }

    public function delete(string $path, array $query = []): Response
    {
        return $this->request('DELETE', $path, [
            'query' => array_merge($query, ['api_key' => $this->config->getApiKey()])
        ]);
    }

    private function request(string $method, string $path, array $options = []): Response
    {
        try {
            $response = $this->client->request($method, $path, $options);

            // Handle HTTP errors
            $this->handleResponseErrors($response);

            return new Response($response);
        } catch (ConnectException $e) {
            $exception = RequestException::connectionFailed($e->getMessage());

            // Report error to WioEX if enabled
            $this->errorReporter->report($exception, [
                'method' => $method,
                'path' => $path,
                'error_type' => 'connection_error',
            ]);

            throw $exception;
        } catch (GuzzleRequestException $e) {
            $response = $e->getResponse();
            if ($response !== null) {
                $this->handleResponseErrors($response);
            }

            $exception = RequestException::networkError($e->getMessage());

            // Report error to WioEX if enabled
            $this->errorReporter->report($exception, [
                'method' => $method,
                'path' => $path,
                'error_type' => 'network_error',
                'status_code' => $response !== null ? $response->getStatusCode() : null,
            ]);

            throw $exception;
        }
    }

    private function handleResponseErrors(\Psr\Http\Message\ResponseInterface $response): void
    {
        $statusCode = $response->getStatusCode();
        $body = (string) $response->getBody();
        /** @var mixed $data */
        $data = json_decode($body, true);

        // Extract error message with type safety
        $errorMessage = 'Unknown error occurred';
        if (is_array($data)) {
            if (isset($data['error']) && is_string($data['error'])) {
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
            $this->reportError($exception, $statusCode);
            throw $exception;
        }

        // 403 Forbidden
        if ($statusCode === 403) {
            $exception = AuthenticationException::unauthorized($errorMessage);
            $this->reportError($exception, $statusCode);
            throw $exception;
        }

        // 400 Bad Request
        if ($statusCode === 400) {
            $exception = ValidationException::fromResponse($errorMessage);
            $this->reportError($exception, $statusCode);
            throw $exception;
        }

        // 422 Unprocessable Entity
        if ($statusCode === 422) {
            $exception = ValidationException::fromResponse($errorMessage);
            $this->reportError($exception, $statusCode);
            throw $exception;
        }

        // 429 Too Many Requests
        if ($statusCode === 429) {
            $retryAfter = $response->hasHeader('Retry-After')
                ? (int) $response->getHeaderLine('Retry-After')
                : null;

            $exception = RateLimitException::exceeded($retryAfter);
            $this->reportError($exception, $statusCode, ['retry_after' => $retryAfter]);
            throw $exception;
        }

        // 500+ Server Errors
        if ($statusCode >= 500) {
            $exception = ServerException::internalError($errorMessage);
            $this->reportError($exception, $statusCode);
            throw $exception;
        }

        // Other client errors (404, etc.)
        if ($statusCode >= 400) {
            $exception = ValidationException::fromResponse($errorMessage);
            $this->reportError($exception, $statusCode);
            throw $exception;
        }
    }

    /**
     * Report error to WioEX if error reporting is enabled
     *
     * @param \Throwable $exception
     * @param int $statusCode
     * @param array<string, mixed> $additionalContext
     */
    private function reportError(
        \Throwable $exception,
        int $statusCode,
        array $additionalContext = []
    ): void {
        $context = array_merge([
            'http_status_code' => $statusCode,
            'error_category' => $this->categorizeError($statusCode),
        ], $additionalContext);

        $this->errorReporter->report($exception, $context);
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
