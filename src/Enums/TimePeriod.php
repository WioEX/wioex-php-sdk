<?php

declare(strict_types=1);

namespace Wioex\SDK\Enums;

enum TimePeriod: string
{
    case MINUTE = '1m';
    case FIVE_MINUTES = '5m';
    case FIFTEEN_MINUTES = '15m';
    case THIRTY_MINUTES = '30m';
    case HOUR = '1h';
    case TWO_HOURS = '2h';
    case FOUR_HOURS = '4h';
    case SIX_HOURS = '6h';
    case TWELVE_HOURS = '12h';
    case DAY = '1d';
    case THREE_DAYS = '3d';
    case WEEK = '1w';
    case TWO_WEEKS = '2w';
    case MONTH = '1M';
    case THREE_MONTHS = '3M';
    case SIX_MONTHS = '6M';
    case YEAR = '1y';
    case TWO_YEARS = '2y';
    case FIVE_YEARS = '5y';
    case TEN_YEARS = '10y';
    case MAX = 'max';

    public function getDescription(): string
    {
        return match ($this) {
            self::MINUTE => '1 Minute',
            self::FIVE_MINUTES => '5 Minutes',
            self::FIFTEEN_MINUTES => '15 Minutes',
            self::THIRTY_MINUTES => '30 Minutes',
            self::HOUR => '1 Hour',
            self::TWO_HOURS => '2 Hours',
            self::FOUR_HOURS => '4 Hours',
            self::SIX_HOURS => '6 Hours',
            self::TWELVE_HOURS => '12 Hours',
            self::DAY => '1 Day',
            self::THREE_DAYS => '3 Days',
            self::WEEK => '1 Week',
            self::TWO_WEEKS => '2 Weeks',
            self::MONTH => '1 Month',
            self::THREE_MONTHS => '3 Months',
            self::SIX_MONTHS => '6 Months',
            self::YEAR => '1 Year',
            self::TWO_YEARS => '2 Years',
            self::FIVE_YEARS => '5 Years',
            self::TEN_YEARS => '10 Years',
            self::MAX => 'Maximum Available',
        };
    }

    public function getSeconds(): int
    {
        return match ($this) {
            self::MINUTE => 60,
            self::FIVE_MINUTES => 300,
            self::FIFTEEN_MINUTES => 900,
            self::THIRTY_MINUTES => 1800,
            self::HOUR => 3600,
            self::TWO_HOURS => 7200,
            self::FOUR_HOURS => 14400,
            self::SIX_HOURS => 21600,
            self::TWELVE_HOURS => 43200,
            self::DAY => 86400,
            self::THREE_DAYS => 259200,
            self::WEEK => 604800,
            self::TWO_WEEKS => 1209600,
            self::MONTH => 2592000,     // 30 days
            self::THREE_MONTHS => 7776000,   // 90 days
            self::SIX_MONTHS => 15552000,    // 180 days
            self::YEAR => 31536000,     // 365 days
            self::TWO_YEARS => 63072000,     // 730 days
            self::FIVE_YEARS => 157680000,   // 1825 days
            self::TEN_YEARS => 315360000,    // 3650 days
            self::MAX => PHP_INT_MAX,
        };
    }

    public function getMinutes(): int
    {
        return (int) ($this->getSeconds() / 60);
    }

    public function getHours(): int
    {
        return (int) ($this->getSeconds() / 3600);
    }

    public function getDays(): int
    {
        return (int) ($this->getSeconds() / 86400);
    }

    public function getCategory(): string
    {
        return match ($this) {
            self::MINUTE, self::FIVE_MINUTES, self::FIFTEEN_MINUTES, self::THIRTY_MINUTES => 'intraday',
            self::HOUR, self::TWO_HOURS, self::FOUR_HOURS, self::SIX_HOURS, self::TWELVE_HOURS => 'hourly',
            self::DAY, self::THREE_DAYS => 'daily',
            self::WEEK, self::TWO_WEEKS => 'weekly',
            self::MONTH, self::THREE_MONTHS, self::SIX_MONTHS => 'monthly',
            self::YEAR, self::TWO_YEARS, self::FIVE_YEARS, self::TEN_YEARS => 'yearly',
            self::MAX => 'unlimited',
        };
    }

    public function isIntraday(): bool
    {
        return $this->getCategory() === 'intraday';
    }

    public function isDaily(): bool
    {
        return $this->getCategory() === 'daily';
    }

    public function isWeekly(): bool
    {
        return $this->getCategory() === 'weekly';
    }

    public function isMonthly(): bool
    {
        return $this->getCategory() === 'monthly';
    }

    public function isYearly(): bool
    {
        return $this->getCategory() === 'yearly';
    }

    public function getSuggestedInterval(): self
    {
        return match ($this) {
            self::MINUTE, self::FIVE_MINUTES => self::MINUTE,
            self::FIFTEEN_MINUTES, self::THIRTY_MINUTES => self::FIVE_MINUTES,
            self::HOUR, self::TWO_HOURS => self::FIFTEEN_MINUTES,
            self::FOUR_HOURS, self::SIX_HOURS => self::THIRTY_MINUTES,
            self::TWELVE_HOURS, self::DAY => self::HOUR,
            self::THREE_DAYS, self::WEEK => self::DAY,
            self::TWO_WEEKS, self::MONTH => self::DAY,
            self::THREE_MONTHS => self::WEEK,
            self::SIX_MONTHS, self::YEAR => self::WEEK,
            self::TWO_YEARS, self::FIVE_YEARS => self::MONTH,
            self::TEN_YEARS, self::MAX => self::MONTH,
        };
    }

    public function getMaxDataPoints(): int
    {
        return match ($this) {
            self::MINUTE => 1440,       // 1 day of minute data
            self::FIVE_MINUTES => 2016, // 1 week of 5-minute data
            self::FIFTEEN_MINUTES => 672, // 1 week of 15-minute data
            self::THIRTY_MINUTES => 336,  // 1 week of 30-minute data
            self::HOUR => 168,           // 1 week of hourly data
            self::TWO_HOURS => 84,       // 1 week of 2-hour data
            self::FOUR_HOURS => 42,      // 1 week of 4-hour data
            self::SIX_HOURS => 28,       // 1 week of 6-hour data
            self::TWELVE_HOURS => 14,    // 1 week of 12-hour data
            self::DAY => 365,            // 1 year of daily data
            self::THREE_DAYS => 122,     // 1 year of 3-day data
            self::WEEK => 104,           // 2 years of weekly data
            self::TWO_WEEKS => 52,       // 2 years of bi-weekly data
            self::MONTH => 60,           // 5 years of monthly data
            self::THREE_MONTHS => 40,    // 10 years of quarterly data
            self::SIX_MONTHS => 20,      // 10 years of semi-annual data
            self::YEAR => 20,            // 20 years of annual data
            self::TWO_YEARS => 10,       // 20 years of bi-annual data
            self::FIVE_YEARS => 4,       // 20 years of 5-year data
            self::TEN_YEARS => 2,        // 20 years of 10-year data
            self::MAX => 10000,          // Maximum reasonable data points
        };
    }

    public function getUseCases(): array
    {
        return match ($this) {
            self::MINUTE => ['scalping', 'high_frequency_trading', 'real_time_monitoring'],
            self::FIVE_MINUTES => ['day_trading', 'short_term_analysis', 'real_time_alerts'],
            self::FIFTEEN_MINUTES => ['swing_trading', 'technical_analysis', 'intraday_patterns'],
            self::THIRTY_MINUTES => ['swing_trading', 'pattern_recognition', 'support_resistance'],
            self::HOUR => ['trend_analysis', 'momentum_trading', 'short_term_trends'],
            self::TWO_HOURS, self::FOUR_HOURS => ['position_trading', 'trend_confirmation'],
            self::SIX_HOURS, self::TWELVE_HOURS => ['swing_analysis', 'medium_term_trends'],
            self::DAY => ['fundamental_analysis', 'long_term_trends', 'portfolio_management'],
            self::THREE_DAYS => ['weekly_analysis', 'trend_confirmation'],
            self::WEEK => ['portfolio_rebalancing', 'sector_analysis', 'long_term_planning'],
            self::TWO_WEEKS => ['monthly_reporting', 'performance_analysis'],
            self::MONTH => ['quarterly_analysis', 'financial_reporting', 'trend_analysis'],
            self::THREE_MONTHS => ['quarterly_reporting', 'seasonal_analysis'],
            self::SIX_MONTHS => ['semi_annual_review', 'long_term_planning'],
            self::YEAR => ['annual_reporting', 'long_term_investment', 'backtesting'],
            self::TWO_YEARS, self::FIVE_YEARS, self::TEN_YEARS => ['historical_analysis', 'long_term_backtesting'],
            self::MAX => ['full_historical_analysis', 'comprehensive_backtesting'],
        };
    }

    public function getDateTimeInterval(): \DateInterval
    {
        return match ($this) {
            self::MINUTE => new \DateInterval('PT1M'),
            self::FIVE_MINUTES => new \DateInterval('PT5M'),
            self::FIFTEEN_MINUTES => new \DateInterval('PT15M'),
            self::THIRTY_MINUTES => new \DateInterval('PT30M'),
            self::HOUR => new \DateInterval('PT1H'),
            self::TWO_HOURS => new \DateInterval('PT2H'),
            self::FOUR_HOURS => new \DateInterval('PT4H'),
            self::SIX_HOURS => new \DateInterval('PT6H'),
            self::TWELVE_HOURS => new \DateInterval('PT12H'),
            self::DAY => new \DateInterval('P1D'),
            self::THREE_DAYS => new \DateInterval('P3D'),
            self::WEEK => new \DateInterval('P1W'),
            self::TWO_WEEKS => new \DateInterval('P2W'),
            self::MONTH => new \DateInterval('P1M'),
            self::THREE_MONTHS => new \DateInterval('P3M'),
            self::SIX_MONTHS => new \DateInterval('P6M'),
            self::YEAR => new \DateInterval('P1Y'),
            self::TWO_YEARS => new \DateInterval('P2Y'),
            self::FIVE_YEARS => new \DateInterval('P5Y'),
            self::TEN_YEARS => new \DateInterval('P10Y'),
            self::MAX => new \DateInterval('P100Y'), // Arbitrary large interval
        };
    }

    public static function getByCategory(string $category): array
    {
        return array_filter(
            self::cases(),
            fn(self $period) => $period->getCategory() === $category
        );
    }

    public static function getIntradayPeriods(): array
    {
        return self::getByCategory('intraday');
    }

    public static function getDailyPeriods(): array
    {
        return self::getByCategory('daily');
    }

    public static function getWeeklyPeriods(): array
    {
        return self::getByCategory('weekly');
    }

    public static function getMonthlyPeriods(): array
    {
        return self::getByCategory('monthly');
    }

    public static function getYearlyPeriods(): array
    {
        return self::getByCategory('yearly');
    }

    public static function fromString(string $period): self
    {
        $period = strtolower(trim($period));

        return match ($period) {
            '1m', 'minute', '1min' => self::MINUTE,
            '5m', '5min', '5minutes' => self::FIVE_MINUTES,
            '15m', '15min', '15minutes' => self::FIFTEEN_MINUTES,
            '30m', '30min', '30minutes' => self::THIRTY_MINUTES,
            '1h', 'hour', '1hour' => self::HOUR,
            '2h', '2hour', '2hours' => self::TWO_HOURS,
            '4h', '4hour', '4hours' => self::FOUR_HOURS,
            '6h', '6hour', '6hours' => self::SIX_HOURS,
            '12h', '12hour', '12hours' => self::TWELVE_HOURS,
            '1d', 'day', '1day', 'daily' => self::DAY,
            '3d', '3day', '3days' => self::THREE_DAYS,
            '1w', 'week', '1week', 'weekly' => self::WEEK,
            '2w', '2week', '2weeks' => self::TWO_WEEKS,
            '1m', 'month', '1month', 'monthly' => self::MONTH,
            '3m', '3month', '3months', 'quarterly' => self::THREE_MONTHS,
            '6m', '6month', '6months' => self::SIX_MONTHS,
            '1y', 'year', '1year', 'yearly', 'annual' => self::YEAR,
            '2y', '2year', '2years' => self::TWO_YEARS,
            '5y', '5year', '5years' => self::FIVE_YEARS,
            '10y', '10year', '10years' => self::TEN_YEARS,
            'max', 'maximum', 'all' => self::MAX,
            default => throw new \InvalidArgumentException("Invalid time period: {$period}"),
        };
    }

    public static function fromSeconds(int $seconds): ?self
    {
        foreach (self::cases() as $period) {
            if ($period->getSeconds() === $seconds) {
                return $period;
            }
        }
        return null;
    }

    public static function getSmallestPeriod(): self
    {
        return self::MINUTE;
    }

    public static function getLargestPeriod(): self
    {
        return self::MAX;
    }

    public function toArray(): array
    {
        return [
            'value' => $this->value,
            'name' => $this->name,
            'description' => $this->getDescription(),
            'seconds' => $this->getSeconds(),
            'minutes' => $this->getMinutes(),
            'hours' => $this->getHours(),
            'days' => $this->getDays(),
            'category' => $this->getCategory(),
            'is_intraday' => $this->isIntraday(),
            'is_daily' => $this->isDaily(),
            'is_weekly' => $this->isWeekly(),
            'is_monthly' => $this->isMonthly(),
            'is_yearly' => $this->isYearly(),
            'suggested_interval' => $this->getSuggestedInterval()->value,
            'max_data_points' => $this->getMaxDataPoints(),
            'use_cases' => $this->getUseCases(),
        ];
    }
}
