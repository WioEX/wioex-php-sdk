<?php

declare(strict_types=1);

namespace Wioex\SDK\Validation;

use Wioex\SDK\Enums\SchemaType;
use Wioex\SDK\Enums\ValidationResult;
use Wioex\SDK\Enums\Environment;
use Wioex\SDK\Logging\Logger;

class SchemaValidator
{
    /** @var array<int, ValidationRule> */
    private array $rules = [];
    /** @var array<string, array<int, ValidationRule>> */
    private array $schemas = [];
    private bool $stopOnFirstError = false;
    private bool $enabled = true;
    private ?Logger $logger = null;
    /** @var array<string, mixed> */
    private array $config;

    /**
     * @param array<string, mixed> $config
     * @param Logger|null $logger
     */
    public function __construct(array $config = [], Logger $logger = null)
    {
        $this->config = array_merge($this->getDefaultConfig(), $config);
        $this->stopOnFirstError = $this->config['stop_on_first_error'] ?? false;
        $this->enabled = $this->config['enabled'] ?? true;
        $this->logger = $logger;
    }

    /**
     * @param array<string, mixed> $config
     * @return self
     */
    public static function create(array $config = []): self
    {
        return new self($config);
    }

    /**
     * @param Environment $environment
     * @param array<string, mixed> $config
     * @return self
     */
    public static function forEnvironment(Environment $environment, array $config = []): self
    {
        $enabled = $environment->shouldEnableValidation();
        $defaultConfig = [
            'enabled' => $enabled,
            'stop_on_first_error' => $environment->isProduction(),
            'log_validation_errors' => $environment->isDevelopment(),
            'strict_mode' => $environment->isProduction(),
        ];

        return new self(array_merge($defaultConfig, $config));
    }

    public function addRule(ValidationRule $rule): self
    {
        $this->rules[] = $rule;
        return $this;
    }

    /**
     * @param array<int, ValidationRule> $rules
     * @return self
     */
    public function addRules(array $rules): self
    {
        foreach ($rules as $rule) {
            if ($rule instanceof ValidationRule) {
                $this->addRule($rule);
            }
        }
        return $this;
    }

    public function required(string $field, string $message = ''): self
    {
        return $this->addRule(ValidationRule::required($field, $message));
    }

    public function type(string $field, string $expectedType, bool $required = false, string $message = ''): self
    {
        return $this->addRule(ValidationRule::type($field, $expectedType, $required, $message));
    }

    public function range(string $field, float $min, float $max, bool $required = false, string $message = ''): self
    {
        return $this->addRule(ValidationRule::range($field, $min, $max, $required, $message));
    }

    /**
     * @param string $field
     * @param array<int, mixed> $allowedValues
     * @param bool $required
     * @param string $message
     * @return self
     */
    public function enum(string $field, array $allowedValues, bool $required = false, string $message = ''): self
    {
        return $this->addRule(ValidationRule::enum($field, $allowedValues, $required, $message));
    }

    public function regex(string $field, string $pattern, bool $required = false, string $message = ''): self
    {
        return $this->addRule(ValidationRule::regex($field, $pattern, $required, $message));
    }

    public function email(string $field, bool $required = false, string $message = ''): self
    {
        return $this->addRule(ValidationRule::email($field, $required, $message));
    }

    public function url(string $field, bool $required = false, string $message = ''): self
    {
        return $this->addRule(ValidationRule::url($field, $required, $message));
    }

    public function date(string $field, string $format = 'Y-m-d', bool $required = false, string $message = ''): self
    {
        return $this->addRule(ValidationRule::date($field, $format, $required, $message));
    }

    public function numeric(string $field, bool $required = false, string $message = ''): self
    {
        return $this->addRule(ValidationRule::numeric($field, $required, $message));
    }

    /**
     * @param string $field
     * @param array<string, string> $structure
     * @param bool $required
     * @param string $message
     * @return self
     */
    public function arrayStructure(string $field, array $structure, bool $required = false, string $message = ''): self
    {
        return $this->addRule(ValidationRule::arrayStructure($field, $structure, $required, $message));
    }

    public function custom(string $field, callable $validator, bool $required = false, string $message = ''): self
    {
        return $this->addRule(ValidationRule::custom($field, $validator, $required, $message));
    }

    /**
     * @param string $name
     * @param array<int, ValidationRule> $rules
     * @return self
     */
    public function defineSchema(string $name, array $rules): self
    {
        $this->schemas[$name] = $rules;
        return $this;
    }

    /**
     * @param array<string, mixed> $data
     * @param string $schemaName
     * @return ValidationReport
     */
    public function validate(array $data, string $schemaName = ''): ValidationReport
    {
        if (!$this->enabled) {
            return new ValidationReport(ValidationResult::SKIPPED, [], $data);
        }

        $startTime = microtime(true);
        $rulesToValidate = $this->getRulesToValidate($schemaName);
        $errors = [];
        $results = [];

        foreach ($rulesToValidate as $rule) {
            $result = $rule->validate($data);
            $results[] = $result;

            if ($result->isInvalid() || $result->isError()) {
                $errors[] = [
                    'field' => $rule->getField(),
                    'message' => $rule->getMessage(),
                    'type' => $rule->getType()->value,
                    'result' => $result,
                    'rule' => $rule->getRule(),
                ];

                if ($this->stopOnFirstError) {
                    break;
                }
            }
        }

        $overallResult = ValidationResult::combine($results);
        $duration = (microtime(true) - $startTime) * 1000; // milliseconds

        $report = new ValidationReport($overallResult, $errors, $data, [
            'schema_name' => $schemaName,
            'rules_count' => count($rulesToValidate),
            'validation_duration_ms' => $duration,
            'stopped_on_first_error' => $this->stopOnFirstError,
        ]);

        $this->logValidationResult($report);

        return $report;
    }

    public function validateField(string $field, mixed $value, ValidationRule $rule): ValidationResult
    {
        if (!$this->enabled) {
            return ValidationResult::SKIPPED;
        }

        return $rule->validate([$field => $value]);
    }

    /**
     * @param array<int, array<string, mixed>> $datasets
     * @param string $schemaName
     * @return array<int, ValidationReport>
     */
    public function validateMultiple(array $datasets, string $schemaName = ''): array
    {
        $reports = [];

        foreach ($datasets as $index => $data) {
            $reports[$index] = $this->validate($data, $schemaName);
        }

        return $reports;
    }

    /**
     * @return array<int, ValidationRule>
     */
    public function getRules(): array
    {
        return $this->rules;
    }

    /**
     * @return array<string, array<int, ValidationRule>>
     */
    public function getSchemas(): array
    {
        return $this->schemas;
    }

    public function clearRules(): self
    {
        $this->rules = [];
        return $this;
    }

    public function clearSchemas(): self
    {
        $this->schemas = [];
        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function enable(): self
    {
        $this->enabled = true;
        return $this;
    }

    public function disable(): self
    {
        $this->enabled = false;
        return $this;
    }

    public function setStopOnFirstError(bool $stop): self
    {
        $this->stopOnFirstError = $stop;
        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getStatistics(): array
    {
        $rulesByType = [];
        foreach ($this->rules as $rule) {
            $type = $rule->getType()->value;
            $rulesByType[$type] = ($rulesByType[$type] ?? 0) + 1;
        }

        return [
            'enabled' => $this->enabled,
            'total_rules' => count($this->rules),
            'total_schemas' => count($this->schemas),
            'rules_by_type' => $rulesByType,
            'stop_on_first_error' => $this->stopOnFirstError,
            'config' => $this->config,
        ];
    }

    // Predefined schema builders for WioEX API Unified ResponseTemplate format
    public static function unifiedResponseSchema(): self
    {
        return self::create()
            ->required('metadata')
            ->type('metadata', 'array', true)
            ->required('data')
            ->type('data', 'array', true)
            ->arrayStructure('metadata', [
                'wioex' => 'array',
                'response' => 'array', 
                'request' => 'array',
                'credits' => 'array',
                'data_quality' => 'array',
                'cache' => 'array',
                'performance' => 'array',
                'security' => 'array'
            ]);
    }

    public static function stockQuoteSchema(): self
    {
        return self::unifiedResponseSchema()
            ->arrayStructure('data', [
                'total_symbols_requested' => 'integer',
                'total_symbols_returned' => 'integer', 
                'market_timezone' => 'string',
                'data_provider' => 'string',
                'instruments' => 'array'
            ])
            ->arrayStructure('data.instruments.*', [
                'symbol' => 'string',
                'name' => 'string',
                'type' => 'string',
                'currency' => 'string',
                'exchange' => 'string',
                'timezone' => 'string',
                'price' => 'array',
                'change' => 'array',
                'volume' => 'array',
                'market_status' => 'array',
                'timestamp' => 'string'
            ]);
    }

    public static function newsSchema(): self
    {
        return self::unifiedResponseSchema()
            ->arrayStructure('data', [
                'total_articles' => 'integer',
                'source' => 'string',
                'market_timezone' => 'string',
                'articles' => 'array'
            ])
            ->arrayStructure('data.articles.*', [
                'title' => 'string',
                'url' => 'string',
                'source' => 'string',
                'published_at' => 'string',
                'summary' => 'string',
                'sentiment' => 'string',
                'tags' => 'array'
            ]);
    }

    public static function marketStatusSchema(): self
    {
        return self::unifiedResponseSchema()
            ->arrayStructure('data', [
                'market_status' => 'string',
                'is_open' => 'boolean',
                'market_timezone' => 'string',
                'current_time' => 'string',
                'next_open' => 'string',
                'next_close' => 'string',
                'session_type' => 'string',
                'trading_days' => 'array'
            ]);
    }

    public static function timelineSchema(): self
    {
        return self::unifiedResponseSchema()
            ->arrayStructure('data', [
                'symbol' => 'string',
                'company_name' => 'string',
                'currency' => 'string',
                'exchange' => 'string',
                'exchange_timezone' => 'string',
                'market_status' => 'string',
                'data_source' => 'string',
                'provider_used' => 'string',
                'timeline' => 'array'
            ])
            ->arrayStructure('data.timeline.*', [
                'datetime' => 'string',
                'open' => 'numeric',
                'high' => 'numeric', 
                'low' => 'numeric',
                'close' => 'numeric',
                'volume' => 'integer'
            ]);
    }

    public static function errorResponseSchema(): self
    {
        return self::create()
            ->required('error')
            ->type('error', 'array', true)
            ->arrayStructure('error', [
                'code' => 'string',
                'title' => 'string',
                'message' => 'string',
                'error_code' => 'integer',
                'category' => 'string',
                'suggestions' => 'array',
                'timestamp' => 'string',
                'request_id' => 'string',
                'support_reference' => 'string',
                'context' => 'array'
            ]);
    }

    public static function enhancedStockQuoteSchema(): self
    {
        return self::unifiedResponseSchema()
            ->arrayStructure('data', [
                'total_symbols_requested' => 'integer',
                'total_symbols_returned' => 'integer',
                'market_timezone' => 'string',
                'data_provider' => 'string',
                'data_level' => 'string',
                'instruments' => 'array'
            ])
            ->arrayStructure('data.instruments.*', [
                'symbol' => 'string',
                'name' => 'string',
                'type' => 'string',
                'currency' => 'string',
                'exchange' => 'string',
                'timezone' => 'string',
                'price' => 'array',
                'change' => 'array',
                'volume' => 'array',
                'market_cap' => 'array',
                'pre_market' => 'array',
                'post_market' => 'array',
                'overnight_market' => 'array',
                'market_status' => 'array',
                'company_info' => 'array',
                'timestamp' => 'string',
                'data_delay' => 'string',
                'data_source' => 'string'
            ]);
    }

    public static function currencySchema(): self
    {
        return self::unifiedResponseSchema()
            ->arrayStructure('data', [
                'base_currency' => 'string',
                'update_time' => 'string',
                'market_timezone' => 'string',
                'total_rates' => 'integer',
                'rates' => 'array'
            ])
            ->arrayStructure('data.rates.*', [
                'currency' => 'string',
                'rate' => 'numeric',
                'change' => 'numeric',
                'change_percent' => 'numeric'
            ]);
    }

    /**
     * Create validation schema for ticker analysis responses
     * 
     * Basic validation for ticker analysis data structure.
     * 
     * @return self Configured validator for ticker analysis responses
     */
    public static function tickerAnalysisSchema(): self
    {
        $validator = new self();
        
        // Basic response structure validation
        $validator
            ->required('status')
            ->type('status', 'string', true)
            ->required('success')
            ->type('success', 'boolean', true)
            ->required('data')
            ->type('data', 'array', true);
            
        return $validator;
    }

    private function getRulesToValidate(string $schemaName): array
    {
        if ($schemaName !== '' && isset($this->schemas[$schemaName])) {
            return $this->schemas[$schemaName];
        }

        return $this->rules;
    }

    private function logValidationResult(ValidationReport $report): void
    {
        if ($this->logger === null || !$this->config['log_validation_errors']) {
            return;
        }

        if ($report->isValid()) {
            $this->logger->debug('Validation passed', [
                'schema' => $report->getMetadata()['schema_name'] ?? 'default',
                'rules_count' => $report->getMetadata()['rules_count'] ?? 0,
                'duration_ms' => $report->getMetadata()['validation_duration_ms'] ?? 0,
            ]);
        } else {
            $this->logger->warning('Validation failed', [
                'schema' => $report->getMetadata()['schema_name'] ?? 'default',
                'errors_count' => count($report->getErrors()),
                'errors' => $report->getErrors(),
                'duration_ms' => $report->getMetadata()['validation_duration_ms'] ?? 0,
            ]);
        }
    }

    private function getDefaultConfig(): array
    {
        return [
            'enabled' => true,
            'stop_on_first_error' => false,
            'log_validation_errors' => true,
            'strict_mode' => false,
        ];
    }
}
