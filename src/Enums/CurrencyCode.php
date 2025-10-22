<?php

declare(strict_types=1);

namespace Wioex\SDK\Enums;

/**
 * Currency codes for exchange rate operations
 *
 * Represents major world currencies using ISO 4217 standard codes.
 * Includes the most commonly traded currencies in global financial markets.
 */
enum CurrencyCode: string
{
    // Major currencies (G10)
    case USD = 'USD'; // US Dollar
    case EUR = 'EUR'; // Euro
    case GBP = 'GBP'; // British Pound
    case JPY = 'JPY'; // Japanese Yen
    case CHF = 'CHF'; // Swiss Franc
    case CAD = 'CAD'; // Canadian Dollar
    case AUD = 'AUD'; // Australian Dollar
    case NZD = 'NZD'; // New Zealand Dollar
    case SEK = 'SEK'; // Swedish Krona
    case NOK = 'NOK'; // Norwegian Krone

    // Other major currencies
    case CNY = 'CNY'; // Chinese Yuan
    case HKD = 'HKD'; // Hong Kong Dollar
    case SGD = 'SGD'; // Singapore Dollar
    case KRW = 'KRW'; // South Korean Won
    case INR = 'INR'; // Indian Rupee
    case BRL = 'BRL'; // Brazilian Real
    case MXN = 'MXN'; // Mexican Peso
    case RUB = 'RUB'; // Russian Ruble
    case TRY = 'TRY'; // Turkish Lira
    case ZAR = 'ZAR'; // South African Rand

    /**
     * Get full currency name
     */
    public function getName(): string
    {
        return match ($this) {
            self::USD => 'US Dollar',
            self::EUR => 'Euro',
            self::GBP => 'British Pound Sterling',
            self::JPY => 'Japanese Yen',
            self::CHF => 'Swiss Franc',
            self::CAD => 'Canadian Dollar',
            self::AUD => 'Australian Dollar',
            self::NZD => 'New Zealand Dollar',
            self::SEK => 'Swedish Krona',
            self::NOK => 'Norwegian Krone',
            self::CNY => 'Chinese Yuan',
            self::HKD => 'Hong Kong Dollar',
            self::SGD => 'Singapore Dollar',
            self::KRW => 'South Korean Won',
            self::INR => 'Indian Rupee',
            self::BRL => 'Brazilian Real',
            self::MXN => 'Mexican Peso',
            self::RUB => 'Russian Ruble',
            self::TRY => 'Turkish Lira',
            self::ZAR => 'South African Rand',
        };
    }

    /**
     * Get currency symbol
     */
    public function getSymbol(): string
    {
        return match ($this) {
            self::USD => '$',
            self::EUR => '€',
            self::GBP => '£',
            self::JPY => '¥',
            self::CHF => 'CHF',
            self::CAD => 'C$',
            self::AUD => 'A$',
            self::NZD => 'NZ$',
            self::SEK => 'kr',
            self::NOK => 'kr',
            self::CNY => '¥',
            self::HKD => 'HK$',
            self::SGD => 'S$',
            self::KRW => '₩',
            self::INR => '₹',
            self::BRL => 'R$',
            self::MXN => '$',
            self::RUB => '₽',
            self::TRY => '₺',
            self::ZAR => 'R',
        };
    }

    /**
     * Get country/region
     */
    public function getCountryRegion(): string
    {
        return match ($this) {
            self::USD => 'United States',
            self::EUR => 'Eurozone',
            self::GBP => 'United Kingdom',
            self::JPY => 'Japan',
            self::CHF => 'Switzerland',
            self::CAD => 'Canada',
            self::AUD => 'Australia',
            self::NZD => 'New Zealand',
            self::SEK => 'Sweden',
            self::NOK => 'Norway',
            self::CNY => 'China',
            self::HKD => 'Hong Kong',
            self::SGD => 'Singapore',
            self::KRW => 'South Korea',
            self::INR => 'India',
            self::BRL => 'Brazil',
            self::MXN => 'Mexico',
            self::RUB => 'Russia',
            self::TRY => 'Turkey',
            self::ZAR => 'South Africa',
        };
    }

    /**
     * Check if currency is a major (G10) currency
     */
    public function isMajor(): bool
    {
        return in_array($this, [
            self::USD, self::EUR, self::GBP, self::JPY, self::CHF,
            self::CAD, self::AUD, self::NZD, self::SEK, self::NOK
        ], true);
    }

    /**
     * Check if currency is from an emerging market
     */
    public function isEmergingMarket(): bool
    {
        return in_array($this, [
            self::CNY, self::INR, self::BRL, self::MXN, self::RUB, self::TRY, self::ZAR
        ], true);
    }

    /**
     * Check if currency is typically traded against USD
     */
    public function isUSDPair(): bool
    {
        return $this !== self::USD;
    }

    /**
     * Get typical decimal places for this currency
     */
    public function getDecimalPlaces(): int
    {
        return match ($this) {
            self::JPY, self::KRW => 0, // Currencies without minor units
            default => 2, // Most currencies use 2 decimal places
        };
    }

    /**
     * Get trading session when this currency is most active
     */
    public function getPrimaryTradingSession(): string
    {
        return match ($this) {
            self::USD, self::CAD, self::MXN, self::BRL => 'New York (EST)',
            self::EUR, self::GBP, self::CHF, self::SEK, self::NOK, self::RUB, self::TRY => 'London (GMT)',
            self::JPY, self::AUD, self::NZD, self::CNY, self::HKD, self::SGD, self::KRW => 'Tokyo/Sydney (JST)',
            self::INR => 'Mumbai (IST)',
            self::ZAR => 'Johannesburg (SAST)',
        };
    }

    /**
     * Get volatility characteristics
     */
    public function getVolatilityProfile(): string
    {
        return match ($this) {
            self::USD, self::EUR, self::GBP, self::JPY, self::CHF => 'Low - Major currency stability',
            self::CAD, self::AUD, self::NZD, self::SEK, self::NOK => 'Medium - Commodity-linked volatility',
            self::CNY, self::HKD, self::SGD => 'Low-Medium - Managed exchange rates',
            self::INR, self::BRL, self::MXN, self::KRW => 'Medium-High - Emerging market dynamics',
            self::RUB, self::TRY, self::ZAR => 'High - Political and economic sensitivity',
        };
    }

    /**
     * Create CurrencyCode from string value
     *
     * @param string $value The currency code string
     * @return self
     * @throws \InvalidArgumentException If the value is not a valid currency code
     */
    public static function fromString(string $value): self
    {
        $normalizedValue = strtoupper($value);
        return self::tryFrom($normalizedValue)
            ?? throw new \InvalidArgumentException("Invalid currency code: {$value}");
    }

    /**
     * Get all major currencies (G10)
     *
     * @return array<CurrencyCode>
     */
    public static function getMajorCurrencies(): array
    {
        return [
            self::USD, self::EUR, self::GBP, self::JPY, self::CHF,
            self::CAD, self::AUD, self::NZD, self::SEK, self::NOK
        ];
    }

    /**
     * Get emerging market currencies
     *
     * @return array<CurrencyCode>
     */
    public static function getEmergingMarketCurrencies(): array
    {
        return [
            self::CNY, self::INR, self::BRL, self::MXN, self::RUB, self::TRY, self::ZAR
        ];
    }

    /**
     * Get Asian currencies
     *
     * @return array<CurrencyCode>
     */
    public static function getAsianCurrencies(): array
    {
        return [
            self::JPY, self::CNY, self::HKD, self::SGD, self::KRW, self::INR
        ];
    }

    /**
     * Get all available currencies with names
     *
     * @return array<string, string> Array of code => name
     */
    public static function getAllCurrencies(): array
    {
        $currencies = [];
        foreach (self::cases() as $currency) {
            $currencies[$currency->value] = $currency->getName();
        }
        return $currencies;
    }
}
