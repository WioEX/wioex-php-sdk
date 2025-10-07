<?php

declare(strict_types=1);

namespace Wioex\SDK\Http;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class RetryHandler
{
    private int $maxRetries;
    private int $baseDelay;
    private int $multiplier;
    private int $maxDelay;

    public function __construct(array $config)
    {
        $this->maxRetries = $config['times'] ?? 3;
        $this->baseDelay = $config['delay'] ?? 100;
        $this->multiplier = $config['multiplier'] ?? 2;
        $this->maxDelay = $config['max_delay'] ?? 5000;
    }

    public function __invoke(
        int $retryCount,
        RequestInterface $request,
        ?ResponseInterface $response = null,
        ?\Exception $exception = null
    ): bool {
        // Don't retry if we've exceeded max retries
        if ($retryCount >= $this->maxRetries) {
            return false;
        }

        // Retry on connection exceptions
        if ($exception instanceof ConnectException) {
            $this->sleep($retryCount);
            return true;
        }

        // Retry on 5xx server errors
        if ($response && $response->getStatusCode() >= 500) {
            $this->sleep($retryCount);
            return true;
        }

        // Retry on 429 rate limit (but not too aggressively)
        if ($response && $response->getStatusCode() === 429) {
            // Check for Retry-After header
            if ($response->hasHeader('Retry-After')) {
                $retryAfter = (int) $response->getHeaderLine('Retry-After');
                usleep($retryAfter * 1000000); // Convert to microseconds
            } else {
                $this->sleep($retryCount);
            }
            return true;
        }

        return false;
    }

    private function sleep(int $retryCount): void
    {
        $delay = min(
            $this->baseDelay * ($this->multiplier ** $retryCount),
            $this->maxDelay
        );

        usleep($delay * 1000); // Convert milliseconds to microseconds
    }

    public function getMaxRetries(): int
    {
        return $this->maxRetries;
    }
}
