<?php

declare(strict_types=1);

namespace Wioex\SDK\Historical;

use Wioex\SDK\Enums\TimePeriod;
use Wioex\SDK\Http\Response;

class HistoricalDataHelper
{
    private array $config;

    public function __construct(array $config = [])
    {
        $this->config = array_merge($this->getDefaultConfig(), $config);
    }

    public static function create(array $config = []): self
    {
        return new self($config);
    }

    /**
     * Get date range for a time period from now
     */
    public function getDateRange(TimePeriod $period, \DateTime $endDate = null): array
    {
        $endDate = $endDate ?: new \DateTime('now', new \DateTimeZone($this->config['timezone']));

        if ($period === TimePeriod::MAX) {
            return [
                'from' => new \DateTime('1900-01-01', new \DateTimeZone($this->config['timezone'])),
                'to' => $endDate,
            ];
        }

        $startDate = clone $endDate;
        $interval = $period->getDateTimeInterval();
        $startDate->sub($interval);

        return [
            'from' => $startDate,
            'to' => $endDate,
        ];
    }

    /**
     * Get date range for specific periods
     */
    public function getTodayRange(\DateTime $date = null): array
    {
        $date = $date ?: new \DateTime('now', new \DateTimeZone($this->config['timezone']));

        return [
            'from' => (clone $date)->setTime(0, 0, 0),
            'to' => (clone $date)->setTime(23, 59, 59),
        ];
    }

    public function getYesterdayRange(\DateTime $date = null): array
    {
        $date = $date ?: new \DateTime('now', new \DateTimeZone($this->config['timezone']));
        $yesterday = (clone $date)->sub(new \DateInterval('P1D'));

        return [
            'from' => $yesterday->setTime(0, 0, 0),
            'to' => $yesterday->setTime(23, 59, 59),
        ];
    }

    public function getThisWeekRange(\DateTime $date = null): array
    {
        $date = $date ?: new \DateTime('now', new \DateTimeZone($this->config['timezone']));

        $startOfWeek = clone $date;
        $startOfWeek->modify('monday this week')->setTime(0, 0, 0);

        $endOfWeek = clone $startOfWeek;
        $endOfWeek->modify('+6 days')->setTime(23, 59, 59);

        return [
            'from' => $startOfWeek,
            'to' => $endOfWeek,
        ];
    }

    public function getLastWeekRange(\DateTime $date = null): array
    {
        $date = $date ?: new \DateTime('now', new \DateTimeZone($this->config['timezone']));

        $startOfLastWeek = clone $date;
        $startOfLastWeek->modify('monday last week')->setTime(0, 0, 0);

        $endOfLastWeek = clone $startOfLastWeek;
        $endOfLastWeek->modify('+6 days')->setTime(23, 59, 59);

        return [
            'from' => $startOfLastWeek,
            'to' => $endOfLastWeek,
        ];
    }

    public function getThisMonthRange(\DateTime $date = null): array
    {
        $date = $date ?: new \DateTime('now', new \DateTimeZone($this->config['timezone']));

        $startOfMonth = clone $date;
        $startOfMonth->modify('first day of this month')->setTime(0, 0, 0);

        $endOfMonth = clone $date;
        $endOfMonth->modify('last day of this month')->setTime(23, 59, 59);

        return [
            'from' => $startOfMonth,
            'to' => $endOfMonth,
        ];
    }

    public function getLastMonthRange(\DateTime $date = null): array
    {
        $date = $date ?: new \DateTime('now', new \DateTimeZone($this->config['timezone']));

        $startOfLastMonth = clone $date;
        $startOfLastMonth->modify('first day of last month')->setTime(0, 0, 0);

        $endOfLastMonth = clone $date;
        $endOfLastMonth->modify('last day of last month')->setTime(23, 59, 59);

        return [
            'from' => $startOfLastMonth,
            'to' => $endOfLastMonth,
        ];
    }

    public function getThisYearRange(\DateTime $date = null): array
    {
        $date = $date ?: new \DateTime('now', new \DateTimeZone($this->config['timezone']));

        $startOfYear = clone $date;
        $startOfYear->setDate((int) $date->format('Y'), 1, 1)->setTime(0, 0, 0);

        $endOfYear = clone $date;
        $endOfYear->setDate((int) $date->format('Y'), 12, 31)->setTime(23, 59, 59);

        return [
            'from' => $startOfYear,
            'to' => $endOfYear,
        ];
    }

    public function getLastYearRange(\DateTime $date = null): array
    {
        $date = $date ?: new \DateTime('now', new \DateTimeZone($this->config['timezone']));
        $lastYear = (int) $date->format('Y') - 1;

        $startOfLastYear = new \DateTime();
        $startOfLastYear->setDate($lastYear, 1, 1)->setTime(0, 0, 0);

        $endOfLastYear = new \DateTime();
        $endOfLastYear->setDate($lastYear, 12, 31)->setTime(23, 59, 59);

        return [
            'from' => $startOfLastYear,
            'to' => $endOfLastYear,
        ];
    }

    /**
     * Get quarter ranges
     */
    public function getQuarterRange(int $quarter, int $year = null): array
    {
        $year = $year ?: (int) date('Y');

        $quarters = [
            1 => ['01-01', '03-31'],
            2 => ['04-01', '06-30'],
            3 => ['07-01', '09-30'],
            4 => ['10-01', '12-31'],
        ];

        if (!isset($quarters[$quarter])) {
            throw new \InvalidArgumentException("Invalid quarter: {$quarter}. Must be 1-4.");
        }

        [$startMonth, $endMonth] = $quarters[$quarter];

        return [
            'from' => new \DateTime("{$year}-{$startMonth} 00:00:00", new \DateTimeZone($this->config['timezone'])),
            'to' => new \DateTime("{$year}-{$endMonth} 23:59:59", new \DateTimeZone($this->config['timezone'])),
        ];
    }

    public function getCurrentQuarter(\DateTime $date = null): int
    {
        $date = $date ?: new \DateTime();
        $month = (int) $date->format('n');

        return match (true) {
            $month >= 1 && $month <= 3 => 1,
            $month >= 4 && $month <= 6 => 2,
            $month >= 7 && $month <= 9 => 3,
            $month >= 10 && $month <= 12 => 4,
        };
    }

    /**
     * Format dates for API requests
     */
    public function formatDateForApi(\DateTime $date, string $format = null): string
    {
        $format = $format ?: $this->config['api_date_format'];
        return $date->format($format);
    }

    public function formatDateRangeForApi(array $dateRange, string $format = null): array
    {
        return [
            'from' => $this->formatDateForApi($dateRange['from'], $format),
            'to' => $this->formatDateForApi($dateRange['to'], $format),
        ];
    }

    /**
     * Parse API date responses
     */
    public function parseApiDate(string $dateString, string $format = null): \DateTime
    {
        $format = $format ?: $this->config['api_date_format'];
        $date = \DateTime::createFromFormat($format, $dateString, new \DateTimeZone($this->config['timezone']));

        if ($date === false) {
            throw new \InvalidArgumentException("Invalid date format: {$dateString}");
        }

        return $date;
    }

    /**
     * Get trading hours for different markets
     */
    public function getMarketHours(string $market = 'NYSE'): array
    {
        $marketHours = [
            'NYSE' => ['09:30', '16:00'],
            'NASDAQ' => ['09:30', '16:00'],
            'LSE' => ['08:00', '16:30'],
            'TSE' => ['09:00', '15:00'],
            'HKEX' => ['09:30', '16:00'],
            'SSE' => ['09:30', '15:00'],
            'CRYPTO' => ['00:00', '23:59'], // 24/7
        ];

        if (!isset($marketHours[$market])) {
            throw new \InvalidArgumentException("Unknown market: {$market}");
        }

        return $marketHours[$market];
    }

    public function isMarketOpen(string $market = 'NYSE', \DateTime $date = null): bool
    {
        $date = $date ?: new \DateTime('now', new \DateTimeZone($this->config['timezone']));

        // Check if it's a weekend
        if (in_array((int) $date->format('w'), [0, 6])) { // Sunday = 0, Saturday = 6
            return false;
        }

        // Check if it's a holiday (simplified check)
        if ($this->isMarketHoliday($market, $date)) {
            return false;
        }

        // Check trading hours
        [$openTime, $closeTime] = $this->getMarketHours($market);
        $currentTime = $date->format('H:i');

        return $currentTime >= $openTime && $currentTime <= $closeTime;
    }

    public function isMarketHoliday(string $market, \DateTime $date): bool
    {
        // Simplified holiday check - in real implementation, this would check against a comprehensive holiday calendar
        $holidays = [
            'NYSE' => [
                '01-01', // New Year's Day
                '07-04', // Independence Day
                '12-25', // Christmas Day
            ],
        ];

        $dateString = $date->format('m-d');
        return in_array($dateString, $holidays[$market] ?? []);
    }

    /**
     * Calculate period statistics
     */
    public function calculatePeriodReturns(array $data, string $priceField = 'close'): array
    {
        if (count($data) < 2) {
            return [];
        }

        $returns = [];
        for ($i = 1; $i < count($data); $i++) {
            $previousPrice = $data[$i - 1][$priceField] ?? 0;
            $currentPrice = $data[$i][$priceField] ?? 0;

            if ($previousPrice > 0) {
                $returns[] = ($currentPrice - $previousPrice) / $previousPrice;
            }
        }

        return $returns;
    }

    public function calculateVolatility(array $returns): float
    {
        if (empty($returns)) {
            return 0.0;
        }

        $mean = array_sum($returns) / count($returns);
        $variance = array_sum(array_map(fn($r) => pow($r - $mean, 2), $returns)) / count($returns);

        return sqrt($variance);
    }

    public function calculateSharpeRatio(array $returns, float $riskFreeRate = 0.02): float
    {
        if (empty($returns)) {
            return 0.0;
        }

        $averageReturn = array_sum($returns) / count($returns);
        $volatility = $this->calculateVolatility($returns);

        if ($volatility == 0) {
            return 0.0;
        }

        return ($averageReturn - $riskFreeRate) / $volatility;
    }

    /**
     * Data aggregation utilities
     */
    public function aggregateToInterval(array $data, TimePeriod $interval, string $timestampField = 'timestamp'): array
    {
        if (empty($data)) {
            return [];
        }

        $intervalSeconds = $interval->getSeconds();
        $aggregated = [];

        foreach ($data as $point) {
            $timestamp = is_string($point[$timestampField])
                ? strtotime($point[$timestampField])
                : $point[$timestampField];

            $bucketTime = floor($timestamp / $intervalSeconds) * $intervalSeconds;

            if (!isset($aggregated[$bucketTime])) {
                $aggregated[$bucketTime] = [
                    'timestamp' => $bucketTime,
                    'open' => $point['open'] ?? $point['price'] ?? 0,
                    'high' => $point['high'] ?? $point['price'] ?? 0,
                    'low' => $point['low'] ?? $point['price'] ?? 0,
                    'close' => $point['close'] ?? $point['price'] ?? 0,
                    'volume' => $point['volume'] ?? 0,
                    'count' => 1,
                ];
            } else {
                $bucket = &$aggregated[$bucketTime];
                $bucket['high'] = max($bucket['high'], $point['high'] ?? $point['price'] ?? 0);
                $bucket['low'] = min($bucket['low'], $point['low'] ?? $point['price'] ?? 0);
                $bucket['close'] = $point['close'] ?? $point['price'] ?? 0;
                $bucket['volume'] += $point['volume'] ?? 0;
                $bucket['count']++;
            }
        }

        return array_values($aggregated);
    }

    public function fillGaps(array $data, TimePeriod $interval, string $timestampField = 'timestamp'): array
    {
        if (count($data) < 2) {
            return $data;
        }

        $filled = [];
        $intervalSeconds = $interval->getSeconds();

        for ($i = 0; $i < count($data) - 1; $i++) {
            $filled[] = $data[$i];

            $currentTime = is_string($data[$i][$timestampField])
                ? strtotime($data[$i][$timestampField])
                : $data[$i][$timestampField];

            $nextTime = is_string($data[$i + 1][$timestampField])
                ? strtotime($data[$i + 1][$timestampField])
                : $data[$i + 1][$timestampField];

            // Fill gaps larger than one interval
            $gap = $nextTime - $currentTime;
            if ($gap > $intervalSeconds * 1.5) {
                $fillTime = $currentTime + $intervalSeconds;
                while ($fillTime < $nextTime) {
                    $filled[] = [
                        $timestampField => $fillTime,
                        'open' => $data[$i]['close'] ?? 0,
                        'high' => $data[$i]['close'] ?? 0,
                        'low' => $data[$i]['close'] ?? 0,
                        'close' => $data[$i]['close'] ?? 0,
                        'volume' => 0,
                        'filled' => true,
                    ];
                    $fillTime += $intervalSeconds;
                }
            }
        }

        $filled[] = $data[count($data) - 1];

        return $filled;
    }

    /**
     * Response helper methods
     */
    public function extractTimelineData(Response $response): array
    {
        $data = $response->data();

        // Handle different response structures
        if (isset($data['timeline'])) {
            return $data['timeline'];
        }

        if (isset($data['data'])) {
            return $data['data'];
        }

        if (isset($data['results'])) {
            return $data['results'];
        }

        return $data;
    }

    public function sortByTimestamp(array $data, string $timestampField = 'timestamp', bool $ascending = true): array
    {
        usort($data, function ($a, $b) use ($timestampField, $ascending) {
            $timeA = is_string($a[$timestampField]) ? strtotime($a[$timestampField]) : $a[$timestampField];
            $timeB = is_string($b[$timestampField]) ? strtotime($b[$timestampField]) : $b[$timestampField];

            return $ascending ? $timeA <=> $timeB : $timeB <=> $timeA;
        });

        return $data;
    }

    public function getDataForPeriod(array $data, TimePeriod $period, \DateTime $endDate = null): array
    {
        $dateRange = $this->getDateRange($period, $endDate);
        $fromTimestamp = $dateRange['from']->getTimestamp();
        $toTimestamp = $dateRange['to']->getTimestamp();

        return array_filter($data, function ($point) use ($fromTimestamp, $toTimestamp) {
            $timestamp = is_string($point['timestamp'])
                ? strtotime($point['timestamp'])
                : $point['timestamp'];

            return $timestamp >= $fromTimestamp && $timestamp <= $toTimestamp;
        });
    }

    private function getDefaultConfig(): array
    {
        return [
            'timezone' => 'America/New_York', // NYSE timezone
            'api_date_format' => 'Y-m-d H:i:s',
            'default_market' => 'NYSE',
        ];
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function setConfig(array $config): self
    {
        $this->config = array_merge($this->config, $config);
        return $this;
    }
}
