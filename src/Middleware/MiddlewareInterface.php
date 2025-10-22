<?php

declare(strict_types=1);

namespace Wioex\SDK\Middleware;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Wioex\SDK\Enums\MiddlewareType;

interface MiddlewareInterface
{
    /**
     * Get the middleware type
     */
    public function getType(): MiddlewareType;

    /**
     * Get middleware priority (higher values execute first)
     */
    public function getPriority(): int;

    /**
     * Check if this middleware should be executed for the given request
     */
    public function shouldExecute(RequestInterface $request, array $context = []): bool;

    /**
     * Process the request before it's sent
     */
    public function processRequest(RequestInterface $request, array $context = []): RequestInterface;

    /**
     * Process the response after it's received
     */
    public function processResponse(ResponseInterface $response, RequestInterface $request, array $context = []): ResponseInterface;

    /**
     * Handle errors that occur during request/response processing
     */
    public function handleError(\Throwable $error, RequestInterface $request, array $context = []): ?\Throwable;

    /**
     * Get middleware configuration
     */
    public function getConfig(): array;

    /**
     * Set middleware configuration
     */
    public function setConfig(array $config): self;

    /**
     * Check if middleware is enabled
     */
    public function isEnabled(): bool;

    /**
     * Enable middleware
     */
    public function enable(): self;

    /**
     * Disable middleware
     */
    public function disable(): self;
}
