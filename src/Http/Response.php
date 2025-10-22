<?php

declare(strict_types=1);

namespace Wioex\SDK\Http;

use ArrayAccess;
use Psr\Http\Message\ResponseInterface;
use Wioex\SDK\Transformers\TransformerPipeline;
use Wioex\SDK\Transformers\TransformerInterface;

/**
 * @implements ArrayAccess<string, mixed>
 */
class Response implements ArrayAccess
{
    private ResponseInterface $response;
    private ?array $decodedData = null;
    private ?array $transformedData = null;
    private ?TransformerPipeline $pipeline = null;

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

    public function getMetadata(): array
    {
        return [
            'status_code' => $this->status(),
            'headers' => $this->headers(),
            'has_transformations' => $this->hasTransformations(),
            'transformer_count' => $this->pipeline ? count($this->pipeline->getTransformers()) : 0,
            'data_count' => $this->count(),
            'response_size' => strlen($this->json()),
        ];
    }
}
