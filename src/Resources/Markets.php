<?php

declare(strict_types=1);

namespace Wioex\SDK\Resources;

use Wioex\SDK\Http\Response;

class Markets extends Resource
{
    /**
     * Get market status and trading hours
     *
     * Returns real-time market status, trading hours, and holiday information
     * for NYSE and NASDAQ exchanges.
     *
     * **Supports both authenticated and public access:**
     * - **With API key**: Costs 1 credit, no rate limit, tracks usage
     * - **Without API key**: Free, rate limited (100 req/min per IP), no tracking
     *
     * @return Response
     *
     * @example
     * ```php
     * // Authenticated usage (with API key)
     * $client = new WioexClient(['api_key' => 'your-api-key']);
     * $status = $client->markets()->status();
     * // Cost: 1 credit, no rate limit
     *
     * if ($status['success']) {
     *     $nyse = $status['markets']['nyse'];
     *     echo "NYSE is " . ($nyse['is_open'] ? "open" : "closed") . "\n";
     *     echo "Status: " . $nyse['status'] . "\n";
     *     echo "Market Time: " . $nyse['market_time'] . "\n";
     *     echo "Next Change: " . $nyse['next_change'] . "\n";
     *
     *     // Trading hours
     *     echo "Regular Hours: " . $nyse['hours']['regular']['open'] .
     *          " - " . $nyse['hours']['regular']['close'] . "\n";
     *     echo "Pre-Market: " . $nyse['hours']['pre_market']['open'] .
     *          " - " . $nyse['hours']['pre_market']['close'] . "\n";
     *     echo "After-Hours: " . $nyse['hours']['after_hours']['open'] .
     *          " - " . $nyse['hours']['after_hours']['close'] . "\n";
     *
     *     // Holidays
     *     foreach ($nyse['holidays'] as $holiday) {
     *         echo "Holiday: " . $holiday['name'] . " on " . $holiday['date'] . "\n";
     *     }
     * }
     * ```
     *
     * @example
     * ```php
     * // Public usage (without API key)
     * $client = new WioexClient(['api_key' => '']);
     * $status = $client->markets()->status();
     * // Cost: FREE, rate limit: 100/min per IP
     *
     * if ($status['success']) {
     *     $nyse = $status['markets']['nyse'];
     *     echo "NYSE is " . ($nyse['is_open'] ? "open" : "closed") . "\n";
     * }
     * ```
     *
     * @example
     * ```javascript
     * // Direct API call from frontend (no SDK needed)
     * fetch('https://api.wioex.com/v2/market/status')
     *   .then(response => response.json())
     *   .then(data => {
     *     console.log('NYSE is', data.markets.nyse.is_open ? 'open' : 'closed');
     *   });
     * ```
     */
    public function status(): Response
    {
        return parent::get('/v2/market/status');
    }
}
