<?php

declare(strict_types=1);

namespace Wioex\SDK\Async;

use Wioex\SDK\Http\Client;
use Wioex\SDK\Cache\CacheInterface;
use Wioex\SDK\Reliability\CircuitBreakerManager;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Promise\Utils;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;

class BatchRequestManager
{
    private Client $httpClient;
    private array $queuedRequests = [];
    private array $config;
    private ?CacheInterface $cache = null;
    private ?CircuitBreakerManager $circuitBreaker = null;
    private array $metrics = [
        'total_batches' => 0,
        'total_requests' => 0,
        'successful_requests' => 0,
        'failed_requests' => 0,
        'cache_hits' => 0,
        'circuit_breaker_trips' => 0
    ];

    public function __construct(Client $httpClient, array $config = [], ?CacheInterface $cache = null, ?CircuitBreakerManager $circuitBreaker = null)
    {
        $this->httpClient = $httpClient;
        $this->config = array_merge($this->getDefaultConfig(), $config);
        $this->cache = $cache;
        $this->circuitBreaker = $circuitBreaker;
    }

    private function getDefaultConfig(): array
    {
        return [
            'max_concurrent' => 10,
            'max_batch_size' => 50,
            'timeout' => 30,
            'retry_attempts' => 3,
            'retry_delay' => 1000, // ms
            'auto_flush' => true,
            'auto_flush_threshold' => 25,
            'priority_processing' => true,
            'cache_results' => true,
            'circuit_breaker_enabled' => true
        ];
    }

    public function add(string $method, string $endpoint, array $params = [], array $options = []): BatchRequest
    {
        $request = new BatchRequest(
            uniqid('batch_', true),
            $method,
            $endpoint,
            $params,
            array_merge([
                'priority' => 1,
                'cache_ttl' => 300,
                'retry_enabled' => true,
                'circuit_breaker_service' => 'api'
            ], $options)
        );

        $this->queuedRequests[] = $request;

        // Auto flush if threshold reached
        if ($this->config['auto_flush'] && count($this->queuedRequests) >= $this->config['auto_flush_threshold']) {
            return $this->flush()[$request->getId()] ?? $request;
        }

        return $request;
    }

    public function addQuote(string $symbol, array $options = []): BatchRequest
    {
        return $this->add('GET', '/v2/stocks/get', ['ticker' => $symbol], array_merge([
            'cache_ttl' => 60,
            'priority' => 2
        ], $options));
    }

    public function addQuotes(array $symbols, array $options = []): BatchRequest
    {
        return $this->add('GET', '/v2/stocks/get', ['ticker' => implode(',', $symbols)], array_merge([
            'cache_ttl' => 60,
            'priority' => 2
        ], $options));
    }

    public function addTimeline(string $symbol, string $interval = '1d', array $options = []): BatchRequest
    {
        return $this->add('GET', '/v2/stocks/chart/timeline', [
            'ticker' => $symbol,
            'interval' => $interval
        ], array_merge([
            'cache_ttl' => 300,
            'priority' => 1
        ], $options));
    }

    public function addNews(string $symbol, array $options = []): BatchRequest
    {
        return $this->add('GET', '/v2/news', ['ticker' => $symbol], array_merge([
            'cache_ttl' => 1800,
            'priority' => 1
        ], $options));
    }

    public function addMarketStatus(array $options = []): BatchRequest
    {
        return $this->add('GET', '/v2/markets/status', [], array_merge([
            'cache_ttl' => 60,
            'priority' => 3
        ], $options));
    }

    public function addStreamToken(array $options = []): BatchRequest
    {
        return $this->add('GET', '/v2/streaming/token', [], array_merge([
            'cache_ttl' => 1800,
            'priority' => 3
        ], $options));
    }

    public function flush(): array
    {
        if (count($this->queuedRequests) === 0) {
            return [];
        }

        $this->metrics['total_batches']++;
        $this->metrics['total_requests'] += count($this->queuedRequests);

        // Sort by priority if enabled
        if ($this->config['priority_processing']) {
            usort($this->queuedRequests, fn($a, $b) => $b->getPriority() <=> $a->getPriority());
        }

        // Check cache first
        $cachedResults = $this->checkCache();
        $uncachedRequests = array_filter($this->queuedRequests, fn($req) => !isset($cachedResults[$req->getId()]));

        // Process uncached requests in batches
        $results = $cachedResults;
        $batches = array_chunk($uncachedRequests, $this->config['max_batch_size']);

        foreach ($batches as $batch) {
            $batchResults = $this->processBatch($batch);
            $results = array_merge($results, $batchResults);
        }

        // Clear the queue
        $this->queuedRequests = [];

        return $results;
    }

    public function execute(): array
    {
        return $this->flush();
    }

    public function executeAsync(): PromiseInterface
    {
        $promise = new Promise(function () use (&$promise) {
            try {
                $results = $this->flush();
                $promise->resolve($results);
            } catch (\Throwable $e) {
                $promise->reject($e);
            }
        });

        return $promise;
    }

    private function checkCache(): array
    {
        if ($this->cache === null || !$this->config['cache_results']) {
            return [];
        }

        $results = [];

        foreach ($this->queuedRequests as $request) {
            $cacheKey = $this->generateCacheKey($request);
            $cached = $this->cache->get($cacheKey);

            if ($cached !== null) {
                $request->setResult($cached);
                $request->setCached(true);
                $results[$request->getId()] = $request;
                $this->metrics['cache_hits']++;
            }
        }

        return $results;
    }

    private function processBatch(array $requests): array
    {
        $promises = [];
        $requestMap = [];

        foreach ($requests as $request) {
            $guzzleRequest = $this->createGuzzleRequest($request);
            $requestMap[spl_object_hash($guzzleRequest)] = $request;

            if ($this->config['circuit_breaker_enabled'] && $this->circuitBreaker !== null) {
                $serviceName = $request->getOption('circuit_breaker_service', 'api');
                
                try {
                    $promises[spl_object_hash($guzzleRequest)] = $this->circuitBreaker->call(
                        $serviceName,
                        fn() => $this->httpClient->sendAsync($guzzleRequest)
                    );
                } catch (\Throwable $e) {
                    $request->setError($e);
                    $this->metrics['circuit_breaker_trips']++;
                    continue;
                }
            } else {
                $promises[spl_object_hash($guzzleRequest)] = $this->httpClient->sendAsync($guzzleRequest);
            }
        }

        // Execute all promises concurrently
        $pool = new Pool($this->httpClient->getGuzzleClient(), $promises, [
            'concurrency' => $this->config['max_concurrent'],
            'fulfilled' => function ($response, $index) use ($requestMap) {
                $request = $requestMap[$index];
                $this->handleSuccessfulResponse($request, $response);
            },
            'rejected' => function ($reason, $index) use ($requestMap) {
                $request = $requestMap[$index];
                $this->handleFailedResponse($request, $reason);
            }
        ]);

        $pool->promise()->wait();

        // Process results and cache if enabled
        $results = [];
        foreach ($requests as $request) {
            if ($request->hasResult()) {
                $this->cacheResult($request);
                $this->metrics['successful_requests']++;
            } else {
                $this->metrics['failed_requests']++;
            }
            
            $results[$request->getId()] = $request;
        }

        return $results;
    }

    private function createGuzzleRequest(BatchRequest $request): Request
    {
        $method = $request->getMethod();
        $endpoint = $request->getEndpoint();
        $params = $request->getParams();

        if ($method === 'GET' && count($params) > 0) {
            $endpoint .= '?' . http_build_query($params);
            $body = null;
        } else {
            $body = json_encode($params);
        }

        return new Request($method, $endpoint, [
            'Content-Type' => 'application/json',
            'User-Agent' => 'WioEX-SDK-PHP-Batch/1.0'
        ], $body);
    }

    private function handleSuccessfulResponse(BatchRequest $request, mixed $response): void
    {
        $body = (string) $response->getBody();
        $data = json_decode($body, true);
        
        $request->setResult($data);
        $request->setResponseCode($response->getStatusCode());
        $request->setResponseHeaders($response->getHeaders());
    }

    private function handleFailedResponse(BatchRequest $request, mixed $reason): void
    {
        if ($request->getOption('retry_enabled', true) === true && $request->incrementRetryCount() < $this->config['retry_attempts']) {
            // Retry logic would go here
            // For now, just mark as error
        }

        $request->setError($reason instanceof \Throwable ? $reason : new \Exception((string) $reason));
    }

    private function generateCacheKey(BatchRequest $request): string
    {
        $data = [
            'endpoint' => $request->getEndpoint(),
            'method' => $request->getMethod(),
            'params' => $request->getParams()
        ];

        return 'batch_request:' . md5(serialize($data));
    }

    private function cacheResult(BatchRequest $request): void
    {
        if (!$this->cache || !$this->config['cache_results'] || !$request->hasResult()) {
            return;
        }

        $cacheKey = $this->generateCacheKey($request);
        $ttl = $request->getOption('cache_ttl', 300);
        
        $this->cache->set($cacheKey, $request->getResult(), $ttl);
    }

    public function getQueueSize(): int
    {
        return count($this->queuedRequests);
    }

    public function clearQueue(): self
    {
        $this->queuedRequests = [];
        return $this;
    }

    public function getMetrics(): array
    {
        $queuedCount = count($this->queuedRequests);
        $successRate = $this->metrics['total_requests'] > 0 
            ? ($this->metrics['successful_requests'] / $this->metrics['total_requests']) * 100 
            : 0;
        
        $cacheHitRate = $this->metrics['total_requests'] > 0 
            ? ($this->metrics['cache_hits'] / $this->metrics['total_requests']) * 100 
            : 0;

        return array_merge($this->metrics, [
            'queued_requests' => $queuedCount,
            'success_rate' => round($successRate, 2),
            'cache_hit_rate' => round($cacheHitRate, 2),
            'avg_batch_size' => $this->metrics['total_batches'] > 0 
                ? round($this->metrics['total_requests'] / $this->metrics['total_batches'], 2) 
                : 0
        ]);
    }

    public function getStatus(): array
    {
        $metrics = $this->getMetrics();
        
        return [
            'enabled' => true,
            'queue_size' => $metrics['queued_requests'],
            'max_batch_size' => $this->config['max_batch_size'],
            'max_concurrent' => $this->config['max_concurrent'],
            'cache_enabled' => $this->cache !== null,
            'circuit_breaker_enabled' => $this->circuitBreaker !== null,
            'metrics' => $metrics,
            'configuration' => $this->config
        ];
    }

    public function benchmark(int $requests = 100, string $endpoint = '/v2/stocks/get'): array
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        // Add test requests
        for ($i = 0; $i < $requests; $i++) {
            $this->add('GET', $endpoint, ['ticker' => 'TEST' . $i]);
        }

        // Execute batch
        $results = $this->flush();

        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);

        return [
            'requests_processed' => count($results),
            'execution_time' => round(($endTime - $startTime) * 1000, 2), // ms
            'memory_used' => $endMemory - $startMemory,
            'requests_per_second' => round($requests / ($endTime - $startTime), 2),
            'successful' => array_filter($results, fn($r) => $r->hasResult()),
            'failed' => array_filter($results, fn($r) => $r->hasError()),
            'cached' => array_filter($results, fn($r) => $r->isCached()),
            'metrics' => $this->getMetrics()
        ];
    }

    public static function create(Client $httpClient, array $config = []): self
    {
        return new self($httpClient, $config);
    }
}