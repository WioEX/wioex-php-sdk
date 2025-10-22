<?php

declare(strict_types=1);

namespace Wioex\SDK\Resources;

use Wioex\SDK\Http\Response;
use Wioex\SDK\Enums\SignalType;
use Wioex\SDK\Enums\TriggerType;

class Signals extends Resource
{
    /**
     * Get active trading signals
     *
     * @param array $options Optional filters:
     *   - symbol: string - Filter by stock symbol (e.g., "AAPL")
     *   - signal_type: SignalType|string - Filter by signal type (default: all types)
     *   - min_confidence: int - Minimum confidence level (0-100), default 70
     *   - timeframe: string - Filter by timeframe (5m, 15m, 1h, 4h, 1d, 1w, 1M)
     *   - limit: int - Maximum number of results, default 50, max 200
     *
     * @return Response
     *
     * @example Using ENUMs (recommended):
     * ```php
     * // Get all active signals
     * $client->signals()->active();
     *
     * // Get signals for specific symbol
     * $client->signals()->active(['symbol' => 'AAPL']);
     *
     * // Get BUY signals with high confidence using ENUM
     * $client->signals()->active([
     *     'signal_type' => SignalType::BUY,
     *     'min_confidence' => 80
     * ]);
     *
     * // Get strong signals only
     * $client->signals()->active([
     *     'signal_type' => SignalType::STRONG_BUY
     * ]);
     * ```
     *
     * @example Backward compatibility with strings:
     * ```php
     * $client->signals()->active([
     *     'signal_type' => 'BUY',
     *     'min_confidence' => 80
     * ]);
     * ```
     */
    public function active(array $options = []): Response
    {
        // Convert ENUMs to strings for API compatibility
        $processedOptions = $this->processSignalOptions($options);

        return parent::get('/v2/stocks/signals/active', $processedOptions);
    }

    /**
     * Get signal history (triggered/expired signals)
     *
     * @param array $options Optional filters:
     *   - symbol: string - Filter by stock symbol
     *   - days: int - Number of days to look back, default 30, max 365
     *   - trigger_type: TriggerType|string - Filter by trigger type
     *   - limit: int - Maximum number of results, default 50, max 200
     *
     * @return Response
     *
     * @example Using ENUMs (recommended):
     * ```php
     * // Get signal history for last 7 days
     * $client->signals()->history(['days' => 7]);
     *
     * // Get triggered signals for TSLA using ENUM
     * $client->signals()->history([
     *     'symbol' => 'TSLA',
     *     'trigger_type' => TriggerType::TARGET
     * ]);
     *
     * // Get all profitable outcomes
     * $client->signals()->history([
     *     'trigger_type' => TriggerType::TARGET
     * ]);
     *
     * // Get stop loss triggers
     * $client->signals()->history([
     *     'trigger_type' => TriggerType::STOP_LOSS
     * ]);
     * ```
     *
     * @example Backward compatibility with strings:
     * ```php
     * $client->signals()->history([
     *     'symbol' => 'TSLA',
     *     'trigger_type' => 'target'
     * ]);
     * ```
     */
    public function history(array $options = []): Response
    {
        // Convert ENUMs to strings for API compatibility
        $processedOptions = $this->processSignalOptions($options);

        return parent::get('/v2/stocks/signals/history', $processedOptions);
    }

    /**
     * Process signal options to convert ENUMs to strings
     *
     * @param array $options Raw options array
     * @return array Processed options with ENUM values converted to strings
     */
    private function processSignalOptions(array $options): array
    {
        $processed = $options;

        // Convert SignalType ENUM to string
        if (isset($processed['signal_type']) && $processed['signal_type'] instanceof SignalType) {
            $processed['signal_type'] = $processed['signal_type']->value;
        }

        // Convert TriggerType ENUM to string
        if (isset($processed['trigger_type']) && $processed['trigger_type'] instanceof TriggerType) {
            $processed['trigger_type'] = $processed['trigger_type']->value;
        }

        return $processed;
    }
}
