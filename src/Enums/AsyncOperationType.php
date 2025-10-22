<?php

declare(strict_types=1);

namespace Wioex\SDK\Enums;

enum AsyncOperationType: string
{
    case HTTP_REQUEST = 'http_request';
    case BULK_OPERATION = 'bulk_operation';
    case BATCH_PROCESSING = 'batch_processing';
    case RETRY_OPERATION = 'retry_operation';
    case TIMEOUT_OPERATION = 'timeout_operation';
    case DELAY_OPERATION = 'delay_operation';
    case CACHE_OPERATION = 'cache_operation';
    case TRANSFORMATION = 'transformation';
    case WEBSOCKET_MESSAGE = 'websocket_message';

    public function getDescription(): string
    {
        return match ($this) {
            self::HTTP_REQUEST => 'HTTP API request operation',
            self::BULK_OPERATION => 'Bulk data processing operation',
            self::BATCH_PROCESSING => 'Batch processing with concurrency control',
            self::RETRY_OPERATION => 'Retry operation with exponential backoff',
            self::TIMEOUT_OPERATION => 'Operation with timeout constraint',
            self::DELAY_OPERATION => 'Delayed execution operation',
            self::CACHE_OPERATION => 'Cache read/write operation',
            self::TRANSFORMATION => 'Data transformation operation',
            self::WEBSOCKET_MESSAGE => 'WebSocket message processing',
        };
    }

    public function getCategory(): string
    {
        return match ($this) {
            self::HTTP_REQUEST => 'network',
            self::BULK_OPERATION, self::BATCH_PROCESSING => 'processing',
            self::RETRY_OPERATION, self::TIMEOUT_OPERATION => 'control',
            self::DELAY_OPERATION => 'timing',
            self::CACHE_OPERATION => 'storage',
            self::TRANSFORMATION => 'data',
            self::WEBSOCKET_MESSAGE => 'realtime',
        };
    }

    public function getDefaultTimeoutMs(): int
    {
        return match ($this) {
            self::HTTP_REQUEST => 30000,
            self::BULK_OPERATION => 120000,
            self::BATCH_PROCESSING => 300000,
            self::RETRY_OPERATION => 60000,
            self::TIMEOUT_OPERATION => 10000,
            self::DELAY_OPERATION => 0,
            self::CACHE_OPERATION => 5000,
            self::TRANSFORMATION => 15000,
            self::WEBSOCKET_MESSAGE => 5000,
        };
    }

    public function getDefaultRetryAttempts(): int
    {
        return match ($this) {
            self::HTTP_REQUEST => 3,
            self::BULK_OPERATION => 2,
            self::BATCH_PROCESSING => 1,
            self::RETRY_OPERATION => 5,
            self::TIMEOUT_OPERATION => 1,
            self::DELAY_OPERATION => 1,
            self::CACHE_OPERATION => 2,
            self::TRANSFORMATION => 2,
            self::WEBSOCKET_MESSAGE => 3,
        };
    }

    public function requiresNetworkAccess(): bool
    {
        return match ($this) {
            self::HTTP_REQUEST, self::WEBSOCKET_MESSAGE => true,
            default => false,
        };
    }

    public function isCpuIntensive(): bool
    {
        return match ($this) {
            self::BULK_OPERATION, self::BATCH_PROCESSING, self::TRANSFORMATION => true,
            default => false,
        };
    }

    public function getMetrics(): array
    {
        return [
            'type' => $this->value,
            'description' => $this->getDescription(),
            'category' => $this->getCategory(),
            'default_timeout_ms' => $this->getDefaultTimeoutMs(),
            'default_retry_attempts' => $this->getDefaultRetryAttempts(),
            'requires_network_access' => $this->requiresNetworkAccess(),
            'is_cpu_intensive' => $this->isCpuIntensive(),
        ];
    }

    public static function fromString(string $type): self
    {
        return match (strtolower(str_replace(['-', '_', ' '], '_', $type))) {
            'http_request', 'http', 'request' => self::HTTP_REQUEST,
            'bulk_operation', 'bulk' => self::BULK_OPERATION,
            'batch_processing', 'batch' => self::BATCH_PROCESSING,
            'retry_operation', 'retry' => self::RETRY_OPERATION,
            'timeout_operation', 'timeout' => self::TIMEOUT_OPERATION,
            'delay_operation', 'delay' => self::DELAY_OPERATION,
            'cache_operation', 'cache' => self::CACHE_OPERATION,
            'transformation', 'transform' => self::TRANSFORMATION,
            'websocket_message', 'websocket' => self::WEBSOCKET_MESSAGE,
            default => throw new \InvalidArgumentException("Invalid async operation type: {$type}"),
        };
    }

    public static function getAllCategories(): array
    {
        $categories = [];
        foreach (self::cases() as $case) {
            $categories[$case->getCategory()][] = $case;
        }
        return $categories;
    }

    public static function getByCategory(string $category): array
    {
        return array_filter(
            self::cases(),
            fn(self $type) => $type->getCategory() === $category
        );
    }
}
