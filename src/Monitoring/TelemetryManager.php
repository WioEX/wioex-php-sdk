<?php

declare(strict_types=1);

namespace Wioex\SDK\Monitoring;

use Wioex\SDK\Config;
use Wioex\SDK\ErrorReporter;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;
use Throwable;

/**
 * Telemetry Manager - Comprehensive SDK telemetry and analytics
 *
 * Provides automatic error reporting, performance tracking, usage analytics,
 * and environment monitoring with privacy-first design. All telemetry is
 * opt-in and configurable with multiple privacy levels.
 */
class TelemetryManager
{
    private Config $config;
    private ?GuzzleClient $client = null;
    private ErrorReporter $errorReporter;
    private bool $enabled;
    private array $queue = [];
    private array $performanceMetrics = [];
    private array $usageStats = [];
    private float $samplingRate;
    private string $privacyMode;
    private int $lastFlushTime;
    private int $flushInterval;
    private int $maxQueueSize;

    // Telemetry types
    private const TYPE_ERROR = 'error';
    private const TYPE_PERFORMANCE = 'performance';
    private const TYPE_USAGE = 'usage';
    private const TYPE_ENVIRONMENT = 'environment';

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->errorReporter = new ErrorReporter($config);
        
        $telemetryConfig = $config->getTelemetryConfig();
        $this->enabled = $telemetryConfig['enabled'] ?? false;
        $this->samplingRate = $telemetryConfig['sampling_rate'] ?? 0.1;
        $this->privacyMode = $telemetryConfig['privacy_mode'] ?? 'production';
        $this->flushInterval = $telemetryConfig['flush_interval'] ?? 30; // seconds
        $this->maxQueueSize = $telemetryConfig['max_queue_size'] ?? 100;
        
        $this->lastFlushTime = time();

        if ($this->enabled) {
            $this->client = new GuzzleClient([
                'timeout' => 10,
                'connect_timeout' => 3,
                'http_errors' => false,
            ]);
            
            // Send environment information once per session
            $this->trackEnvironment();
            
            // Register shutdown function for final flush
            register_shutdown_function([$this, 'flush']);
        }
    }

    /**
     * Track an error with enhanced context
     *
     * @param Throwable $exception The exception to track
     * @param array<string, mixed> $context Additional context
     */
    public function trackError(Throwable $exception, array $context = []): void
    {
        if (!$this->enabled || !$this->shouldSample()) {
            return;
        }

        // Use existing ErrorReporter for error tracking
        $this->errorReporter->report($exception, $context);

        // Also add to telemetry queue for analytics
        $this->addToQueue(self::TYPE_ERROR, [
            'type' => get_class($exception),
            'message' => $this->sanitizeMessage($exception->getMessage()),
            'category' => $context['category'] ?? 'unknown',
            'endpoint' => $context['endpoint'] ?? null,
            'timestamp' => microtime(true)
        ]);
    }

    /**
     * Track API performance metrics
     *
     * @param string $endpoint API endpoint called
     * @param float $responseTime Response time in milliseconds
     * @param int $statusCode HTTP status code
     * @param array<string, mixed> $metadata Additional metadata
     */
    public function trackPerformance(string $endpoint, float $responseTime, int $statusCode, array $metadata = []): void
    {
        if (!$this->enabled || !$this->shouldSample()) {
            return;
        }

        $performanceData = [
            'endpoint' => $endpoint,
            'response_time_ms' => round($responseTime, 2),
            'status_code' => $statusCode,
            'timestamp' => microtime(true),
            'success' => $statusCode >= 200 && $statusCode < 400
        ];

        // Add safe metadata
        if (isset($metadata['method'])) {
            $performanceData['method'] = $metadata['method'];
        }
        if (isset($metadata['cache_hit'])) {
            $performanceData['cache_hit'] = (bool)$metadata['cache_hit'];
        }
        if (isset($metadata['retry_count'])) {
            $performanceData['retry_count'] = (int)$metadata['retry_count'];
        }

        $this->addToQueue(self::TYPE_PERFORMANCE, $performanceData);
        
        // Store for local analytics
        $this->updatePerformanceStats($endpoint, $responseTime, $statusCode);
    }

    /**
     * Track API usage patterns
     *
     * @param string $endpoint API endpoint used
     * @param array<string, mixed> $parameters Request parameters (sanitized)
     */
    public function trackUsage(string $endpoint, array $parameters = []): void
    {
        if (!$this->enabled || !$this->shouldSample()) {
            return;
        }

        $usageData = [
            'endpoint' => $endpoint,
            'parameter_count' => count($parameters),
            'timestamp' => microtime(true)
        ];

        // Add parameter insights without sensitive data
        if ($this->privacyMode === 'development') {
            $usageData['parameter_types'] = $this->getParameterTypes($parameters);
        }

        $this->addToQueue(self::TYPE_USAGE, $usageData);
        
        // Update local usage statistics
        $this->updateUsageStats($endpoint);
    }

    /**
     * Track environment information (sent once per session)
     */
    private function trackEnvironment(): void
    {
        $environmentData = [
            'sdk_version' => $this->config->getSdkVersion(),
            'sdk_type' => 'php',
            'php_version' => PHP_VERSION,
            'php_sapi' => PHP_SAPI,
            'os' => PHP_OS,
            'timezone' => date_default_timezone_get(),
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'session_id' => $this->generateSessionId(),
            'timestamp' => microtime(true)
        ];

        // Add framework detection if possible
        $framework = $this->detectFramework();
        if ($framework) {
            $environmentData['framework'] = $framework;
        }

        $this->addToQueue(self::TYPE_ENVIRONMENT, $environmentData);
    }

    /**
     * Flush telemetry queue to server
     */
    public function flush(): void
    {
        if (!$this->enabled || ($this->queue === null || $this->queue === '' || $this->queue === []) || $this->client === null) {
            return;
        }

        try {
            $payload = [
                'sdk_version' => $this->config->getSdkVersion(),
                'api_key_id' => $this->config->getApiKeyIdentification(),
                'privacy_mode' => $this->privacyMode,
                'telemetry_version' => '2.0',
                'batch_size' => count($this->queue),
                'events' => $this->queue,
                'timestamp' => microtime(true)
            ];

            $endpoint = $this->config->getTelemetryEndpoint();
            
            $this->client->post($endpoint, [
                'json' => $payload,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-SDK-Version' => $this->config->getSdkVersion(),
                    'X-Telemetry-Version' => '2.0',
                ],
            ]);

            // Clear queue after successful send
            $this->queue = [];
            $this->lastFlushTime = time();
            
        } catch (GuzzleException $e) {
            // Silently fail - telemetry should never break the application
            $this->queue = []; // Clear queue to prevent memory leaks
        }
    }

    /**
     * Get local performance statistics
     *
     * @return array<string, mixed>
     */
    public function getPerformanceStats(): array
    {
        return $this->performanceMetrics;
    }

    /**
     * Get local usage statistics
     *
     * @return array<string, mixed>
     */
    public function getUsageStats(): array
    {
        return $this->usageStats;
    }

    /**
     * Check if telemetry is enabled
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Force disable telemetry (emergency switch)
     */
    public function disable(): void
    {
        $this->enabled = false;
        $this->queue = [];
    }

    /**
     * Add event to telemetry queue
     *
     * @param string $type Event type
     * @param array<string, mixed> $data Event data
     */
    private function addToQueue(string $type, array $data): void
    {
        $this->queue[] = [
            'type' => $type,
            'data' => $data
        ];

        // Auto-flush if queue is full or time interval reached
        if (count($this->queue) >= $this->maxQueueSize || 
            (time() - $this->lastFlushTime) >= $this->flushInterval) {
            $this->flush();
        }
    }

    /**
     * Determine if this event should be sampled
     */
    private function shouldSample(): bool
    {
        return (mt_rand() / mt_getrandmax()) < $this->samplingRate;
    }

    /**
     * Update local performance statistics
     */
    private function updatePerformanceStats(string $endpoint, float $responseTime, int $statusCode): void
    {
        if (!isset($this->performanceMetrics[$endpoint])) {
            $this->performanceMetrics[$endpoint] = [
                'count' => 0,
                'total_time' => 0.0,
                'avg_time' => 0.0,
                'min_time' => PHP_FLOAT_MAX,
                'max_time' => 0.0,
                'success_count' => 0,
                'error_count' => 0
            ];
        }

        $stats = &$this->performanceMetrics[$endpoint];
        $stats['count']++;
        $stats['total_time'] += $responseTime;
        $stats['avg_time'] = $stats['total_time'] / $stats['count'];
        $stats['min_time'] = min($stats['min_time'], $responseTime);
        $stats['max_time'] = max($stats['max_time'], $responseTime);

        if ($statusCode >= 200 && $statusCode < 400) {
            $stats['success_count']++;
        } else {
            $stats['error_count']++;
        }
    }

    /**
     * Update local usage statistics
     */
    private function updateUsageStats(string $endpoint): void
    {
        if (!isset($this->usageStats[$endpoint])) {
            $this->usageStats[$endpoint] = 0;
        }
        $this->usageStats[$endpoint]++;
    }

    /**
     * Sanitize message to remove sensitive information
     */
    private function sanitizeMessage(string $message): string
    {
        // Remove common sensitive patterns
        $patterns = [
            '/api[_-]?key[_-]?[a-zA-Z0-9\-_]{10,}/' => '[API_KEY]',
            '/bearer[_\s]+[a-zA-Z0-9\-_\.]{10,}/i' => '[BEARER_TOKEN]',
            '/password[_\s]*[:=][_\s]*[^\s]{4,}/i' => 'password=[REDACTED]',
            '/token[_\s]*[:=][_\s]*[a-zA-Z0-9\-_\.]{10,}/i' => 'token=[REDACTED]'
        ];

        foreach ($patterns as $pattern => $replacement) {
            $message = preg_replace($pattern, $replacement, $message);
        }

        return $message;
    }

    /**
     * Get parameter types for usage analytics
     *
     * @param array<string, mixed> $parameters
     * @return array<string, string>
     */
    private function getParameterTypes(array $parameters): array
    {
        $types = [];
        foreach ($parameters as $key => $value) {
            $types[$key] = gettype($value);
        }
        return $types;
    }

    /**
     * Generate a unique session ID for this execution
     */
    private function generateSessionId(): string
    {
        return substr(md5(uniqid((string)mt_rand(), true)), 0, 16);
    }

    /**
     * Attempt to detect the PHP framework being used
     */
    private function detectFramework(): ?string
    {
        // Laravel
        if (class_exists('Illuminate\Foundation\Application')) {
            return 'laravel';
        }
        
        // Symfony
        if (class_exists('Symfony\Component\HttpKernel\Kernel')) {
            return 'symfony';
        }
        
        // CodeIgniter
        if (defined('BASEPATH') || class_exists('CodeIgniter\CodeIgniter')) {
            return 'codeigniter';
        }
        
        // WordPress
        if (function_exists('wp_version')) {
            return 'wordpress';
        }
        
        // Drupal
        if (function_exists('drupal_get_version') || class_exists('Drupal')) {
            return 'drupal';
        }

        return null;
    }
}