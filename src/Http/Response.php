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
    private ?array $decodedData = null;
    private ?array $transformedData = null;
    private ?TransformerPipeline $pipeline = null;
    private ?SchemaValidator $validator = null;
    private ?ValidationReport $validationReport = null;

    public function __construct(ResponseInterface $response)
    {
        $this->response = $response;
    }

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

    public function filter(callable $callback): array
    {
        $data = $this->hasTransformations() ? $this->transform() : $this->data();
        return array_filter($data, $callback, ARRAY_FILTER_USE_BOTH);
    }

    public function map(callable $callback): array
    {
        $data = $this->hasTransformations() ? $this->transform() : $this->data();
        return array_map($callback, $data);
    }

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

    public function only(array $keys): array
    {
        $data = $this->hasTransformations() ? $this->transform() : $this->data();
        return array_intersect_key($data, array_flip($keys));
    }

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

    public function validateErrorResponse(): ValidationReport
    {
        return $this->validate(SchemaValidator::errorResponseSchema());
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

    public function getMetadata(): array
    {
        $metadata = [
            'status_code' => $this->status(),
            'headers' => $this->headers(),
            'has_transformations' => $this->hasTransformations(),
            'transformer_count' => $this->pipeline ? count($this->pipeline->getTransformers()) : 0,
            'data_count' => $this->count(),
            'response_size' => strlen($this->json()),
            'has_validator' => $this->hasValidator(),
            'has_validation_report' => $this->validationReport !== null,
            'is_valid' => $this->isValid(),
        ];

        if ($this->validationReport !== null) {
            $metadata['validation'] = $this->validationReport->getSummary();
        }

        return $metadata;
    }
}
