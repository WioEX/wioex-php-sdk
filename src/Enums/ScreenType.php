<?php

declare(strict_types=1);

namespace Wioex\SDK\Enums;

/**
 * Screen types for stock market screening and filtering
 *
 * Represents different types of stock screens available:
 * - ACTIVE: Most actively traded stocks by volume
 * - GAINERS: Top gaining stocks by percentage change
 * - LOSERS: Top losing stocks by percentage change
 * - PRE_GAINERS: Pre-market top gainers
 * - PRE_LOSERS: Pre-market top losers
 * - POST_GAINERS: Post-market top gainers
 * - POST_LOSERS: Post-market top losers
 * - ALL_STOCKS: Comprehensive stock data (large dataset)
 * - ALL_ETFS: Comprehensive ETF data (large dataset)
 */
enum ScreenType: string
{
    case ACTIVE = 'active';
    case GAINERS = 'gainers';
    case LOSERS = 'losers';
    case PRE_GAINERS = 'pre_gainers';
    case PRE_LOSERS = 'pre_losers';
    case POST_GAINERS = 'post_gainers';
    case POST_LOSERS = 'post_losers';
    case ALL_STOCKS = 'all_stocks';
    case ALL_ETFS = 'all_etfs';

    /**
     * Get API endpoint for this screen type
     */
    public function getEndpoint(): string
    {
        return match ($this) {
            self::ACTIVE => '/v2/stocks/screens/active',
            self::GAINERS => '/v2/stocks/screens/gainers',
            self::LOSERS => '/v2/stocks/screens/losers',
            self::PRE_GAINERS => '/v2/stocks/screens/pre_gainers',
            self::PRE_LOSERS => '/v2/stocks/screens/pre_losers',
            self::POST_GAINERS => '/v2/stocks/screens/post_gainers',
            self::POST_LOSERS => '/v2/stocks/screens/post_losers',
            self::ALL_STOCKS => '/v2/stocks/screens/all_stocks_one_screen',
            self::ALL_ETFS => '/v2/stocks/screens/all_etf_one_screen',
        };
    }

    /**
     * Get human-readable description
     */
    public function getDescription(): string
    {
        return match ($this) {
            self::ACTIVE => 'Most actively traded stocks by volume',
            self::GAINERS => 'Top gaining stocks by percentage change',
            self::LOSERS => 'Top losing stocks by percentage change',
            self::PRE_GAINERS => 'Pre-market top gaining stocks',
            self::PRE_LOSERS => 'Pre-market top losing stocks',
            self::POST_GAINERS => 'Post-market top gaining stocks',
            self::POST_LOSERS => 'Post-market top losing stocks',
            self::ALL_STOCKS => 'Comprehensive data for all stocks (large dataset)',
            self::ALL_ETFS => 'Comprehensive data for all ETFs (large dataset)',
        };
    }

    /**
     * Get supported trading sessions for this screen type
     *
     * @return array<TradingSession>
     */
    public function getSupportedSessions(): array
    {
        return match ($this) {
            self::ACTIVE => [TradingSession::ALL, TradingSession::REGULAR, TradingSession::EXTENDED],
            self::GAINERS => [TradingSession::ALL, TradingSession::REGULAR, TradingSession::EXTENDED],
            self::LOSERS => [TradingSession::ALL, TradingSession::REGULAR, TradingSession::EXTENDED],
            self::PRE_GAINERS => [TradingSession::PRE_MARKET],
            self::PRE_LOSERS => [TradingSession::PRE_MARKET],
            self::POST_GAINERS => [TradingSession::AFTER_HOURS],
            self::POST_LOSERS => [TradingSession::AFTER_HOURS],
            self::ALL_STOCKS => [TradingSession::ALL, TradingSession::REGULAR],
            self::ALL_ETFS => [TradingSession::ALL, TradingSession::REGULAR],
        };
    }

    /**
     * Get the primary trading session for this screen type
     */
    public function getPrimarySession(): TradingSession
    {
        return match ($this) {
            self::ACTIVE, self::GAINERS, self::LOSERS => TradingSession::REGULAR,
            self::PRE_GAINERS, self::PRE_LOSERS => TradingSession::PRE_MARKET,
            self::POST_GAINERS, self::POST_LOSERS => TradingSession::AFTER_HOURS,
            self::ALL_STOCKS, self::ALL_ETFS => TradingSession::ALL,
        };
    }

    /**
     * Get screen category
     */
    public function getCategory(): string
    {
        return match ($this) {
            self::ACTIVE => 'Volume-based',
            self::GAINERS, self::PRE_GAINERS, self::POST_GAINERS => 'Performance (Positive)',
            self::LOSERS, self::PRE_LOSERS, self::POST_LOSERS => 'Performance (Negative)',
            self::ALL_STOCKS, self::ALL_ETFS => 'Comprehensive Data',
        };
    }

    /**
     * Get sorting metric for this screen type
     */
    public function getSortingMetric(): string
    {
        return match ($this) {
            self::ACTIVE => 'Trading volume',
            self::GAINERS, self::PRE_GAINERS, self::POST_GAINERS => 'Percentage gain',
            self::LOSERS, self::PRE_LOSERS, self::POST_LOSERS => 'Percentage loss',
            self::ALL_STOCKS, self::ALL_ETFS => 'Market capitalization',
        };
    }

    /**
     * Check if screen type is performance-based (gains/losses)
     */
    public function isPerformanceBased(): bool
    {
        return match ($this) {
            self::GAINERS, self::LOSERS, self::PRE_GAINERS, self::PRE_LOSERS,
            self::POST_GAINERS, self::POST_LOSERS => true,
            self::ACTIVE, self::ALL_STOCKS, self::ALL_ETFS => false,
        };
    }

    /**
     * Check if screen type shows positive performance
     */
    public function isPositivePerformance(): bool
    {
        return match ($this) {
            self::GAINERS, self::PRE_GAINERS, self::POST_GAINERS => true,
            self::LOSERS, self::PRE_LOSERS, self::POST_LOSERS,
            self::ACTIVE, self::ALL_STOCKS, self::ALL_ETFS => false,
        };
    }

    /**
     * Check if screen type is session-specific (pre/post market)
     */
    public function isSessionSpecific(): bool
    {
        return match ($this) {
            self::PRE_GAINERS, self::PRE_LOSERS, self::POST_GAINERS, self::POST_LOSERS => true,
            self::ACTIVE, self::GAINERS, self::LOSERS, self::ALL_STOCKS, self::ALL_ETFS => false,
        };
    }

    /**
     * Check if screen type returns large datasets
     */
    public function isLargeDataset(): bool
    {
        return match ($this) {
            self::ALL_STOCKS, self::ALL_ETFS => true,
            self::ACTIVE, self::GAINERS, self::LOSERS, self::PRE_GAINERS,
            self::PRE_LOSERS, self::POST_GAINERS, self::POST_LOSERS => false,
        };
    }

    /**
     * Get recommended limit for this screen type
     */
    public function getRecommendedLimit(): ?int
    {
        return match ($this) {
            self::ACTIVE => 50,
            self::GAINERS, self::LOSERS => 25,
            self::PRE_GAINERS, self::PRE_LOSERS => 20,
            self::POST_GAINERS, self::POST_LOSERS => 20,
            self::ALL_STOCKS, self::ALL_ETFS => null, // No limit recommended for comprehensive data
        };
    }

    /**
     * Get use case description
     */
    public function getUseCase(): string
    {
        return match ($this) {
            self::ACTIVE => 'Find liquid stocks with high trading volume for day trading',
            self::GAINERS => 'Identify momentum stocks and bullish market sentiment',
            self::LOSERS => 'Find potential value opportunities and bearish sentiment',
            self::PRE_GAINERS => 'Early detection of positive news impact and earnings reactions',
            self::PRE_LOSERS => 'Early detection of negative news impact and earnings disappointments',
            self::POST_GAINERS => 'Analysis of after-hours positive momentum and late news',
            self::POST_LOSERS => 'Analysis of after-hours negative momentum and late news',
            self::ALL_STOCKS => 'Comprehensive market analysis and custom screening',
            self::ALL_ETFS => 'ETF universe analysis and sector/theme screening',
        };
    }

    /**
     * Get data refresh frequency
     */
    public function getRefreshFrequency(): string
    {
        return match ($this) {
            self::ACTIVE => 'Real-time (every few seconds)',
            self::GAINERS, self::LOSERS => 'Near real-time (every minute)',
            self::PRE_GAINERS, self::PRE_LOSERS => 'Pre-market hours only (4:00-9:30 AM EST)',
            self::POST_GAINERS, self::POST_LOSERS => 'After-hours only (4:00-8:00 PM EST)',
            self::ALL_STOCKS, self::ALL_ETFS => 'End of day (market close)',
        };
    }

    /**
     * Get typical investor type for this screen
     */
    public function getInvestorType(): string
    {
        return match ($this) {
            self::ACTIVE => 'Day traders, scalpers, high-frequency traders',
            self::GAINERS => 'Momentum traders, growth investors',
            self::LOSERS => 'Value investors, contrarian traders',
            self::PRE_GAINERS, self::PRE_LOSERS => 'News traders, earnings players',
            self::POST_GAINERS, self::POST_LOSERS => 'Extended hours traders, institutional',
            self::ALL_STOCKS, self::ALL_ETFS => 'Quantitative analysts, systematic traders',
        };
    }

    /**
     * Get risk level associated with this screen type
     */
    public function getRiskLevel(): string
    {
        return match ($this) {
            self::ACTIVE => 'High - Volatile, liquid stocks',
            self::GAINERS => 'Medium-High - Momentum can reverse',
            self::LOSERS => 'Medium - Value opportunities but falling knives',
            self::PRE_GAINERS, self::PRE_LOSERS => 'High - Low liquidity, wide spreads',
            self::POST_GAINERS, self::POST_LOSERS => 'High - Limited liquidity, gaps possible',
            self::ALL_STOCKS, self::ALL_ETFS => 'Variable - Depends on selection criteria',
        };
    }

    /**
     * Create ScreenType from string value
     *
     * @param string $value The screen type string
     * @return self
     * @throws \InvalidArgumentException If the value is not a valid screen type
     */
    public static function fromString(string $value): self
    {
        return self::tryFrom($value)
            ?? throw new \InvalidArgumentException("Invalid screen type: {$value}");
    }

    /**
     * Get default screen type (most active stocks)
     */
    public static function default(): self
    {
        return self::ACTIVE;
    }

    /**
     * Get performance-based screen types
     *
     * @return array<ScreenType>
     */
    public static function getPerformanceScreens(): array
    {
        return [
            self::GAINERS,
            self::LOSERS,
            self::PRE_GAINERS,
            self::PRE_LOSERS,
            self::POST_GAINERS,
            self::POST_LOSERS,
        ];
    }

    /**
     * Get regular market hours screen types
     *
     * @return array<ScreenType>
     */
    public static function getRegularHoursScreens(): array
    {
        return [
            self::ACTIVE,
            self::GAINERS,
            self::LOSERS,
        ];
    }

    /**
     * Get extended hours screen types (pre/post market)
     *
     * @return array<ScreenType>
     */
    public static function getExtendedHoursScreens(): array
    {
        return [
            self::PRE_GAINERS,
            self::PRE_LOSERS,
            self::POST_GAINERS,
            self::POST_LOSERS,
        ];
    }

    /**
     * Get bullish sentiment screen types
     *
     * @return array<ScreenType>
     */
    public static function getBullishScreens(): array
    {
        return [
            self::GAINERS,
            self::PRE_GAINERS,
            self::POST_GAINERS,
        ];
    }

    /**
     * Get bearish sentiment screen types
     *
     * @return array<ScreenType>
     */
    public static function getBearishScreens(): array
    {
        return [
            self::LOSERS,
            self::PRE_LOSERS,
            self::POST_LOSERS,
        ];
    }

    /**
     * Get comprehensive data screen types (large datasets)
     *
     * @return array<ScreenType>
     */
    public static function getComprehensiveScreens(): array
    {
        return [
            self::ALL_STOCKS,
            self::ALL_ETFS,
        ];
    }

    /**
     * Get screen types suitable for day trading
     *
     * @return array<ScreenType>
     */
    public static function getDayTradingScreens(): array
    {
        return [
            self::ACTIVE,
            self::GAINERS,
            self::LOSERS,
            self::PRE_GAINERS,
        ];
    }

    /**
     * Get screen types by trading session
     *
     * @param TradingSession $session
     * @return array<ScreenType>
     */
    public static function getScreensBySession(TradingSession $session): array
    {
        return array_filter(
            self::cases(),
            fn(ScreenType $screen) => in_array($session, $screen->getSupportedSessions(), true)
        );
    }
}
