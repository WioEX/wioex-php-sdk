<?php

declare(strict_types=1);

namespace Wioex\SDK\Enums;

/**
 * Error categories for API error classification
 *
 * Represents different types of errors that can occur during API communication:
 * - AUTHENTICATION: Authentication and authorization errors
 * - RATE_LIMIT: Rate limiting and quota exceeded errors
 * - CLIENT_ERROR: Client-side errors (bad requests, validation)
 * - SERVER_ERROR: Server-side errors (internal errors, service unavailable)
 * - UNKNOWN: Unclassified or unexpected errors
 */
enum ErrorCategory: string
{
    case AUTHENTICATION = 'authentication';
    case RATE_LIMIT = 'rate_limit';
    case CLIENT_ERROR = 'client_error';
    case SERVER_ERROR = 'server_error';
    case UNKNOWN = 'unknown';

    /**
     * Get human-readable description
     */
    public function getDescription(): string
    {
        return match ($this) {
            self::AUTHENTICATION => 'Authentication or authorization error',
            self::RATE_LIMIT => 'Rate limit or quota exceeded',
            self::CLIENT_ERROR => 'Client-side error (bad request, validation)',
            self::SERVER_ERROR => 'Server-side error (internal error, unavailable)',
            self::UNKNOWN => 'Unknown or unclassified error',
        };
    }

    /**
     * Get typical HTTP status codes for this category
     */
    public function getHttpStatusCodes(): array
    {
        return match ($this) {
            self::AUTHENTICATION => [401, 403],
            self::RATE_LIMIT => [429],
            self::CLIENT_ERROR => [400, 404, 422, 409],
            self::SERVER_ERROR => [500, 502, 503, 504],
            self::UNKNOWN => [], // Could be any status
        };
    }

    /**
     * Get error severity level
     */
    public function getSeverity(): string
    {
        return match ($this) {
            self::AUTHENTICATION => 'Medium - Check API key and permissions',
            self::RATE_LIMIT => 'Low - Temporary limitation, retry later',
            self::CLIENT_ERROR => 'Medium - Fix request parameters or logic',
            self::SERVER_ERROR => 'High - Service issue, may require support',
            self::UNKNOWN => 'High - Unexpected error, needs investigation',
        };
    }

    /**
     * Check if error is likely temporary
     */
    public function isTemporary(): bool
    {
        return match ($this) {
            self::RATE_LIMIT, self::SERVER_ERROR => true,
            self::AUTHENTICATION, self::CLIENT_ERROR, self::UNKNOWN => false,
        };
    }

    /**
     * Check if error is retry-able
     */
    public function isRetryable(): bool
    {
        return match ($this) {
            self::RATE_LIMIT => true,  // Retry after waiting
            self::SERVER_ERROR => true, // May be temporary server issue
            self::AUTHENTICATION, self::CLIENT_ERROR => false, // Fix required
            self::UNKNOWN => false, // Unknown, better not retry
        };
    }

    /**
     * Get recommended action for this error category
     */
    public function getRecommendedAction(): string
    {
        return match ($this) {
            self::AUTHENTICATION => 'Verify API key, check account status and permissions',
            self::RATE_LIMIT => 'Wait and retry, consider reducing request frequency',
            self::CLIENT_ERROR => 'Check request parameters, validate input data',
            self::SERVER_ERROR => 'Retry later, contact support if persistent',
            self::UNKNOWN => 'Log details, investigate, contact support if needed',
        };
    }

    /**
     * Get typical resolution time
     */
    public function getTypicalResolutionTime(): string
    {
        return match ($this) {
            self::AUTHENTICATION => 'Immediate - Fix API key or permissions',
            self::RATE_LIMIT => '1-60 minutes - Wait for rate limit reset',
            self::CLIENT_ERROR => 'Immediate - Fix request parameters',
            self::SERVER_ERROR => '5-60 minutes - Server recovery time',
            self::UNKNOWN => 'Variable - Depends on root cause',
        };
    }

    /**
     * Get logging priority for this error category
     */
    public function getLoggingPriority(): string
    {
        return match ($this) {
            self::AUTHENTICATION => 'WARN - May indicate security issue',
            self::RATE_LIMIT => 'INFO - Normal operational limit',
            self::CLIENT_ERROR => 'WARN - Application logic issue',
            self::SERVER_ERROR => 'ERROR - Service availability issue',
            self::UNKNOWN => 'ERROR - Unexpected condition',
        };
    }

    /**
     * Check if error should be reported to error tracking
     */
    public function shouldReport(): bool
    {
        return match ($this) {
            self::SERVER_ERROR, self::UNKNOWN => true,
            self::AUTHENTICATION, self::RATE_LIMIT, self::CLIENT_ERROR => false,
        };
    }

    /**
     * Get developer guidance for this error category
     */
    public function getDeveloperGuidance(): string
    {
        return match ($this) {
            self::AUTHENTICATION => 'Check API key configuration and account permissions',
            self::RATE_LIMIT => 'Implement exponential backoff, reduce request frequency',
            self::CLIENT_ERROR => 'Validate request data, check API documentation',
            self::SERVER_ERROR => 'Implement retry logic, have fallback mechanisms',
            self::UNKNOWN => 'Add comprehensive error logging, handle gracefully',
        };
    }

    /**
     * Create ErrorCategory from HTTP status code
     *
     * @param int $statusCode HTTP status code
     * @return self
     */
    public static function fromHttpStatusCode(int $statusCode): self
    {
        return match ($statusCode) {
            401, 403 => self::AUTHENTICATION,
            429 => self::RATE_LIMIT,
            400, 404, 422, 409 => self::CLIENT_ERROR,
            500, 502, 503, 504 => self::SERVER_ERROR,
            default => self::UNKNOWN,
        };
    }

    /**
     * Create ErrorCategory from string value
     *
     * @param string $value The error category string
     * @return self
     * @throws \InvalidArgumentException If the value is not a valid error category
     */
    public static function fromString(string $value): self
    {
        return self::tryFrom($value)
            ?? throw new \InvalidArgumentException("Invalid error category: {$value}");
    }

    /**
     * Get categories that should trigger automatic retry
     *
     * @return array<ErrorCategory>
     */
    public static function getRetryableCategories(): array
    {
        return [self::RATE_LIMIT, self::SERVER_ERROR];
    }

    /**
     * Get categories that indicate client-side issues
     *
     * @return array<ErrorCategory>
     */
    public static function getClientSideCategories(): array
    {
        return [self::AUTHENTICATION, self::CLIENT_ERROR];
    }

    /**
     * Get categories that indicate server-side issues
     *
     * @return array<ErrorCategory>
     */
    public static function getServerSideCategories(): array
    {
        return [self::SERVER_ERROR];
    }
}
