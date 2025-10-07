<?php

declare(strict_types=1);

namespace Wioex\SDK\Resources;

use Wioex\SDK\Http\Response;

class Screens extends Resource
{
    /**
     * Get most actively traded stocks
     */
    public function active(?int $limit = null): Response
    {
        $params = [];
        if ($limit) {
            $params['limit'] = $limit;
        }

        return $this->get('/v2/stocks/screens/active', $params);
    }

    /**
     * Get top gaining stocks
     */
    public function gainers(): Response
    {
        return $this->get('/v2/stocks/screens/gainers');
    }

    /**
     * Get top losing stocks
     */
    public function losers(): Response
    {
        return $this->get('/v2/stocks/screens/losers');
    }

    /**
     * Get pre-market top gainers
     */
    public function preMarketGainers(): Response
    {
        return $this->get('/v2/stocks/screens/pre_gainers');
    }

    /**
     * Get pre-market top losers
     */
    public function preMarketLosers(): Response
    {
        return $this->get('/v2/stocks/screens/pre_losers');
    }

    /**
     * Get post-market top gainers
     */
    public function postMarketGainers(): Response
    {
        return $this->get('/v2/stocks/screens/post_gainers');
    }

    /**
     * Get post-market top losers
     */
    public function postMarketLosers(): Response
    {
        return $this->get('/v2/stocks/screens/post_losers');
    }

    /**
     * Get IPO information
     * @param string $list IPO type: recent, upcoming, or filings
     */
    public function ipos(string $list = 'recent'): Response
    {
        return $this->get('/v2/stocks/screens/ipos', ['list' => $list]);
    }

    /**
     * Get comprehensive data for all stocks in one request
     * Warning: Large dataset - use with caution
     */
    public function allStocks(): Response
    {
        return $this->get('/v2/stocks/screens/all_stocks_one_screen');
    }

    /**
     * Get comprehensive data for all ETFs in one request
     * Warning: Large dataset - use with caution
     */
    public function allEtfs(): Response
    {
        return $this->get('/v2/stocks/screens/all_etf_one_screen');
    }
}
