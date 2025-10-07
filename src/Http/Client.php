<?php

declare(strict_types=1);

namespace Wioex\SDK\Http;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException as GuzzleRequestException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Wioex\SDK\Config;
use Wioex\SDK\Exceptions\AuthenticationException;
use Wioex\SDK\Exceptions\RateLimitException;
use Wioex\SDK\Exceptions\RequestException;
use Wioex\SDK\Exceptions\ServerException;
use Wioex\SDK\Exceptions\ValidationException;

class Client
{
    private Config $config;
    private GuzzleClient $client;

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->client = $this->createGuzzleClient();
    }

    private function createGuzzleClient(): GuzzleClient
    {
        $stack = HandlerStack::create();

        // Add retry middleware
        $retryHandler = new RetryHandler($this->config->getRetryConfig());
        $stack->push(Middleware::retry(
            function ($retries, $request, $response, $exception) use ($retryHandler) {
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
            throw RequestException::connectionFailed($e->getMessage());
        } catch (GuzzleRequestException $e) {
            if ($e->hasResponse()) {
                $this->handleResponseErrors($e->getResponse());
            }
            throw RequestException::networkError($e->getMessage());
        }
    }

    private function handleResponseErrors(\Psr\Http\Message\ResponseInterface $response): void
    {
        $statusCode = $response->getStatusCode();
        $body = (string) $response->getBody();
        $data = json_decode($body, true) ?? [];
        $errorMessage = $data['error'] ?? $data['message'] ?? 'Unknown error occurred';

        // Success - no error
        if ($statusCode >= 200 && $statusCode < 300) {
            return;
        }

        // 401 Unauthorized
        if ($statusCode === 401) {
            throw AuthenticationException::unauthorized($errorMessage);
        }

        // 403 Forbidden
        if ($statusCode === 403) {
            throw AuthenticationException::unauthorized($errorMessage);
        }

        // 400 Bad Request
        if ($statusCode === 400) {
            throw ValidationException::fromResponse($errorMessage);
        }

        // 422 Unprocessable Entity
        if ($statusCode === 422) {
            throw ValidationException::fromResponse($errorMessage);
        }

        // 429 Too Many Requests
        if ($statusCode === 429) {
            $retryAfter = $response->hasHeader('Retry-After')
                ? (int) $response->getHeaderLine('Retry-After')
                : null;

            throw RateLimitException::exceeded($retryAfter);
        }

        // 500+ Server Errors
        if ($statusCode >= 500) {
            throw ServerException::internalError($errorMessage);
        }

        // Other client errors (404, etc.)
        if ($statusCode >= 400 && $statusCode < 500) {
            throw ValidationException::fromResponse($errorMessage);
        }
    }

    public function getConfig(): Config
    {
        return $this->config;
    }
}
