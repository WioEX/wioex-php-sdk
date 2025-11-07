<?php

declare(strict_types=1);

namespace Wioex\SDK\Debug;

use Wioex\SDK\Config;
use Wioex\SDK\Cache\CacheInterface;

class DebugManager
{
    private Config $config;
    private ?CacheInterface $cache;
    private array $queryLog = [];
    private array $performanceLog = [];
    private array $errorLog = [];
    private array $validationLog = [];
    private float $startTime;
    private int $startMemory;
    private array $metrics = [
        'total_queries' => 0,
        'total_errors' => 0,
        'total_validation_failures' => 0,
        'execution_time' => 0,
        'memory_usage' => 0
    ];

    public function __construct(Config $config, ?CacheInterface $cache = null)
    {
        $this->config = $config;
        $this->cache = $cache;
        $this->startTime = microtime(true);
        $this->startMemory = memory_get_usage(true);
    }

    /**
     * Check if debug mode is enabled
     */
    public function isDebugEnabled(): bool
    {
        return (bool) $this->config->get('debug.enabled', false);
    }

    /**
     * Log API query with detailed information
     */
    public function logQuery(string $method, string $url, array $headers = [], string $body = '', ?array $response = null, float $executionTime = 0): void
    {
        if (!$this->isDebugEnabled() || !(bool) $this->config->get('debug.query_logging', true)) {
            return;
        }

        $this->metrics['total_queries']++;
        $this->metrics['execution_time'] += $executionTime;

        $logEntry = [
            'timestamp' => microtime(true),
            'method' => $method,
            'url' => $url,
            'headers' => $this->sanitizeHeaders($headers),
            'body' => $this->sanitizeBody($body),
            'response' => $this->sanitizeResponse($response),
            'execution_time' => $executionTime,
            'memory_usage' => memory_get_usage(true),
            'trace' => $this->config->get('debug.include_trace', false) ? debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10) : null
        ];

        $this->queryLog[] = $logEntry;

        // Limit log size
        $maxEntries = $this->config->get('debug.max_log_entries', 1000);
        if (count($this->queryLog) > $maxEntries) {
            $this->queryLog = array_slice($this->queryLog, -$maxEntries);
        }

        $this->persistLog('query', $logEntry);
    }

    /**
     * Log performance metrics
     */
    public function logPerformance(string $operation, float $executionTime, array $metrics = []): void
    {
        if (!$this->isDebugEnabled() || !$this->config->get('debug.performance_profiling', true)) {
            return;
        }

        $logEntry = [
            'timestamp' => microtime(true),
            'operation' => $operation,
            'execution_time' => $executionTime,
            'memory_before' => $metrics['memory_before'] ?? 0,
            'memory_after' => $metrics['memory_after'] ?? memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'cpu_time' => $metrics['cpu_time'] ?? 0,
            'additional_metrics' => $metrics['custom'] ?? []
        ];

        $this->performanceLog[] = $logEntry;

        // Check for slow operations
        $slowThreshold = $this->config->get('debug.slow_operation_threshold', 2.0);
        if ($executionTime > $slowThreshold) {
            $this->logSlowOperation($operation, $executionTime, $logEntry);
        }

        $this->persistLog('performance', $logEntry);
    }

    /**
     * Log validation failures
     */
    public function logValidationFailure(string $type, array $data, array $errors, array $context = []): void
    {
        if (!$this->isDebugEnabled() || !$this->config->get('debug.response_validation', true)) {
            return;
        }

        $this->metrics['total_validation_failures']++;

        $logEntry = [
            'timestamp' => microtime(true),
            'type' => $type,
            'data' => $this->sanitizeData($data),
            'errors' => $errors,
            'context' => $context,
            'stack_trace' => $this->config->get('debug.include_trace', false) ? debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5) : null
        ];

        $this->validationLog[] = $logEntry;
        $this->persistLog('validation', $logEntry);
    }

    /**
     * Log errors with context
     */
    public function logError(\Throwable $error, array $context = []): void
    {
        if (!$this->isDebugEnabled()) {
            return;
        }

        $this->metrics['total_errors']++;

        $logEntry = [
            'timestamp' => microtime(true),
            'type' => get_class($error),
            'message' => $error->getMessage(),
            'code' => $error->getCode(),
            'file' => $error->getFile(),
            'line' => $error->getLine(),
            'trace' => $error->getTraceAsString(),
            'context' => $context,
            'memory_usage' => memory_get_usage(true)
        ];

        $this->errorLog[] = $logEntry;
        $this->persistLog('error', $logEntry);
    }

    /**
     * Profile a function or code block
     */
    public function profile(string $name, callable $callback): mixed
    {
        if (!$this->isDebugEnabled() || !$this->config->get('debug.performance_profiling', true)) {
            return $callback();
        }

        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);
        $startCpuTime = $this->getCpuTime();

        try {
            $result = $callback();
            
            $endTime = microtime(true);
            $endMemory = memory_get_usage(true);
            $endCpuTime = $this->getCpuTime();

            $this->logPerformance($name, $endTime - $startTime, [
                'memory_before' => $startMemory,
                'memory_after' => $endMemory,
                'cpu_time' => $endCpuTime - $startCpuTime,
                'custom' => ['status' => 'success']
            ]);

            return $result;
        } catch (\Throwable $e) {
            $endTime = microtime(true);
            $endMemory = memory_get_usage(true);

            $this->logPerformance($name, $endTime - $startTime, [
                'memory_before' => $startMemory,
                'memory_after' => $endMemory,
                'custom' => ['status' => 'error', 'error' => $e->getMessage()]
            ]);

            $this->logError($e, ['operation' => $name]);
            throw $e;
        }
    }

    /**
     * Start profiling timer
     */
    public function startTimer(string $name): void
    {
        if (!$this->isDebugEnabled()) {
            return;
        }

        $this->performanceLog[$name . '_timer'] = [
            'start_time' => microtime(true),
            'start_memory' => memory_get_usage(true),
            'start_cpu' => $this->getCpuTime()
        ];
    }

    /**
     * Stop profiling timer
     */
    public function stopTimer(string $name): array
    {
        if (!$this->isDebugEnabled()) {
            return [];
        }

        $timerKey = $name . '_timer';
        if (!isset($this->performanceLog[$timerKey])) {
            return [];
        }

        $startData = $this->performanceLog[$timerKey];
        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);
        $endCpu = $this->getCpuTime();

        $metrics = [
            'name' => $name,
            'execution_time' => $endTime - $startData['start_time'],
            'memory_used' => $endMemory - $startData['start_memory'],
            'cpu_time' => $endCpu - $startData['start_cpu']
        ];

        unset($this->performanceLog[$timerKey]);

        $this->logPerformance($name, $metrics['execution_time'], [
            'memory_before' => $startData['start_memory'],
            'memory_after' => $endMemory,
            'cpu_time' => $metrics['cpu_time']
        ]);

        return $metrics;
    }

    /**
     * Get debug information summary
     */
    public function getDebugSummary(): array
    {
        $currentTime = microtime(true);
        $currentMemory = memory_get_usage(true);
        
        $this->metrics['execution_time'] = $currentTime - $this->startTime;
        $this->metrics['memory_usage'] = $currentMemory - $this->startMemory;

        return [
            'debug_enabled' => $this->isDebugEnabled(),
            'session_duration' => $this->metrics['execution_time'],
            'memory_used' => $this->metrics['memory_usage'],
            'peak_memory' => memory_get_peak_usage(true),
            'total_queries' => $this->metrics['total_queries'],
            'total_errors' => $this->metrics['total_errors'],
            'total_validation_failures' => $this->metrics['total_validation_failures'],
            'query_log_entries' => count($this->queryLog),
            'performance_log_entries' => count($this->performanceLog),
            'error_log_entries' => count($this->errorLog),
            'validation_log_entries' => count($this->validationLog),
            'average_query_time' => $this->metrics['total_queries'] > 0 
                ? $this->metrics['execution_time'] / $this->metrics['total_queries'] 
                : 0
        ];
    }

    /**
     * Get query log with filtering options
     */
    public function getQueryLog(array $filters = []): array
    {
        $log = $this->queryLog;

        if (isset($filters['method'])) {
            $log = array_filter($log, fn($entry) => strtoupper($entry['method']) === strtoupper($filters['method']));
        }

        if (isset($filters['url_pattern'])) {
            $log = array_filter($log, fn($entry) => preg_match($filters['url_pattern'], $entry['url']));
        }

        if (isset($filters['min_execution_time'])) {
            $log = array_filter($log, fn($entry) => $entry['execution_time'] >= $filters['min_execution_time']);
        }

        if (isset($filters['since'])) {
            $log = array_filter($log, fn($entry) => $entry['timestamp'] >= $filters['since']);
        }

        return array_values($log);
    }

    /**
     * Get performance log with analysis
     */
    public function getPerformanceLog(): array
    {
        $log = $this->performanceLog;
        
        // Add performance analysis
        $analysis = $this->analyzePerformance($log);
        
        return [
            'entries' => $log,
            'analysis' => $analysis
        ];
    }

    /**
     * Get error log
     */
    public function getErrorLog(): array
    {
        return $this->errorLog;
    }

    /**
     * Get validation log
     */
    public function getValidationLog(): array
    {
        return $this->validationLog;
    }

    /**
     * Export debug data to file
     */
    public function exportDebugData(string $format = 'json', string $filename = ''): string
    {
        $data = [
            'summary' => $this->getDebugSummary(),
            'query_log' => $this->queryLog,
            'performance_log' => $this->performanceLog,
            'error_log' => $this->errorLog,
            'validation_log' => $this->validationLog,
            'exported_at' => date('Y-m-d H:i:s'),
            'config' => $this->config->get('debug', [])
        ];

        if (empty($filename)) {
            $filename = 'debug_export_' . date('Y-m-d_H-i-s') . '.' . $format;
        }

        $content = match (strtolower($format)) {
            'json' => json_encode($data, JSON_PRETTY_PRINT),
            'csv' => $this->exportToCsv($data),
            'html' => $this->exportToHtml($data),
            default => json_encode($data, JSON_PRETTY_PRINT)
        };

        file_put_contents($filename, $content);
        return $filename;
    }

    /**
     * Clear all debug logs
     */
    public function clearLogs(): void
    {
        $this->queryLog = [];
        $this->performanceLog = [];
        $this->errorLog = [];
        $this->validationLog = [];
        
        $this->metrics = [
            'total_queries' => 0,
            'total_errors' => 0,
            'total_validation_failures' => 0,
            'execution_time' => 0,
            'memory_usage' => 0
        ];

        $this->startTime = microtime(true);
        $this->startMemory = memory_get_usage(true);
    }

    /**
     * Get real-time debugging information
     */
    public function getRealTimeDebugInfo(): array
    {
        return [
            'timestamp' => microtime(true),
            'memory_current' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'included_files_count' => count(get_included_files()),
            'declared_classes_count' => count(get_declared_classes()),
            'active_timers' => array_keys(array_filter($this->performanceLog, fn($k) => str_ends_with($k, '_timer'), ARRAY_FILTER_USE_KEY)),
            'recent_queries' => array_slice($this->queryLog, -5),
            'recent_errors' => array_slice($this->errorLog, -3)
        ];
    }

    /**
     * Sanitize headers for logging
     */
    private function sanitizeHeaders(array $headers): array
    {
        $sensitiveHeaders = ['authorization', 'x-api-key', 'x-wioex-signature'];
        $sanitized = [];
        
        foreach ($headers as $key => $value) {
            if (in_array(strtolower($key), $sensitiveHeaders)) {
                $sanitized[$key] = '***REDACTED***';
            } else {
                $sanitized[$key] = $value;
            }
        }
        
        return $sanitized;
    }

    /**
     * Sanitize request body for logging
     */
    private function sanitizeBody(string $body): string
    {
        if (empty($body)) {
            return $body;
        }

        $maxLength = $this->config->get('debug.max_body_length', 10000);
        if (strlen($body) > $maxLength) {
            return substr($body, 0, $maxLength) . '... [TRUNCATED]';
        }

        return $body;
    }

    /**
     * Sanitize response for logging
     */
    private function sanitizeResponse(?array $response): ?array
    {
        if ($response === null) {
            return null;
        }

        $maxDepth = $this->config->get('debug.max_response_depth', 5);
        return $this->limitArrayDepth($response, $maxDepth);
    }

    /**
     * Sanitize general data for logging
     */
    private function sanitizeData(array $data): array
    {
        $maxDepth = $this->config->get('debug.max_data_depth', 3);
        return $this->limitArrayDepth($data, $maxDepth);
    }

    /**
     * Limit array depth to prevent excessive logging
     */
    private function limitArrayDepth(array $array, int $maxDepth, int $currentDepth = 0): array
    {
        if ($currentDepth >= $maxDepth) {
            return ['...DEPTH_LIMIT_REACHED'];
        }

        $result = [];
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $result[$key] = $this->limitArrayDepth($value, $maxDepth, $currentDepth + 1);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * Log slow operations
     */
    private function logSlowOperation(string $operation, float $executionTime, array $logEntry): void
    {
        $slowLog = [
            'timestamp' => microtime(true),
            'operation' => $operation,
            'execution_time' => $executionTime,
            'threshold' => $this->config->get('debug.slow_operation_threshold', 2.0),
            'details' => $logEntry
        ];

        $this->persistLog('slow_operations', $slowLog);
    }

    /**
     * Persist log entry to cache if available
     */
    private function persistLog(string $type, array $entry): void
    {
        if (!$this->cache || !$this->config->get('debug.persist_logs', false)) {
            return;
        }

        $cacheKey = "debug_log:{$type}:" . date('Y-m-d');
        $existingLogs = $this->cache->get($cacheKey) ?? [];
        $existingLogs[] = $entry;

        // Limit cached log size
        $maxCachedEntries = $this->config->get('debug.max_cached_entries', 100);
        if (count($existingLogs) > $maxCachedEntries) {
            $existingLogs = array_slice($existingLogs, -$maxCachedEntries);
        }

        $this->cache->set($cacheKey, $existingLogs, 86400); // 24 hours
    }

    /**
     * Get CPU time (if available)
     */
    private function getCpuTime(): float
    {
        if (function_exists('getrusage')) {
            $usage = getrusage();
            return ($usage['ru_utime.tv_sec'] + $usage['ru_utime.tv_usec'] / 1000000) +
                   ($usage['ru_stime.tv_sec'] + $usage['ru_stime.tv_usec'] / 1000000);
        }
        return 0.0;
    }

    /**
     * Analyze performance data
     */
    private function analyzePerformance(array $log): array
    {
        if (empty($log)) {
            return ['no_data' => true];
        }

        $executionTimes = array_column($log, 'execution_time');
        $memoryUsage = array_map(fn($entry) => $entry['memory_after'] - $entry['memory_before'], $log);

        return [
            'total_operations' => count($log),
            'average_execution_time' => array_sum($executionTimes) / count($executionTimes),
            'max_execution_time' => max($executionTimes),
            'min_execution_time' => min($executionTimes),
            'total_memory_used' => array_sum($memoryUsage),
            'average_memory_per_operation' => array_sum($memoryUsage) / count($memoryUsage),
            'slow_operations' => array_filter($log, fn($entry) => $entry['execution_time'] > $this->config->get('debug.slow_operation_threshold', 2.0))
        ];
    }

    /**
     * Export to CSV format
     */
    private function exportToCsv(array $data): string
    {
        $csv = "Type,Timestamp,Details\n";
        
        foreach ($data['query_log'] as $entry) {
            $csv .= sprintf("Query,%s,\"%s %s - %sms\"\n", 
                date('Y-m-d H:i:s', (int)$entry['timestamp']),
                $entry['method'],
                $entry['url'],
                round($entry['execution_time'] * 1000, 2)
            );
        }

        foreach ($data['error_log'] as $entry) {
            $csv .= sprintf("Error,%s,\"%s: %s\"\n",
                date('Y-m-d H:i:s', (int)$entry['timestamp']),
                $entry['type'],
                str_replace('"', '""', $entry['message'])
            );
        }

        return $csv;
    }

    /**
     * Export to HTML format
     */
    private function exportToHtml(array $data): string
    {
        $html = '<!DOCTYPE html><html><head><title>Debug Report</title></head><body>';
        $html .= '<h1>Debug Report</h1>';
        $html .= '<h2>Summary</h2>';
        $html .= '<ul>';
        
        foreach ($data['summary'] as $key => $value) {
            $html .= "<li><strong>{$key}:</strong> {$value}</li>";
        }
        
        $html .= '</ul>';
        $html .= '<h2>Query Log</h2><table border="1">';
        $html .= '<tr><th>Timestamp</th><th>Method</th><th>URL</th><th>Execution Time</th></tr>';
        
        foreach ($data['query_log'] as $entry) {
            $html .= sprintf('<tr><td>%s</td><td>%s</td><td>%s</td><td>%sms</td></tr>',
                date('Y-m-d H:i:s', (int)$entry['timestamp']),
                htmlspecialchars($entry['method']),
                htmlspecialchars($entry['url']),
                round($entry['execution_time'] * 1000, 2)
            );
        }
        
        $html .= '</table></body></html>';
        return $html;
    }
}