<?php

declare(strict_types=1);

namespace Wioex\SDK\Resources;

use Wioex\SDK\Http\Response;

class Currency extends Resource
{
    /**
     * Get historical currency exchange rate data
     */
    public function graph(string $base, string $target, string $interval): Response
    {
        return $this->get('/v2/currency/graph', [
            'base' => $base,
            'target' => $target,
            'interval' => $interval
        ]);
    }

    /**
     * Get current exchange rates for all currencies against USD
     */
    public function baseUsd(): Response
    {
        return $this->get('/v2/currency/base_usd');
    }

    /**
     * Calculate currency conversion
     */
    public function calculator(string $base, string $target, float $amount): Response
    {
        return $this->get("/v2/currency/calculator/{$base}/{$target}/{$amount}");
    }

    /**
     * Get all exchange rates for a specific base currency
     */
    public function allRates(string $base): Response
    {
        return $this->get("/v2/currency/all/{$base}");
    }
}
