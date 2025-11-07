<?php

declare(strict_types=1);

namespace Wioex\SDK\Async;

class BatchRequest
{
    private string $id;
    private string $method;
    private string $endpoint;
    private array $params;
    private array $options;
    private mixed $result = null;
    private ?\Throwable $error = null;
    private bool $cached = false;
    private int $retryCount = 0;
    private float $startTime;
    private float $endTime = 0;
    private int $responseCode = 0;
    private array $responseHeaders = [];

    public function __construct(string $id, string $method, string $endpoint, array $params = [], array $options = [])
    {
        $this->id = $id;
        $this->method = strtoupper($method);
        $this->endpoint = $endpoint;
        $this->params = $params;
        $this->options = $options;
        $this->startTime = microtime(true);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getEndpoint(): string
    {
        return $this->endpoint;
    }

    public function getParams(): array
    {
        return $this->params;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function getOption(string $key, mixed $default = null): mixed
    {
        return $this->options[$key] ?? $default;
    }

    public function getPriority(): int
    {
        return (int) ($this->options['priority'] ?? 1);
    }

    public function setResult(mixed $result): self
    {
        $this->result = $result;
        $this->endTime = microtime(true);
        return $this;
    }

    public function getResult(): mixed
    {
        return $this->result;
    }

    public function hasResult(): bool
    {
        return $this->result !== null;
    }

    public function setError(\Throwable $error): self
    {
        $this->error = $error;
        $this->endTime = microtime(true);
        return $this;
    }

    public function getError(): ?\Throwable
    {
        return $this->error;
    }

    public function hasError(): bool
    {
        return $this->error !== null;
    }

    public function setCached(bool $cached): self
    {
        $this->cached = $cached;
        return $this;
    }

    public function isCached(): bool
    {
        return $this->cached;
    }

    public function incrementRetryCount(): int
    {
        return ++$this->retryCount;
    }

    public function getRetryCount(): int
    {
        return $this->retryCount;
    }

    public function setResponseCode(int $code): self
    {
        $this->responseCode = $code;
        return $this;
    }

    public function getResponseCode(): int
    {
        return $this->responseCode;
    }

    public function setResponseHeaders(array $headers): self
    {
        $this->responseHeaders = $headers;
        return $this;
    }

    public function getResponseHeaders(): array
    {
        return $this->responseHeaders;
    }

    public function getExecutionTime(): float
    {
        if ($this->endTime === 0) {
            return microtime(true) - $this->startTime;
        }
        return $this->endTime - $this->startTime;
    }

    public function isSuccessful(): bool
    {
        return $this->hasResult() && !$this->hasError();
    }

    public function isFailed(): bool
    {
        return $this->hasError();
    }

    public function isPending(): bool
    {
        return !$this->hasResult() && !$this->hasError();
    }

    public function getStatus(): string
    {
        if ($this->hasError()) {
            return 'failed';
        }
        if ($this->hasResult()) {
            return 'completed';
        }
        return 'pending';
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'method' => $this->method,
            'endpoint' => $this->endpoint,
            'params' => $this->params,
            'options' => $this->options,
            'status' => $this->getStatus(),
            'cached' => $this->cached,
            'retry_count' => $this->retryCount,
            'execution_time' => $this->getExecutionTime(),
            'response_code' => $this->responseCode,
            'has_result' => $this->hasResult(),
            'has_error' => $this->hasError(),
            'error_message' => $this->error?->getMessage(),
            'priority' => $this->getPriority()
        ];
    }

    public function __toString(): string
    {
        $status = $this->getStatus();
        $executionTime = round($this->getExecutionTime() * 1000, 2);
        
        return sprintf(
            'BatchRequest[%s] %s %s - %s (%sms)',
            $this->id,
            $this->method,
            $this->endpoint,
            $status,
            $executionTime
        );
    }
}