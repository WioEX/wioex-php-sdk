<?php

declare(strict_types=1);

namespace Wioex\SDK\Types;

/**
 * Database Type Safety Enums
 * Prevents runtime type errors by enforcing compile-time type checking
 */

/**
 * UUID Type - ensures valid UUID format
 */
readonly class UuidType
{
    public function __construct(private string $value)
    {
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value)) {
            throw new \InvalidArgumentException("Invalid UUID format: {$value}");
        }
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}

/**
 * API Key Type - enforces UUID format for API keys
 */
readonly class ApiKeyType
{
    private UuidType $uuid;

    public function __construct(string $apiKey)
    {
        $this->uuid = UuidType::fromString($apiKey);
    }

    public static function fromString(string $apiKey): self
    {
        return new self($apiKey);
    }

    public function toString(): string
    {
        return $this->uuid->toString();
    }

    public function getHash(): string
    {
        return substr(md5($this->uuid->toString()), 0, 16);
    }
}

/**
 * Member ID Type - enforces UUID format for member IDs  
 */
readonly class MemberIdType
{
    private UuidType $uuid;

    public function __construct(string $memberId)
    {
        $this->uuid = UuidType::fromString($memberId);
    }

    public static function fromString(string $memberId): self
    {
        return new self($memberId);
    }

    public function toString(): string
    {
        return $this->uuid->toString();
    }
}

/**
 * Active Status Enum - prevents boolean/integer confusion
 */
enum ActiveStatus: bool
{
    case ACTIVE = true;
    case INACTIVE = false;

    public static function fromInt(int $value): self
    {
        return match ($value) {
            1 => self::ACTIVE,
            0 => self::INACTIVE,
            default => throw new \InvalidArgumentException("Invalid active status: {$value}")
        };
    }

    public function toSqlValue(): string
    {
        return $this->value ? 'true' : 'false';
    }

    public function toBool(): bool
    {
        return $this->value;
    }
}

/**
 * Database Connection Status - for proper 503 vs 500 error handling
 */
enum ConnectionStatus: string
{
    case CONNECTED = 'connected';
    case DISCONNECTED = 'disconnected';
    case TIMEOUT = 'timeout';
    case AUTH_FAILED = 'auth_failed';

    public function shouldReturn503(): bool
    {
        return match ($this) {
            self::DISCONNECTED, self::TIMEOUT, self::AUTH_FAILED => true,
            self::CONNECTED => false
        };
    }
}

/**
 * Token Type Enum - prevents magic strings
 */
enum TokenType: string
{
    case STREAM_PRODUCTION = 'stream_production';
    case STREAM_DEMO = 'stream_demo';
    case API_ACCESS = 'api_access';

    public function getWebSocketUrl(): string
    {
        return match ($this) {
            self::STREAM_PRODUCTION => 'wss://stream.wioex.com/ws',
            self::STREAM_DEMO => 'wss://stream.wioex.com/ws',
            self::API_ACCESS => throw new \InvalidArgumentException('API access tokens do not have WebSocket URLs')
        };
    }

    public static function fromLiveFlow(int $liveFlow): self
    {
        return $liveFlow === 1 ? self::STREAM_PRODUCTION : self::STREAM_DEMO;
    }
}