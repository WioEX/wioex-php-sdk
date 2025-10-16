<?php

declare(strict_types=1);

namespace Wioex\SDK\Resources;

use Wioex\SDK\Http\Response;

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
     * Get real-time stock data for one or multiple tickers
     * @param string $ticker Single ticker or comma-separated list (e.g., "AAPL" or "AAPL,GOOGL,MSFT")
     */
    public function quote(string $ticker): Response
    {
        return parent::get('/v2/stocks/get', ['ticker' => $ticker]);
    }

    /**
     * Get detailed company information and stock fundamentals
     */
    public function info(string $ticker): Response
    {
        return parent::get('/v2/stocks/info', ['ticker' => $ticker]);
    }

    /**
     * Get historical price data for charting
     *
     * @param string $ticker Stock ticker symbol
     * @param array $options Available options:
     *   - interval: '1min' or '1day' (default: '1day')
     *   - orderBy: 'ASC' or 'DESC' (default: 'ASC')
     *   - size: Number of data points 1-5000 (default: 78)
     *   - session: 'all', 'regular', 'pre_market', 'after_hours', 'extended' (default: 'all', only applies to 1min interval)
     *   - started_date: Date string (e.g., '2024-10-16') or timestamp (filters data from this date onward)
     *   - timestamp: Unix timestamp (alternative to started_date)
     */
    public function timeline(string $ticker, array $options = []): Response
    {
        return parent::get('/v2/stocks/chart/timeline', array_merge(
            ['ticker' => $ticker],
            $options
        ));
    }

    /**
     * Get 1-minute timeline data filtered by trading session
     *
     * @param string $ticker Stock ticker symbol
     * @param string $session Trading session: 'regular', 'pre_market', 'after_hours', 'extended'
     * @param array $options Additional options (size, orderBy, started_date)
     */
    public function timelineBySession(string $ticker, string $session, array $options = []): Response
    {
        return $this->timeline($ticker, array_merge([
            'interval' => '1min',
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
        return $this->timelineBySession($ticker, 'regular', $options);
    }

    /**
     * Get extended hours timeline data (pre-market + regular + after-hours)
     *
     * @param string $ticker Stock ticker symbol
     * @param array $options Additional options (size, orderBy, started_date)
     */
    public function extendedHoursTimeline(string $ticker, array $options = []): Response
    {
        return $this->timelineBySession($ticker, 'extended', $options);
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
     * @param string $market Market index: nasdaq100, sp500, or dowjones
     */
    public function heatmap(string $market): Response
    {
        return parent::get('/v2/stocks/heatmap', ['market' => $market]);
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
}
