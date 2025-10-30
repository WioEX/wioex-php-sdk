<?php

declare(strict_types=1);

namespace Wioex\SDK\Resources;

use Wioex\SDK\Http\Response;
use Wioex\SDK\Enums\CurrencyCode;
use Wioex\SDK\Enums\CurrencyInterval;

class Currency extends Resource
{
    /**
     * Get historical currency exchange rate data
     *
     * @param CurrencyCode|string $base Base currency
     * @param CurrencyCode|string $target Target currency
     * @param CurrencyInterval|string $interval Chart interval
     *
     * @example Using ENUMs (recommended):
     * ```php
     * $graph = $client->currency()->graph(
     *     CurrencyCode::USD,
     *     CurrencyCode::EUR,
     *     CurrencyInterval::ONE_MONTH
     * );
     * ```
     */
    public function graph(CurrencyCode|string $base, CurrencyCode|string $target, CurrencyInterval|string $interval): Response
    {
        $baseValue = $base instanceof CurrencyCode ? $base->value : $base;
        $targetValue = $target instanceof CurrencyCode ? $target->value : $target;
        $intervalValue = $interval instanceof CurrencyInterval ? $interval->value : $interval;

        return $this->get('/api/currency/graph', [
            'base' => $baseValue,
            'target' => $targetValue,
            'interval' => $intervalValue
        ]);
    }

    /**
     * Get current exchange rates for all currencies against USD
     */
    public function baseUsd(): Response
    {
        return $this->get('/api/currency/base_usd');
    }

    /**
     * Calculate currency conversion
     *
     * @param CurrencyCode|string $base Base currency
     * @param CurrencyCode|string $target Target currency
     * @param float $amount Amount to convert
     *
     * @example Using ENUMs (recommended):
     * ```php
     * $result = $client->currency()->calculator(
     *     CurrencyCode::USD,
     *     CurrencyCode::EUR,
     *     100.0
     * );
     * ```
     */
    public function calculator(CurrencyCode|string $base, CurrencyCode|string $target, float $amount): Response
    {
        $baseValue = $base instanceof CurrencyCode ? $base->value : $base;
        $targetValue = $target instanceof CurrencyCode ? $target->value : $target;

        return $this->get("/currency/calculator/{$baseValue}/{$targetValue}/{$amount}");
    }

    /**
     * Get all exchange rates for a specific base currency
     *
     * @param CurrencyCode|string $base Base currency
     *
     * @example Using ENUMs (recommended):
     * ```php
     * $rates = $client->currency()->allRates(CurrencyCode::USD);
     * ```
     */
    public function allRates(CurrencyCode|string $base): Response
    {
        $baseValue = $base instanceof CurrencyCode ? $base->value : $base;
        return $this->get("/currency/all/{$baseValue}");
    }
}
