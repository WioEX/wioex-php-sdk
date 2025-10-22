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
}
