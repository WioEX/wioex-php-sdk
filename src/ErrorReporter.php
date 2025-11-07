<?php

declare(strict_types=1);

namespace Wioex\SDK;

use Wioex\SDK\Exceptions\WioexException;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\Utils;
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
    
    // Enhanced reporting features
    private array $errorQueue = [];
    private array $reportingStats = [
        'total_reports' => 0,
        'successful_reports' => 0,
        'failed_reports' => 0,
        'last_report_time' => null,
        'rate_limit_hits' => 0
    ];
    
    // Rate limiting
    private int $maxReportsPerMinute = 10;
    private array $reportTimes = [];
    
    // Batch reporting
    private int $batchSize = 5;
    private float $batchTimeout = 30.0; // seconds
    private ?float $lastBatchTime = null;
    
    // Privacy enhancement levels
    private const PRIVACY_MINIMAL = 'minimal';
    private const PRIVACY_STANDARD = 'standard';
    private const PRIVACY_DETAILED = 'detailed';
    private const PRIVACY_DEBUG = 'debug';

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
            'sdk_version' => $this->config->getSdkVersion(),
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
     * @return array<string, mixed>|string|int|float|bool|null
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
        try {
            $endpoint = $this->config->getErrorReportingEndpoint();
            $jsonData = json_encode($data);
            
            if ($jsonData === false) {
                return;
            }

            // Use cURL for background request
            $this->sendCurlBackground($endpoint, $jsonData);
            
        } catch (\Throwable $e) {
            // Silently fail - don't throw exceptions from error reporter
        }
    }

    /**
     * Send error report using cURL in background (non-blocking)
     *
     * @param string $url
     * @param string $jsonData
     * @return void
     */
    private function sendCurlBackground(string $url, string $jsonData): void
    {
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $jsonData,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($jsonData),
                'X-SDK-Version: ' . $this->config->getSdkVersion(),
                'User-Agent: WioEX-PHP-SDK/' . $this->config->getSdkVersion(),
            ],
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_HEADER => false,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_NOSIGNAL => 1,
        ]);
        
        // Execute and close immediately (fire and forget)
        curl_exec($ch);
        curl_close($ch);
    }

    /**
     * Report an error asynchronously (non-blocking)
     *
     * @param Throwable $exception The exception to report
     * @param array<string, mixed> $context Additional context
     * @return PromiseInterface|bool Promise for async operation, or false if disabled
     */
    public function reportAsync(Throwable $exception, array $context = [])
    {
        if (!$this->enabled) {
            return false;
        }

        if (!$this->checkRateLimit()) {
            $this->reportingStats['rate_limit_hits']++;
            return false;
        }

        try {
            $data = $this->buildErrorData($exception, $context);
            return $this->sendReportAsync($data);
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Add error to batch queue for later reporting
     *
     * @param Throwable $exception The exception to queue
     * @param array<string, mixed> $context Additional context
     * @return bool True if queued successfully
     */
    public function queueError(Throwable $exception, array $context = []): bool
    {
        if (!$this->enabled) {
            return false;
        }

        if (!$this->checkRateLimit()) {
            $this->reportingStats['rate_limit_hits']++;
            return false;
        }

        try {
            $data = $this->buildErrorData($exception, $context);
            $this->errorQueue[] = $data;

            // Auto-flush if batch size reached
            if (count($this->errorQueue) >= $this->batchSize) {
                $this->flushErrorQueue();
            }

            // Auto-flush if timeout reached
            if ($this->lastBatchTime !== null && 
                (microtime(true) - $this->lastBatchTime) >= $this->batchTimeout) {
                $this->flushErrorQueue();
            }

            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Flush all queued errors in a single batch request
     *
     * @return bool True if flushed successfully
     */
    public function flushErrorQueue(): bool
    {
        if (empty($this->errorQueue) || !$this->enabled) {
            return false;
        }

        try {
            $batchData = [
                'sdk_version' => $this->config->getSdkVersion(),
                'api_key_id' => $this->config->getApiKeyIdentification(),
                'privacy_mode' => $this->config->getErrorReportingLevel(),
                'telemetry_version' => '2.0',
                'batch_size' => count($this->errorQueue),
                'events' => array_map(function($errorData) {
                    return [
                        'type' => 'error',
                        'data' => $errorData,
                        'timestamp' => $errorData['timestamp'] ?? time() * 1000
                    ];
                }, $this->errorQueue)
            ];

            $this->sendBatchReport($batchData);
            $this->errorQueue = [];
            $this->lastBatchTime = microtime(true);
            
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Enhanced privacy control - sanitize data based on enhanced privacy levels
     *
     * @param array<string, mixed> $data Raw data to sanitize
     * @param string $privacyLevel Privacy level (minimal, standard, detailed, debug)
     * @return array<string, mixed> Sanitized data
     */
    public function enhancedSanitizeData(array $data, string $privacyLevel = self::PRIVACY_STANDARD): array
    {
        switch ($privacyLevel) {
            case self::PRIVACY_MINIMAL:
                return $this->sanitizeMinimal($data);
            case self::PRIVACY_STANDARD:
                return $this->sanitizeStandard($data);
            case self::PRIVACY_DETAILED:
                return $this->sanitizeDetailed($data);
            case self::PRIVACY_DEBUG:
                return $this->sanitizeDebug($data);
            default:
                return $this->sanitizeStandard($data);
        }
    }

    /**
     * Get error reporting statistics
     *
     * @return array<string, mixed> Reporting statistics
     */
    public function getReportingStats(): array
    {
        return array_merge($this->reportingStats, [
            'queue_size' => count($this->errorQueue),
            'rate_limit_status' => $this->getRateLimitStatus(),
            'batch_config' => [
                'batch_size' => $this->batchSize,
                'batch_timeout' => $this->batchTimeout,
                'max_reports_per_minute' => $this->maxReportsPerMinute
            ]
        ]);
    }

    /**
     * Configure batch reporting settings
     *
     * @param int $batchSize Number of errors per batch
     * @param float $batchTimeout Timeout in seconds before auto-flush
     * @return self
     */
    public function configureBatchReporting(int $batchSize = 5, float $batchTimeout = 30.0): self
    {
        $this->batchSize = max(1, min($batchSize, 20)); // Limit between 1-20
        $this->batchTimeout = max(5.0, min($batchTimeout, 300.0)); // Limit between 5s-5min
        return $this;
    }

    /**
     * Configure rate limiting
     *
     * @param int $maxReportsPerMinute Maximum reports allowed per minute
     * @return self
     */
    public function configureRateLimit(int $maxReportsPerMinute = 10): self
    {
        $this->maxReportsPerMinute = max(1, min($maxReportsPerMinute, 60));
        return $this;
    }

    /**
     * Check if error reporting is enabled
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Check rate limiting status
     */
    private function checkRateLimit(): bool
    {
        $now = time();
        
        // Clean old entries (older than 1 minute)
        $this->reportTimes = array_filter(
            $this->reportTimes, 
            fn($time) => ($now - $time) < 60
        );
        
        return count($this->reportTimes) < $this->maxReportsPerMinute;
    }

    /**
     * Get current rate limit status
     *
     * @return array<string, mixed> Rate limit information
     */
    private function getRateLimitStatus(): array
    {
        $now = time();
        $recentReports = array_filter(
            $this->reportTimes, 
            fn($time) => ($now - $time) < 60
        );
        
        return [
            'reports_in_last_minute' => count($recentReports),
            'reports_remaining' => max(0, $this->maxReportsPerMinute - count($recentReports)),
            'rate_limited' => count($recentReports) >= $this->maxReportsPerMinute
        ];
    }

    /**
     * Send error report asynchronously
     */
    private function sendReportAsync(array $data): PromiseInterface
    {
        if ($this->client === null) {
            throw new \RuntimeException('HTTP client not initialized');
        }

        $endpoint = $this->config->getErrorReportingEndpoint();
        
        $this->recordReportAttempt();
        
        return $this->client->postAsync($endpoint, [
            'json' => $data,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-SDK-Version' => $this->config->getSdkVersion(),
            ],
        ])->then(
            function ($response) {
                $this->reportingStats['successful_reports']++;
                return $response;
            },
            function ($exception) {
                $this->reportingStats['failed_reports']++;
                throw $exception;
            }
        );
    }

    /**
     * Send batch error report
     */
    private function sendBatchReport(array $batchData): void
    {
        if ($this->client === null) {
            return;
        }

        try {
            $endpoint = $this->config->getTelemetryEndpoint() ?? '/v2/sdk/telemetry';
            
            $this->recordReportAttempt();
            
            $response = $this->client->post($endpoint, [
                'json' => $batchData,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-SDK-Version' => $this->config->getSdkVersion(),
                ],
            ]);
            
            if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
                $this->reportingStats['successful_reports'] += count($batchData['events']);
            } else {
                $this->reportingStats['failed_reports'] += count($batchData['events']);
            }
        } catch (GuzzleException $e) {
            $this->reportingStats['failed_reports'] += count($batchData['events']);
        }
    }

    /**
     * Record report attempt for rate limiting
     */
    private function recordReportAttempt(): void
    {
        $this->reportTimes[] = time();
        $this->reportingStats['total_reports']++;
        $this->reportingStats['last_report_time'] = date('c');
    }

    /**
     * Minimal privacy sanitization - only essential error information
     */
    private function sanitizeMinimal(array $data): array
    {
        return [
            'error' => [
                'type' => $data['error']['type'] ?? 'Unknown',
                'message' => '[REDACTED]',
                'code' => $data['error']['code'] ?? null
            ],
            'timestamp' => $data['timestamp'] ?? time() * 1000,
            'sdk_version' => $data['sdk_version'] ?? 'unknown'
        ];
    }

    /**
     * Standard privacy sanitization - balanced approach
     */
    private function sanitizeStandard(array $data): array
    {
        $sanitized = $data;
        
        // Sanitize error message to remove potential sensitive data
        if (isset($sanitized['error']['message'])) {
            $sanitized['error']['message'] = $this->sanitizeErrorMessage($sanitized['error']['message']);
        }
        
        // Remove or sanitize context
        if (isset($sanitized['context'])) {
            $sanitized['context'] = $this->sanitizeContext($sanitized['context'], 'standard');
        }
        
        // Limit stack trace
        if (isset($sanitized['error']['stack_trace'])) {
            $sanitized['error']['stack_trace'] = array_slice($sanitized['error']['stack_trace'], 0, 5);
        }
        
        return $sanitized;
    }

    /**
     * Detailed privacy sanitization - more information but still safe
     */
    private function sanitizeDetailed(array $data): array
    {
        $sanitized = $data;
        
        // Keep more context but sanitize sensitive keys
        if (isset($sanitized['context'])) {
            $sanitized['context'] = $this->sanitizeContext($sanitized['context'], 'detailed');
        }
        
        return $sanitized;
    }

    /**
     * Debug privacy sanitization - maximum information for debugging
     */
    private function sanitizeDebug(array $data): array
    {
        // In debug mode, return data with minimal sanitization
        $sanitized = $data;
        
        // Still remove critical sensitive data
        if (isset($sanitized['context'])) {
            $criticalKeys = ['api_key', 'password', 'secret', 'token'];
            foreach ($criticalKeys as $key) {
                if (isset($sanitized['context'][$key])) {
                    $sanitized['context'][$key] = '[REDACTED]';
                }
            }
        }
        
        return $sanitized;
    }

    /**
     * Sanitize error message to remove potential sensitive information
     */
    private function sanitizeErrorMessage(string $message): string
    {
        // Patterns to remove from error messages
        $patterns = [
            '/api[_-]?key[_-:]?\s*[^\s\]},]{10,}/i' => '[API_KEY]',
            '/token[_-:]?\s*[^\s\]},]{10,}/i' => '[TOKEN]',
            '/secret[_-:]?\s*[^\s\]},]{10,}/i' => '[SECRET]',
            '/password[_-:]?\s*[^\s\]},]{4,}/i' => '[PASSWORD]',
            '/authorization:\s*bearer\s+[^\s\]},]+/i' => 'authorization: bearer [TOKEN]',
            '/\/[a-z]:[^\/\s\]},]+/i' => '/[PATH]', // Windows paths
            '/\/home\/[^\/\s\]},]+/i' => '/home/[USER]', // Unix home paths
        ];
        
        foreach ($patterns as $pattern => $replacement) {
            $message = preg_replace($pattern, $replacement, $message);
        }
        
        return $message;
    }
}
