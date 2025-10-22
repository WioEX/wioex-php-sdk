<?php

declare(strict_types=1);

namespace Wioex\SDK\Enums;

/**
 * Major market indices for heatmap data
 *
 * Represents the main US stock market indices:
 * - NASDAQ_100: Top 100 largest non-financial companies on NASDAQ
 * - SP_500: 500 largest publicly traded companies in the US
 * - DOW_JONES: 30 large publicly-owned companies based in the US
 */
enum MarketIndex: string
{
    case NASDAQ_100 = 'nasdaq100';
    case SP_500 = 'sp500';
    case DOW_JONES = 'dowjones';

    /**
     * Get human-readable name
     */
    public function getName(): string
    {
        return match ($this) {
            self::NASDAQ_100 => 'NASDAQ-100',
            self::SP_500 => 'S&P 500',
            self::DOW_JONES => 'Dow Jones Industrial Average',
        };
    }

    /**
     * Get full description
     */
    public function getDescription(): string
    {
        return match ($this) {
            self::NASDAQ_100 => 'Top 100 largest non-financial companies listed on NASDAQ',
            self::SP_500 => '500 largest publicly traded companies in the United States',
            self::DOW_JONES => '30 large publicly-owned companies based in the United States',
        };
    }

    /**
     * Get approximate number of companies
     */
    public function getCompanyCount(): int
    {
        return match ($this) {
            self::NASDAQ_100 => 100,
            self::SP_500 => 500,
            self::DOW_JONES => 30,
        };
    }

    /**
     * Get market cap focus
     */
    public function getMarketCapFocus(): string
    {
        return match ($this) {
            self::NASDAQ_100 => 'Large-cap technology and growth companies',
            self::SP_500 => 'Large-cap companies across all sectors',
            self::DOW_JONES => 'Blue-chip large-cap companies',
        };
    }

    /**
     * Get primary sectors represented
     */
    public function getPrimarySectors(): array
    {
        return match ($this) {
            self::NASDAQ_100 => [
                'Technology',
                'Consumer Discretionary',
                'Communication Services',
                'Healthcare'
            ],
            self::SP_500 => [
                'Technology',
                'Healthcare',
                'Financials',
                'Consumer Discretionary',
                'Communication Services',
                'Industrials',
                'Consumer Staples',
                'Energy',
                'Utilities',
                'Real Estate',
                'Materials'
            ],
            self::DOW_JONES => [
                'Technology',
                'Healthcare',
                'Financials',
                'Consumer Discretionary',
                'Industrials',
                'Consumer Staples'
            ],
        };
    }

    /**
     * Get weighting methodology
     */
    public function getWeightingMethod(): string
    {
        return match ($this) {
            self::NASDAQ_100 => 'Market capitalization weighted (modified)',
            self::SP_500 => 'Market capitalization weighted',
            self::DOW_JONES => 'Price weighted',
        };
    }

    /**
     * Get typical volatility characteristics
     */
    public function getVolatilityCharacteristics(): string
    {
        return match ($this) {
            self::NASDAQ_100 => 'Higher volatility due to tech concentration',
            self::SP_500 => 'Moderate volatility, broad market representation',
            self::DOW_JONES => 'Lower volatility, blue-chip stability',
        };
    }

    /**
     * Get investment use case
     */
    public function getInvestmentUseCase(): string
    {
        return match ($this) {
            self::NASDAQ_100 => 'Growth investing, tech sector exposure',
            self::SP_500 => 'Broad market exposure, passive investing benchmark',
            self::DOW_JONES => 'Blue-chip exposure, conservative large-cap investing',
        };
    }

    /**
     * Get heatmap color scheme recommendation
     */
    public function getColorScheme(): string
    {
        return match ($this) {
            self::NASDAQ_100 => 'Purple-to-green gradient (tech theme)',
            self::SP_500 => 'Red-to-green gradient (standard market)',
            self::DOW_JONES => 'Blue-to-green gradient (conservative theme)',
        };
    }

    /**
     * Get ticker symbol for index
     */
    public function getTickerSymbol(): string
    {
        return match ($this) {
            self::NASDAQ_100 => 'NDX',
            self::SP_500 => 'SPX',
            self::DOW_JONES => 'DJI',
        };
    }

    /**
     * Get common ETF tracking this index
     */
    public function getCommonETF(): string
    {
        return match ($this) {
            self::NASDAQ_100 => 'QQQ (Invesco QQQ Trust)',
            self::SP_500 => 'SPY (SPDR S&P 500 ETF Trust)',
            self::DOW_JONES => 'DIA (SPDR Dow Jones Industrial Average ETF)',
        };
    }

    /**
     * Create MarketIndex from string value
     *
     * @param string $value The market index string
     * @return self
     * @throws \InvalidArgumentException If the value is not a valid market index
     */
    public static function fromString(string $value): self
    {
        return self::tryFrom($value)
            ?? throw new \InvalidArgumentException("Invalid market index: {$value}");
    }

    /**
     * Get all available indices with descriptions
     *
     * @return array<string, string> Array of index value => name
     */
    public static function getAllIndices(): array
    {
        $indices = [];
        foreach (self::cases() as $index) {
            $indices[$index->value] = $index->getName();
        }
        return $indices;
    }

    /**
     * Get indices suitable for growth investing
     *
     * @return array<MarketIndex>
     */
    public static function getGrowthIndices(): array
    {
        return [self::NASDAQ_100];
    }

    /**
     * Get indices suitable for conservative investing
     *
     * @return array<MarketIndex>
     */
    public static function getConservativeIndices(): array
    {
        return [self::DOW_JONES];
    }

    /**
     * Get indices suitable for broad market exposure
     *
     * @return array<MarketIndex>
     */
    public static function getBroadMarketIndices(): array
    {
        return [self::SP_500];
    }
}
