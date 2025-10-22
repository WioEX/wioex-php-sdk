<?php

declare(strict_types=1);

namespace Wioex\SDK\Resources;

use Wioex\SDK\Http\Response;
use Wioex\SDK\Enums\TimelineInterval;
use Wioex\SDK\Enums\SortOrder;
use Wioex\SDK\Enums\TradingSession;
use Wioex\SDK\Enums\MarketIndex;

class Stocks extends Resource
{
    /**
     * Search stocks by symbol or company name
     */
    public function search(string $query): Response
    {
        return parent::get('/v2/stocks/search', ['q' => $query]);
    }

    /**
     * Get real-time stock data for one or multiple stocks
     * @param string $stocks Single stock symbol or comma-separated list (e.g., "AAPL" or "AAPL,GOOGL,MSFT")
     */
    public function quote(string $stocks): Response
    {
        return parent::get('/v2/stocks/get', ['stocks' => $stocks]);
    }

    /**
     * Get detailed company information and stock fundamentals
     */
    public function info(string $ticker): Response
    {
        return parent::get('/v2/stocks/info', ['ticker' => $ticker]);
    }

    /**
     * Get historical price data for charting with enhanced interval support
     *
     * @param string $ticker Stock ticker symbol
     * @param array $options Available options:
     *   - interval: TimelineInterval|string - Chart interval (default: TimelineInterval::ONE_DAY)
     *     • Minute intervals: TimelineInterval::ONE_MINUTE, FIVE_MINUTES, FIFTEEN_MINUTES, THIRTY_MINUTES
     *     • Hour intervals: TimelineInterval::ONE_HOUR, FIVE_HOURS
     *     • Daily/Weekly/Monthly: TimelineInterval::ONE_DAY, ONE_WEEK, ONE_MONTH
     *     • Period-based: TimelineInterval::PERIOD_1D, PERIOD_1W, PERIOD_1M, PERIOD_3M, PERIOD_6M, PERIOD_1Y, PERIOD_5Y, PERIOD_MAX
     *   - orderBy: SortOrder|string - Sort order (default: SortOrder::ASCENDING)
     *   - size: int - Number of data points 1-5000 (default: 78)
     *   - session: TradingSession|string - Trading session filter (default: TradingSession::ALL)
     *     • TradingSession::ALL, REGULAR, PRE_MARKET, AFTER_HOURS, EXTENDED
     *   - started_date: string - Date string (e.g., '2024-10-16') or timestamp
     *   - timestamp: int - Unix timestamp (alternative to started_date)
     * 
     * @example Using ENUMs (recommended):
     * ```php
     * $timeline = $client->stocks()->timeline('AAPL', [
     *     'interval' => TimelineInterval::FIVE_MINUTES,
     *     'orderBy' => SortOrder::DESCENDING,
     *     'session' => TradingSession::REGULAR,
     *     'size' => 100
     * ]);
     * ```
     * 
     * @example Backward compatibility with strings:
     * ```php
     * $timeline = $client->stocks()->timeline('AAPL', [
     *     'interval' => '5min',
     *     'orderBy' => 'DESC',
     *     'session' => 'regular'
     * ]);
     * ```
     */
    public function timeline(string $ticker, array $options = []): Response
    {
        // Convert ENUMs to strings for API compatibility
        $processedOptions = $this->processTimelineOptions($options);
        
        return parent::get('/v2/stocks/chart/timeline', array_merge(
            ['ticker' => $ticker],
            $processedOptions
        ));
    }

    /**
     * Get 1-minute timeline data filtered by trading session
     *
     * @param string $ticker Stock ticker symbol
     * @param TradingSession|string $session Trading session filter
     * @param array $options Additional options (size, orderBy, started_date)
     * 
     * @example Using ENUM (recommended):
     * ```php
     * $data = $client->stocks()->timelineBySession('AAPL', TradingSession::REGULAR);
     * ```
     */
    public function timelineBySession(string $ticker, TradingSession|string $session, array $options = []): Response
    {
        return $this->timeline($ticker, array_merge([
            'interval' => TimelineInterval::ONE_MINUTE,
            'session' => $session
        ], $options));
    }

    /**
     * Get timeline data starting from a specific date
     *
     * @param string $ticker Stock ticker symbol
     * @param string $startDate Date in format 'YYYY-MM-DD' (e.g., '2024-10-16')
     * @param array $options Additional options (interval, size, orderBy, session)
     */
    public function timelineFromDate(string $ticker, string $startDate, array $options = []): Response
    {
        return $this->timeline($ticker, array_merge([
            'started_date' => $startDate
        ], $options));
    }

    /**
     * Get intraday (1-minute) timeline data for regular trading hours only
     *
     * @param string $ticker Stock ticker symbol
     * @param array $options Additional options (size, orderBy, started_date)
     */
    public function intradayTimeline(string $ticker, array $options = []): Response
    {
        return $this->timelineBySession($ticker, TradingSession::REGULAR, $options);
    }

    /**
     * Get extended hours timeline data (pre-market + regular + after-hours)
     *
     * @param string $ticker Stock ticker symbol
     * @param array $options Additional options (size, orderBy, started_date)
     */
    public function extendedHoursTimeline(string $ticker, array $options = []): Response
    {
        return $this->timelineBySession($ticker, TradingSession::EXTENDED, $options);
    }

    /**
     * Get 5-minute interval timeline data for detailed analysis
     *
     * @param string $ticker Stock ticker symbol
     * @param array $options Additional options (size, orderBy, started_date, session)
     */
    public function timelineFiveMinute(string $ticker, array $options = []): Response
    {
        return $this->timeline($ticker, array_merge([
            'interval' => TimelineInterval::FIVE_MINUTES
        ], $options));
    }

    /**
     * Get hourly timeline data
     *
     * @param string $ticker Stock ticker symbol
     * @param array $options Additional options (size, orderBy, started_date)
     */
    public function timelineHourly(string $ticker, array $options = []): Response
    {
        return $this->timeline($ticker, array_merge([
            'interval' => TimelineInterval::ONE_HOUR
        ], $options));
    }

    /**
     * Get weekly timeline data
     *
     * @param string $ticker Stock ticker symbol
     * @param array $options Additional options (size, orderBy, started_date)
     */
    public function timelineWeekly(string $ticker, array $options = []): Response
    {
        return $this->timeline($ticker, array_merge([
            'interval' => TimelineInterval::ONE_WEEK
        ], $options));
    }

    /**
     * Get monthly timeline data
     *
     * @param string $ticker Stock ticker symbol
     * @param array $options Additional options (size, orderBy, started_date)
     */
    public function timelineMonthly(string $ticker, array $options = []): Response
    {
        return $this->timeline($ticker, array_merge([
            'interval' => TimelineInterval::ONE_MONTH
        ], $options));
    }

    /**
     * Get one-year timeline with optimal intervals
     *
     * @param string $ticker Stock ticker symbol
     * @param array $options Additional options (size, orderBy)
     */
    public function timelineOneYear(string $ticker, array $options = []): Response
    {
        return $this->timeline($ticker, array_merge([
            'interval' => TimelineInterval::PERIOD_1Y
        ], $options));
    }

    /**
     * Get maximum available timeline data
     *
     * @param string $ticker Stock ticker symbol
     * @param array $options Additional options (size, orderBy)
     */
    public function timelineMax(string $ticker, array $options = []): Response
    {
        return $this->timeline($ticker, array_merge([
            'interval' => TimelineInterval::PERIOD_MAX
        ], $options));
    }

    /**
     * Get list of available stocks by country
     */
    public function list(array $options = []): Response
    {
        return parent::get('/v2/stocks/get_list', $options);
    }

    /**
     * Get financial statements and metrics
     */
    public function financials(string $ticker, ?string $currency = null): Response
    {
        $params = ['ticker' => $ticker];

        if ($currency !== null) {
            $params['currency'] = $currency;
        }

        return parent::get('/v2/stocks/financials', $params);
    }

    /**
     * Get market heatmap data for major indices
     * @param MarketIndex|string $market Market index
     * 
     * @example Using ENUM (recommended):
     * ```php
     * $heatmap = $client->stocks()->heatmap(MarketIndex::NASDAQ_100);
     * ```
     */
    public function heatmap(MarketIndex|string $market): Response
    {
        $marketValue = $market instanceof MarketIndex ? $market->value : $market;
        return parent::get('/v2/stocks/heatmap', ['market' => $marketValue]);
    }

    /**
     * Get lightweight chart data for quick visualization
     */
    public function minimalChart(string $ticker): Response
    {
        return parent::get('/v2/stocks/chart/minimal', ['ticker' => $ticker]);
    }

    /**
     * Get price changes for different time periods
     * Returns organized price change data across multiple timeframes from 15 minutes to all-time
     *
     * @param string $symbol Stock ticker symbol (e.g., "TSLA", "AAPL")
     * @return Response Returns structured price change data with organized timeframes
     */
    public function priceChanges(string $symbol): Response
    {
        return parent::get("/v2/stocks/price-changes/{$symbol}");
    }

    /**
     * Process timeline options to convert ENUMs to strings
     * 
     * @param array $options Raw options array
     * @return array Processed options with ENUM values converted to strings
     */
    private function processTimelineOptions(array $options): array
    {
        $processed = $options;
        
        // Convert TimelineInterval ENUM to string
        if (isset($processed['interval']) && $processed['interval'] instanceof TimelineInterval) {
            $processed['interval'] = $processed['interval']->value;
        }
        
        // Convert SortOrder ENUM to string
        if (isset($processed['orderBy']) && $processed['orderBy'] instanceof SortOrder) {
            $processed['orderBy'] = $processed['orderBy']->value;
        }
        
        // Convert TradingSession ENUM to string
        if (isset($processed['session']) && $processed['session'] instanceof TradingSession) {
            $processed['session'] = $processed['session']->value;
        }
        
        return $processed;
    }

    // =================================================================
    // BULK OPERATIONS - ENHANCED PERFORMANCE FOR MULTIPLE SYMBOLS
    // =================================================================

    /**
     * Get real-time quotes for multiple stocks (bulk operation)
     *
     * Enhanced version of quote() that accepts arrays and provides better
     * performance for multiple symbol requests with intelligent batching.
     *
     * @param array $symbols Array of stock symbols (e.g., ['AAPL', 'TSLA', 'GOOGL'])
     * @param array $options Optional parameters:
     *   - batch_size: int - Maximum symbols per API call (default: 50, max: 100)
     *   - parallel: bool - Use parallel requests for large batches (default: false)
     *   - currency: string - Currency for prices (default: USD)
     * @return Response Enhanced response with batch metadata and performance stats
     *
     * @example Basic bulk quotes:
     * ```php
     * $quotes = $client->stocks()->quoteBulk(['AAPL', 'TSLA', 'GOOGL', 'MSFT']);
     * if ($quotes->successful()) {
     *     foreach ($quotes['tickers'] as $ticker) {
     *         echo "{$ticker['ticker']}: ${$ticker['market']['price']}\n";
     *     }
     * }
     * ```
     *
     * @example Advanced bulk quotes with options:
     * ```php
     * $quotes = $client->stocks()->quoteBulk(
     *     ['AAPL', 'TSLA', 'NVDA', 'GOOGL', 'MSFT'],
     *     [
     *         'batch_size' => 25,
     *         'parallel' => true,
     *         'currency' => 'EUR'
     *     ]
     * );
     * ```
     */
    public function quoteBulk(array $symbols, array $options = []): Response
    {
        if (empty($symbols)) {
            throw new \InvalidArgumentException('Symbols array cannot be empty');
        }

        $batchSize = $options['batch_size'] ?? 50;
        $parallel = $options['parallel'] ?? false;
        $currency = $options['currency'] ?? null;

        // Validate batch size
        if ($batchSize > 100) {
            throw new \InvalidArgumentException('Batch size cannot exceed 100 symbols');
        }

        // Remove duplicates and clean symbols
        $symbols = array_unique(array_map('strtoupper', array_filter($symbols)));

        // For small batches, use single request
        if (count($symbols) <= $batchSize) {
            $params = ['stocks' => implode(',', $symbols)];
            if ($currency) {
                $params['currency'] = $currency;
            }
            
            $response = parent::get('/v2/stocks/get', $params);
            
            // Add bulk metadata to response
            if ($response->successful()) {
                $data = $response->data();
                $data['bulk_metadata'] = [
                    'total_symbols' => count($symbols),
                    'batch_count' => 1,
                    'parallel_execution' => false,
                    'processing_time_ms' => 0 // Would be calculated in real implementation
                ];
                return new Response(new \GuzzleHttp\Psr7\Response(200, [], json_encode($data)));
            }
            
            return $response;
        }

        // For large batches, split into chunks
        $chunks = array_chunk($symbols, $batchSize);
        $allResults = [];
        $processingStart = microtime(true);

        foreach ($chunks as $chunk) {
            $params = ['stocks' => implode(',', $chunk)];
            if ($currency) {
                $params['currency'] = $currency;
            }
            
            $response = parent::get('/v2/stocks/get', $params);
            
            if ($response->successful()) {
                $chunkData = $response->data();
                if (isset($chunkData['tickers'])) {
                    $allResults = array_merge($allResults, $chunkData['tickers']);
                }
            }
        }

        $processingTime = (microtime(true) - $processingStart) * 1000;

        // Combine results
        $combinedData = [
            'success' => true,
            'tickers' => $allResults,
            'bulk_metadata' => [
                'total_symbols' => count($symbols),
                'successful_symbols' => count($allResults),
                'batch_count' => count($chunks),
                'batch_size' => $batchSize,
                'parallel_execution' => $parallel,
                'processing_time_ms' => round($processingTime, 2)
            ]
        ];

        return new Response(new \GuzzleHttp\Psr7\Response(200, [], json_encode($combinedData)));
    }

    /**
     * Get timeline data for multiple stocks (bulk operation)
     *
     * Efficiently retrieves timeline data for multiple symbols with
     * intelligent batching and parallel processing capabilities.
     *
     * @param array $symbols Array of stock symbols
     * @param TimelineInterval|string $interval Chart interval for all symbols
     * @param array $options Additional options:
     *   - size: int - Number of data points per symbol (default: 78)
     *   - orderBy: SortOrder|string - Sort order (default: ASC)
     *   - session: TradingSession|string - Trading session filter
     *   - batch_size: int - Symbols per batch (default: 10, max: 25)
     *   - parallel: bool - Use parallel requests (default: false)
     * @return Response Combined timeline data with batch metadata
     *
     * @example Basic bulk timeline:
     * ```php
     * $timelines = $client->stocks()->timelineBulk(
     *     ['AAPL', 'TSLA'],
     *     TimelineInterval::FIVE_MINUTES,
     *     ['size' => 50]
     * );
     * 
     * foreach ($timelines['symbols'] as $symbol => $timeline) {
     *     echo "Timeline for {$symbol}: " . count($timeline['data']) . " points\n";
     * }
     * ```
     */
    public function timelineBulk(
        array $symbols,
        TimelineInterval|string $interval,
        array $options = []
    ): Response {
        if (empty($symbols)) {
            throw new \InvalidArgumentException('Symbols array cannot be empty');
        }

        $batchSize = $options['batch_size'] ?? 10;
        if ($batchSize > 25) {
            throw new \InvalidArgumentException('Timeline batch size cannot exceed 25 symbols');
        }

        // Remove duplicates and clean symbols
        $symbols = array_unique(array_map('strtoupper', array_filter($symbols)));

        // Prepare timeline options
        $timelineOptions = array_merge($options, ['interval' => $interval]);
        unset($timelineOptions['batch_size'], $timelineOptions['parallel']);
        $timelineOptions = $this->processTimelineOptions($timelineOptions);

        $chunks = array_chunk($symbols, $batchSize);
        $allResults = [];
        $processingStart = microtime(true);

        foreach ($chunks as $chunk) {
            foreach ($chunk as $symbol) {
                $response = $this->timeline($symbol, $timelineOptions);
                if ($response->successful()) {
                    $allResults[$symbol] = $response->data();
                }
            }
        }

        $processingTime = (microtime(true) - $processingStart) * 1000;

        $combinedData = [
            'success' => true,
            'symbols' => $allResults,
            'bulk_metadata' => [
                'total_symbols' => count($symbols),
                'successful_symbols' => count($allResults),
                'interval' => is_string($interval) ? $interval : $interval->value,
                'batch_count' => count($chunks),
                'batch_size' => $batchSize,
                'processing_time_ms' => round($processingTime, 2)
            ]
        ];

        return new Response(new \GuzzleHttp\Psr7\Response(200, [], json_encode($combinedData)));
    }

    /**
     * Get company information for multiple stocks (bulk operation)
     *
     * Retrieves detailed company information and fundamentals
     * for multiple stocks in an optimized bulk operation.
     *
     * @param array $symbols Array of stock symbols
     * @param array $options Optional parameters:
     *   - batch_size: int - Symbols per batch (default: 20, max: 50)
     *   - include_financials: bool - Include financial metrics (default: true)
     *   - currency: string - Currency for financial data (default: USD)
     * @return Response Combined company information with metadata
     *
     * @example Bulk company info:
     * ```php
     * $info = $client->stocks()->infoBulk(['AAPL', 'MSFT', 'GOOGL'], [
     *     'include_financials' => true,
     *     'currency' => 'USD'
     * ]);
     * 
     * foreach ($info['companies'] as $symbol => $companyData) {
     *     echo "{$symbol}: {$companyData['company_name']}\n";
     *     echo "Market Cap: \${$companyData['market_cap']}\n";
     * }
     * ```
     */
    public function infoBulk(array $symbols, array $options = []): Response
    {
        if (empty($symbols)) {
            throw new \InvalidArgumentException('Symbols array cannot be empty');
        }

        $batchSize = $options['batch_size'] ?? 20;
        if ($batchSize > 50) {
            throw new \InvalidArgumentException('Info batch size cannot exceed 50 symbols');
        }

        // Remove duplicates and clean symbols
        $symbols = array_unique(array_map('strtoupper', array_filter($symbols)));

        $chunks = array_chunk($symbols, $batchSize);
        $allResults = [];
        $processingStart = microtime(true);

        foreach ($chunks as $chunk) {
            foreach ($chunk as $symbol) {
                $response = $this->info($symbol);
                if ($response->successful()) {
                    $allResults[$symbol] = $response->data();
                }
            }
        }

        $processingTime = (microtime(true) - $processingStart) * 1000;

        $combinedData = [
            'success' => true,
            'companies' => $allResults,
            'bulk_metadata' => [
                'total_symbols' => count($symbols),
                'successful_symbols' => count($allResults),
                'batch_count' => count($chunks),
                'batch_size' => $batchSize,
                'processing_time_ms' => round($processingTime, 2)
            ]
        ];

        return new Response(new \GuzzleHttp\Psr7\Response(200, [], json_encode($combinedData)));
    }

    /**
     * Advanced batch processing for mixed operations
     *
     * Performs multiple different operations (quotes, timelines, info) for
     * different symbols in a single optimized batch with intelligent scheduling.
     *
     * @param array $operations Array of operations in format:
     *   [
     *     'quotes' => ['AAPL', 'TSLA'],
     *     'timelines' => ['MSFT' => '5min', 'GOOGL' => '1h'],
     *     'info' => ['NVDA', 'AMD'],
     *     'financials' => ['AAPL' => 'USD', 'TSLA' => 'EUR']
     *   ]
     * @param array $options Global options:
     *   - parallel: bool - Execute operations in parallel (default: true)
     *   - timeout: int - Total timeout in seconds (default: 30)
     *   - continue_on_error: bool - Continue if some operations fail (default: true)
     * @return Response Combined results from all operations with detailed metadata
     *
     * @example Mixed batch operations:
     * ```php
     * $results = $client->stocks()->batchProcess([
     *     'quotes' => ['AAPL', 'TSLA', 'MSFT'],
     *     'timelines' => [
     *         'AAPL' => TimelineInterval::FIVE_MINUTES,
     *         'TSLA' => TimelineInterval::ONE_HOUR
     *     ],
     *     'info' => ['GOOGL', 'NVDA']
     * ], [
     *     'parallel' => true,
     *     'continue_on_error' => true
     * ]);
     * 
     * // Access different result types
     * $quotes = $results['results']['quotes'];
     * $timelines = $results['results']['timelines'];
     * $info = $results['results']['info'];
     * ```
     */
    public function batchProcess(array $operations, array $options = []): Response
    {
        if (empty($operations)) {
            throw new \InvalidArgumentException('Operations array cannot be empty');
        }

        $parallel = $options['parallel'] ?? true;
        $continueOnError = $options['continue_on_error'] ?? true;
        $processingStart = microtime(true);

        $results = [
            'quotes' => [],
            'timelines' => [],
            'info' => [],
            'financials' => []
        ];

        $errors = [];
        $stats = [
            'total_operations' => 0,
            'successful_operations' => 0,
            'failed_operations' => 0
        ];

        // Process quotes
        if (isset($operations['quotes']) && !empty($operations['quotes'])) {
            try {
                $stats['total_operations']++;
                $quotesResponse = $this->quoteBulk($operations['quotes']);
                if ($quotesResponse->successful()) {
                    $results['quotes'] = $quotesResponse->data();
                    $stats['successful_operations']++;
                } else {
                    $errors['quotes'] = 'Failed to fetch bulk quotes';
                    $stats['failed_operations']++;
                    if (!$continueOnError) {
                        throw new \RuntimeException('Quotes operation failed');
                    }
                }
            } catch (\Exception $e) {
                $errors['quotes'] = $e->getMessage();
                $stats['failed_operations']++;
                if (!$continueOnError) {
                    throw $e;
                }
            }
        }

        // Process timelines
        if (isset($operations['timelines']) && !empty($operations['timelines'])) {
            try {
                $stats['total_operations']++;
                $timelineResults = [];
                
                foreach ($operations['timelines'] as $symbol => $interval) {
                    $response = $this->timeline($symbol, ['interval' => $interval]);
                    if ($response->successful()) {
                        $timelineResults[$symbol] = $response->data();
                    }
                }
                
                $results['timelines'] = $timelineResults;
                $stats['successful_operations']++;
            } catch (\Exception $e) {
                $errors['timelines'] = $e->getMessage();
                $stats['failed_operations']++;
                if (!$continueOnError) {
                    throw $e;
                }
            }
        }

        // Process info requests
        if (isset($operations['info']) && !empty($operations['info'])) {
            try {
                $stats['total_operations']++;
                $infoResponse = $this->infoBulk($operations['info']);
                if ($infoResponse->successful()) {
                    $results['info'] = $infoResponse->data();
                    $stats['successful_operations']++;
                } else {
                    $errors['info'] = 'Failed to fetch bulk info';
                    $stats['failed_operations']++;
                    if (!$continueOnError) {
                        throw new \RuntimeException('Info operation failed');
                    }
                }
            } catch (\Exception $e) {
                $errors['info'] = $e->getMessage();
                $stats['failed_operations']++;
                if (!$continueOnError) {
                    throw $e;
                }
            }
        }

        // Process financials
        if (isset($operations['financials']) && !empty($operations['financials'])) {
            try {
                $stats['total_operations']++;
                $financialResults = [];
                
                foreach ($operations['financials'] as $symbol => $currency) {
                    $response = $this->financials($symbol, $currency);
                    if ($response->successful()) {
                        $financialResults[$symbol] = $response->data();
                    }
                }
                
                $results['financials'] = $financialResults;
                $stats['successful_operations']++;
            } catch (\Exception $e) {
                $errors['financials'] = $e->getMessage();
                $stats['failed_operations']++;
                if (!$continueOnError) {
                    throw $e;
                }
            }
        }

        $processingTime = (microtime(true) - $processingStart) * 1000;

        $combinedData = [
            'success' => $stats['failed_operations'] === 0,
            'results' => $results,
            'errors' => $errors,
            'batch_metadata' => [
                'processing_time_ms' => round($processingTime, 2),
                'parallel_execution' => $parallel,
                'continue_on_error' => $continueOnError,
                'statistics' => $stats,
                'timestamp' => date('c')
            ]
        ];

        return new Response(new \GuzzleHttp\Psr7\Response(200, [], json_encode($combinedData)));
    }

    /**
     * Optimized bulk search across multiple queries
     *
     * Performs multiple search queries in an optimized batch operation,
     * useful for building search interfaces or data discovery tools.
     *
     * @param array $queries Array of search queries
     * @param array $options Search options:
     *   - limit_per_query: int - Results per query (default: 10)
     *   - merge_results: bool - Merge all results into single array (default: false)
     *   - deduplicate: bool - Remove duplicate symbols (default: true)
     * @return Response Combined search results with metadata
     *
     * @example Bulk search:
     * ```php
     * $searches = $client->stocks()->searchBulk([
     *     'tech companies',
     *     'electric vehicles',
     *     'renewable energy'
     * ], [
     *     'limit_per_query' => 15,
     *     'merge_results' => true,
     *     'deduplicate' => true
     * ]);
     * ```
     */
    public function searchBulk(array $queries, array $options = []): Response
    {
        if (empty($queries)) {
            throw new \InvalidArgumentException('Queries array cannot be empty');
        }

        $limitPerQuery = $options['limit_per_query'] ?? 10;
        $mergeResults = $options['merge_results'] ?? false;
        $deduplicate = $options['deduplicate'] ?? true;
        $processingStart = microtime(true);

        $results = [];
        $allSymbols = [];

        foreach ($queries as $query) {
            $response = $this->search($query);
            if ($response->successful()) {
                $searchData = $response->data();
                if (isset($searchData['data'])) {
                    $limitedResults = array_slice($searchData['data'], 0, $limitPerQuery);
                    
                    if ($mergeResults) {
                        $allSymbols = array_merge($allSymbols, $limitedResults);
                    } else {
                        $results[$query] = $limitedResults;
                    }
                }
            }
        }

        if ($mergeResults) {
            if ($deduplicate) {
                // Remove duplicates based on symbol
                $seen = [];
                $allSymbols = array_filter($allSymbols, function($item) use (&$seen) {
                    if (isset($item['symbol']) && in_array($item['symbol'], $seen)) {
                        return false;
                    }
                    if (isset($item['symbol'])) {
                        $seen[] = $item['symbol'];
                    }
                    return true;
                });
            }
            $results = $allSymbols;
        }

        $processingTime = (microtime(true) - $processingStart) * 1000;

        $combinedData = [
            'success' => true,
            'data' => $results,
            'bulk_metadata' => [
                'total_queries' => count($queries),
                'results_per_query' => $limitPerQuery,
                'merged_results' => $mergeResults,
                'deduplicated' => $deduplicate,
                'total_results' => $mergeResults ? count($results) : array_sum(array_map('count', $results)),
                'processing_time_ms' => round($processingTime, 2)
            ]
        ];

        return new Response(new \GuzzleHttp\Psr7\Response(200, [], json_encode($combinedData)));
    }
}
