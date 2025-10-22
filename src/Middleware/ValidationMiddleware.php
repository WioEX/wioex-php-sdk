<?php

declare(strict_types=1);

namespace Wioex\SDK\Middleware;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Wioex\SDK\Enums\MiddlewareType;
use Wioex\SDK\Validation\SchemaValidator;
use Wioex\SDK\Validation\ValidationReport;
use Wioex\SDK\Exceptions\ValidationException;

class ValidationMiddleware extends AbstractMiddleware
{
    private array $validators = [];
    private array $pathMappings = [];

    public function __construct(array $config = [])
    {
        parent::__construct(MiddlewareType::VALIDATION, $config);
        $this->initializeDefaultValidators();
    }

    protected function getDefaultConfig(): array
    {
        return [
            'validate_responses' => true,
            'validate_requests' => false,
            'fail_on_validation_error' => false,
            'add_validation_headers' => true,
            'log_validation_errors' => true,
            'auto_detect_schemas' => true,
            'strict_mode' => false,
        ];
    }

    protected function processRequestCustom(RequestInterface $request, array $context = []): RequestInterface
    {
        if (!$this->getConfigValue('validate_requests', false)) {
            return $request;
        }

        // Request validation implementation could go here
        // For now, we focus on response validation
        return $request;
    }

    protected function processResponseCustom(ResponseInterface $response, RequestInterface $request, array $context = []): ResponseInterface
    {
        if (!$this->getConfigValue('validate_responses', true)) {
            return $response;
        }

        $validator = $this->getValidatorForRequest($request);
        if ($validator === null) {
            return $response;
        }

        $validationReport = $this->validateResponse($response, $validator);

        if ($this->getConfigValue('add_validation_headers', true)) {
            $response = $this->addValidationHeaders($response, $validationReport);
        }

        if ($validationReport->hasErrors() && $this->getConfigValue('fail_on_validation_error', false)) {
            throw new ValidationException('Response validation failed: ' . implode(', ', $validationReport->getFormattedErrors()));
        }

        return $response;
    }

    public function addValidator(string $path, SchemaValidator $validator): self
    {
        $this->validators[$path] = $validator;
        return $this;
    }

    public function addPathMapping(string $pathPattern, string $schemaName): self
    {
        $this->pathMappings[$pathPattern] = $schemaName;
        return $this;
    }

    public function setValidators(array $validators): self
    {
        $this->validators = $validators;
        return $this;
    }

    public function getValidators(): array
    {
        return $this->validators;
    }

    private function initializeDefaultValidators(): void
    {
        // Stock endpoints
        $this->addValidator('/v2/stocks/quote', SchemaValidator::stockQuoteSchema());
        $this->addValidator('/v2/stocks/timeline', SchemaValidator::timelineSchema());

        // News endpoints
        $this->addValidator('/v2/news/*', SchemaValidator::newsSchema());

        // Market endpoints
        $this->addValidator('/v2/markets/status', SchemaValidator::marketStatusSchema());

        // Path mappings for auto-detection
        $this->pathMappings = [
            '/v2/stocks/quote*' => 'stock_quote',
            '/v2/stocks/timeline*' => 'timeline',
            '/v2/news/*' => 'news',
            '/v2/markets/status*' => 'market_status',
        ];
    }

    private function getValidatorForRequest(RequestInterface $request): ?SchemaValidator
    {
        $path = $request->getUri()->getPath();

        // Direct path match
        if (isset($this->validators[$path])) {
            return $this->validators[$path];
        }

        // Pattern matching
        foreach ($this->validators as $pattern => $validator) {
            if ($this->matchesPattern($path, $pattern)) {
                return $validator;
            }
        }

        // Auto-detection
        if ($this->getConfigValue('auto_detect_schemas', true)) {
            return $this->autoDetectValidator($path);
        }

        return null;
    }

    private function autoDetectValidator(string $path): ?SchemaValidator
    {
        foreach ($this->pathMappings as $pattern => $schemaName) {
            if ($this->matchesPattern($path, $pattern)) {
                return $this->createValidatorByName($schemaName);
            }
        }

        return null;
    }

    private function createValidatorByName(string $schemaName): ?SchemaValidator
    {
        return match ($schemaName) {
            'stock_quote' => SchemaValidator::stockQuoteSchema(),
            'timeline' => SchemaValidator::timelineSchema(),
            'news' => SchemaValidator::newsSchema(),
            'market_status' => SchemaValidator::marketStatusSchema(),
            'error_response' => SchemaValidator::errorResponseSchema(),
            default => null,
        };
    }

    private function validateResponse(ResponseInterface $response, SchemaValidator $validator): ValidationReport
    {
        $body = (string) $response->getBody();
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            // Invalid JSON
            $validator = SchemaValidator::create()
                ->type('root', 'array', true, 'Response must be valid JSON');
            return $validator->validate([]);
        }

        if (!is_array($data)) {
            $data = ['root' => $data];
        }

        return $validator->validate($data);
    }

    private function addValidationHeaders(ResponseInterface $response, ValidationReport $validationReport): ResponseInterface
    {
        $headers = [
            'X-Validation-Result' => $validationReport->getResult()->value,
            'X-Validation-Errors-Count' => (string) $validationReport->getErrorsCount(),
        ];

        if ($validationReport->hasErrors()) {
            $headers['X-Validation-Errors'] = base64_encode(json_encode($validationReport->getFormattedErrors()));
        }

        foreach ($headers as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        return $response;
    }

    private function shouldSkipValidation(RequestInterface $request): bool
    {
        // Skip validation for certain endpoints
        $skipPaths = $this->getConfigValue('skip_paths', []);
        $path = $request->getUri()->getPath();

        foreach ($skipPaths as $skipPath) {
            if ($this->matchesPattern($path, $skipPath)) {
                return true;
            }
        }

        // Skip validation for certain status codes would go in response processing
        return false;
    }

    public function getValidationStatistics(): array
    {
        return [
            'validators_count' => count($this->validators),
            'path_mappings_count' => count($this->pathMappings),
            'config' => $this->config,
            'validators' => array_map(fn($v) => $v->getStatistics(), $this->validators),
        ];
    }

    public function toArray(): array
    {
        $baseArray = parent::toArray();
        $baseArray['validation_statistics'] = $this->getValidationStatistics();
        return $baseArray;
    }
}
