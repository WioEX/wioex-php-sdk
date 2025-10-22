<?php

declare(strict_types=1);

namespace Wioex\SDK\Enums;

/**
 * HTTP methods for API requests
 *
 * Represents standard HTTP methods used in REST API communication:
 * - GET: Retrieve data (read operations)
 * - POST: Create new resources or submit data
 * - PUT: Update existing resources (replace)
 * - DELETE: Remove resources
 */
enum HttpMethod: string
{
    case GET = 'GET';
    case POST = 'POST';
    case PUT = 'PUT';
    case DELETE = 'DELETE';

    /**
     * Get human-readable description
     */
    public function getDescription(): string
    {
        return match ($this) {
            self::GET => 'Retrieve data (read operation)',
            self::POST => 'Create new resource or submit data',
            self::PUT => 'Update existing resource (replace)',
            self::DELETE => 'Remove resource',
        };
    }

    /**
     * Check if method is safe (read-only, no side effects)
     */
    public function isSafe(): bool
    {
        return $this === self::GET;
    }

    /**
     * Check if method is idempotent (same result on multiple calls)
     */
    public function isIdempotent(): bool
    {
        return match ($this) {
            self::GET, self::PUT, self::DELETE => true,
            self::POST => false,
        };
    }

    /**
     * Check if method typically includes request body
     */
    public function hasRequestBody(): bool
    {
        return match ($this) {
            self::POST, self::PUT => true,
            self::GET, self::DELETE => false,
        };
    }

    /**
     * Get typical use cases for this method
     */
    public function getUseCases(): array
    {
        return match ($this) {
            self::GET => [
                'Retrieve stock quotes',
                'Get historical data',
                'Fetch user account information',
                'Search for stocks',
                'Get market status'
            ],
            self::POST => [
                'Create new trading signals',
                'Submit error reports',
                'Authenticate for streaming',
                'Generate reports'
            ],
            self::PUT => [
                'Update user preferences',
                'Modify API key settings',
                'Update notification settings'
            ],
            self::DELETE => [
                'Remove API keys',
                'Delete custom watchlists',
                'Cancel subscriptions'
            ],
        };
    }

    /**
     * Get expected HTTP status codes for successful responses
     */
    public function getSuccessStatusCodes(): array
    {
        return match ($this) {
            self::GET => [200], // OK
            self::POST => [201, 200], // Created, OK
            self::PUT => [200, 204], // OK, No Content
            self::DELETE => [200, 204], // OK, No Content
        };
    }

    /**
     * Get caching characteristics
     */
    public function getCachingBehavior(): string
    {
        return match ($this) {
            self::GET => 'Cacheable - Responses can be cached',
            self::POST => 'Not cacheable - Creates new resources',
            self::PUT => 'Not cacheable - Modifies resources',
            self::DELETE => 'Not cacheable - Removes resources',
        };
    }

    /**
     * Check if method modifies server state
     */
    public function modifiesState(): bool
    {
        return match ($this) {
            self::GET => false,
            self::POST, self::PUT, self::DELETE => true,
        };
    }

    /**
     * Get retry safety for this method
     */
    public function isRetrySafe(): bool
    {
        // Safe to retry idempotent methods
        return $this->isIdempotent();
    }

    /**
     * Create HttpMethod from string value
     *
     * @param string $value The HTTP method string
     * @return self
     * @throws \InvalidArgumentException If the value is not a valid HTTP method
     */
    public static function fromString(string $value): self
    {
        $normalizedValue = strtoupper($value);
        return self::tryFrom($normalizedValue)
            ?? throw new \InvalidArgumentException("Invalid HTTP method: {$value}");
    }

    /**
     * Get safe methods (read-only)
     *
     * @return array<HttpMethod>
     */
    public static function getSafeMethods(): array
    {
        return [self::GET];
    }

    /**
     * Get methods that modify server state
     *
     * @return array<HttpMethod>
     */
    public static function getMutatingMethods(): array
    {
        return [self::POST, self::PUT, self::DELETE];
    }

    /**
     * Get idempotent methods
     *
     * @return array<HttpMethod>
     */
    public static function getIdempotentMethods(): array
    {
        return [self::GET, self::PUT, self::DELETE];
    }
}
