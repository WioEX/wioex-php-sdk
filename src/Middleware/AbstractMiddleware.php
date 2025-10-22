<?php

declare(strict_types=1);

namespace Wioex\SDK\Middleware;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Wioex\SDK\Enums\MiddlewareType;

abstract class AbstractMiddleware implements MiddlewareInterface
{
    protected MiddlewareType $type;
    protected array $config;
    protected bool $enabled = true;
    protected int $priority;

    public function __construct(MiddlewareType $type, array $config = [])
    {
        $this->type = $type;
        $this->config = array_merge($this->getDefaultConfig(), $config);
        $this->priority = $config['priority'] ?? $type->getPriority();
    }

    public function getType(): MiddlewareType
    {
        return $this->type;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function setPriority(int $priority): self
    {
        $this->priority = $priority;
        return $this;
    }

    public function shouldExecute(RequestInterface $request, array $context = []): bool
    {
        if (!$this->enabled) {
            return false;
        }

        // Check if middleware should run based on configuration
        if (isset($this->config['only_paths'])) {
            $path = $request->getUri()->getPath();
            $patterns = (array) $this->config['only_paths'];

            if (!$this->matchesPatterns($path, $patterns)) {
                return false;
            }
        }

        if (isset($this->config['except_paths'])) {
            $path = $request->getUri()->getPath();
            $patterns = (array) $this->config['except_paths'];

            if ($this->matchesPatterns($path, $patterns)) {
                return false;
            }
        }

        if (isset($this->config['only_methods'])) {
            $method = $request->getMethod();
            $methods = array_map('strtoupper', (array) $this->config['only_methods']);

            if (!in_array(strtoupper($method), $methods)) {
                return false;
            }
        }

        if (isset($this->config['except_methods'])) {
            $method = $request->getMethod();
            $methods = array_map('strtoupper', (array) $this->config['except_methods']);

            if (in_array(strtoupper($method), $methods)) {
                return false;
            }
        }

        return $this->shouldExecuteCustom($request, $context);
    }

    public function processRequest(RequestInterface $request, array $context = []): RequestInterface
    {
        if (!$this->type->isRequestPhase()) {
            return $request;
        }

        return $this->processRequestCustom($request, $context);
    }

    public function processResponse(ResponseInterface $response, RequestInterface $request, array $context = []): ResponseInterface
    {
        if (!$this->type->isResponsePhase()) {
            return $response;
        }

        return $this->processResponseCustom($response, $request, $context);
    }

    public function handleError(\Throwable $error, RequestInterface $request, array $context = []): ?\Throwable
    {
        if (!$this->type->isErrorPhase()) {
            return $error;
        }

        return $this->handleErrorCustom($error, $request, $context);
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function setConfig(array $config): self
    {
        $this->config = array_merge($this->config, $config);
        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function enable(): self
    {
        $this->enabled = true;
        return $this;
    }

    public function disable(): self
    {
        $this->enabled = false;
        return $this;
    }

    /**
     * Get default configuration for this middleware
     */
    protected function getDefaultConfig(): array
    {
        return [];
    }

    /**
     * Custom logic to determine if middleware should execute
     */
    protected function shouldExecuteCustom(RequestInterface $request, array $context = []): bool
    {
        return true;
    }

    /**
     * Custom request processing logic
     */
    protected function processRequestCustom(RequestInterface $request, array $context = []): RequestInterface
    {
        return $request;
    }

    /**
     * Custom response processing logic
     */
    protected function processResponseCustom(ResponseInterface $response, RequestInterface $request, array $context = []): ResponseInterface
    {
        return $response;
    }

    /**
     * Custom error handling logic
     */
    protected function handleErrorCustom(\Throwable $error, RequestInterface $request, array $context = []): ?\Throwable
    {
        return $error;
    }

    /**
     * Check if a path matches any of the given patterns
     */
    protected function matchesPatterns(string $path, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if ($this->matchesPattern($path, $pattern)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if a path matches a pattern (supports wildcards)
     */
    protected function matchesPattern(string $path, string $pattern): bool
    {
        // Convert wildcard pattern to regex
        $regex = str_replace(
            ['*', '?'],
            ['.*', '.'],
            preg_quote($pattern, '/')
        );

        return preg_match('/^' . $regex . '$/', $path) === 1;
    }

    /**
     * Extract value from nested array using dot notation
     */
    protected function getConfigValue(string $key, mixed $default = null): mixed
    {
        $keys = explode('.', $key);
        $value = $this->config;

        foreach ($keys as $k) {
            if (!is_array($value) || !array_key_exists($k, $value)) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }

    /**
     * Set value in nested array using dot notation
     */
    protected function setConfigValue(string $key, mixed $value): void
    {
        $keys = explode('.', $key);
        $current = &$this->config;

        foreach ($keys as $i => $k) {
            if ($i === count($keys) - 1) {
                $current[$k] = $value;
            } else {
                if (!isset($current[$k]) || !is_array($current[$k])) {
                    $current[$k] = [];
                }
                $current = &$current[$k];
            }
        }
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type->toArray(),
            'priority' => $this->priority,
            'enabled' => $this->enabled,
            'config' => $this->config,
        ];
    }

    public function __toString(): string
    {
        return sprintf(
            '%s (Type: %s, Priority: %d, Enabled: %s)',
            static::class,
            $this->type->value,
            $this->priority,
            $this->enabled ? 'Yes' : 'No'
        );
    }
}
