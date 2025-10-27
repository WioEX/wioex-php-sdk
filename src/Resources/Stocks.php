<?php

declare(strict_types=1);

namespace Wioex\SDK\Resources;

use Wioex\SDK\Http\Response;
use Wioex\SDK\Http\BulkRequestManager;
use Wioex\SDK\Enums\TimelineInterval;
use Wioex\SDK\Enums\SortOrder;
use Wioex\SDK\Enums\TradingSession;
use Wioex\SDK\Enums\MarketIndex;
use Wioex\SDK\Exceptions\BulkOperationException;
use Wioex\SDK\Exceptions\ValidationException;

class Stocks extends Resource
{
    /**
     * Search stocks by symbol or company name
     * 
     * Returns standardized WioEX API response with unified metadata structure.
     * 
     * @param string $query Search term (company name or symbol, max 50 characters)
     * @return Response Standardized search results with metadata
     * 
     * Response structure:
     * ```json
     * {
     *   "metadata": {
     *     "wioex": {"api_version": "2.0", "brand": "WioEX Financial Data API"},
     *     "response": {"timestamp_utc": "...", "request_id": "..."},
     *     "performance": {"total_time_ms": 3.36}
     *   },
     *   "status": "success",
     *   "data": {
     *     "query": "AAPL",
     *     "total_results": 1,
     *     "instruments": [
     *       {"symbol": "AAPL", "name": "Apple Inc", "exc_name": "NASDAQ", "country": "USA"}
     *     ],
     *     "search_provider": "sarexDb"
     *   }
     * }
     * ```
     * 
     * @example Basic Usage:
     * ```php
     * // Search for Apple stocks (case-insensitive)
     * $results = $client->stocks()->search('apple');
     * 
     * if ($results->successful()) {
     *     // Using convenient helper methods (recommended)
     *     echo "Found {$results->getSearchResultsCount()} results for: {$results->getSearchQuery()}\n";
     *     echo "Provider: {$results->getSearchProvider()}\n\n";
     *     
     *     foreach ($results->getSearchResults() as $stock) {
     *         echo "- {$stock['symbol']}: {$stock['name']} ({$stock['exc_name']})\n";
     *     }
     *     
     *     // Access metadata
     *     $performance = $results->getPerformance();
     *     echo "Search completed in {$performance['total_time_ms']}ms\n";
     * }
     * ```
     * 
     * @example Advanced Usage:
     * ```php
     * // Case-insensitive search with result processing
     * $results = $client->stocks()->search('PALa');  // Finds Palantir
     * 
     * if ($results->hasSearchResults()) {
     *     // Get most relevant result
     *     $topResult = $results->getFirstSearchResult();
     *     echo "Top result: {$topResult['symbol']} - {$topResult['name']}\n";
     *     
     *     // Find specific symbol
     *     $palantir = $results->findResultBySymbol('PLTR');
     *     if ($palantir) {
     *         echo "Found Palantir: {$palantir['name']} ({$palantir['exc_name']})\n";
     *     }
     *     
     *     // Get all symbols for further processing
     *     $symbols = $results->getSearchSymbols();
     *     echo "Symbols found: " . implode(', ', $symbols) . "\n";
     *     
     *     // Validate response structure
     *     if ($results->isSearchResponse()) {
     *         echo "✅ Valid search response format\n";
     *     }
     * }
     * ```
     * 
     * @example Raw Data Access (legacy compatibility):
     * ```php
     * $results = $client->stocks()->search('tesla');
     * 
     * if ($results->successful()) {
     *     // Direct data access (still supported)
     *     $searchData = $results->data()['data'];
     *     echo "Query: {$searchData['query']}\n";
     *     echo "Results: {$searchData['total_results']}\n";
     *     
     *     foreach ($searchData['instruments'] as $stock) {
     *         echo "- {$stock['symbol']}: {$stock['name']}\n";
     *     }
     * }
     * ```
     */
    public function search(string $query): Response
    {
        return parent::get('/v2/stocks/search', ['q' => $query]);
    }

    /**
     * Get real-time stock data for one or multiple stocks using unified ResponseTemplate format
     * 
     * @param string $stocks Single stock symbol or comma-separated list (e.g., "AAPL" or "AAPL,GOOGL,MSFT")
     * @param array $options Additional options:
     *   - detailed: bool - Get enhanced data with pre/post market, 52-week ranges, logos, etc.
     * 
     * @return Response Unified response with metadata and data sections
     * 
     * Response structure:
     * ```json
     * {
     *   "metadata": {
     *     "wioex": {"api_version": "2.0", "brand": "WioEX Financial Data API"},
     *     "response": {"timestamp_utc": "...", "request_id": "..."},
     *     "data_quality": {"data_freshness": "real_time", "provider_used": "..."},
     *     "performance": {"response_time_ms": 150}
     *   },
     *   "data": {
     *     "instruments": [{"symbol": "AAPL", "price": {...}, "change": {...}}]
     *   }
     * }
     * ```
     * 
     * @example
     * ```php
     * // Basic quote
     * $response = $client->stocks()->quote('AAPL');
     * $instruments = $response->getInstruments();
     * $stock = $instruments[0];
     * echo "Price: $" . $stock['price']['current'];
     * 
     * // Enhanced quote with detailed data
     * $detailed = $client->stocks()->quote('AAPL', ['detailed' => true]);
     * $stock = $detailed->getInstruments()[0];
     * echo "52W High: $" . $stock['price']['fifty_two_week_high'];
     * echo "Pre-market: $" . $stock['pre_market']['price'];
     * ```
     */
    public function quote(string $stocks, array $options = []): Response
    {
        $params = ['stocks' => $stocks];
        
        if (isset($options['detailed']) && $options['detailed']) {
            $params['detailed'] = 'true';
        }
        
        return parent::get('/v2/stocks/get', $params);
    }

    /**
     * Get detailed real-time stock data with enhanced Yahoo Finance integration
     * 
     * Returns comprehensive market data including pre/post market trading, 52-week ranges, 
     * market capitalization, company logos, and institutional-grade metadata using the 
     * unified ResponseTemplate format.
     * 
     * @param string $stocks Single stock symbol or comma-separated list
     * @return Response Enhanced stock data with unified metadata structure
     * 
     * Enhanced data includes:
     * - Pre-market and post-market trading data
     * - 52-week high/low ranges
     * - Market capitalization information
     * - Company logos and enhanced info
     * - Overnight market data
     * - Professional performance metrics
     * - Data quality and provider information
     * 
     * @example
     * ```php
     * // Get detailed SOUN data with all enhanced features
     * $detailed = $client->stocks()->quoteDetailed('SOUN');
     * 
     * // Access unified metadata
     * $metadata = $detailed->getWioexMetadata();
     * $performance = $detailed->getPerformance();
     * $dataQuality = $detailed->getDataQuality();
     * 
     * // Access enhanced instrument data
     * $instruments = $detailed->getInstruments();
     * $stock = $instruments[0];
     * 
     * echo "Symbol: " . $stock['symbol'] . "\n";
     * echo "Current: $" . $stock['price']['current'] . "\n";
     * echo "52W High: $" . $stock['price']['fifty_two_week_high'] . "\n";
     * echo "Pre-market: $" . $stock['pre_market']['price'] . "\n";
     * echo "Market Cap: $" . number_format($stock['market_cap']['value']/1e9, 2) . "B\n";
     * echo "Logo: " . $stock['company_info']['logo_url'] . "\n";
     * echo "Response time: " . $performance['total_time_ms'] . "ms\n";
     * 
     * // Validate response format
     * $validation = $detailed->validateEnhancedStockQuote();
     * if ($validation->isValid()) {
     *     echo "✅ Enhanced data validation passed\n";
     * }
     * ```
     */
    public function quoteDetailed(string $stocks): Response
    {
        return $this->quote($stocks, ['detailed' => true]);
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
     * Get comprehensive ticker analysis including analyst ratings, earnings insights, and market sentiment
     * 
     * Provides institutional-grade analysis combining multiple data sources into a unified report.
     * Perfect for investment research, portfolio analysis, and comprehensive stock evaluation.
     * 
     * **Cost**: 5 credits per analysis (premium endpoint)
     * **Data Sources**: Institutional-grade financial data providers
     * **Update Frequency**: Real-time analysis with market hours refresh
     * 
     * @param string $symbol Stock ticker symbol (e.g., "AAPL", "TSLA", "GOOGL")
     * @param array<string, mixed> $options Additional analysis options (reserved for future use)
     * 
     * @return Response Comprehensive analysis with unified ResponseTemplate format
     * 
     * Analysis includes:
     * - **Analyst Ratings**: Price targets, recommendations, and analyst consensus
     * - **Earnings Insights**: Quarterly results, guidance, and earnings call highlights
     * - **Insider Activity**: Executive transactions, insider sentiment, and key takeaways
     * - **News Analysis**: Market sentiment, news themes, and key events
     * - **Options Analysis**: Put/call ratios, options sentiment, and market implications
     * - **Price Movement**: Technical analysis, sector comparison, and movement explanations
     * - **Financial Metrics**: Valuation ratios, growth metrics, and financial health indicators
     * - **Market Overview**: Comprehensive summary with key observations and insights
     * 
     * @example
     * ```php
     * // Get comprehensive analysis for Apple Inc.
     * $analysis = $client->stocks()->tickerAnalysis('AAPL');
     * 
     * if ($analysis->successful()) {
     *     // Access structured analysis data
     *     $analystRatings = $analysis->getAnalystRatings();
     *     $earnings = $analysis->getEarningsInsights();
     *     $insider = $analysis->getInsiderActivity();
     *     $news = $analysis->getNewsAnalysis();
     *     $options = $analysis->getOptionsAnalysis();
     *     
     *     // Get analyst consensus
     *     echo "Analyst Consensus:\n";
     *     echo "Summary: " . $analystRatings['summary']['tldr'] . "\n";
     *     echo "Price Target: " . $analystRatings['summary']['price_target'] . "\n";
     *     echo "Viewpoint: " . $analystRatings['summary']['viewpoint'] . "\n\n";
     *     
     *     // Get earnings highlights
     *     echo "Earnings Insights:\n";
     *     echo "Summary: " . $earnings['analysis']['tldr'] . "\n";
     *     echo "Outlook: " . $earnings['analysis']['key_insights']['Outlook'] . "\n\n";
     *     
     *     // Get insider activity
     *     echo "Insider Activity:\n";
     *     echo "Highlights: " . $insider['highlights'] . "\n";
     *     echo "Key Takeaways: " . $insider['key_takeaways'] . "\n\n";
     *     
     *     // Get news sentiment
     *     echo "News Analysis:\n";
     *     echo "Summary: " . $news['summary'] . "\n";
     *     foreach ($news['themes'] as $theme) {
     *         echo "Theme: {$theme['theme_name']} - {$theme['theme_description']}\n";
     *     }
     *     
     *     // Get options sentiment
     *     echo "\nOptions Analysis:\n";
     *     echo "Put/Call Ratio: " . $options['put_call_ratio']['pcr_volume'] . "\n";
     *     echo "Takeaways: " . $options['key_takeaways']['tldr'] . "\n";
     *     
     *     // Access metadata
     *     $credits = $analysis->getCredits();
     *     $performance = $analysis->getPerformance();
     *     echo "\nCost: {$credits['consumed']} credits\n";
     *     echo "Response time: {$performance['total_time_ms']}ms\n";
     * }
     * ```
     * 
     * @example Portfolio Analysis:
     * ```php
     * // Analyze multiple stocks for portfolio research
     * $symbols = ['AAPL', 'GOOGL', 'MSFT', 'TSLA', 'NVDA'];
     * $analyses = [];
     * 
     * foreach ($symbols as $symbol) {
     *     $analysis = $client->stocks()->tickerAnalysis($symbol);
     *     if ($analysis->successful()) {
     *         $analyses[$symbol] = [
     *             'analyst_consensus' => $analysis->getAnalystRatings()['summary']['viewpoint'],
     *             'earnings_outlook' => $analysis->getEarningsInsights()['analysis']['key_insights']['Outlook'],
     *             'news_sentiment' => $analysis->getNewsAnalysis()['summary'],
     *             'options_sentiment' => $analysis->getOptionsAnalysis()['key_takeaways']['tldr']
     *         ];
     *     }
     * }
     * 
     * // Generate portfolio report
     * echo "Portfolio Analysis Report:\n";
     * foreach ($analyses as $symbol => $data) {
     *     echo "\n{$symbol}:\n";
     *     echo "  Analyst View: {$data['analyst_consensus']}\n";
     *     echo "  Earnings: {$data['earnings_outlook']}\n";
     *     echo "  Sentiment: {$data['news_sentiment']}\n";
     * }
     * ```
     * 
     * @throws AuthenticationException When API key is invalid
     * @throws ValidationException When symbol is invalid or not found in database
     * @throws RateLimitException When rate limits are exceeded
     * @throws RequestException When API request fails
     * @throws WioexException For other API-related errors
     * 
     * @see https://docs.wioex.com/api/stocks/ticker-analysis Documentation
     */
    public function tickerAnalysis(string $symbol, array $options = []): Response
    {
        return parent::get('/v2/stocks/ticker-analysis', array_merge(
            ['symbol' => $symbol],
            $options
        ));
    }

    /**
     * Get comprehensive ticker analysis with enhanced formatting (alias method)
     * 
     * Convenience method that provides the same functionality as tickerAnalysis()
     * with additional validation and enhanced error messaging.
     * 
     * @param string $symbol Stock ticker symbol
     * @param array<string, mixed> $options Additional analysis options
     * @return Response Comprehensive ticker analysis
     * 
     * @example
     * ```php
     * // Get detailed analysis with enhanced validation
     * $analysis = $client->stocks()->analysisDetailed('SOUN');
     * 
     * // Validate response structure
     * $validation = $analysis->validateTickerAnalysisResponse();
     * if ($validation->isValid()) {
     *     echo "✅ Analysis data validation passed\n";
     *     
     *     // Access all analysis sections
     *     $overview = $analysis->getTickerAnalysis()['overview'];
     *     echo "Analysis Summary: " . $overview['summary'] . "\n";
     * } else {
     *     echo "❌ Validation failed: " . $validation->getErrorSummary() . "\n";
     * }
     * ```
     */
    public function analysisDetailed(string $symbol, array $options = []): Response
    {
        return $this->tickerAnalysis($symbol, $options);
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


    // ================================
    // BULK OPERATIONS (NEW in v2.0.0)
    // ================================

    /**
     * Get real-time quotes for multiple stocks in bulk (HIGH PERFORMANCE)
     *
     * Optimized for high-volume portfolio management applications.
     * Automatically chunks large requests and handles partial failures.
     * 
     * **Performance**: 95% faster than individual calls for 100+ symbols
     * **Scalability**: Supports up to 1000 symbols with automatic chunking
     * **Reliability**: Handles partial failures gracefully
     *
     * @param array<string> $symbols Array of stock symbols (e.g., ['AAPL', 'TSLA', 'GOOGL'])
     * @param array<string, mixed> $options Bulk operation options:
     *   - chunk_size: int (default: 50) - Symbols per API request
     *   - chunk_delay: float (default: 0.1) - Delay between chunks in seconds
     *   - fail_on_partial_errors: bool (default: false) - Fail if any chunk fails
     *   - timeout: int - Custom timeout per chunk
     *
     * @return Response Merged response with all quotes and bulk operation metadata
     *
     * @throws BulkOperationException When bulk operation fails (with partial results)
     * @throws ValidationException When input parameters are invalid
     *
     * @example
     * ```php
     * // Basic usage - 500 stocks in ~30 seconds vs 8-10 minutes individual calls
     * $portfolioSymbols = ['AAPL', 'TSLA', 'NVDA']; // ...497 more symbols
     * $quotes = $client->stocks()->quoteBulk($portfolioSymbols);
     * 
     * foreach ($quotes['tickers'] as $stock) {
     *     echo "{$stock['ticker']}: \${$stock['market']['price']} ({$stock['market']['change']['percent']}%)\n";
     * }
     * 
     * // With custom options for maximum performance
     * $quotes = $client->stocks()->quoteBulk($portfolioSymbols, [
     *     'chunk_size' => 100,        // Larger chunks
     *     'chunk_delay' => 0.05,      // Faster processing
     *     'fail_on_partial_errors' => false  // Continue on errors
     * ]);
     * 
     * echo "Processed {$quotes['bulk_operation']['success_count']} stocks successfully\n";
     * ```
     */
    public function quoteBulk(array $symbols, array $options = []): Response
    {
        return $this->executeBulkOperation('/v2/stocks/bulk/quote', $symbols, $options);
    }

    /**
     * Get historical timeline data for multiple stocks in bulk
     *
     * Retrieve historical price data for large portfolios efficiently.
     * Perfect for portfolio analysis and backtesting applications.
     *
     * @param array<string> $symbols Array of stock symbols
     * @param TimelineInterval|string $interval Chart interval
     * @param array<string, mixed> $options Timeline and bulk options:
     *   - size: int - Number of data points per symbol
     *   - orderBy: SortOrder|string - Sort order
     *   - started_date: string - Start date (YYYY-MM-DD)
     *   - session: TradingSession|string - Trading session filter
     *   - chunk_size: int (default: 25) - Symbols per request (smaller for timeline)
     *   - chunk_delay: float (default: 0.2) - Delay between chunks
     *   - fail_on_partial_errors: bool (default: false)
     *
     * @return Response Merged timeline data for all symbols
     *
     * @example
     * ```php
     * // Get 30 days of 5-minute data for 100 stocks
     * $symbols = ['AAPL', 'TSLA', 'GOOGL']; // ...97 more symbols
     * $timelines = $client->stocks()->timelineBulk($symbols, TimelineInterval::FIVE_MINUTES, [
     *     'size' => 30,
     *     'orderBy' => SortOrder::DESCENDING
     * ]);
     * 
     * // Process timeline data for each symbol
     * foreach ($timelines['data'] as $timelineData) {
     *     echo "Symbol: {$timelineData['symbol']} - {$timelineData['points']} data points\n";
     * }
     * ```
     */
    public function timelineBulk(array $symbols, $interval = TimelineInterval::ONE_DAY, array $options = []): Response
    {
        // Convert interval enum to string if needed
        $intervalValue = $interval instanceof TimelineInterval ? $interval->value : (string)$interval;
        
        $requestOptions = array_merge($options, ['interval' => $intervalValue]);
        
        // Timeline endpoint only supports 1 symbol per request (API limitation)
        if (!isset($requestOptions['chunk_size'])) {
            $requestOptions['chunk_size'] = 1; // Must be 1 for timeline endpoint
        }
        if (!isset($requestOptions['chunk_delay'])) {
            $requestOptions['chunk_delay'] = 0.2; // Slower for timeline data
        }

        return $this->executeBulkOperation('/v2/stocks/bulk/timeline', $symbols, $requestOptions);
    }

    /**
     * Get detailed company information for multiple stocks in bulk
     *
     * Retrieve fundamental data, financial metrics, and company information
     * for entire portfolios in a single optimized operation.
     *
     * @param array<string> $symbols Array of stock symbols
     * @param array<string, mixed> $options Bulk operation options
     *
     * @return Response Company information for all symbols
     *
     * @example
     * ```php
     * $symbols = ['AAPL', 'MSFT', 'GOOGL', 'AMZN', 'TSLA'];
     * $companyInfo = $client->stocks()->infoBulk($symbols);
     * 
     * foreach ($companyInfo['data'] as $info) {
     *     echo "{$info['symbol']}: {$info['company_name']} - P/E: {$info['pe_ratio']}\n";
     * }
     * ```
     */
    public function infoBulk(array $symbols, array $options = []): Response
    {
        return $this->executeBulkOperation('/v2/stocks/bulk/info', $symbols, $options);
    }

    /**
     * Get financial data for multiple stocks in bulk
     *
     * Retrieve financial metrics, ratios, and performance data for
     * portfolio analysis and stock screening applications.
     *
     * @param array<string> $symbols Array of stock symbols
     * @param string $currency Currency for financial data (default: USD)
     * @param array<string, mixed> $options Bulk operation options
     *
     * @return Response Financial data for all symbols
     *
     * @example
     * ```php
     * $symbols = ['AAPL', 'MSFT', 'GOOGL'];
     * $financials = $client->stocks()->financialsBulk($symbols, 'USD');
     * 
     * foreach ($financials['data'] as $financial) {
     *     echo "{$financial['symbol']}: Revenue \${$financial['revenue']}, EPS: \${$financial['eps']}\n";
     * }
     * ```
     */
    public function financialsBulk(array $symbols, string $currency = 'USD', array $options = []): Response
    {
        $requestOptions = array_merge($options, ['currency' => $currency]);
        return $this->executeBulkOperation('/v2/stocks/bulk/financials', $symbols, $requestOptions);
    }

    /**
     * Execute bulk operation with intelligent chunking and error handling
     *
     * @param string $endpoint API endpoint for bulk operation
     * @param array<string> $symbols Array of stock symbols
     * @param array<string, mixed> $options Request and bulk options
     * @return Response
     * @throws BulkOperationException
     * @throws ValidationException
     */
    private function executeBulkOperation(string $endpoint, array $symbols, array $options = []): Response
    {
        // Extract bulk-specific options
        $chunkSize = (int)($options['chunk_size'] ?? 50);
        $chunkDelay = (float)($options['chunk_delay'] ?? 0.1);
        $failOnPartialErrors = (bool)($options['fail_on_partial_errors'] ?? false);

        // Remove bulk options from request options
        $requestOptions = $options;
        unset($requestOptions['chunk_size'], $requestOptions['chunk_delay'], $requestOptions['fail_on_partial_errors']);

        // Create bulk request manager
        $bulkManager = new BulkRequestManager(
            $this->client,
            $chunkSize,
            $chunkDelay,
            $failOnPartialErrors
        );

        try {
            return $bulkManager->executeBulkRequest($endpoint, $symbols, $requestOptions);
        } catch (BulkOperationException $e) {
            // Add context about the operation
            throw new BulkOperationException(
                $e->getMessage() . " (Operation: {$endpoint})",
                $e->getErrors(),
                $e->getSuccessfulResponses(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Get bulk operation configuration and limits
     *
     * @return array<string, mixed> Configuration information
     */
    public function getBulkOperationLimits(): array
    {
        return [
            'max_symbols_per_request' => 1000,
            'max_symbols_per_chunk' => 30, // Updated to match API quote limit
            'api_endpoint_limits' => [
                'quote' => 30,      // /v2/stocks/get max limit
                'timeline' => 1,    // /v2/stocks/chart/timeline single symbol only
                'info' => 1,        // /v2/stocks/info single symbol only
                'financials' => 1   // /v2/stocks/financials single symbol only
            ],
            'default_chunk_sizes' => [
                'quote' => 30,      // Updated to API limit
                'timeline' => 1,    // Must be 1 for single symbol API
                'info' => 1,        // Must be 1 for single symbol API
                'financials' => 1   // Must be 1 for single symbol API
            ],
            'recommended_delays' => [
                'quote' => 0.1,
                'timeline' => 0.2,
                'info' => 0.15,
                'financials' => 0.15
            ],
            'credit_consumption' => [
                'quote' => '1 credit per symbol',
                'timeline' => '1 credit per symbol', 
                'info' => '1 credit per symbol',
                'financials' => '1 credit per symbol'
            ],
            'features' => [
                'automatic_chunking' => true,
                'partial_failure_handling' => true,
                'progress_tracking' => true,
                'server_side_processing' => true, // No CORS issues
                'response_merging' => true,
                'real_api_endpoints' => true // Uses actual API endpoints, not bulk endpoints
            ]
        ];
    }
}
