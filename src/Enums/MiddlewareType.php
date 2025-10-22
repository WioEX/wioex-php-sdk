<?php

declare(strict_types=1);

namespace Wioex\SDK\Enums;

enum MiddlewareType: string
{
    case REQUEST = 'request';
    case RESPONSE = 'response';
    case BOTH = 'both';
    case ERROR = 'error';
    case AUTHENTICATION = 'authentication';
    case RATE_LIMITING = 'rate_limiting';
    case CACHING = 'caching';
    case LOGGING = 'logging';
    case MONITORING = 'monitoring';
    case VALIDATION = 'validation';
    case TRANSFORMATION = 'transformation';
    case SECURITY = 'security';
    case COMPRESSION = 'compression';
    case RETRY = 'retry';
    case TIMEOUT = 'timeout';

    public function getDescription(): string
    {
        return match ($this) {
            self::REQUEST => 'Processes outgoing HTTP requests',
            self::RESPONSE => 'Processes incoming HTTP responses',
            self::BOTH => 'Processes both requests and responses',
            self::ERROR => 'Handles errors and exceptions',
            self::AUTHENTICATION => 'Manages authentication and authorization',
            self::RATE_LIMITING => 'Controls request rate limiting',
            self::CACHING => 'Handles response caching',
            self::LOGGING => 'Logs requests and responses',
            self::MONITORING => 'Monitors performance and metrics',
            self::VALIDATION => 'Validates requests and responses',
            self::TRANSFORMATION => 'Transforms data structures',
            self::SECURITY => 'Applies security measures',
            self::COMPRESSION => 'Handles data compression',
            self::RETRY => 'Manages request retries',
            self::TIMEOUT => 'Manages request timeouts',
        };
    }

    public function getExecutionPhase(): string
    {
        return match ($this) {
            self::REQUEST, self::AUTHENTICATION, self::RATE_LIMITING, self::SECURITY, self::TIMEOUT => 'before_request',
            self::RESPONSE, self::CACHING, self::VALIDATION, self::TRANSFORMATION => 'after_response',
            self::BOTH, self::LOGGING, self::MONITORING => 'both_phases',
            self::ERROR => 'on_error',
            self::COMPRESSION => 'both_phases',
            self::RETRY => 'on_error',
        };
    }

    public function getPriority(): int
    {
        return match ($this) {
            self::AUTHENTICATION => 100,
            self::SECURITY => 90,
            self::RATE_LIMITING => 80,
            self::TIMEOUT => 70,
            self::REQUEST => 60,
            self::COMPRESSION => 50,
            self::CACHING => 40,
            self::MONITORING => 30,
            self::LOGGING => 20,
            self::VALIDATION => 15,
            self::TRANSFORMATION => 10,
            self::RESPONSE => 5,
            self::BOTH => 0,
            self::ERROR => -10,
            self::RETRY => -20,
        };
    }

    public function getPerformanceImpact(): string
    {
        return match ($this) {
            self::AUTHENTICATION, self::SECURITY => 'medium',
            self::RATE_LIMITING, self::CACHING => 'low',
            self::LOGGING, self::MONITORING => 'low',
            self::VALIDATION => 'medium',
            self::TRANSFORMATION, self::COMPRESSION => 'high',
            self::REQUEST, self::RESPONSE, self::BOTH => 'minimal',
            self::ERROR, self::RETRY, self::TIMEOUT => 'minimal',
        };
    }

    public function isRequestPhase(): bool
    {
        return in_array($this->getExecutionPhase(), ['before_request', 'both_phases']);
    }

    public function isResponsePhase(): bool
    {
        return in_array($this->getExecutionPhase(), ['after_response', 'both_phases']);
    }

    public function isErrorPhase(): bool
    {
        return $this->getExecutionPhase() === 'on_error';
    }

    public function requiresAuthentication(): bool
    {
        return match ($this) {
            self::AUTHENTICATION => true,
            default => false,
        };
    }

    public function modifiesRequest(): bool
    {
        return match ($this) {
            self::REQUEST, self::AUTHENTICATION, self::SECURITY, self::COMPRESSION, self::BOTH => true,
            default => false,
        };
    }

    public function modifiesResponse(): bool
    {
        return match ($this) {
            self::RESPONSE, self::CACHING, self::VALIDATION, self::TRANSFORMATION, self::COMPRESSION, self::BOTH => true,
            default => false,
        };
    }

    public function isPassive(): bool
    {
        return match ($this) {
            self::LOGGING, self::MONITORING => true,
            default => false,
        };
    }

    public static function getByExecutionPhase(string $phase): array
    {
        return array_filter(
            self::cases(),
            fn(self $type) => $type->getExecutionPhase() === $phase
        );
    }

    public static function getByPerformanceImpact(string $impact): array
    {
        return array_filter(
            self::cases(),
            fn(self $type) => $type->getPerformanceImpact() === $impact
        );
    }

    public static function getRequestMiddleware(): array
    {
        return array_filter(
            self::cases(),
            fn(self $type) => $type->isRequestPhase()
        );
    }

    public static function getResponseMiddleware(): array
    {
        return array_filter(
            self::cases(),
            fn(self $type) => $type->isResponsePhase()
        );
    }

    public static function getErrorMiddleware(): array
    {
        return array_filter(
            self::cases(),
            fn(self $type) => $type->isErrorPhase()
        );
    }

    public static function getPassiveMiddleware(): array
    {
        return array_filter(
            self::cases(),
            fn(self $type) => $type->isPassive()
        );
    }

    public static function fromString(string $type): self
    {
        $type = strtolower(str_replace(['-', '_', ' '], '_', $type));

        return match ($type) {
            'request' => self::REQUEST,
            'response' => self::RESPONSE,
            'both' => self::BOTH,
            'error' => self::ERROR,
            'authentication', 'auth' => self::AUTHENTICATION,
            'rate_limiting', 'ratelimiting', 'rate_limit' => self::RATE_LIMITING,
            'caching', 'cache' => self::CACHING,
            'logging', 'log' => self::LOGGING,
            'monitoring', 'monitor' => self::MONITORING,
            'validation', 'validate' => self::VALIDATION,
            'transformation', 'transform' => self::TRANSFORMATION,
            'security' => self::SECURITY,
            'compression', 'compress' => self::COMPRESSION,
            'retry' => self::RETRY,
            'timeout' => self::TIMEOUT,
            default => throw new \InvalidArgumentException("Invalid middleware type: {$type}"),
        };
    }

    public function toArray(): array
    {
        return [
            'value' => $this->value,
            'name' => $this->name,
            'description' => $this->getDescription(),
            'execution_phase' => $this->getExecutionPhase(),
            'priority' => $this->getPriority(),
            'performance_impact' => $this->getPerformanceImpact(),
            'is_request_phase' => $this->isRequestPhase(),
            'is_response_phase' => $this->isResponsePhase(),
            'is_error_phase' => $this->isErrorPhase(),
            'requires_authentication' => $this->requiresAuthentication(),
            'modifies_request' => $this->modifiesRequest(),
            'modifies_response' => $this->modifiesResponse(),
            'is_passive' => $this->isPassive(),
        ];
    }
}
