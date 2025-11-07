<?php

declare(strict_types=1);

namespace Wioex\SDK\Http;

use ArrayAccess;
use Psr\Http\Message\ResponseInterface;
use Wioex\SDK\Transformers\TransformerPipeline;
use Wioex\SDK\Transformers\TransformerInterface;
use Wioex\SDK\Validation\SchemaValidator;
use Wioex\SDK\Validation\ValidationReport;

/**
 * @implements ArrayAccess<string, mixed>
 */
class Response implements ArrayAccess
{
    private ResponseInterface $response;
    /** @var array<string, mixed>|null */
    private ?array $decodedData = null;
    /** @var array<string, mixed>|null */
    private ?array $transformedData = null;
    private ?TransformerPipeline $pipeline = null;
    private ?SchemaValidator $validator = null;
    private ?ValidationReport $validationReport = null;

    public function __construct(ResponseInterface $response)
    {
        $this->response = $response;
    }

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        if ($this->decodedData === null) {
            $body = (string) $this->response->getBody();
            /** @var mixed $decoded */
            $decoded = json_decode($body, true);
            $this->decodedData = is_array($decoded) ? $decoded : [];
        }

        return $this->decodedData;
    }

    public function json(): string
    {
        return (string) $this->response->getBody();
    }

    public function status(): int
    {
        return $this->response->getStatusCode();
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function headers(): array
    {
        return $this->response->getHeaders();
    }

    public function header(string $name): ?string
    {
        return $this->response->hasHeader($name)
            ? $this->response->getHeaderLine($name)
            : null;
    }

    public function successful(): bool
    {
        return $this->status() >= 200 && $this->status() < 300;
    }

    public function failed(): bool
    {
        return !$this->successful();
    }

    public function clientError(): bool
    {
        return $this->status() >= 400 && $this->status() < 500;
    }

    public function serverError(): bool
    {
        return $this->status() >= 500;
    }

    // ArrayAccess implementation
    public function offsetExists(mixed $offset): bool
    {
        return isset($this->data()[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->data()[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new \RuntimeException('Response data is read-only');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new \RuntimeException('Response data is read-only');
    }

    public function __get(string $name): mixed
    {
        return $this->data()[$name] ?? null;
    }

    public function __isset(string $name): bool
    {
        return isset($this->data()[$name]);
    }

    public function toArray(): array
    {
        return $this->data();
    }

    public function __toString(): string
    {
        return $this->json();
    }

    /**
     * @param TransformerPipeline|null $pipeline
     * @return array<string, mixed>
     */
    public function transform(TransformerPipeline $pipeline = null): array
    {
        if ($pipeline !== null) {
            $this->pipeline = $pipeline;
        }

        if ($this->pipeline === null) {
            return $this->data();
        }

        if ($this->transformedData === null) {
            $this->transformedData = $this->pipeline->transform($this->data());
        }

        return $this->transformedData;
    }

    public function withTransformer(TransformerInterface $transformer): self
    {
        if ($this->pipeline === null) {
            $this->pipeline = new TransformerPipeline();
        }

        $this->pipeline->add($transformer);
        $this->transformedData = null; // Reset transformed data

        return $this;
    }

    /**
     * @param array<int, TransformerInterface> $transformers
     * @return self
     */
    public function withTransformers(array $transformers): self
    {
        if ($this->pipeline === null) {
            $this->pipeline = new TransformerPipeline();
        }

        foreach ($transformers as $transformer) {
            if ($transformer instanceof TransformerInterface) {
                $this->pipeline->add($transformer);
            }
        }

        $this->transformedData = null; // Reset transformed data

        return $this;
    }

    public function withPipeline(TransformerPipeline $pipeline): self
    {
        $this->pipeline = $pipeline;
        $this->transformedData = null; // Reset transformed data

        return $this;
    }

    public function getPipeline(): ?TransformerPipeline
    {
        return $this->pipeline;
    }

    public function clearTransformations(): self
    {
        $this->pipeline = null;
        $this->transformedData = null;

        return $this;
    }

    public function hasTransformations(): bool
    {
        return $this->pipeline !== null && count($this->pipeline->getTransformers()) > 0;
    }

    public function getTransformedData(): ?array
    {
        return $this->transformedData;
    }

    public function collect(): ResponseCollection
    {
        return new ResponseCollection($this->hasTransformations() ? $this->transform() : $this->data());
    }

    /**
     * @param callable $callback
     * @return array<string, mixed>
     */
    public function filter(callable $callback): array
    {
        $data = $this->hasTransformations() ? $this->transform() : $this->data();
        return array_filter($data, $callback, ARRAY_FILTER_USE_BOTH);
    }

    /**
     * @param callable $callback
     * @return array<int, mixed>
     */
    public function map(callable $callback): array
    {
        $data = $this->hasTransformations() ? $this->transform() : $this->data();
        return array_map($callback, $data);
    }

    /**
     * @param string $key
     * @return array<int, mixed>
     */
    public function pluck(string $key): array
    {
        $data = $this->hasTransformations() ? $this->transform() : $this->data();
        $result = [];

        foreach ($data as $item) {
            if (is_array($item) && isset($item[$key])) {
                $result[] = $item[$key];
            }
        }

        return $result;
    }

    /**
     * @param array<int, string> $keys
     * @return array<string, mixed>
     */
    public function only(array $keys): array
    {
        $data = $this->hasTransformations() ? $this->transform() : $this->data();
        return array_intersect_key($data, array_flip($keys));
    }

    /**
     * @param array<int, string> $keys
     * @return array<string, mixed>
     */
    public function except(array $keys): array
    {
        $data = $this->hasTransformations() ? $this->transform() : $this->data();
        return array_diff_key($data, array_flip($keys));
    }

    public function get(string $key, $default = null)
    {
        $data = $this->hasTransformations() ? $this->transform() : $this->data();
        return $data[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        $data = $this->hasTransformations() ? $this->transform() : $this->data();
        return isset($data[$key]);
    }

    public function count(): int
    {
        $data = $this->hasTransformations() ? $this->transform() : $this->data();
        return count($data);
    }

    public function isEmpty(): bool
    {
        return $this->count() === 0;
    }

    public function isNotEmpty(): bool
    {
        return !$this->isEmpty();
    }

    public function validate(SchemaValidator $validator = null, string $schemaName = ''): ValidationReport
    {
        if ($validator !== null) {
            $this->validator = $validator;
        }

        if ($this->validator === null) {
            throw new \RuntimeException('No validator configured for response validation');
        }

        $data = $this->hasTransformations() ? $this->transform() : $this->data();
        $this->validationReport = $this->validator->validate($data, $schemaName);

        return $this->validationReport;
    }

    public function withValidator(SchemaValidator $validator): self
    {
        $this->validator = $validator;
        $this->validationReport = null; // Reset validation report
        return $this;
    }

    public function withValidation(string $schemaName = ''): self
    {
        if ($this->validator === null) {
            throw new \RuntimeException('No validator configured. Use withValidator() first.');
        }

        $this->validate($this->validator, $schemaName);
        return $this;
    }

    public function isValid(): bool
    {
        return $this->validationReport?->isValid() ?? true;
    }

    public function hasValidationErrors(): bool
    {
        return $this->validationReport?->hasErrors() ?? false;
    }

    public function getValidationReport(): ?ValidationReport
    {
        return $this->validationReport;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getValidationErrors(): array
    {
        return $this->validationReport?->getErrors() ?? [];
    }

    public function throwIfInvalid(string $message = 'Response validation failed'): self
    {
        $this->validationReport?->throwIfInvalid($message);
        return $this;
    }

    public function validateStockQuote(): ValidationReport
    {
        return $this->validate(SchemaValidator::stockQuoteSchema());
    }

    public function validateEnhancedStockQuote(): ValidationReport
    {
        return $this->validate(SchemaValidator::enhancedStockQuoteSchema());
    }

    public function validateNews(): ValidationReport
    {
        return $this->validate(SchemaValidator::newsSchema());
    }

    public function validateMarketStatus(): ValidationReport
    {
        return $this->validate(SchemaValidator::marketStatusSchema());
    }

    public function validateTimeline(): ValidationReport
    {
        return $this->validate(SchemaValidator::timelineSchema());
    }

    public function validateCurrency(): ValidationReport
    {
        return $this->validate(SchemaValidator::currencySchema());
    }

    public function validateErrorResponse(): ValidationReport
    {
        return $this->validate(SchemaValidator::errorResponseSchema());
    }

    public function validateUnifiedResponse(): ValidationReport
    {
        return $this->validate(SchemaValidator::unifiedResponseSchema());
    }

    public function hasValidator(): bool
    {
        return $this->validator !== null;
    }

    public function getValidator(): ?SchemaValidator
    {
        return $this->validator;
    }

    public function clearValidation(): self
    {
        $this->validator = null;
        $this->validationReport = null;
        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->data()['metadata'] ?? [];
    }

    /**
     * Get WioEX metadata from unified response format
     * 
     * @return array<string, mixed>|null
     */
    public function getWioexMetadata(): ?array
    {
        return $this->data()['metadata']['wioex'] ?? null;
    }

    /**
     * Get response metadata from unified format
     */
    public function getResponseMetadata(): ?array
    {
        return $this->data()['metadata']['response'] ?? null;
    }

    /**
     * Get data quality metadata from unified format
     * 
     * @return array<string, mixed>|null
     */
    public function getDataQuality(): ?array
    {
        return $this->data()['metadata']['data_quality'] ?? null;
    }

    /**
     * Get performance metadata from unified format
     */
    public function getPerformance(): ?array
    {
        return $this->data()['metadata']['performance'] ?? null;
    }

    /**
     * Get credits metadata from unified format
     */
    public function getCredits(): ?array
    {
        return $this->data()['metadata']['credits'] ?? null;
    }

    /**
     * Get cache metadata from unified format
     */
    public function getCache(): ?array
    {
        return $this->data()['metadata']['cache'] ?? null;
    }

    /**
     * Get request ID from unified format
     */
    public function getRequestId(): ?string
    {
        return $this->data()['metadata']['response']['request_id'] ?? null;
    }

    /**
     * Get response time in milliseconds from unified format
     */
    public function getResponseTime(): ?float
    {
        return $this->data()['metadata']['response']['response_time_ms'] ?? null;
    }


    /**
     * Get request metadata from unified format
     * 
     * @return array<string, mixed>|null
     */
    public function getRequestMetadata(): ?array
    {
        return $this->data()['metadata']['request'] ?? null;
    }

    /**
     * Check if response was from detailed mode
     */
    public function isDetailedMode(): bool
    {
        return $this->data()['metadata']['request']['detailed'] ?? false;
    }

    /**
     * Get symbols from request metadata
     * 
     * @return array<int, string>
     */
    public function getRequestedSymbols(): array
    {
        return $this->data()['metadata']['request']['symbols'] ?? [];
    }

    /**
     * Get symbol count from request
     */
    public function getSymbolCount(): int
    {
        return $this->data()['metadata']['request']['count'] ?? 0;
    }

    /**
     * Get data level (basic, detailed, etc.)
     */
    public function getDataLevel(): ?string
    {
        return $this->data()['data']['data_level'] ?? null;
    }

    /**
     * Get provider used for this response
     */
    public function getProviderUsed(): ?string
    {
        return $this->data()['data']['provider_used'] ?? 
               $this->data()['metadata']['data_quality']['provider_used'] ?? null;
    }

    /**
     * Get total symbols requested vs returned
     * 
     * @return array<string, mixed>
     */
    public function getSymbolStats(): array
    {
        $data = $this->getCoreData();
        return [
            'requested' => $data['total_symbols_requested'] ?? 0,
            'returned' => $data['total_symbols_returned'] ?? 0,
            'success_rate' => $this->calculateSuccessRate()
        ];
    }

    /**
     * Calculate success rate for symbol requests
     */
    private function calculateSuccessRate(): float
    {
        $stats = $this->getSymbolStats();
        if ($stats['requested'] === 0) {
            return 0.0;
        }
        return round(($stats['returned'] / $stats['requested']) * 100, 2);
    }

    /**
     * Check if all requested symbols were returned
     */
    public function isCompleteResponse(): bool
    {
        $stats = $this->getSymbolStats();
        return $stats['requested'] === $stats['returned'];
    }

    /**
     * Get market timezone from response
     */
    public function getMarketTimezone(): ?string
    {
        return $this->getCoreData()['market_timezone'] ?? 
               $this->data()['metadata']['data_quality']['market_timezone'] ?? null;
    }

    /**
     * Get last market close time
     */
    public function getLastMarketClose(): ?string
    {
        return $this->data()['metadata']['data_quality']['last_market_close_utc'] ?? null;
    }

    /**
     * Get next market open time
     */
    public function getNextMarketOpen(): ?string
    {
        return $this->data()['metadata']['data_quality']['next_market_open_utc'] ?? null;
    }

    /**
     * Check if response has enhanced data (pre/post market, etc.)
     */
    public function hasEnhancedData(): bool
    {
        $instruments = $this->getInstruments();
        if (empty($instruments)) {
            return false;
        }
        
        $firstInstrument = $instruments[0];
        return isset($firstInstrument['pre_market']) || 
               isset($firstInstrument['post_market']) ||
               isset($firstInstrument['company_info']);
    }

    /**
     * Get instruments with only basic price data
     * 
     * @return array<int, array<string, mixed>>
     */
    public function getBasicPriceData(): array
    {
        $instruments = $this->getInstruments();
        $basicData = [];
        
        foreach ($instruments as $instrument) {
            $basicData[] = [
                'symbol' => $instrument['symbol'],
                'name' => $instrument['name'] ?? '',
                'price' => $instrument['price']['current'] ?? 0,
                'change' => $instrument['change']['amount'] ?? 0,
                'change_percent' => $instrument['change']['percent'] ?? 0,
                'volume' => $instrument['volume']['current'] ?? 0
            ];
        }
        
        return $basicData;
    }

    /**
     * Get instruments with enhanced pre/post market data
     * 
     * @return array<int, array<string, mixed>>
     */
    public function getExtendedHoursData(): array
    {
        $instruments = $this->getInstruments();
        $extendedData = [];
        
        foreach ($instruments as $instrument) {
            if (isset($instrument['pre_market']) || isset($instrument['post_market'])) {
                $extendedData[] = [
                    'symbol' => $instrument['symbol'],
                    'regular_price' => $instrument['price']['current'] ?? 0,
                    'pre_market' => $instrument['pre_market'] ?? null,
                    'post_market' => $instrument['post_market'] ?? null,
                    'market_status' => $instrument['market_status']['session'] ?? 'unknown'
                ];
            }
        }
        
        return $extendedData;
    }

    /**
     * Get data provider information from unified format
     */
    public function getDataProvider(): ?string
    {
        return $this->data()['metadata']['data_quality']['provider_used'] ?? 
               $this->data()['data']['data_provider'] ?? null;
    }

    /**
     * Get core data from unified response format
     * 
     * @return array<string, mixed>
     */
    public function getCoreData(): array
    {
        $data = $this->data();
        
        // Check for unified format (has 'metadata' and 'data' keys)
        if (isset($data['metadata']) && isset($data['data'])) {
            return $data['data'];
        }
        
        // Legacy format - return the whole response
        return $data;
    }

    /**
     * Get instruments from unified stocks response format
     * Also supports legacy 'tickers' format for backward compatibility
     * 
     * @return array<int, array<string, mixed>>
     */
    public function getInstruments(): array
    {
        $data = $this->data();
        
        // Check for unified format first
        if (isset($data['data']['instruments'])) {
            return $data['data']['instruments'];
        }
        
        // Check for legacy format (tickers array)
        if (isset($data['tickers'])) {
            return $this->adaptLegacyTickersToInstruments($data['tickers']);
        }
        
        // Return empty array if neither format is found
        return [];
    }

    /**
     * Get currency exchange rates from unified currency response format
     * 
     * @return array<string, mixed>
     */
    public function getCurrencyRates(): array
    {
        return $this->data()['data']['exchange_rates'] ?? [];
    }

    /**
     * Get single currency conversion from unified currency response format
     */
    public function getCurrencyConversion(): ?array
    {
        return $this->data()['data']['conversion'] ?? null;
    }

    /**
     * Get currency chart data from unified currency response format
     * 
     * @return array<int, array<string, mixed>>
     */
    public function getCurrencyChartData(): array
    {
        return $this->data()['data']['chart_data'] ?? [];
    }

    /**
     * Get news articles from unified news response format
     * 
     * @return array<int, array<string, mixed>>
     */
    public function getNewsArticles(): array
    {
        return $this->data()['data']['articles'] ?? 
               $this->data()['data']['news'] ?? [];
    }

    /**
     * Get company analysis from unified analysis response format
     */
    public function getCompanyAnalysis(): ?array
    {
        return $this->data()['data']['analysis'] ?? 
               $this->data()['data']['company_analysis'] ?? null;
    }

    /**
     * Get timeline/chart data from unified timeline response format
     * 
     * @return array<int, array<string, mixed>>
     */
    public function getTimelineData(): array
    {
        return $this->data()['data']['timeline'] ?? 
               $this->data()['data']['chart'] ?? 
               $this->data()['data']['historical_data'] ?? [];
    }

    /**
     * Get chart points for visualization from timeline data
     * 
     * @return array<int, array<string, mixed>>
     */
    public function getChartPoints(): array
    {
        $timeline = $this->getTimelineData();
        
        if (empty($timeline)) {
            return [];
        }

        // Normalize chart points to consistent format
        $points = [];
        foreach ($timeline as $point) {
            $points[] = [
                'timestamp' => $point['timestamp'] ?? $point['time'] ?? null,
                'datetime' => $point['datetime'] ?? $point['date'] ?? null,
                'open' => $point['open'] ?? null,
                'high' => $point['high'] ?? null,
                'low' => $point['low'] ?? null,
                'close' => $point['close'] ?? $point['price'] ?? null,
                'volume' => $point['volume'] ?? null
            ];
        }

        return $points;
    }

    /**
     * Get exchange rate for specific currency pair
     */
    public function getExchangeRate(string $baseCurrency, string $targetCurrency): ?float
    {
        $rates = $this->getCurrencyRates();
        
        // Check if rates structure has nested rates
        if (isset($rates['rates'])) {
            $rates = $rates['rates'];
        }

        // Look for direct rate
        $pair = strtoupper($baseCurrency) . '/' . strtoupper($targetCurrency);
        if (isset($rates[$pair])) {
            return (float) $rates[$pair];
        }

        // Look for target currency in rates (assuming base is standard)
        $target = strtoupper($targetCurrency);
        if (isset($rates[$target])) {
            return (float) $rates[$target];
        }

        return null;
    }

    /**
     * Get total number of news articles
     */
    public function getNewsCount(): int
    {
        return count($this->getNewsArticles());
    }

    /**
     * Get latest news article
     */
    public function getLatestNews(): ?array
    {
        $articles = $this->getNewsArticles();
        return !empty($articles) ? $articles[0] : null;
    }

    /**
     * Get timeline data count
     */
    public function getTimelinePointsCount(): int
    {
        return count($this->getTimelineData());
    }

    /**
     * Get latest price from timeline data
     */
    public function getLatestPrice(): ?float
    {
        $timeline = $this->getTimelineData();
        
        if (empty($timeline)) {
            return null;
        }

        $latest = $timeline[0];
        return (float) ($latest['close'] ?? $latest['price'] ?? null);
    }

    /**
     * Check if response contains currency data
     */
    public function isCurrencyResponse(): bool
    {
        $data = $this->getCoreData();
        return isset($data['exchange_rates']) || 
               isset($data['conversion']) || 
               isset($data['chart_data']);
    }

    /**
     * Check if response contains news data
     */
    public function isNewsResponse(): bool
    {
        $data = $this->getCoreData();
        return isset($data['articles']) || 
               isset($data['news']) || 
               isset($data['analysis']) || 
               isset($data['company_analysis']);
    }

    /**
     * Check if response contains timeline data
     */
    public function isTimelineResponse(): bool
    {
        $data = $this->getCoreData();
        return isset($data['timeline']) || 
               isset($data['chart']) || 
               isset($data['historical_data']);
    }

    /**
     * Get response summary for debugging (enhanced)
     * 
     * @return array<string, mixed>
     */
    public function getSummary(): array
    {
        $summary = [
            'status_code' => $this->status(),
            'successful' => $this->successful(),
            'request_id' => $this->getRequestId(),
            'response_time_ms' => $this->getResponseTime(),
            'data_provider' => $this->getDataProvider(),
            'credits_consumed' => $this->getCredits()['consumed'] ?? null,
        ];

        // Add service-specific counts
        if ($this->isTimelineResponse()) {
            $summary['timeline_points'] = $this->getTimelinePointsCount();
            $summary['latest_price'] = $this->getLatestPrice();
        }

        if ($this->isNewsResponse()) {
            $summary['news_count'] = $this->getNewsCount();
        }

        if ($this->isCurrencyResponse()) {
            $rates = $this->getCurrencyRates();
            $summary['currency_pairs'] = is_array($rates) ? count($rates) : 0;
        }

        // Legacy support for stocks
        $instruments = $this->getInstruments();
        if (!empty($instruments)) {
            $summary['instruments_count'] = count($instruments);
            $summary['detailed_mode'] = $this->isDetailedMode();
        }

        return $summary;
    }

    // Backward Compatibility Methods
    
    /**
     * Check if response is in unified format
     */
    public function isUnifiedFormat(): bool
    {
        $data = $this->data();
        return isset($data['metadata']) && isset($data['data']);
    }

    /**
     * Check if response is in legacy format
     */
    public function isLegacyFormat(): bool
    {
        return !$this->isUnifiedFormat();
    }

    /**
     * Adapt legacy tickers array to unified instruments format
     * 
     * @param array<int, array<string, mixed>> $tickers
     * @return array<int, array<string, mixed>>
     */
    private function adaptLegacyTickersToInstruments(array $tickers): array
    {
        $instruments = [];
        
        foreach ($tickers as $ticker) {
            $instruments[] = [
                'symbol' => $ticker['ticker'] ?? $ticker['symbol'] ?? '',
                'name' => $ticker['name'] ?? $ticker['companyName'] ?? '',
                'type' => 'stock',
                'currency' => $ticker['currency'] ?? 'USD',
                'exchange' => $ticker['exchange'] ?? 'NASDAQ',
                'timezone' => 'America/New_York',
                'price' => [
                    'current' => $ticker['price'] ?? $ticker['market']['price'] ?? 0,
                    'open' => $ticker['open'] ?? $ticker['market']['open'] ?? 0,
                    'high' => $ticker['high'] ?? $ticker['market']['high'] ?? 0,
                    'low' => $ticker['low'] ?? $ticker['market']['low'] ?? 0,
                    'previous_close' => $ticker['previousClose'] ?? $ticker['market']['previousClose'] ?? 0
                ],
                'change' => [
                    'amount' => $ticker['change'] ?? $ticker['market']['change'] ?? 0,
                    'percent' => $ticker['changePercent'] ?? $ticker['market']['changePercent'] ?? 0
                ],
                'volume' => [
                    'current' => $ticker['volume'] ?? $ticker['market']['volume'] ?? 0
                ],
                'market_status' => [
                    'is_open' => $ticker['isMarketOpen'] ?? true,
                    'session' => 'regular',
                    'real_time' => true
                ],
                'timestamp' => date('c'), // Current timestamp as fallback
                'data_delay' => 'legacy_format'
            ];
        }
        
        return $instruments;
    }

    /**
     * Get legacy service name for backward compatibility
     */
    public function getLegacyService(): ?string
    {
        $data = $this->data();
        
        // Check unified format first
        if (isset($data['metadata']['wioex']['service'])) {
            return $data['metadata']['wioex']['service'];
        }
        
        // Legacy format
        return $data['wioex']['service'] ?? null;
    }

    /**
     * Get legacy timezone for backward compatibility
     */
    public function getLegacyTimezone(): ?string
    {
        $data = $this->data();
        
        // Check unified format first
        if (isset($data['metadata']['data_quality']['market_timezone'])) {
            return $data['metadata']['data_quality']['market_timezone'];
        }
        
        // Legacy format
        return $data['wioex']['timezone'] ?? $data['timezone'] ?? null;
    }

    /**
     * Get comprehensive ticker analysis data from response
     * 
     * @return array<string, mixed>|null Complete ticker analysis data structure
     */
    public function getTickerAnalysis(): ?array
    {
        $data = $this->getCoreData();
        return $data['analysis'][0] ?? null;
    }

    /**
     * Get analyst ratings and price targets from ticker analysis
     * 
     * @return array<string, mixed>|null Analyst ratings, price targets, and consensus data
     */
    public function getAnalystRatings(): ?array
    {
        $analysis = $this->getTickerAnalysis();
        return $analysis['analyst_ratings'] ?? null;
    }

    /**
     * Get earnings insights and call analysis from ticker analysis
     * 
     * @return array<string, mixed>|null Earnings analysis, quarterly results, and call highlights
     */
    public function getEarningsInsights(): ?array
    {
        $analysis = $this->getTickerAnalysis();
        return $analysis['earnings_insights'] ?? null;
    }

    /**
     * Get insider activity and transaction data from ticker analysis
     * 
     * @return array<string, mixed>|null Insider transactions, executive activity, and sentiment
     */
    public function getInsiderActivity(): ?array
    {
        $analysis = $this->getTickerAnalysis();
        return $analysis['insider_activity'] ?? null;
    }

    /**
     * Get news analysis and market sentiment from ticker analysis
     * 
     * @return array<string, mixed>|null News sentiment, themes, and key events
     */
    public function getNewsAnalysis(): ?array
    {
        $analysis = $this->getTickerAnalysis();
        return $analysis['news_analysis'] ?? null;
    }

    /**
     * Get options analysis and put/call ratios from ticker analysis
     * 
     * @return array<string, mixed>|null Options data, put/call ratios, and market implications
     */
    public function getOptionsAnalysis(): ?array
    {
        $analysis = $this->getTickerAnalysis();
        return $analysis['options_analysis'] ?? null;
    }

    /**
     * Get price movement analysis and technical insights from ticker analysis
     * 
     * @return array<string, mixed>|null Price movement explanations and technical analysis
     */
    public function getPriceMovement(): ?array
    {
        $analysis = $this->getTickerAnalysis();
        return $analysis['price_movement'] ?? null;
    }

    /**
     * Get financial metrics and valuation ratios from ticker analysis
     * 
     * @return array<string, mixed>|null Financial ratios, valuation metrics, and growth indicators
     */
    public function getFinancialMetrics(): ?array
    {
        $analysis = $this->getTickerAnalysis();
        return $analysis['financial_metrics'] ?? null;
    }

    /**
     * Get comprehensive overview and summary from ticker analysis
     * 
     * @return array<string, mixed>|null Market overview, key observations, and analysis summary
     */
    public function getAnalysisOverview(): ?array
    {
        $analysis = $this->getTickerAnalysis();
        return $analysis['overview'] ?? null;
    }

    /**
     * Check if response contains ticker analysis data
     * 
     * @return bool True if ticker analysis data is present
     */
    public function hasTickerAnalysis(): bool
    {
        return $this->getTickerAnalysis() !== null;
    }

    /**
     * Get ticker analysis symbol
     * 
     * @return string|null The analyzed stock symbol
     */
    public function getAnalysisSymbol(): ?string
    {
        $analysis = $this->getTickerAnalysis();
        return $analysis['symbol'] ?? null;
    }

    /**
     * Get ticker analysis timestamp
     * 
     * @return string|null Analysis generation timestamp
     */
    public function getAnalysisTimestamp(): ?string
    {
        $analysis = $this->getTickerAnalysis();
        return $analysis['timestamp'] ?? null;
    }

    /**
     * Validate ticker analysis response structure
     * 
     * @return ValidationReport Validation results for ticker analysis data
     */
    public function validateTickerAnalysisResponse(): ValidationReport
    {
        return $this->validate(SchemaValidator::tickerAnalysisSchema());
    }

    /**
     * Get analyst price target summary
     * 
     * Convenience method to quickly access analyst price targets and recommendations
     * 
     * @return array<string, mixed>|null Price target summary with high/low/average targets
     */
    public function getAnalystPriceTargets(): ?array
    {
        $ratings = $this->getAnalystRatings();
        if (!$ratings || !isset($ratings['summary'])) {
            return null;
        }

        $summary = $ratings['summary'];
        return [
            'current_price' => $this->extractPriceFromText($summary['price_target'] ?? ''),
            'summary' => $summary['price_target'] ?? null,
            'viewpoint' => $summary['viewpoint'] ?? null,
            'tldr' => $summary['tldr'] ?? null
        ];
    }

    /**
     * Get earnings performance summary
     * 
     * Convenience method to quickly access earnings highlights and outlook
     * 
     * @return array<string, mixed>|null Earnings summary with key insights
     */
    public function getEarningsPerformance(): ?array
    {
        $earnings = $this->getEarningsInsights();
        if (!$earnings || !isset($earnings['analysis'])) {
            return null;
        }

        $analysis = $earnings['analysis'];
        return [
            'tldr' => $analysis['tldr'] ?? null,
            'outlook' => $analysis['key_insights']['Outlook'] ?? null,
            'highlights' => $analysis['key_insights']['Performance Highlights'] ?? null,
            'fiscal_period' => $earnings['fiscal_period'] ?? null
        ];
    }

    /**
     * Get market sentiment summary
     * 
     * Convenience method to access overall market sentiment and news themes
     * 
     * @return array<string, mixed>|null Market sentiment analysis
     */
    public function getMarketSentiment(): ?array
    {
        $news = $this->getNewsAnalysis();
        $options = $this->getOptionsAnalysis();
        
        if (!$news && !$options) {
            return null;
        }

        return [
            'news_summary' => $news['summary'] ?? null,
            'news_themes' => $news['themes'] ?? [],
            'options_sentiment' => $options['key_takeaways']['tldr'] ?? null,
            'put_call_ratio' => $options['put_call_ratio']['pcr_volume'] ?? null
        ];
    }

    /**
     * Extract price from text (utility method for analyst targets)
     * 
     * @param string $text Text containing price information
     * @return float|null Extracted price value
     */
    private function extractPriceFromText(string $text): ?float
    {
        // Look for price patterns like $26, $16.50, etc.
        if (preg_match('/\$(\d+(?:\.\d{2})?)/', $text, $matches)) {
            return (float) $matches[1];
        }
        return null;
    }

    // ================================
    // SEARCH RESPONSE METHODS
    // ================================

    /**
     * Get search query from unified search response
     */
    public function getSearchQuery(): ?string
    {
        return $this->getCoreData()['query'] ?? null;
    }

    /**
     * Get total search results count
     */
    public function getSearchResultsCount(): int
    {
        return $this->getCoreData()['total_results'] ?? 0;
    }

    /**
     * Get search results (instruments)
     * 
     * @return array<int, array<string, mixed>>
     */
    public function getSearchResults(): array
    {
        return $this->getCoreData()['instruments'] ?? [];
    }

    /**
     * Get search provider used
     */
    public function getSearchProvider(): ?string
    {
        return $this->getCoreData()['search_provider'] ?? null;
    }

    /**
     * Get country filter used in search
     */
    public function getSearchCountry(): ?string
    {
        return $this->getCoreData()['country'] ?? null;
    }

    /**
     * Check if search returned any results
     */
    public function hasSearchResults(): bool
    {
        return $this->getSearchResultsCount() > 0;
    }

    /**
     * Get first search result (most relevant)
     * 
     * @return array<string, mixed>|null
     */
    public function getFirstSearchResult(): ?array
    {
        $results = $this->getSearchResults();
        return $results[0] ?? null;
    }

    /**
     * Check if this is a search response
     */
    public function isSearchResponse(): bool
    {
        $data = $this->getCoreData();
        return isset($data['query']) && isset($data['instruments']) && isset($data['total_results']);
    }

    /**
     * Validate search response structure
     */
    public function validateSearchResponse(): ValidationReport
    {
        return $this->validate(SchemaValidator::searchResponseSchema());
    }

    /**
     * Extract symbols from search results
     * 
     * @return array<int, string>
     */
    public function getSearchSymbols(): array
    {
        $symbols = [];
        foreach ($this->getSearchResults() as $result) {
            if (isset($result['symbol'])) {
                $symbols[] = $result['symbol'];
            }
        }
        return $symbols;
    }

    /**
     * Find search result by symbol
     * 
     * @return array<string, mixed>|null
     */
    public function findResultBySymbol(string $symbol): ?array
    {
        foreach ($this->getSearchResults() as $result) {
            if (isset($result['symbol']) && $result['symbol'] === strtoupper($symbol)) {
                return $result;
            }
        }
        return null;
    }


    /**
     * Wrapper for legacy format compatibility
     * 
     * @return array<string, mixed>
     */
    public function getLegacyCompatibleData(): array
    {
        $this->logLegacyFormatWarning(__METHOD__);
        
        if ($this->isUnifiedFormat()) {
            // Convert unified format to legacy-like structure for compatibility
            $coreData = $this->getCoreData();
            $metadata = $this->data()['metadata'] ?? [];
            
            return [
                'wioex' => $metadata['wioex'] ?? [],
                'data' => $coreData,
                'instruments' => $this->getInstruments(),
                'timezone' => $this->getMarketTimezone(),
                'service' => $metadata['wioex']['api_version'] ?? 'unknown'
            ];
        }
        
        return $this->data();
    }
}
