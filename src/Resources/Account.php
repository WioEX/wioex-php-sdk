<?php

declare(strict_types=1);

namespace Wioex\SDK\Resources;

use Wioex\SDK\Http\Response;
use Wioex\SDK\Enums\UsagePeriod;
use Wioex\SDK\Enums\AnalyticsPeriod;

class Account extends Resource
{
    /**
     * Get your current credit balance
     */
    public function balance(): Response
    {
        return $this->get('/account/balance');
    }

    /**
     * Get API usage statistics for a time period
     * @param UsagePeriod|int|null $days Time period (default: 30 days)
     *
     * @example Using ENUM (recommended):
     * ```php
     * $usage = $client->account()->usage(UsagePeriod::THIRTY_DAYS);
     * $weeklyUsage = $client->account()->usage(UsagePeriod::SEVEN_DAYS);
     * ```
     */
    public function usage(UsagePeriod|int|null $days = null): Response
    {
        $params = [];
        if ($days !== null) {
            $daysValue = $days instanceof UsagePeriod ? $days->value : $days;
            $params['days'] = $daysValue;
        }

        return $this->get('/account/usage', $params);
    }

    /**
     * Get detailed analytics and insights about your API usage
     * @param AnalyticsPeriod|string $period Analysis period (default: month)
     *
     * @example Using ENUM (recommended):
     * ```php
     * $analytics = $client->account()->analytics(AnalyticsPeriod::MONTH);
     * $quarterly = $client->account()->analytics(AnalyticsPeriod::QUARTER);
     * ```
     */
    public function analytics(AnalyticsPeriod|string $period = 'month'): Response
    {
        $periodValue = $period instanceof AnalyticsPeriod ? $period->value : $period;
        return $this->get('/account/analytics', ['period' => $periodValue]);
    }

    /**
     * List all API keys associated with your account
     */
    public function keys(): Response
    {
        return $this->get('/account/keys');
    }
}
