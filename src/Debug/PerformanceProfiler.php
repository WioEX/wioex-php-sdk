<?php

declare(strict_types=1);

namespace Wioex\SDK\Debug;

use Wioex\SDK\Config;

class PerformanceProfiler
{
    private Config $config;
    private array $profiles = [];
    private array $activeTimers = [];
    private array $checkpoints = [];
    private array $memorySnapshots = [];
    private float $sessionStartTime;
    private int $sessionStartMemory;

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->sessionStartTime = microtime(true);
        $this->sessionStartMemory = memory_get_usage(true);
    }

    /**
     * Start profiling an operation
     */
    public function startProfiling(string $name, array $metadata = []): void
    {
        if (!$this->config->get('debug.performance_profiling', true)) {
            return;
        }

        $this->activeTimers[$name] = [
            'start_time' => microtime(true),
            'start_memory' => memory_get_usage(true),
            'start_peak_memory' => memory_get_peak_usage(true),
            'start_cpu' => $this->getCpuUsage(),
            'metadata' => $metadata,
            'checkpoints' => []
        ];
    }

    /**
     * Add checkpoint during profiling
     */
    public function checkpoint(string $profileName, string $checkpointName, array $data = []): void
    {
        if (!isset($this->activeTimers[$profileName])) {
            return;
        }

        $currentTime = microtime(true);
        $startTime = $this->activeTimers[$profileName]['start_time'];

        $checkpoint = [
            'name' => $checkpointName,
            'timestamp' => $currentTime,
            'elapsed_time' => $currentTime - $startTime,
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
            'data' => $data
        ];

        $this->activeTimers[$profileName]['checkpoints'][] = $checkpoint;
    }

    /**
     * Stop profiling and return results
     */
    public function stopProfiling(string $name): array
    {
        if (!isset($this->activeTimers[$name])) {
            return [];
        }

        $timer = $this->activeTimers[$name];
        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);
        $endPeakMemory = memory_get_peak_usage(true);
        $endCpu = $this->getCpuUsage();

        $profile = [
            'name' => $name,
            'start_time' => $timer['start_time'],
            'end_time' => $endTime,
            'execution_time' => $endTime - $timer['start_time'],
            'start_memory' => $timer['start_memory'],
            'end_memory' => $endMemory,
            'memory_used' => $endMemory - $timer['start_memory'],
            'peak_memory_start' => $timer['start_peak_memory'],
            'peak_memory_end' => $endPeakMemory,
            'peak_memory_used' => $endPeakMemory - $timer['start_peak_memory'],
            'cpu_start' => $timer['start_cpu'],
            'cpu_end' => $endCpu,
            'cpu_time' => $endCpu - $timer['start_cpu'],
            'checkpoints' => $timer['checkpoints'],
            'metadata' => $timer['metadata']
        ];

        // Add analysis
        $profile['analysis'] = $this->analyzeProfile($profile);

        $this->profiles[] = $profile;
        unset($this->activeTimers[$name]);

        return $profile;
    }

    /**
     * Profile a callable function
     */
    public function profileCallable(string $name, callable $callback, array $metadata = []): mixed
    {
        $this->startProfiling($name, $metadata);

        try {
            $result = $callback($this);
            $this->stopProfiling($name);
            return $result;
        } catch (\Throwable $e) {
            $profile = $this->stopProfiling($name);
            $profile['error'] = [
                'type' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ];
            throw $e;
        }
    }

    /**
     * Memory snapshot for tracking memory leaks
     */
    public function takeMemorySnapshot(string $name, array $context = []): array
    {
        $snapshot = [
            'name' => $name,
            'timestamp' => microtime(true),
            'memory_usage' => memory_get_usage(true),
            'memory_usage_real' => memory_get_usage(false),
            'peak_memory' => memory_get_peak_usage(true),
            'peak_memory_real' => memory_get_peak_usage(false),
            'included_files_count' => count(get_included_files()),
            'declared_classes_count' => count(get_declared_classes()),
            'context' => $context
        ];

        if (function_exists('gc_status')) {
            $snapshot['gc_status'] = gc_status();
        }

        $this->memorySnapshots[] = $snapshot;
        return $snapshot;
    }

    /**
     * Compare memory snapshots to detect leaks
     */
    public function compareMemorySnapshots(string $snapshot1Name, string $snapshot2Name): array
    {
        $snapshot1 = $this->findSnapshot($snapshot1Name);
        $snapshot2 = $this->findSnapshot($snapshot2Name);

        if (!$snapshot1 || !$snapshot2) {
            return ['error' => 'Snapshot not found'];
        }

        return [
            'memory_difference' => $snapshot2['memory_usage'] - $snapshot1['memory_usage'],
            'peak_memory_difference' => $snapshot2['peak_memory'] - $snapshot1['peak_memory'],
            'time_elapsed' => $snapshot2['timestamp'] - $snapshot1['timestamp'],
            'files_loaded' => $snapshot2['included_files_count'] - $snapshot1['included_files_count'],
            'classes_declared' => $snapshot2['declared_classes_count'] - $snapshot1['declared_classes_count'],
            'leak_detected' => ($snapshot2['memory_usage'] - $snapshot1['memory_usage']) > $this->config->get('debug.memory_leak_threshold', 1048576), // 1MB
            'snapshot1' => $snapshot1,
            'snapshot2' => $snapshot2
        ];
    }

    /**
     * Get comprehensive performance report
     */
    public function getPerformanceReport(): array
    {
        $currentTime = microtime(true);
        $currentMemory = memory_get_usage(true);
        $sessionDuration = $currentTime - $this->sessionStartTime;
        $sessionMemoryUsage = $currentMemory - $this->sessionStartMemory;

        return [
            'session' => [
                'start_time' => $this->sessionStartTime,
                'current_time' => $currentTime,
                'duration' => $sessionDuration,
                'start_memory' => $this->sessionStartMemory,
                'current_memory' => $currentMemory,
                'memory_used' => $sessionMemoryUsage,
                'peak_memory' => memory_get_peak_usage(true)
            ],
            'profiles' => $this->profiles,
            'active_timers' => array_keys($this->activeTimers),
            'memory_snapshots' => $this->memorySnapshots,
            'system_info' => $this->getSystemInfo(),
            'analysis' => $this->analyzeSession()
        ];
    }

    /**
     * Get top slow operations
     */
    public function getSlowOperations(int $limit = 10): array
    {
        $slowOperations = array_filter($this->profiles, function($profile) {
            $threshold = $this->config->get('debug.slow_operation_threshold', 2.0);
            return $profile['execution_time'] > $threshold;
        });

        usort($slowOperations, fn($a, $b) => $b['execution_time'] <=> $a['execution_time']);

        return array_slice($slowOperations, 0, $limit);
    }

    /**
     * Get memory intensive operations
     */
    public function getMemoryIntensiveOperations(int $limit = 10): array
    {
        $memoryIntensive = array_filter($this->profiles, function($profile) {
            $threshold = $this->config->get('debug.memory_intensive_threshold', 10485760); // 10MB
            return $profile['memory_used'] > $threshold;
        });

        usort($memoryIntensive, fn($a, $b) => $b['memory_used'] <=> $a['memory_used']);

        return array_slice($memoryIntensive, 0, $limit);
    }

    /**
     * Detect potential memory leaks
     */
    public function detectMemoryLeaks(): array
    {
        $leaks = [];
        
        for ($i = 1; $i < count($this->memorySnapshots); $i++) {
            $prev = $this->memorySnapshots[$i - 1];
            $curr = $this->memorySnapshots[$i];
            
            $memoryGrowth = $curr['memory_usage'] - $prev['memory_usage'];
            $threshold = $this->config->get('debug.memory_leak_threshold', 1048576); // 1MB
            
            if ($memoryGrowth > $threshold) {
                $leaks[] = [
                    'between_snapshots' => [$prev['name'], $curr['name']],
                    'memory_growth' => $memoryGrowth,
                    'time_elapsed' => $curr['timestamp'] - $prev['timestamp'],
                    'growth_rate' => $memoryGrowth / ($curr['timestamp'] - $prev['timestamp']), // bytes per second
                    'severity' => $this->calculateLeakSeverity($memoryGrowth)
                ];
            }
        }

        return $leaks;
    }

    /**
     * Generate performance recommendations
     */
    public function generateRecommendations(): array
    {
        $recommendations = [];

        // Analyze slow operations
        $slowOps = $this->getSlowOperations(5);
        if (($slowOps !== null && $slowOps !== '' && $slowOps !== [])) {
            $recommendations[] = [
                'type' => 'performance',
                'priority' => 'high',
                'message' => sprintf('Found %d slow operations. Consider optimizing operations taking more than %ss.', 
                    count($slowOps), 
                    $this->config->get('debug.slow_operation_threshold', 2.0)
                ),
                'details' => array_column($slowOps, 'name')
            ];
        }

        // Analyze memory usage
        $memoryIntensive = $this->getMemoryIntensiveOperations(5);
        if (($memoryIntensive !== null && $memoryIntensive !== '' && $memoryIntensive !== [])) {
            $recommendations[] = [
                'type' => 'memory',
                'priority' => 'medium',
                'message' => sprintf('Found %d memory-intensive operations. Consider optimizing memory usage.', count($memoryIntensive)),
                'details' => array_map(fn($op) => [
                    'name' => $op['name'],
                    'memory_used' => $this->formatBytes($op['memory_used'])
                ], $memoryIntensive)
            ];
        }

        // Check for memory leaks
        $leaks = $this->detectMemoryLeaks();
        if (($leaks !== null && $leaks !== '' && $leaks !== [])) {
            $recommendations[] = [
                'type' => 'memory_leak',
                'priority' => 'critical',
                'message' => sprintf('Detected %d potential memory leaks.', count($leaks)),
                'details' => $leaks
            ];
        }

        // Check overall performance trends
        if (count($this->profiles) > 10) {
            $avgExecutionTime = array_sum(array_column($this->profiles, 'execution_time')) / count($this->profiles);
            if ($avgExecutionTime > 1.0) {
                $recommendations[] = [
                    'type' => 'general_performance',
                    'priority' => 'medium',
                    'message' => sprintf('Average operation time is %.2fs. Consider general performance optimizations.', $avgExecutionTime)
                ];
            }
        }

        return $recommendations;
    }

    /**
     * Clear all profiling data
     */
    public function clearProfiles(): void
    {
        $this->profiles = [];
        $this->activeTimers = [];
        $this->memorySnapshots = [];
        $this->sessionStartTime = microtime(true);
        $this->sessionStartMemory = memory_get_usage(true);
    }

    /**
     * Get CPU usage information
     */
    private function getCpuUsage(): array
    {
        if (function_exists('getrusage')) {
            $usage = getrusage();
            return [
                'user_time' => $usage['ru_utime.tv_sec'] + $usage['ru_utime.tv_usec'] / 1000000,
                'system_time' => $usage['ru_stime.tv_sec'] + $usage['ru_stime.tv_usec'] / 1000000,
                'context_switches' => $usage['ru_nvcsw'] + $usage['ru_nivcsw']
            ];
        }

        return ['user_time' => 0, 'system_time' => 0, 'context_switches' => 0];
    }

    /**
     * Analyze individual profile
     */
    private function analyzeProfile(array $profile): array
    {
        $analysis = [
            'performance_rating' => 'good',
            'memory_efficiency' => 'good',
            'recommendations' => []
        ];

        // Performance analysis
        $slowThreshold = $this->config->get('debug.slow_operation_threshold', 2.0);
        if ($profile['execution_time'] > $slowThreshold * 2) {
            $analysis['performance_rating'] = 'poor';
            $analysis['recommendations'][] = 'Execution time is significantly above threshold';
        } elseif ($profile['execution_time'] > $slowThreshold) {
            $analysis['performance_rating'] = 'fair';
            $analysis['recommendations'][] = 'Execution time exceeds recommended threshold';
        }

        // Memory analysis
        $memoryThreshold = $this->config->get('debug.memory_intensive_threshold', 10485760);
        if ($profile['memory_used'] > $memoryThreshold * 2) {
            $analysis['memory_efficiency'] = 'poor';
            $analysis['recommendations'][] = 'Memory usage is very high';
        } elseif ($profile['memory_used'] > $memoryThreshold) {
            $analysis['memory_efficiency'] = 'fair';
            $analysis['recommendations'][] = 'Memory usage is above recommended threshold';
        }

        // Checkpoint analysis
        if (($profile['checkpoints'] !== null && $profile['checkpoints'] !== '' && $profile['checkpoints'] !== [])) {
            $checkpointTimes = [];
            for ($i = 1; $i < count($profile['checkpoints']); $i++) {
                $prev = $profile['checkpoints'][$i - 1];
                $curr = $profile['checkpoints'][$i];
                $checkpointTimes[] = $curr['elapsed_time'] - $prev['elapsed_time'];
            }

            if (($checkpointTimes !== null && $checkpointTimes !== '' && $checkpointTimes !== [])) {
                $analysis['checkpoint_analysis'] = [
                    'total_checkpoints' => count($profile['checkpoints']),
                    'slowest_segment' => max($checkpointTimes),
                    'fastest_segment' => min($checkpointTimes),
                    'average_segment_time' => array_sum($checkpointTimes) / count($checkpointTimes)
                ];
            }
        }

        return $analysis;
    }

    /**
     * Analyze overall session performance
     */
    private function analyzeSession(): array
    {
        if (($this->profiles === null || $this->profiles === '' || $this->profiles === [])) {
            return ['no_data' => true];
        }

        $executionTimes = array_column($this->profiles, 'execution_time');
        $memoryUsages = array_column($this->profiles, 'memory_used');

        return [
            'total_operations' => count($this->profiles),
            'total_execution_time' => array_sum($executionTimes),
            'average_execution_time' => array_sum($executionTimes) / count($executionTimes),
            'median_execution_time' => $this->calculateMedian($executionTimes),
            'max_execution_time' => max($executionTimes),
            'min_execution_time' => min($executionTimes),
            'total_memory_used' => array_sum($memoryUsages),
            'average_memory_per_operation' => array_sum($memoryUsages) / count($memoryUsages),
            'median_memory_usage' => $this->calculateMedian($memoryUsages),
            'max_memory_usage' => max($memoryUsages),
            'performance_distribution' => $this->analyzePerformanceDistribution($executionTimes),
            'memory_distribution' => $this->analyzeMemoryDistribution($memoryUsages)
        ];
    }

    /**
     * Get system information
     */
    private function getSystemInfo(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'opcache_enabled' => function_exists('opcache_get_status') && opcache_get_status() !== false,
            'xdebug_enabled' => extension_loaded('xdebug'),
            'loaded_extensions' => get_loaded_extensions(),
            'server_api' => php_sapi_name(),
            'operating_system' => php_uname()
        ];
    }

    /**
     * Find memory snapshot by name
     */
    private function findSnapshot(string $name): ?array
    {
        foreach ($this->memorySnapshots as $snapshot) {
            if ($snapshot['name'] === $name) {
                return $snapshot;
            }
        }
        return null;
    }

    /**
     * Calculate leak severity
     */
    private function calculateLeakSeverity(int $memoryGrowth): string
    {
        $threshold = $this->config->get('debug.memory_leak_threshold', 1048576);
        
        if ($memoryGrowth > $threshold * 10) {
            return 'critical';
        } elseif ($memoryGrowth > $threshold * 5) {
            return 'high';
        } elseif ($memoryGrowth > $threshold * 2) {
            return 'medium';
        }
        
        return 'low';
    }

    /**
     * Format bytes for human readable output
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * Calculate median value
     */
    private function calculateMedian(array $values): float
    {
        sort($values);
        $count = count($values);
        $middle = floor($count / 2);
        
        if ($count % 2 === 0) {
            return ($values[$middle - 1] + $values[$middle]) / 2;
        } else {
            return $values[$middle];
        }
    }

    /**
     * Analyze performance distribution
     */
    private function analyzePerformanceDistribution(array $executionTimes): array
    {
        $slowThreshold = $this->config->get('debug.slow_operation_threshold', 2.0);
        
        $fast = array_filter($executionTimes, fn($t) => $t < $slowThreshold / 2);
        $medium = array_filter($executionTimes, fn($t) => $t >= $slowThreshold / 2 && $t < $slowThreshold);
        $slow = array_filter($executionTimes, fn($t) => $t >= $slowThreshold);

        return [
            'fast_operations' => count($fast),
            'medium_operations' => count($medium),
            'slow_operations' => count($slow),
            'fast_percentage' => round((count($fast) / count($executionTimes)) * 100, 2),
            'medium_percentage' => round((count($medium) / count($executionTimes)) * 100, 2),
            'slow_percentage' => round((count($slow) / count($executionTimes)) * 100, 2)
        ];
    }

    /**
     * Analyze memory distribution
     */
    private function analyzeMemoryDistribution(array $memoryUsages): array
    {
        $threshold = $this->config->get('debug.memory_intensive_threshold', 10485760);
        
        $light = array_filter($memoryUsages, fn($m) => $m < $threshold / 2);
        $medium = array_filter($memoryUsages, fn($m) => $m >= $threshold / 2 && $m < $threshold);
        $heavy = array_filter($memoryUsages, fn($m) => $m >= $threshold);

        return [
            'light_memory_operations' => count($light),
            'medium_memory_operations' => count($medium),
            'heavy_memory_operations' => count($heavy),
            'light_percentage' => round((count($light) / count($memoryUsages)) * 100, 2),
            'medium_percentage' => round((count($medium) / count($memoryUsages)) * 100, 2),
            'heavy_percentage' => round((count($heavy) / count($memoryUsages)) * 100, 2)
        ];
    }
}