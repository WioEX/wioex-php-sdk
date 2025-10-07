<?php

declare(strict_types=1);

namespace Wioex\SDK;

use Wioex\SDK\Exceptions\WioexException;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;
use Throwable;

/**
 * Error Reporter - Sends error reports to WioEX API
 *
 * This class collects error information and sends it to WioEX
 * for monitoring and improving SDK quality. All data is privacy-safe
 * and can be disabled via configuration.
 */
class ErrorReporter
{
    private Config $config;
    private ?GuzzleClient $client = null;
    private bool $enabled;

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->enabled = $config->isErrorReportingEnabled();

        if ($this->enabled) {
            $this->client = new GuzzleClient([
                'timeout' => 5, // Quick timeout to not block user requests
                'connect_timeout' => 2,
                'http_errors' => false, // Don't throw on error responses
            ]);
        }
    }

    /**
     * Report an error to WioEX API
     *
     * @param Throwable $exception The exception to report
     * @param array<string, mixed> $context Additional context (request details, etc.)
     * @return bool True if reported successfully, false otherwise
     */
    public function report(Throwable $exception, array $context = []): bool
    {
        if (!$this->enabled) {
            return false;
        }

        try {
            $data = $this->buildErrorData($exception, $context);
            $this->sendReport($data);
            return true;
        } catch (Throwable $e) {
            // Silently fail - we don't want error reporting to break the application
            return false;
        }
    }

    /**
     * Build error data payload
     *
     * @param Throwable $exception
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function buildErrorData(Throwable $exception, array $context): array
    {
        $level = $this->config->getErrorReportingLevel();

        $data = [
            'sdk_version' => '1.0.0',
            'sdk_type' => 'php',
            'runtime' => 'PHP/' . PHP_VERSION,
            'api_key_id' => $this->config->getApiKeyIdentification(), // Hashed API key for customer identification
            'reporting_level' => $level,
            'error' => [
                'type' => get_class($exception),
                'message' => $exception->getMessage(),
                'code' => (string)$exception->getCode(),
                'file' => $this->getRelativeFilePath($exception->getFile()),
                'line' => $exception->getLine(),
            ],
            'context' => $this->sanitizeContext($context, $level),
            'timestamp' => time() * 1000, // Unix timestamp in milliseconds
        ];

        // Add category if provided in context (stocks, currency, news, crypto, account)
        if (isset($context['category'])) {
            $data['category'] = $context['category'];
        }

        // Legacy environment fields (kept for backward compatibility)
        $data['php_version'] = PHP_VERSION;
        $data['environment'] = [
            'os' => PHP_OS,
            'sapi' => PHP_SAPI,
        ];

        // Add stack trace based on level
        if ($level === 'standard' || $level === 'detailed' || $this->config->shouldIncludeStackTrace()) {
            $data['error']['stack_trace'] = $this->sanitizeStackTrace($exception->getTrace());
        }

        // Add exception context if available
        if ($exception instanceof WioexException) {
            $data['exception_context'] = $this->sanitizeContext($exception->getContext(), $level);
        }

        // Add request data if enabled (detailed level or explicit opt-in)
        if ($this->config->shouldIncludeRequestData() || $level === 'detailed') {
            if (isset($context['request_data'])) {
                $data['request'] = $this->sanitizePayload($context['request_data'], $level);
            }
        }

        // Add response data if enabled (detailed level or explicit opt-in)
        if ($this->config->shouldIncludeResponseData() || $level === 'detailed') {
            if (isset($context['response_data'])) {
                $data['response'] = $this->sanitizePayload($context['response_data'], $level);
            }
        }

        return $data;
    }

    /**
     * Get relative file path for debugging
     * Keeps meaningful path information while removing sensitive parts
     */
    private function getRelativeFilePath(string $path): string
    {
        // Try to find common project markers
        $markers = [
            '/vendor/wioex/' => 'vendor/wioex/',
            '/vendor/' => 'vendor/',
            '/src/' => 'src/',
            '/app/' => 'app/',
            '/public/' => 'public/',
        ];

        foreach ($markers as $marker => $replacement) {
            $pos = strpos($path, $marker);
            if ($pos !== false) {
                return $replacement . substr($path, $pos + strlen($marker));
            }
        }

        // If no marker found, return last 3 segments of path
        $segments = explode('/', $path);
        $relevant = array_slice($segments, -3);
        return implode('/', $relevant);
    }

    /**
     * Sanitize context to remove sensitive data based on reporting level
     *
     * @param array<string, mixed> $context
     * @param string $level
     * @return array<string, mixed>
     */
    private function sanitizeContext(array $context, string $level = 'standard'): array
    {
        $sanitized = [];
        $sensitiveKeys = ['api_key', 'password', 'token', 'secret', 'authorization', 'bearer'];

        foreach ($context as $key => $value) {
            // Remove sensitive keys in all levels
            $lowerKey = strtolower($key);
            $isSensitive = false;

            foreach ($sensitiveKeys as $sensitiveKey) {
                if (str_contains($lowerKey, $sensitiveKey)) {
                    $isSensitive = true;
                    break;
                }
            }

            if ($isSensitive) {
                // In detailed mode, show partial data
                if ($level === 'detailed' && is_string($value) && strlen($value) > 4) {
                    $sanitized[$key] = substr($value, 0, 4) . '...' . substr($value, -4);
                } else {
                    $sanitized[$key] = '[REDACTED]';
                }
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->sanitizeContext($value, $level);
            } elseif (is_scalar($value) || $value === null) {
                $sanitized[$key] = $value;
            } else {
                $sanitized[$key] = '[' . gettype($value) . ']';
            }
        }

        return $sanitized;
    }

    /**
     * Sanitize request/response payload data
     *
     * @param mixed $payload
     * @param string $level
     * @return mixed
     */
    private function sanitizePayload($payload, string $level = 'standard')
    {
        if (is_array($payload)) {
            return $this->sanitizeContext($payload, $level);
        }

        if (is_string($payload)) {
            // Try to parse JSON
            $decoded = json_decode($payload, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $this->sanitizeContext($decoded, $level);
            }

            // For non-JSON strings in minimal/standard, truncate
            if ($level === 'minimal') {
                return '[' . strlen($payload) . ' bytes]';
            } elseif ($level === 'standard') {
                return strlen($payload) > 200 ? substr($payload, 0, 200) . '... [truncated]' : $payload;
            }

            // Detailed mode: include full payload
            return $payload;
        }

        if (is_scalar($payload) || $payload === null) {
            return $payload;
        }

        return '[' . gettype($payload) . ']';
    }

    /**
     * Sanitize stack trace
     *
     * @param array<int, array<string, mixed>> $trace
     * @return array<int, array<string, mixed>>
     */
    private function sanitizeStackTrace(array $trace): array
    {
        $sanitized = [];
        $maxFrames = 10; // Limit stack trace depth

        foreach (array_slice($trace, 0, $maxFrames) as $frame) {
            $sanitizedFrame = [];

            if (isset($frame['file']) && is_string($frame['file'])) {
                $sanitizedFrame['file'] = $this->getRelativeFilePath($frame['file']);
            }

            if (isset($frame['line']) && is_int($frame['line'])) {
                $sanitizedFrame['line'] = $frame['line'];
            }

            if (isset($frame['class']) && is_string($frame['class'])) {
                $sanitizedFrame['class'] = $frame['class'];
            }

            if (isset($frame['function']) && is_string($frame['function'])) {
                $sanitizedFrame['function'] = $frame['function'];
            }

            // Don't include arguments as they may contain sensitive data
            $sanitized[] = $sanitizedFrame;
        }

        return $sanitized;
    }

    /**
     * Send error report to WioEX API
     *
     * @param array<string, mixed> $data
     */
    private function sendReport(array $data): void
    {
        if ($this->client === null) {
            return;
        }

        try {
            $endpoint = $this->config->getErrorReportingEndpoint();

            $this->client->post($endpoint, [
                'json' => $data,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-SDK-Version' => '1.0.0',
                ],
            ]);
        } catch (GuzzleException $e) {
            // Silently fail - don't throw exceptions from error reporter
        }
    }

    /**
     * Check if error reporting is enabled
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }
}
