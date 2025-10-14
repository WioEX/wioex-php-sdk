<?php

declare(strict_types=1);

namespace Wioex\SDK\Resources;

use Wioex\SDK\Http\Response;

class Signals extends Resource
{
    /**
     * Get active trading signals
     *
     * @param array $options Optional filters:
     *   - symbol: string - Filter by stock symbol (e.g., "AAPL")
     *   - signal_type: string - Filter by signal type (BUY, SELL, HOLD, STRONG_BUY, STRONG_SELL)
     *   - min_confidence: int - Minimum confidence level (0-100), default 70
     *   - timeframe: string - Filter by timeframe (5m, 15m, 1h, 4h, 1d, 1w, 1M)
     *   - limit: int - Maximum number of results, default 50, max 200
     *
     * @return Response
     *
     * @example
     * ```php
     * // Get all active signals
     * $client->signals()->active();
     *
     * // Get signals for specific symbol
     * $client->signals()->active(['symbol' => 'AAPL']);
     *
     * // Get BUY signals with high confidence
     * $client->signals()->active([
     *     'signal_type' => 'BUY',
     *     'min_confidence' => 80
     * ]);
     * ```
     */
    public function active(array $options = []): Response
    {
        return parent::get('/v2/stocks/signals/active', $options);
    }

    /**
     * Get signal history (triggered/expired signals)
     *
     * @param array $options Optional filters:
     *   - symbol: string - Filter by stock symbol
     *   - days: int - Number of days to look back, default 30, max 365
     *   - trigger_type: string - Filter by trigger type (entry, target, stop_loss, expired)
     *   - limit: int - Maximum number of results, default 50, max 200
     *
     * @return Response
     *
     * @example
     * ```php
     * // Get signal history for last 7 days
     * $client->signals()->history(['days' => 7]);
     *
     * // Get triggered signals for TSLA
     * $client->signals()->history([
     *     'symbol' => 'TSLA',
     *     'trigger_type' => 'target'
     * ]);
     * ```
     */
    public function history(array $options = []): Response
    {
        return parent::get('/v2/stocks/signals/history', $options);
    }
}
