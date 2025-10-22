<?php

declare(strict_types=1);

namespace Wioex\SDK\Async;

use Wioex\SDK\Config;
use Wioex\SDK\Http\Client;
use Wioex\SDK\Http\Response;
use Wioex\SDK\Enums\AsyncOperationType;

class AsyncClient
{
    private Client $client;
    private EventLoop $eventLoop;
    private array $pendingRequests = [];

    public function __construct(Config $config, ?EventLoop $eventLoop = null)
    {
        $this->client = new Client($config);
        $this->eventLoop = $eventLoop ?? new EventLoop();
    }

    public function getAsync(string $path, array $query = []): Promise
    {
        return $this->requestAsync('GET', $path, ['query' => $query], AsyncOperationType::HTTP_REQUEST);
    }

    public function postAsync(string $path, array $data = []): Promise
    {
        $options = ['json' => $data];

        // Only add API key if it's set (for authenticated endpoints)
        if ($this->client->getConfig()->hasApiKey()) {
            $options['query'] = ['api_key' => $this->client->getConfig()->getApiKey()];
        }

        return $this->requestAsync('POST', $path, $options, AsyncOperationType::HTTP_REQUEST);
    }

    public function putAsync(string $path, array $data = []): Promise
    {
        $options = ['json' => $data];

        // Only add API key if it's set (for authenticated endpoints)
        if ($this->client->getConfig()->hasApiKey()) {
            $options['query'] = ['api_key' => $this->client->getConfig()->getApiKey()];
        }

        return $this->requestAsync('PUT', $path, $options, AsyncOperationType::HTTP_REQUEST);
    }

    public function deleteAsync(string $path, array $query = []): Promise
    {
        // Only add API key if it's set (for authenticated endpoints)
        if ($this->client->getConfig()->hasApiKey()) {
            $query['api_key'] = $this->client->getConfig()->getApiKey();
        }

        return $this->requestAsync('DELETE', $path, ['query' => $query], AsyncOperationType::HTTP_REQUEST);
    }

    private function requestAsync(string $method, string $path, array $options = [], AsyncOperationType $type = AsyncOperationType::HTTP_REQUEST): Promise
    {
        $promise = new Promise();
        $requestId = uniqid('req_', true);

        $this->pendingRequests[$requestId] = $promise;

        // Schedule the request to be executed asynchronously
        $this->eventLoop->nextTick(function () use ($promise, $method, $path, $options, $requestId) {
            try {
                // Ensure options is an array and extract parameters safely
                $safeOptions = is_array($options) ? $options : [];
                $queryParams = isset($safeOptions['query']) && is_array($safeOptions['query']) ? $safeOptions['query'] : [];
                $jsonData = isset($safeOptions['json']) && is_array($safeOptions['json']) ? $safeOptions['json'] : [];
                
                // Use appropriate HTTP method from the client
                $response = match (strtoupper($method)) {
                    'GET' => $this->client->get($path, $queryParams),
                    'POST' => $this->client->post($path, $jsonData),
                    'PUT' => $this->client->put($path, $jsonData),
                    'DELETE' => $this->client->delete($path, $queryParams),
                    default => throw new \InvalidArgumentException("Unsupported HTTP method: {$method}"),
                };

                unset($this->pendingRequests[$requestId]);
                $promise->resolve($response);
            } catch (\Throwable $e) {
                unset($this->pendingRequests[$requestId]);
                $promise->reject($e);
            }
        }, $type);

        return $promise;
    }

    public function bulkAsync(array $requests): Promise
    {
        $promises = [];

        foreach ($requests as $i => $request) {
            $method = $request['method'] ?? 'GET';
            $path = $request['path'] ?? '';
            $options = $request['options'] ?? [];

            $promises[$i] = $this->requestAsync($method, $path, $options, AsyncOperationType::BULK_OPERATION);
        }

        return Promise::allSettled($promises);
    }

    public function batchAsync(array $requests, int $concurrency = 5): Promise
    {
        $promise = new Promise();
        $results = [];
        $errors = [];
        $completed = 0;
        $total = count($requests);

        if ($total === 0) {
            $promise->resolve([]);
            return $promise;
        }

        $concurrency = max(1, $concurrency);
        $chunks = array_chunk($requests, $concurrency, true);

        $processChunk = function ($chunk) use (&$results, &$errors, &$completed, $total, $promise, &$processChunk, $chunks) {
            $chunkPromises = [];

            foreach ($chunk as $index => $request) {
                $method = $request['method'] ?? 'GET';
                $path = $request['path'] ?? '';
                $options = $request['options'] ?? [];

                $chunkPromises[$index] = $this->requestAsync($method, $path, $options, AsyncOperationType::BATCH_PROCESSING);
            }

            Promise::allSettled($chunkPromises)->then(function ($chunkResults) use (&$results, &$errors, &$completed, $total, $promise, &$processChunk, $chunks) {
                foreach ($chunkResults as $index => $result) {
                    if ($result['status'] === 'fulfilled') {
                        $results[$index] = $result['value'];
                    } else {
                        $errors[$index] = $result['reason'];
                    }
                    $completed++;
                }

                // Process next chunk
                $nextChunk = array_shift($chunks);
                if ($nextChunk !== null && is_array($nextChunk) && count($nextChunk) > 0) {
                    $processChunk($nextChunk);
                } elseif ($completed >= $total) {
                    $promise->resolve([
                        'results' => $results,
                        'errors' => $errors,
                        'completed' => $completed,
                        'total' => $total,
                    ]);
                }
            });
        };

        // Start processing first chunk
        $firstChunk = array_shift($chunks);
        if ($firstChunk !== null && is_array($firstChunk)) {
            $processChunk($firstChunk);
        }

        return $promise;
    }

    public function timeoutAsync(Promise $promise, int $timeoutMs): Promise
    {
        $timeoutPromise = new Promise();

        $this->eventLoop->setTimeout(function () use ($timeoutPromise) {
            $timeoutPromise->reject(new AsyncTimeoutException('Operation timed out'));
        }, $timeoutMs, AsyncOperationType::TIMEOUT_OPERATION);

        return Promise::race([$promise, $timeoutPromise]);
    }

    public function retryAsync(callable $operation, int $maxAttempts = 3, int $delayMs = 1000): Promise
    {
        $promise = new Promise();
        $attempt = 0;

        $tryOperation = function () use (&$tryOperation, $operation, $maxAttempts, $delayMs, &$attempt, $promise) {
            $attempt++;

            try {
                $result = $operation();

                if ($result instanceof Promise) {
                    $result->then(
                        fn($value) => $promise->resolve($value),
                        function ($reason) use (&$tryOperation, $maxAttempts, $delayMs, $attempt, $promise) {
                            if ($attempt < $maxAttempts) {
                                $this->eventLoop->setTimeout($tryOperation, $delayMs * $attempt, AsyncOperationType::RETRY_OPERATION);
                            } else {
                                $promise->reject($reason);
                            }
                        }
                    );
                } else {
                    $promise->resolve($result);
                }
            } catch (\Throwable $e) {
                if ($attempt < $maxAttempts) {
                    $this->eventLoop->setTimeout($tryOperation, $delayMs * $attempt, AsyncOperationType::RETRY_OPERATION);
                } else {
                    $promise->reject($e);
                }
            }
        };

        $tryOperation();

        return $promise;
    }

    public function delayAsync(int $delayMs): Promise
    {
        $promise = new Promise();

        $this->eventLoop->setTimeout(function () use ($promise) {
            $promise->resolve(null);
        }, $delayMs, AsyncOperationType::DELAY_OPERATION);

        return $promise;
    }

    public function getEventLoop(): EventLoop
    {
        return $this->eventLoop;
    }

    public function getPendingRequestCount(): int
    {
        return count($this->pendingRequests);
    }

    public function cancelAllPendingRequests(): void
    {
        foreach ($this->pendingRequests as $promise) {
            $promise->reject(new AsyncCancelledException('Request cancelled'));
        }
        $this->pendingRequests = [];
    }

    public function wait(Promise $promise, ?int $timeoutMs = null): mixed
    {
        $resolved = false;
        $result = null;
        $error = null;

        $promise->then(
            function ($value) use (&$resolved, &$result) {
                $resolved = true;
                $result = $value;
            },
            function ($reason) use (&$resolved, &$error) {
                $resolved = true;
                $error = $reason;
            }
        );

        $startTime = microtime(true);

        while (!$resolved) {
            $this->eventLoop->tick();

            if ($timeoutMs !== null && (microtime(true) - $startTime) * 1000 > $timeoutMs) {
                throw new AsyncTimeoutException('Wait timeout');
            }

            usleep(1000); // 1ms sleep to prevent busy waiting
        }

        if ($error) {
            throw $error instanceof \Throwable ? $error : new \Exception($error);
        }

        return $result;
    }

    public function runEventLoop(): void
    {
        $this->eventLoop->run();
    }

    public function stopEventLoop(): void
    {
        $this->eventLoop->stop();
    }
}

class AsyncTimeoutException extends \Exception
{
}
class AsyncCancelledException extends \Exception
{
}
