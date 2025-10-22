<?php

declare(strict_types=1);

namespace Wioex\SDK\Validation;

use Wioex\SDK\Enums\SchemaType;
use Wioex\SDK\Enums\ValidationResult;
use Wioex\SDK\Enums\Environment;
use Wioex\SDK\Logging\Logger;

class SchemaValidator
{
    private array $rules = [];
    private array $schemas = [];
    private bool $stopOnFirstError = false;
    private bool $enabled = true;
    private ?Logger $logger = null;
    private array $config;

    public function __construct(array $config = [], Logger $logger = null)
    {
        $this->config = array_merge($this->getDefaultConfig(), $config);
        $this->stopOnFirstError = $this->config['stop_on_first_error'] ?? false;
        $this->enabled = $this->config['enabled'] ?? true;
        $this->logger = $logger;
    }

    public static function create(array $config = []): self
    {
        return new self($config);
    }

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

    public function arrayStructure(string $field, array $structure, bool $required = false, string $message = ''): self
    {
        return $this->addRule(ValidationRule::arrayStructure($field, $structure, $required, $message));
    }

    public function custom(string $field, callable $validator, bool $required = false, string $message = ''): self
    {
        return $this->addRule(ValidationRule::custom($field, $validator, $required, $message));
    }

    public function defineSchema(string $name, array $rules): self
    {
        $this->schemas[$name] = $rules;
        return $this;
    }

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

    public function validateMultiple(array $datasets, string $schemaName = ''): array
    {
        $reports = [];

        foreach ($datasets as $index => $data) {
            $reports[$index] = $this->validate($data, $schemaName);
        }

        return $reports;
    }

    public function getRules(): array
    {
        return $this->rules;
    }

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

    // Predefined schema builders for common WioEX API responses
    public static function stockQuoteSchema(): self
    {
        return self::create()
            ->required('symbol')
            ->type('symbol', 'string', true)
            ->required('price')
            ->type('price', 'numeric', true)
            ->range('price', 0, PHP_FLOAT_MAX, true)
            ->type('change', 'numeric')
            ->type('change_percent', 'numeric')
            ->type('volume', 'integer')
            ->range('volume', 0, PHP_INT_MAX)
            ->type('market_cap', 'numeric')
            ->type('timestamp', 'string')
            ->date('timestamp', 'Y-m-d H:i:s');
    }

    public static function newsSchema(): self
    {
        return self::create()
            ->required('title')
            ->type('title', 'string', true)
            ->required('url')
            ->url('url', true)
            ->type('source', 'string')
            ->type('published_at', 'string')
            ->date('published_at', 'Y-m-d H:i:s')
            ->type('summary', 'string')
            ->arrayStructure('tags', ['string']);
    }

    public static function marketStatusSchema(): self
    {
        return self::create()
            ->required('is_open')
            ->type('is_open', 'boolean', true)
            ->type('market', 'string', true)
            ->enum('market', ['NYSE', 'NASDAQ', 'AMEX', 'OTC'], true)
            ->type('next_open', 'string')
            ->date('next_open', 'Y-m-d H:i:s')
            ->type('next_close', 'string')
            ->date('next_close', 'Y-m-d H:i:s');
    }

    public static function timelineSchema(): self
    {
        return self::create()
            ->required('symbol')
            ->type('symbol', 'string', true)
            ->required('data')
            ->type('data', 'array', true)
            ->arrayStructure('data', [
                'timestamp' => 'string',
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
                'message' => 'string',
                'code' => 'string',
                'type' => 'string'
            ]);
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
