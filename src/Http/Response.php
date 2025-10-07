<?php

declare(strict_types=1);

namespace Wioex\SDK\Http;

use ArrayAccess;
use Psr\Http\Message\ResponseInterface;

class Response implements ArrayAccess
{
    private ResponseInterface $response;
    private ?array $decodedData = null;

    public function __construct(ResponseInterface $response)
    {
        $this->response = $response;
    }

    public function data(): array
    {
        if ($this->decodedData === null) {
            $body = (string) $this->response->getBody();
            $this->decodedData = json_decode($body, true) ?? [];
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
}
