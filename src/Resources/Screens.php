<?php

declare(strict_types=1);

namespace Wioex\SDK\Resources;

use Wioex\SDK\Http\Response;
use Wioex\SDK\Enums\IpoType;

class Screens extends Resource
{
    /**
     * Get most actively traded stocks
     */
    public function active(?int $limit = null): Response
    {
        $params = [];
        if ($limit !== null) {
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
     * @param IpoType|string $list IPO type (default: recent)
     * 
     * @example Using ENUM (recommended):
     * ```php
     * $recent = $client->screens()->ipos(IpoType::RECENT);
     * $upcoming = $client->screens()->ipos(IpoType::UPCOMING);
     * $filings = $client->screens()->ipos(IpoType::FILINGS);
     * ```
     */
    public function ipos(IpoType|string $list = 'recent'): Response
    {
        $listValue = $list instanceof IpoType ? $list->value : $list;
        return $this->get('/v2/stocks/screens/ipos', ['list' => $listValue]);
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
