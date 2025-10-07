<?php

declare(strict_types=1);

namespace Wioex\SDK\Resources;

use Wioex\SDK\Http\Response;

class Account extends Resource
{
    /**
     * Get your current credit balance
     */
    public function balance(): Response
    {
        return $this->get('/v2/account/balance');
    }

    /**
     * Get API usage statistics for a time period
     * @param int|null $days Time period: 7, 30, or 90 days
     */
    public function usage(?int $days = null): Response
    {
        $params = [];
        if ($days) {
            $params['days'] = $days;
        }

        return $this->get('/v2/account/usage', $params);
    }

    /**
     * Get detailed analytics and insights about your API usage
     * @param string $period Analysis period: week, month, quarter, or year
     */
    public function analytics(string $period = 'month'): Response
    {
        return $this->get('/v2/account/analytics', ['period' => $period]);
    }

    /**
     * List all API keys associated with your account
     */
    public function keys(): Response
    {
        return $this->get('/v2/account/keys');
    }
}
