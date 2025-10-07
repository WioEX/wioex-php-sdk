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
        return $this->get('/v2/stocks/search', ['q' => $query]);
    }

    /**
     * Get real-time stock data for one or multiple tickers
     * @param string $ticker Single ticker or comma-separated list (e.g., "AAPL" or "AAPL,GOOGL,MSFT")
     */
    public function get(string $ticker): Response
    {
        return $this->get('/v2/stocks/get', ['ticker' => $ticker]);
    }

    /**
     * Get detailed company information and stock fundamentals
     */
    public function info(string $ticker): Response
    {
        return $this->get('/v2/stocks/info', ['ticker' => $ticker]);
    }

    /**
     * Get historical price data for charting
     * @param string $ticker Stock ticker symbol
     * @param array $options Available options: interval, orderBy, size
     */
    public function timeline(string $ticker, array $options = []): Response
    {
        return $this->get('/v2/stocks/chart/timeline', array_merge(
            ['ticker' => $ticker],
            $options
        ));
    }

    /**
     * Get list of available stocks by country
     */
    public function list(array $options = []): Response
    {
        return $this->get('/v2/stocks/get_list', $options);
    }

    /**
     * Get financial statements and metrics
     */
    public function financials(string $ticker, ?string $currency = null): Response
    {
        $params = ['ticker' => $ticker];

        if ($currency) {
            $params['currency'] = $currency;
        }

        return $this->get('/v2/stocks/financials', $params);
    }

    /**
     * Get market heatmap data for major indices
     * @param string $market Market index: nasdaq100, sp500, or dowjones
     */
    public function heatmap(string $market): Response
    {
        return $this->get('/v2/stocks/heatmap', ['market' => $market]);
    }

    /**
     * Get lightweight chart data for quick visualization
     */
    public function minimalChart(string $ticker): Response
    {
        return $this->get('/v2/stocks/chart/minimal', ['ticker' => $ticker]);
    }
}
