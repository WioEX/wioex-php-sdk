<?php

declare(strict_types=1);

namespace Wioex\SDK\Resources;

use Wioex\SDK\Http\Response;
use Wioex\SDK\Enums\IpoType;
use Wioex\SDK\Enums\ScreenType;
use Wioex\SDK\Enums\SortOrder;
use Wioex\SDK\Enums\TradingSession;
use Wioex\SDK\Enums\MarketIndex;

class Screens extends Resource
{
    /**
     * Unified screen method with runtime flexibility
     *
     * @param ScreenType|string $type Screen type
     * @param array $options Additional options (limit, sortOrder, market, session, filters)
     *
     * @example Using ENUM (recommended):
     * ```php
     * // Basic usage
     * $gainers = $client->screens()->screen(ScreenType::GAINERS, ['limit' => 20]);
     *
     * // Advanced filtering
     * $active = $client->screens()->screen(ScreenType::ACTIVE, [
     *     'limit' => 50,
     *     'sortOrder' => SortOrder::DESCENDING,
     *     'session' => TradingSession::REGULAR
     * ]);
     *
     * // Pre-market screening
     * $preGainers = $client->screens()->screen(ScreenType::PRE_GAINERS, [
     *     'limit' => 15,
     *     'sortOrder' => SortOrder::DESCENDING
     * ]);
     * ```
     */
    public function screen(ScreenType|string $type, array $options = []): Response
    {
        $screenType = $type instanceof ScreenType ? $type : ScreenType::fromString($type);

        // Build parameters
        $params = [];

        // Add limit if specified
        if (isset($options['limit'])) {
            $params['limit'] = (int) $options['limit'];
        }

        // Add sort order if specified
        if (isset($options['sortOrder'])) {
            $sortOrder = $options['sortOrder'] instanceof SortOrder
                ? $options['sortOrder']
                : SortOrder::fromString($options['sortOrder']);
            $params['sort'] = $sortOrder->value;
        }

        // Add session if specified and supported
        if (isset($options['session'])) {
            $session = $options['session'] instanceof TradingSession
                ? $options['session']
                : TradingSession::fromString($options['session']);

            // Validate session compatibility
            if (!in_array($session, $screenType->getSupportedSessions(), true)) {
                throw new \InvalidArgumentException(
                    "Session '{$session->value}' is not supported for screen type '{$screenType->value}'. " .
                    "Supported sessions: " . implode(', ', array_map(fn($s) => $s->value, $screenType->getSupportedSessions()))
                );
            }
            $params['session'] = $session->value;
        }

        // Add market index if specified
        if (isset($options['market'])) {
            $market = $options['market'] instanceof MarketIndex
                ? $options['market']
                : MarketIndex::fromString($options['market']);
            $params['market'] = $market->value;
        }

        // Add any additional filters
        if (isset($options['filters']) && is_array($options['filters'])) {
            $params = array_merge($params, $options['filters']);
        }

        return $this->get($screenType->getEndpoint(), $params);
    }
    /**
     * Get most actively traded stocks
     *
     * @param int|null $limit Maximum number of results
     * @param SortOrder|string|null $sortOrder Sort order for results
     * @param MarketIndex|string|null $market Market index filter
     * @param array $options Additional filtering options
     *
     * @example Enhanced usage:
     * ```php
     * $active = $client->screens()->active(
     *     limit: 50,
     *     sortOrder: SortOrder::DESCENDING,
     *     market: MarketIndex::NASDAQ_100
     * );
     * ```
     */
    public function active(
        ?int $limit = null,
        SortOrder|string|null $sortOrder = null,
        MarketIndex|string|null $market = null,
        array $options = []
    ): Response {
        $params = [];

        if ($limit !== null) {
            $params['limit'] = $limit;
        }

        if ($sortOrder !== null) {
            $sort = $sortOrder instanceof SortOrder ? $sortOrder : SortOrder::fromString($sortOrder);
            $params['sort'] = $sort->value;
        }

        if ($market !== null) {
            $marketEnum = $market instanceof MarketIndex ? $market : MarketIndex::fromString($market);
            $params['market'] = $marketEnum->value;
        }

        $params = array_merge($params, $options);

        return $this->get('/stocks/screens/active', $params);
    }

    /**
     * Get top gaining stocks
     *
     * @param int|null $limit Maximum number of results
     * @param SortOrder|string|null $sortOrder Sort order for results
     * @param MarketIndex|string|null $market Market index filter
     * @param array $options Additional filtering options
     */
    public function gainers(
        ?int $limit = null,
        SortOrder|string|null $sortOrder = null,
        MarketIndex|string|null $market = null,
        array $options = []
    ): Response {
        $params = [];

        if ($limit !== null) {
            $params['limit'] = $limit;
        }

        if ($sortOrder !== null) {
            $sort = $sortOrder instanceof SortOrder ? $sortOrder : SortOrder::fromString($sortOrder);
            $params['sort'] = $sort->value;
        }

        if ($market !== null) {
            $marketEnum = $market instanceof MarketIndex ? $market : MarketIndex::fromString($market);
            $params['market'] = $marketEnum->value;
        }

        $params = array_merge($params, $options);

        return $this->get('/stocks/screens/gainers', $params);
    }

    /**
     * Get top losing stocks
     *
     * @param int|null $limit Maximum number of results
     * @param SortOrder|string|null $sortOrder Sort order for results
     * @param MarketIndex|string|null $market Market index filter
     * @param array $options Additional filtering options
     */
    public function losers(
        ?int $limit = null,
        SortOrder|string|null $sortOrder = null,
        MarketIndex|string|null $market = null,
        array $options = []
    ): Response {
        $params = [];

        if ($limit !== null) {
            $params['limit'] = $limit;
        }

        if ($sortOrder !== null) {
            $sort = $sortOrder instanceof SortOrder ? $sortOrder : SortOrder::fromString($sortOrder);
            $params['sort'] = $sort->value;
        }

        if ($market !== null) {
            $marketEnum = $market instanceof MarketIndex ? $market : MarketIndex::fromString($market);
            $params['market'] = $marketEnum->value;
        }

        $params = array_merge($params, $options);

        return $this->get('/stocks/screens/losers', $params);
    }

    /**
     * Get pre-market top gainers
     *
     * @param int|null $limit Maximum number of results
     * @param SortOrder|string|null $sortOrder Sort order for results
     * @param array $options Additional filtering options
     */
    public function preMarketGainers(
        ?int $limit = null,
        SortOrder|string|null $sortOrder = null,
        array $options = []
    ): Response {
        $params = [];

        if ($limit !== null) {
            $params['limit'] = $limit;
        }

        if ($sortOrder !== null) {
            $sort = $sortOrder instanceof SortOrder ? $sortOrder : SortOrder::fromString($sortOrder);
            $params['sort'] = $sort->value;
        }

        $params = array_merge($params, $options);

        return $this->get('/stocks/screens/pre_gainers', $params);
    }

    /**
     * Get pre-market top losers
     *
     * @param int|null $limit Maximum number of results
     * @param SortOrder|string|null $sortOrder Sort order for results
     * @param array $options Additional filtering options
     */
    public function preMarketLosers(
        ?int $limit = null,
        SortOrder|string|null $sortOrder = null,
        array $options = []
    ): Response {
        $params = [];

        if ($limit !== null) {
            $params['limit'] = $limit;
        }

        if ($sortOrder !== null) {
            $sort = $sortOrder instanceof SortOrder ? $sortOrder : SortOrder::fromString($sortOrder);
            $params['sort'] = $sort->value;
        }

        $params = array_merge($params, $options);

        return $this->get('/stocks/screens/pre_losers', $params);
    }

    /**
     * Get post-market top gainers
     *
     * @param int|null $limit Maximum number of results
     * @param SortOrder|string|null $sortOrder Sort order for results
     * @param array $options Additional filtering options
     */
    public function postMarketGainers(
        ?int $limit = null,
        SortOrder|string|null $sortOrder = null,
        array $options = []
    ): Response {
        $params = [];

        if ($limit !== null) {
            $params['limit'] = $limit;
        }

        if ($sortOrder !== null) {
            $sort = $sortOrder instanceof SortOrder ? $sortOrder : SortOrder::fromString($sortOrder);
            $params['sort'] = $sort->value;
        }

        $params = array_merge($params, $options);

        return $this->get('/stocks/screens/post_gainers', $params);
    }

    /**
     * Get post-market top losers
     *
     * @param int|null $limit Maximum number of results
     * @param SortOrder|string|null $sortOrder Sort order for results
     * @param array $options Additional filtering options
     */
    public function postMarketLosers(
        ?int $limit = null,
        SortOrder|string|null $sortOrder = null,
        array $options = []
    ): Response {
        $params = [];

        if ($limit !== null) {
            $params['limit'] = $limit;
        }

        if ($sortOrder !== null) {
            $sort = $sortOrder instanceof SortOrder ? $sortOrder : SortOrder::fromString($sortOrder);
            $params['sort'] = $sort->value;
        }

        $params = array_merge($params, $options);

        return $this->get('/stocks/screens/post_losers', $params);
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
        return $this->get('/stocks/screens/ipos', ['list' => $listValue]);
    }

    /**
     * Get comprehensive data for all stocks in one request
     * Warning: Large dataset - use with caution
     */
    public function allStocks(): Response
    {
        return $this->get('/stocks/screens/all_stocks_one_screen');
    }

    /**
     * Get comprehensive data for all ETFs in one request
     * Warning: Large dataset - use with caution
     */
    public function allEtfs(): Response
    {
        return $this->get('/stocks/screens/all_etf_one_screen');
    }

    // =================================================================
    // SESSION-BASED CONVENIENCE METHODS
    // =================================================================

    /**
     * Get screens by trading session
     *
     * @param TradingSession|string $session Trading session
     * @param ScreenType|string $type Screen type (default: GAINERS)
     * @param array $options Additional options
     */
    public function screensBySession(
        TradingSession|string $session,
        ScreenType|string $type = ScreenType::GAINERS,
        array $options = []
    ): Response {
        $sessionEnum = $session instanceof TradingSession ? $session : TradingSession::fromString($session);
        $screenType = $type instanceof ScreenType ? $type : ScreenType::fromString($type);

        return $this->screen($screenType, array_merge($options, ['session' => $sessionEnum]));
    }

    /**
     * Get pre-market screens (gainers by default)
     *
     * @param ScreenType|string $type Screen type (default: PRE_GAINERS)
     * @param array $options Additional options
     */
    public function preMarketScreens(
        ScreenType|string $type = ScreenType::PRE_GAINERS,
        array $options = []
    ): Response {
        $screenType = $type instanceof ScreenType ? $type : ScreenType::fromString($type);

        // Validate that screen type is suitable for pre-market
        if (!in_array(TradingSession::PRE_MARKET, $screenType->getSupportedSessions(), true)) {
            throw new \InvalidArgumentException(
                "Screen type '{$screenType->value}' is not supported for pre-market sessions"
            );
        }

        return $this->screen($screenType, array_merge($options, ['session' => TradingSession::PRE_MARKET]));
    }

    /**
     * Get post-market screens (gainers by default)
     *
     * @param ScreenType|string $type Screen type (default: POST_GAINERS)
     * @param array $options Additional options
     */
    public function postMarketScreens(
        ScreenType|string $type = ScreenType::POST_GAINERS,
        array $options = []
    ): Response {
        $screenType = $type instanceof ScreenType ? $type : ScreenType::fromString($type);

        // Validate that screen type is suitable for post-market
        if (!in_array(TradingSession::AFTER_HOURS, $screenType->getSupportedSessions(), true)) {
            throw new \InvalidArgumentException(
                "Screen type '{$screenType->value}' is not supported for post-market sessions"
            );
        }

        return $this->screen($screenType, array_merge($options, ['session' => TradingSession::AFTER_HOURS]));
    }

    /**
     * Get regular hours screens
     *
     * @param ScreenType|string $type Screen type (default: ACTIVE)
     * @param array $options Additional options
     */
    public function regularHoursScreens(
        ScreenType|string $type = ScreenType::ACTIVE,
        array $options = []
    ): Response {
        $screenType = $type instanceof ScreenType ? $type : ScreenType::fromString($type);

        return $this->screen($screenType, array_merge($options, ['session' => TradingSession::REGULAR]));
    }

    // =================================================================
    // SMART FILTERING METHODS
    // =================================================================

    /**
     * Get top movers (both gainers and losers)
     *
     * @param int $limit Number of gainers and losers each (default: 10)
     * @param SortOrder|string $order Sort order (default: DESCENDING)
     * @param MarketIndex|string|null $market Market index filter
     */
    public function topMovers(
        int $limit = 10,
        SortOrder|string $order = SortOrder::DESCENDING,
        MarketIndex|string|null $market = null
    ): Response {
        $sortOrder = $order instanceof SortOrder ? $order : SortOrder::fromString($order);

        // Get both gainers and losers
        $gainersResponse = $this->gainers($limit, $sortOrder, $market);
        $losersResponse = $this->losers($limit, $sortOrder, $market);

        // Combine the data
        $combined = [
            'success' => $gainersResponse['success'] && $losersResponse['success'],
            'data' => [
                'gainers' => $gainersResponse['data'] ?? [],
                'losers' => $losersResponse['data'] ?? [],
                'metadata' => [
                    'limit' => $limit,
                    'sort_order' => $sortOrder->value,
                    'market' => $market instanceof MarketIndex ? $market->value : $market,
                    'timestamp' => date('c'),
                    'total_gainers' => count($gainersResponse['data'] ?? []),
                    'total_losers' => count($losersResponse['data'] ?? [])
                ]
            ]
        ];

        // Create a new Response object with combined data
        return new Response(new \GuzzleHttp\Psr7\Response(200, [], json_encode($combined)));
    }

    /**
     * Get market sentiment analysis based on gainers vs losers ratio
     *
     * @param MarketIndex|string $index Market index (default: SP500)
     * @param int $sampleSize Sample size for analysis (default: 50)
     */
    public function marketSentiment(
        MarketIndex|string $index = MarketIndex::SP_500,
        int $sampleSize = 50
    ): Response {
        $marketIndex = $index instanceof MarketIndex ? $index : MarketIndex::fromString($index);

        // Get gainers and losers for sentiment analysis
        $gainersResponse = $this->gainers($sampleSize, SortOrder::DESCENDING, $marketIndex);
        $losersResponse = $this->losers($sampleSize, SortOrder::DESCENDING, $marketIndex);

        $gainersData = $gainersResponse['data'] ?? [];
        $losersData = $losersResponse['data'] ?? [];

        // Calculate sentiment metrics
        $gainerCount = count($gainersData);
        $loserCount = count($losersData);
        $total = $gainerCount + $loserCount;

        $bullishRatio = $total > 0 ? ($gainerCount / $total) * 100 : 0;
        $bearishRatio = $total > 0 ? ($loserCount / $total) * 100 : 0;

        // Determine sentiment
        $sentiment = match (true) {
            $bullishRatio > 70 => 'Strongly Bullish',
            $bullishRatio > 55 => 'Bullish',
            $bullishRatio > 45 => 'Neutral',
            $bullishRatio > 30 => 'Bearish',
            default => 'Strongly Bearish'
        };

        $analysis = [
            'success' => true,
            'data' => [
                'market_index' => $marketIndex->value,
                'sample_size' => $sampleSize,
                'sentiment' => $sentiment,
                'metrics' => [
                    'bullish_ratio' => round($bullishRatio, 2),
                    'bearish_ratio' => round($bearishRatio, 2),
                    'gainer_count' => $gainerCount,
                    'loser_count' => $loserCount,
                    'total_analyzed' => $total
                ],
                'top_gainers' => array_slice($gainersData, 0, 5),
                'top_losers' => array_slice($losersData, 0, 5),
                'timestamp' => date('c')
            ]
        ];

        return new Response(new \GuzzleHttp\Psr7\Response(200, [], json_encode($analysis)));
    }

    /**
     * Get volatility screens (high volume + significant price movement)
     *
     * @param array $options Filtering options (minVolumeChange, minPriceChange, limit)
     */
    public function volatilityScreens(array $options = []): Response
    {
        $limit = $options['limit'] ?? 25;
        $minVolumeChange = $options['minVolumeChange'] ?? 150; // 150% above average
        $minPriceChange = $options['minPriceChange'] ?? 5.0; // 5% price change

        // Get high volume stocks
        $activeResponse = $this->active($limit * 2, SortOrder::DESCENDING);

        // Get movers for price change analysis
        $gainersResponse = $this->gainers($limit, SortOrder::DESCENDING);
        $losersResponse = $this->losers($limit, SortOrder::DESCENDING);

        $volatileStocks = [
            'success' => true,
            'data' => [
                'filters' => [
                    'min_volume_change_percent' => $minVolumeChange,
                    'min_price_change_percent' => $minPriceChange,
                    'limit' => $limit
                ],
                'high_volume_stocks' => array_slice($activeResponse['data'] ?? [], 0, $limit),
                'volatile_gainers' => array_slice($gainersResponse['data'] ?? [], 0, $limit),
                'volatile_losers' => array_slice($losersResponse['data'] ?? [], 0, $limit),
                'metadata' => [
                    'scan_type' => 'volatility',
                    'timestamp' => date('c'),
                    'market_session' => 'regular_hours'
                ]
            ]
        ];

        return new Response(new \GuzzleHttp\Psr7\Response(200, [], json_encode($volatileStocks)));
    }

    /**
     * Get day trading candidates (high volume + reasonable spreads)
     *
     * @param int $limit Number of candidates to return
     * @param array $options Additional filtering options
     */
    public function dayTradingCandidates(int $limit = 20, array $options = []): Response
    {
        // Use the unified screen method to get active stocks
        return $this->screen(ScreenType::ACTIVE, array_merge([
            'limit' => $limit,
            'sortOrder' => SortOrder::DESCENDING,
            'session' => TradingSession::REGULAR
        ], $options));
    }

    /**
     * Get earnings reaction screens (pre/post market movers)
     *
     * @param TradingSession|string $session Session (PRE_MARKET or AFTER_HOURS)
     * @param int $limit Number of results
     */
    public function earningsReactionScreens(
        TradingSession|string $session = TradingSession::PRE_MARKET,
        int $limit = 15
    ): Response {
        $sessionEnum = $session instanceof TradingSession ? $session : TradingSession::fromString($session);

        $screenType = match ($sessionEnum) {
            TradingSession::PRE_MARKET => ScreenType::PRE_GAINERS,
            TradingSession::AFTER_HOURS => ScreenType::POST_GAINERS,
            default => throw new \InvalidArgumentException('Earnings reaction screens only support PRE_MARKET or AFTER_HOURS sessions')
        };

        return $this->screen($screenType, [
            'limit' => $limit,
            'sortOrder' => SortOrder::DESCENDING
        ]);
    }
}
