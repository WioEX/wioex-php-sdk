<?php

declare(strict_types=1);

namespace Wioex\SDK\Http;

use Wioex\SDK\Response;
use Wioex\SDK\Exceptions\BulkOperationException;
use Wioex\SDK\Exceptions\ValidationException;
use Wioex\SDK\Monitoring\ProgressTracker;

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
    private ?ProgressTracker $progressTracker = null;

    // Performance optimization constants - Updated for real API limits
    private const MAX_SYMBOLS_PER_CHUNK = 30; // Reduced to match API limit for quotes
    private const DEFAULT_CHUNK_DELAY = 0.1; // 100ms between chunks
    private const MAX_TOTAL_SYMBOLS = 1000;
    private const CHUNK_TIMEOUT_MULTIPLIER = 1.5;
    
    // Endpoint-specific limits based on real API constraints
    private const ENDPOINT_LIMITS = [
        'quotes' => 30,      // /v2/stocks/get supports up to 30 symbols
        'timeline' => 1,     // /v2/stocks/chart/timeline supports only 1 symbol
        'info' => 1,         // /v2/stocks/info supports only 1 symbol
        'financials' => 1    // /v2/stocks/financials supports only 1 symbol
    ];

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
     * Execute bulk request with intelligent chunking and progress tracking
     *
     * @param string $endpoint Base API endpoint (will be mapped to real endpoints)
     * @param array<string> $symbols Array of stock symbols
     * @param array<string, mixed> $options Request options (can include 'progress_callback')
     * @return Response Merged response from all chunks
     * @throws BulkOperationException
     * @throws ValidationException
     */
    public function executeBulkRequest(string $endpoint, array $symbols, array $options = []): Response
    {
        // Validate input
        $this->validateBulkRequest($symbols, $options);

        // Map bulk endpoint to real API endpoint and get limits
        $endpointInfo = $this->mapBulkEndpoint($endpoint);
        $realEndpoint = $endpointInfo['endpoint'];
        $maxSymbolsPerRequest = $endpointInfo['max_symbols'];
        $endpointType = $endpointInfo['type'];

        // Remove duplicates and normalize symbols
        $symbols = array_unique(array_map('strtoupper', array_filter($symbols)));

        if (($symbols === null || $symbols === '' || $symbols === [])) {
            throw new ValidationException('No valid symbols provided for bulk operation');
        }

        // Initialize progress tracking if callback provided
        $progressCallback = $options['progress_callback'] ?? null;
        if ($progressCallback !== null) {
            $this->progressTracker = new ProgressTracker(count($symbols), $progressCallback);
        }

        // Single symbol optimization
        if (count($symbols) === 1) {
            $response = $this->executeSingleRequest($realEndpoint, $symbols[0], $options, $endpointType);
            
            // Report completion for single request
            if ($this->progressTracker) {
                $this->progressTracker->reportChunkProgress(0, 1, 0.1, true);
            }
            
            return $response;
        }

        // Chunk symbols based on endpoint-specific limits
        $chunks = $this->chunkSymbolsForEndpoint($symbols, $maxSymbolsPerRequest);
        $responses = [];
        $errors = [];
        $totalProcessed = 0;

        foreach ($chunks as $chunkIndex => $chunk) {
            $chunkStartTime = microtime(true);
            
            try {
                $chunkResponse = $this->executeChunkRequest($realEndpoint, $chunk, $options, $chunkIndex, $endpointType);
                $chunkEndTime = microtime(true);
                $chunkDuration = $chunkEndTime - $chunkStartTime;
                
                $responses[] = $chunkResponse;
                $totalProcessed += count($chunk);

                // Report progress for successful chunk
                if ($this->progressTracker) {
                    $this->progressTracker->reportChunkProgress($chunkIndex, count($chunk), $chunkDuration, true);
                }

                // Add delay between chunks to avoid rate limiting
                if ($chunkIndex < count($chunks) - 1 && $this->chunkDelay > 0) {
                    usleep((int)($this->chunkDelay * 1000000));
                }

            } catch (\Exception $e) {
                $chunkEndTime = microtime(true);
                $chunkDuration = $chunkEndTime - $chunkStartTime;
                
                $error = [
                    'chunk' => $chunkIndex,
                    'symbols' => $chunk,
                    'error' => $e->getMessage(),
                    'code' => $e->getCode(),
                    'duration' => $chunkDuration
                ];
                $errors[] = $error;

                // Report progress for failed chunk
                if ($this->progressTracker) {
                    $this->progressTracker->reportChunkProgress($chunkIndex, count($chunk), $chunkDuration, false);
                }

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
        if (($responses === null || $responses === '' || $responses === [])) {
            throw new BulkOperationException(
                'All bulk operation chunks failed',
                $errors,
                []
            );
        }

        // Merge responses with progress information
        $mergedResponse = $this->mergeResponses($responses, $errors, $totalProcessed, count($symbols));
        
        // Add final progress information to response metadata
        if ($this->progressTracker) {
            $finalProgress = $this->progressTracker->getCompletionStatus();
            $mergedResponse = $this->addProgressMetadata($mergedResponse, $finalProgress);
        }

        return $mergedResponse;
    }

    /**
     * Execute single symbol request (optimization path)
     */
    private function executeSingleRequest(string $endpoint, string $symbol, array $options, string $endpointType): Response
    {
        // Build URL based on endpoint type
        $url = $this->buildUrlForEndpoint($endpoint, [$symbol], $options, $endpointType);
        return $this->client->get($url);
    }

    /**
     * Execute request for a chunk of symbols
     */
    private function executeChunkRequest(string $endpoint, array $chunk, array $options, int $chunkIndex, string $endpointType): Response
    {
        // Build URL based on endpoint type and chunk
        $url = $this->buildUrlForEndpoint($endpoint, $chunk, $options, $endpointType);
        
        // Increase timeout for larger chunks
        $chunkTimeout = $this->calculateChunkTimeout(count($chunk));
        $chunkOptions = array_merge($options, ['timeout' => $chunkTimeout]);

        return $this->client->get($url, $chunkOptions);
    }

    /**
     * Build URL for specific endpoint type
     */
    private function buildUrlForEndpoint(string $endpoint, array $symbols, array $options, string $endpointType): string
    {
        $url = $endpoint;
        
        // Handle different endpoint types based on their API requirements
        switch ($endpointType) {
            case 'quotes':
                // /v2/stocks/get supports multiple symbols via 'stocks' parameter
                $symbolsParam = implode(',', $symbols);
                $separator = strpos($url, '?') !== false ? '&' : '?';
                $url .= $separator . 'stocks=' . urlencode($symbolsParam);
                break;
                
            case 'timeline':
                // /v2/stocks/chart/timeline supports single symbol via 'ticker' parameter
                if (count($symbols) > 1) {
                    throw new ValidationException('Timeline endpoint only supports single symbol per request');
                }
                $separator = strpos($url, '?') !== false ? '&' : '?';
                $url .= $separator . 'ticker=' . urlencode($symbols[0]);
                break;
                
            case 'info':
                // /v2/stocks/info supports single symbol via 'ticker' parameter
                if (count($symbols) > 1) {
                    throw new ValidationException('Info endpoint only supports single symbol per request');
                }
                $separator = strpos($url, '?') !== false ? '&' : '?';
                $url .= $separator . 'ticker=' . urlencode($symbols[0]);
                break;
                
            case 'financials':
                // /v2/stocks/financials supports single symbol via 'ticker' parameter
                if (count($symbols) > 1) {
                    throw new ValidationException('Financials endpoint only supports single symbol per request');
                }
                $separator = strpos($url, '?') !== false ? '&' : '?';
                $url .= $separator . 'ticker=' . urlencode($symbols[0]);
                break;
                
            default:
                throw new ValidationException("Unknown endpoint type: {$endpointType}");
        }

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
     * Chunk symbols into manageable groups (legacy method)
     *
     * @param array<string> $symbols
     * @return array<int, array<string>>
     */
    private function chunkSymbols(array $symbols): array
    {
        return array_chunk($symbols, $this->maxSymbolsPerChunk);
    }
    
    /**
     * Chunk symbols based on endpoint-specific limits
     *
     * @param array<string> $symbols
     * @param int $maxSymbolsPerRequest
     * @return array<int, array<string>>
     */
    private function chunkSymbolsForEndpoint(array $symbols, int $maxSymbolsPerRequest): array
    {
        return array_chunk($symbols, $maxSymbolsPerRequest);
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
        if (($responses === null || $responses === '' || $responses === [])) {
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
        if (($allTickers !== null && $allTickers !== '' && $allTickers !== [])) {
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
        if (($errors !== null && $errors !== '' && $errors !== [])) {
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
        if (($symbols === null || $symbols === '' || $symbols === [])) {
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
            if (!is_string($symbol) || ($symbol === null || $symbol === '' || $symbol === [])) {
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
     * Map bulk endpoint to real API endpoint with limits
     *
     * @param string $bulkEndpoint The bulk endpoint (e.g., /v2/stocks/bulk/quote)
     * @return array<string, mixed> Endpoint info with real endpoint, limits, and type
     * @throws ValidationException
     */
    private function mapBulkEndpoint(string $bulkEndpoint): array
    {
        // Map bulk endpoints to real API endpoints
        $endpointMapping = [
            '/v2/stocks/bulk/quote' => [
                'endpoint' => '/v2/stocks/get',
                'max_symbols' => self::ENDPOINT_LIMITS['quotes'],
                'type' => 'quotes'
            ],
            '/v2/stocks/bulk/timeline' => [
                'endpoint' => '/v2/stocks/chart/timeline',
                'max_symbols' => self::ENDPOINT_LIMITS['timeline'],
                'type' => 'timeline'
            ],
            '/v2/stocks/bulk/info' => [
                'endpoint' => '/v2/stocks/info',
                'max_symbols' => self::ENDPOINT_LIMITS['info'],
                'type' => 'info'
            ],
            '/v2/stocks/bulk/financials' => [
                'endpoint' => '/v2/stocks/financials',
                'max_symbols' => self::ENDPOINT_LIMITS['financials'],
                'type' => 'financials'
            ]
        ];

        if (!isset($endpointMapping[$bulkEndpoint])) {
            throw new ValidationException("Unknown bulk endpoint: {$bulkEndpoint}");
        }

        return $endpointMapping[$bulkEndpoint];
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
            'max_total_symbols' => self::MAX_TOTAL_SYMBOLS,
            'endpoint_limits' => self::ENDPOINT_LIMITS
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

    /**
     * Get the current progress tracker
     */
    public function getProgressTracker(): ?ProgressTracker
    {
        return $this->progressTracker;
    }

    /**
     * Add progress metadata to response
     */
    private function addProgressMetadata(Response $response, array $progressData): Response
    {
        $data = $response->data();
        
        // Add progress information to bulk_operation metadata
        if (isset($data['bulk_operation'])) {
            $data['bulk_operation']['progress'] = $progressData;
        } else {
            $data['bulk_operation'] = ['progress' => $progressData];
        }

        // Create new response with updated data
        return new Response(
            $response->status(),
            $data,
            $response->headers(),
            $response->raw()
        );
    }
}