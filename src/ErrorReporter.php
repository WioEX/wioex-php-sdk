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
        $data = [
            'sdk_version' => '1.0.0',
            'php_version' => PHP_VERSION,
            'error' => [
                'type' => get_class($exception),
                'message' => $exception->getMessage(),
                'code' => $exception->getCode(),
                'file' => $this->sanitizeFilePath($exception->getFile()),
                'line' => $exception->getLine(),
            ],
            'context' => $this->sanitizeContext($context),
            'timestamp' => date('c'),
            'environment' => [
                'os' => PHP_OS,
                'sapi' => PHP_SAPI,
            ],
        ];

        // Add stack trace if enabled
        if ($this->config->shouldIncludeStackTrace()) {
            $data['error']['stack_trace'] = $this->sanitizeStackTrace($exception->getTrace());
        }

        // Add exception context if available
        if ($exception instanceof WioexException) {
            $data['exception_context'] = $this->sanitizeContext($exception->getContext());
        }

        return $data;
    }

    /**
     * Sanitize file path to remove sensitive information
     */
    private function sanitizeFilePath(string $path): string
    {
        // Remove absolute path, keep only relative path from project root
        $vendorPos = strpos($path, '/vendor/');
        if ($vendorPos !== false) {
            return 'vendor/' . substr($path, $vendorPos + 8);
        }

        $srcPos = strpos($path, '/src/');
        if ($srcPos !== false) {
            return 'src/' . substr($path, $srcPos + 5);
        }

        return basename($path);
    }

    /**
     * Sanitize context to remove sensitive data
     *
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function sanitizeContext(array $context): array
    {
        $sanitized = [];
        $sensitiveKeys = ['api_key', 'password', 'token', 'secret', 'authorization'];

        foreach ($context as $key => $value) {
            // Remove sensitive keys
            $lowerKey = strtolower($key);
            $isSensitive = false;

            foreach ($sensitiveKeys as $sensitiveKey) {
                if (str_contains($lowerKey, $sensitiveKey)) {
                    $isSensitive = true;
                    break;
                }
            }

            if ($isSensitive) {
                $sanitized[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->sanitizeContext($value);
            } elseif (is_scalar($value) || $value === null) {
                $sanitized[$key] = $value;
            } else {
                $sanitized[$key] = '[' . gettype($value) . ']';
            }
        }

        return $sanitized;
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
                $sanitizedFrame['file'] = $this->sanitizeFilePath($frame['file']);
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
