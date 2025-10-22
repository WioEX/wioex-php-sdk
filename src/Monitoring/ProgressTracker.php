<?php

declare(strict_types=1);

namespace Wioex\SDK\Monitoring;

/**
 * Progress Tracker for Bulk Operations
 * 
 * Tracks progress of bulk operations and provides ETA calculations
 * based on real-time processing speed and historical data.
 */
class ProgressTracker
{
    private int $totalItems;
    private int $processedItems = 0;
    private int $failedItems = 0;
    private float $startTime;
    private array $chunkTimes = [];
    private array $progressCallbacks = [];
    private $progressCallback = null;
    
    // ETA calculation parameters
    private const ETA_SMOOTHING_FACTOR = 0.3; // For exponential smoothing
    private const MIN_SAMPLES_FOR_ETA = 2;    // Minimum chunks processed before ETA calculation
    
    public function __construct(int $totalItems, $progressCallback = null)
    {
        $this->totalItems = $totalItems;
        $this->progressCallback = $progressCallback;
        $this->startTime = microtime(true);
    }

    /**
     * Report progress for a completed chunk
     */
    public function reportChunkProgress(int $chunkIndex, int $itemsInChunk, float $chunkTime, bool $success = true): void
    {
        $this->chunkTimes[] = [
            'chunk_index' => $chunkIndex,
            'items' => $itemsInChunk,
            'time' => $chunkTime,
            'success' => $success,
            'timestamp' => microtime(true)
        ];

        if ($success) {
            $this->processedItems += $itemsInChunk;
        } else {
            $this->failedItems += $itemsInChunk;
        }

        // Trigger progress callback if registered
        if ($this->progressCallback !== null) {
            $progress = $this->getCurrentProgress();
            call_user_func($this->progressCallback, $progress);
        }
    }

    /**
     * Get current progress information
     */
    public function getCurrentProgress(): array
    {
        $totalProcessed = $this->processedItems + $this->failedItems;
        $progressPercent = $this->totalItems > 0 ? ($totalProcessed / $this->totalItems) * 100 : 0;
        $elapsed = microtime(true) - $this->startTime;
        
        return [
            'total_items' => $this->totalItems,
            'processed_items' => $this->processedItems,
            'failed_items' => $this->failedItems,
            'remaining_items' => $this->totalItems - $totalProcessed,
            'progress_percent' => round($progressPercent, 2),
            'elapsed_time' => $elapsed,
            'chunks_completed' => count($this->chunkTimes),
            'success_rate' => $totalProcessed > 0 ? round(($this->processedItems / $totalProcessed) * 100, 2) : 0,
            'estimated_total_time' => $this->calculateEstimatedTotalTime(),
            'estimated_remaining_time' => $this->calculateETA(),
            'processing_speed' => $this->calculateProcessingSpeed(),
            'is_complete' => $totalProcessed >= $this->totalItems
        ];
    }

    /**
     * Calculate Estimated Time of Arrival (ETA)
     */
    public function calculateETA(): ?float
    {
        if (count($this->chunkTimes) < self::MIN_SAMPLES_FOR_ETA) {
            return null; // Not enough data for accurate ETA
        }

        $remaining = $this->totalItems - ($this->processedItems + $this->failedItems);
        if ($remaining <= 0) {
            return 0; // Already complete
        }

        $speed = $this->calculateProcessingSpeed();
        if ($speed <= 0) {
            return null; // Cannot calculate ETA with zero speed
        }

        return $remaining / $speed;
    }

    /**
     * Calculate processing speed (items per second)
     */
    public function calculateProcessingSpeed(): float
    {
        if (empty($this->chunkTimes)) {
            return 0;
        }

        $totalTime = 0;
        $totalItems = 0;

        // Use exponential smoothing for recent chunks (more weight to recent performance)
        $recentChunks = array_slice($this->chunkTimes, -5); // Last 5 chunks
        $weight = 1.0;
        $totalWeight = 0;

        foreach (array_reverse($recentChunks) as $chunk) {
            if ($chunk['success'] && $chunk['time'] > 0) {
                $chunkSpeed = $chunk['items'] / $chunk['time'];
                $totalTime += $chunkSpeed * $weight;
                $totalWeight += $weight;
                $weight *= (1 - self::ETA_SMOOTHING_FACTOR);
            }
        }

        return $totalWeight > 0 ? $totalTime / $totalWeight : 0;
    }

    /**
     * Calculate estimated total completion time
     */
    public function calculateEstimatedTotalTime(): ?float
    {
        $speed = $this->calculateProcessingSpeed();
        if ($speed <= 0) {
            return null;
        }

        return $this->totalItems / $speed;
    }

    /**
     * Get detailed chunk statistics
     */
    public function getChunkStatistics(): array
    {
        if (empty($this->chunkTimes)) {
            return [
                'total_chunks' => 0,
                'successful_chunks' => 0,
                'failed_chunks' => 0,
                'average_chunk_time' => 0,
                'fastest_chunk_time' => 0,
                'slowest_chunk_time' => 0,
                'chunk_success_rate' => 0
            ];
        }

        $successfulChunks = array_filter($this->chunkTimes, fn($chunk) => $chunk['success']);
        $failedChunks = array_filter($this->chunkTimes, fn($chunk) => !$chunk['success']);
        
        $successfulTimes = array_column($successfulChunks, 'time');
        
        return [
            'total_chunks' => count($this->chunkTimes),
            'successful_chunks' => count($successfulChunks),
            'failed_chunks' => count($failedChunks),
            'average_chunk_time' => !empty($successfulTimes) ? array_sum($successfulTimes) / count($successfulTimes) : 0,
            'fastest_chunk_time' => !empty($successfulTimes) ? min($successfulTimes) : 0,
            'slowest_chunk_time' => !empty($successfulTimes) ? max($successfulTimes) : 0,
            'chunk_success_rate' => count($this->chunkTimes) > 0 ? (count($successfulChunks) / count($this->chunkTimes)) * 100 : 0
        ];
    }

    /**
     * Format time duration for display
     */
    public static function formatDuration(?float $seconds): string
    {
        if ($seconds === null || $seconds < 0) {
            return 'Unknown';
        }

        if ($seconds < 1) {
            return '<1s';
        }

        if ($seconds < 60) {
            return sprintf('%.1fs', $seconds);
        }

        if ($seconds < 3600) {
            $minutes = floor($seconds / 60);
            $remainingSeconds = $seconds % 60;
            return sprintf('%dm %.1fs', $minutes, $remainingSeconds);
        }

        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        return sprintf('%dh %dm', $hours, $minutes);
    }

    /**
     * Get human-readable progress summary
     */
    public function getProgressSummary(): string
    {
        $progress = $this->getCurrentProgress();
        
        $summary = sprintf(
            "Progress: %d/%d items (%.1f%%) | Speed: %.1f items/s",
            $progress['processed_items'],
            $progress['total_items'],
            $progress['progress_percent'],
            $progress['processing_speed']
        );

        if ($progress['estimated_remaining_time'] !== null) {
            $summary .= sprintf(
                " | ETA: %s",
                self::formatDuration($progress['estimated_remaining_time'])
            );
        }

        if ($progress['failed_items'] > 0) {
            $summary .= sprintf(" | Failed: %d", $progress['failed_items']);
        }

        return $summary;
    }

    /**
     * Check if operation is complete
     */
    public function isComplete(): bool
    {
        return ($this->processedItems + $this->failedItems) >= $this->totalItems;
    }

    /**
     * Get completion status
     */
    public function getCompletionStatus(): array
    {
        $progress = $this->getCurrentProgress();
        $stats = $this->getChunkStatistics();
        
        return [
            'is_complete' => $this->isComplete(),
            'total_time' => $progress['elapsed_time'],
            'items_processed' => $this->processedItems,
            'items_failed' => $this->failedItems,
            'success_rate' => $progress['success_rate'],
            'average_speed' => $progress['processing_speed'],
            'chunk_statistics' => $stats,
            'summary' => $this->getProgressSummary()
        ];
    }

    /**
     * Set progress callback for real-time updates
     */
    public function setProgressCallback($callback): void
    {
        $this->progressCallback = $callback;
    }

    /**
     * Remove progress callback
     */
    public function removeProgressCallback(): void
    {
        $this->progressCallback = null;
    }

    /**
     * Reset tracker for reuse
     */
    public function reset(int $newTotalItems = null): void
    {
        $this->totalItems = $newTotalItems ?? $this->totalItems;
        $this->processedItems = 0;
        $this->failedItems = 0;
        $this->startTime = microtime(true);
        $this->chunkTimes = [];
    }

    /**
     * Export progress data for external analysis
     */
    public function exportProgressData(): array
    {
        return [
            'operation_info' => [
                'total_items' => $this->totalItems,
                'start_time' => $this->startTime,
                'duration' => microtime(true) - $this->startTime
            ],
            'current_progress' => $this->getCurrentProgress(),
            'chunk_details' => $this->chunkTimes,
            'statistics' => $this->getChunkStatistics(),
            'completion_status' => $this->getCompletionStatus()
        ];
    }
}