<?php

declare(strict_types=1);

namespace Wioex\SDK\Http;

use Wioex\SDK\Response;
use Wioex\SDK\Exceptions\BulkOperationException;
use Wioex\SDK\Exceptions\ValidationException;

/**
 * Bulk Request Manager
 *
 * Handles high-volume bulk operations with intelligent chunking,
 * partial failure handling, and response merging for optimal performance.
 */
class BulkRequestManager
{
    private Client $client;
    private int $maxSymbolsPerChunk;
    private float $chunkDelay;
    private bool $failOnPartialErrors;

    // Performance optimization constants
    private const MAX_SYMBOLS_PER_CHUNK = 50;
    private const DEFAULT_CHUNK_DELAY = 0.1; // 100ms between chunks
    private const MAX_TOTAL_SYMBOLS = 1000;
    private const CHUNK_TIMEOUT_MULTIPLIER = 1.5;

    public function __construct(
        Client $client,
        int $maxSymbolsPerChunk = self::MAX_SYMBOLS_PER_CHUNK,
        float $chunkDelay = self::DEFAULT_CHUNK_DELAY,
        bool $failOnPartialErrors = false
    ) {
        $this->client = $client;
        $this->maxSymbolsPerChunk = min($maxSymbolsPerChunk, self::MAX_SYMBOLS_PER_CHUNK);
        $this->chunkDelay = $chunkDelay;
        $this->failOnPartialErrors = $failOnPartialErrors;
    }

    /**
     * Execute bulk request with intelligent chunking
     *
     * @param string $endpoint Base API endpoint
     * @param array<string> $symbols Array of stock symbols
     * @param array<string, mixed> $options Request options
     * @return Response Merged response from all chunks
     * @throws BulkOperationException
     * @throws ValidationException
     */
    public function executeBulkRequest(string $endpoint, array $symbols, array $options = []): Response
    {
        // Validate input
        $this->validateBulkRequest($symbols, $options);

        // Remove duplicates and normalize symbols
        $symbols = array_unique(array_map('strtoupper', array_filter($symbols)));

        if (empty($symbols)) {
            throw new ValidationException('No valid symbols provided for bulk operation');
        }

        // Single symbol optimization
        if (count($symbols) === 1) {
            return $this->executeSingleRequest($endpoint, $symbols[0], $options);
        }

        // Chunk symbols for processing
        $chunks = $this->chunkSymbols($symbols);
        $responses = [];
        $errors = [];
        $totalProcessed = 0;

        foreach ($chunks as $chunkIndex => $chunk) {
            try {
                $chunkResponse = $this->executeChunkRequest($endpoint, $chunk, $options, $chunkIndex);
                $responses[] = $chunkResponse;
                $totalProcessed += count($chunk);

                // Add delay between chunks to avoid rate limiting
                if ($chunkIndex < count($chunks) - 1 && $this->chunkDelay > 0) {
                    usleep((int)($this->chunkDelay * 1000000));
                }

            } catch (\Exception $e) {
                $error = [
                    'chunk' => $chunkIndex,
                    'symbols' => $chunk,
                    'error' => $e->getMessage(),
                    'code' => $e->getCode()
                ];
                $errors[] = $error;

                if ($this->failOnPartialErrors) {
                    throw new BulkOperationException(
                        "Bulk operation failed on chunk {$chunkIndex}: " . $e->getMessage(),
                        $errors,
                        $responses
                    );
                }
            }
        }

        // Check if we have any successful responses
        if (empty($responses)) {
            throw new BulkOperationException(
                'All bulk operation chunks failed',
                $errors,
                []
            );
        }

        // Merge responses
        $mergedResponse = $this->mergeResponses($responses, $errors, $totalProcessed, count($symbols));

        return $mergedResponse;
    }

    /**
     * Execute single symbol request (optimization path)
     */
    private function executeSingleRequest(string $endpoint, string $symbol, array $options): Response
    {
        // For single symbols, use the existing individual endpoint logic
        $url = $this->buildUrl($endpoint, [$symbol], $options);
        return $this->client->get($url);
    }

    /**
     * Execute request for a chunk of symbols
     */
    private function executeChunkRequest(string $endpoint, array $chunk, array $options, int $chunkIndex): Response
    {
        $url = $this->buildUrl($endpoint, $chunk, $options);
        
        // Increase timeout for larger chunks
        $chunkTimeout = $this->calculateChunkTimeout(count($chunk));
        $chunkOptions = array_merge($options, ['timeout' => $chunkTimeout]);

        return $this->client->get($url, $chunkOptions);
    }

    /**
     * Build URL for bulk request
     */
    private function buildUrl(string $endpoint, array $symbols, array $options): string
    {
        $symbolsParam = implode(',', $symbols);
        $url = $endpoint;

        // Add symbols as query parameter for bulk endpoints
        $separator = strpos($url, '?') !== false ? '&' : '?';
        $url .= $separator . 'symbols=' . urlencode($symbolsParam);

        // Add other query parameters
        unset($options['timeout']); // Don't include timeout in URL
        foreach ($options as $key => $value) {
            if (is_scalar($value) && $value !== null && $value !== '') {
                $url .= '&' . urlencode($key) . '=' . urlencode((string)$value);
            }
        }

        return $url;
    }

    /**
     * Chunk symbols into manageable groups
     *
     * @param array<string> $symbols
     * @return array<int, array<string>>
     */
    private function chunkSymbols(array $symbols): array
    {
        return array_chunk($symbols, $this->maxSymbolsPerChunk);
    }

    /**
     * Merge multiple responses into a single response
     *
     * @param Response[] $responses
     * @param array<array<string, mixed>> $errors
     * @param int $totalProcessed
     * @param int $totalRequested
     * @return Response
     */
    private function mergeResponses(array $responses, array $errors, int $totalProcessed, int $totalRequested): Response
    {
        if (empty($responses)) {
            throw new BulkOperationException('No successful responses to merge', $errors, []);
        }

        // Use the first response as the base
        $baseResponse = $responses[0];
        $mergedData = [];
        $allTickers = [];
        $successCount = 0;
        $failureCount = count($errors);

        // Merge data from all responses
        foreach ($responses as $response) {
            if ($response->successful()) {
                $responseData = $response->data();
                
                // Handle different response structures
                if (isset($responseData['tickers']) && is_array($responseData['tickers'])) {
                    // Multiple tickers response (like quote endpoint)
                    $allTickers = array_merge($allTickers, $responseData['tickers']);
                    $successCount += count($responseData['tickers']);
                } elseif (isset($responseData['data']) && is_array($responseData['data'])) {
                    // Timeline or other data structures
                    if (!isset($mergedData['data'])) {
                        $mergedData['data'] = [];
                    }
                    $mergedData['data'] = array_merge($mergedData['data'], $responseData['data']);
                    $successCount += count($responseData['data']);
                } else {
                    // Single item response
                    $allTickers[] = $responseData;
                    $successCount++;
                }
            }
        }

        // Build merged response structure
        if (!empty($allTickers)) {
            $mergedData['tickers'] = $allTickers;
        }

        // Add bulk operation metadata
        $mergedData['bulk_operation'] = [
            'total_requested' => $totalRequested,
            'total_processed' => $totalProcessed,
            'success_count' => $successCount,
            'failure_count' => $failureCount,
            'chunks_processed' => count($responses),
            'success_rate' => $totalRequested > 0 ? round(($successCount / $totalRequested) * 100, 2) : 0
        ];

        // Add errors if any occurred and not in strict mode
        if (!empty($errors)) {
            $mergedData['bulk_operation']['errors'] = $errors;
        }

        // Create new response with merged data
        return new Response(
            $baseResponse->getStatusCode(),
            json_encode($mergedData),
            $baseResponse->getHeaders(),
            $baseResponse->getHttpStatusCode()
        );
    }

    /**
     * Validate bulk request parameters
     *
     * @param array<string> $symbols
     * @param array<string, mixed> $options
     * @throws ValidationException
     */
    private function validateBulkRequest(array $symbols, array $options): void
    {
        if (empty($symbols)) {
            throw new ValidationException('Symbols array cannot be empty');
        }

        if (count($symbols) > self::MAX_TOTAL_SYMBOLS) {
            throw new ValidationException(
                sprintf('Too many symbols requested. Maximum allowed: %d, requested: %d', 
                    self::MAX_TOTAL_SYMBOLS, 
                    count($symbols)
                )
            );
        }

        // Validate symbol format
        foreach ($symbols as $symbol) {
            if (!is_string($symbol) || empty(trim($symbol))) {
                throw new ValidationException('All symbols must be non-empty strings');
            }

            if (strlen($symbol) > 10) {
                throw new ValidationException("Symbol '{$symbol}' is too long (max 10 characters)");
            }

            if (!preg_match('/^[A-Za-z0-9._-]+$/', $symbol)) {
                throw new ValidationException("Symbol '{$symbol}' contains invalid characters");
            }
        }
    }

    /**
     * Calculate appropriate timeout for chunk size
     */
    private function calculateChunkTimeout(int $chunkSize): int
    {
        // Base timeout + extra time per symbol
        $baseTimeout = 10; // seconds
        $timePerSymbol = 0.5; // seconds per symbol
        $calculatedTimeout = $baseTimeout + ($chunkSize * $timePerSymbol);
        
        // Apply multiplier and cap
        $timeout = (int)($calculatedTimeout * self::CHUNK_TIMEOUT_MULTIPLIER);
        
        return min($timeout, 60); // Cap at 60 seconds
    }

    /**
     * Get current configuration
     *
     * @return array<string, mixed>
     */
    public function getConfiguration(): array
    {
        return [
            'max_symbols_per_chunk' => $this->maxSymbolsPerChunk,
            'chunk_delay_seconds' => $this->chunkDelay,
            'fail_on_partial_errors' => $this->failOnPartialErrors,
            'max_total_symbols' => self::MAX_TOTAL_SYMBOLS
        ];
    }

    /**
     * Set chunk delay between requests
     */
    public function setChunkDelay(float $delay): self
    {
        $this->chunkDelay = max(0, $delay);
        return $this;
    }

    /**
     * Set whether to fail on partial errors
     */
    public function setFailOnPartialErrors(bool $fail): self
    {
        $this->failOnPartialErrors = $fail;
        return $this;
    }

    /**
     * Set maximum symbols per chunk
     */
    public function setMaxSymbolsPerChunk(int $max): self
    {
        $this->maxSymbolsPerChunk = min($max, self::MAX_SYMBOLS_PER_CHUNK);
        return $this;
    }
}