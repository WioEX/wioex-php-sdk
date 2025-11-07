<?php

declare(strict_types=1);

namespace Wioex\SDK\Async;

use Wioex\SDK\Enums\EventLoopState;
use Wioex\SDK\Enums\AsyncOperationType;

class EventLoop
{
    private EventLoopState $state;
    private array $timers = [];
    private array $nextTickCallbacks = [];
    private array $operationQueue = [];
    private float $lastTickTime;
    private array $statistics = [
        'total_ticks' => 0,
        'total_operations' => 0,
        'operations_by_type' => [],
        'avg_tick_time' => 0.0,
        'max_tick_time' => 0.0,
    ];

    public function __construct()
    {
        $this->state = EventLoopState::IDLE;
        $this->lastTickTime = microtime(true);
    }

    public function run(): void
    {
        if (!$this->state->canStart()) {
            throw new \RuntimeException("Cannot start event loop in state: {$this->state->value}");
        }

        $this->state = EventLoopState::RUNNING;

        while ($this->state->isActive()) {
            $this->tick();

            if ($this->shouldStop()) {
                break;
            }

            usleep(1000); // 1ms sleep to prevent busy waiting
        }

        $this->state = EventLoopState::STOPPED;
    }

    public function tick(): void
    {
        $tickStart = microtime(true);
        $this->statistics['total_ticks']++;

        try {
            // Process next tick callbacks first
            $this->processNextTickCallbacks();

            // Process timers
            $this->processTimers();

            // Process operation queue
            $this->processOperationQueue();
        } finally {
            $tickTime = microtime(true) - $tickStart;
            $this->updateTickStatistics($tickTime);
        }
    }

    public function stop(): void
    {
        if (!$this->state->canStop()) {
            throw new \RuntimeException("Cannot stop event loop in state: {$this->state->value}");
        }

        $this->state = EventLoopState::STOPPING;
    }

    public function nextTick(callable $callback, AsyncOperationType $type = AsyncOperationType::HTTP_REQUEST): void
    {
        $this->nextTickCallbacks[] = [
            'callback' => $callback,
            'type' => $type,
            'created_at' => microtime(true),
        ];
    }

    public function setTimeout(callable $callback, int $delayMs, AsyncOperationType $type = AsyncOperationType::DELAY_OPERATION): string
    {
        $timerId = uniqid('timer_', true);
        $this->timers[$timerId] = [
            'callback' => $callback,
            'type' => $type,
            'delay_ms' => $delayMs,
            'execute_at' => microtime(true) + ($delayMs / 1000),
            'created_at' => microtime(true),
            'repeating' => false,
        ];

        return $timerId;
    }

    public function setInterval(callable $callback, int $intervalMs, AsyncOperationType $type = AsyncOperationType::DELAY_OPERATION): string
    {
        $timerId = uniqid('interval_', true);
        $this->timers[$timerId] = [
            'callback' => $callback,
            'type' => $type,
            'delay_ms' => $intervalMs,
            'execute_at' => microtime(true) + ($intervalMs / 1000),
            'created_at' => microtime(true),
            'repeating' => true,
            'interval_ms' => $intervalMs,
        ];

        return $timerId;
    }

    public function clearTimeout(string $timerId): bool
    {
        if (isset($this->timers[$timerId])) {
            unset($this->timers[$timerId]);
            return true;
        }
        return false;
    }

    public function clearInterval(string $timerId): bool
    {
        return $this->clearTimeout($timerId);
    }

    public function addOperation(callable $operation, AsyncOperationType $type, int $priority = 0): void
    {
        $this->operationQueue[] = [
            'operation' => $operation,
            'type' => $type,
            'priority' => $priority,
            'created_at' => microtime(true),
        ];

        // Sort by priority (higher priority first)
        usort($this->operationQueue, fn($a, $b) => $b['priority'] <=> $a['priority']);

        $this->statistics['total_operations']++;
        $this->statistics['operations_by_type'][$type->value] =
            ($this->statistics['operations_by_type'][$type->value] ?? 0) + 1;
    }

    public function getState(): EventLoopState
    {
        return $this->state;
    }

    public function getStatistics(): array
    {
        return array_merge($this->statistics, [
            'state' => $this->state->toArray(),
            'pending_timers' => count($this->timers),
            'pending_callbacks' => count($this->nextTickCallbacks),
            'pending_operations' => count($this->operationQueue),
            'uptime_seconds' => microtime(true) - $this->lastTickTime,
        ]);
    }

    public function getPendingOperationsByType(): array
    {
        $byType = [];

        foreach ($this->operationQueue as $operation) {
            $type = $operation['type']->value;
            $byType[$type] = ($byType[$type] ?? 0) + 1;
        }

        return $byType;
    }

    public function clearAllTimers(): void
    {
        $this->timers = [];
    }

    public function clearAllCallbacks(): void
    {
        $this->nextTickCallbacks = [];
    }

    public function clearAllOperations(): void
    {
        $this->operationQueue = [];
    }

    public function reset(): void
    {
        $this->clearAllTimers();
        $this->clearAllCallbacks();
        $this->clearAllOperations();
        $this->state = EventLoopState::IDLE;
        $this->statistics = [
            'total_ticks' => 0,
            'total_operations' => 0,
            'operations_by_type' => [],
            'avg_tick_time' => 0.0,
            'max_tick_time' => 0.0,
        ];
    }

    private function processNextTickCallbacks(): void
    {
        $callbacks = $this->nextTickCallbacks;
        $this->nextTickCallbacks = [];

        foreach ($callbacks as $callbackInfo) {
            try {
                $callback = $callbackInfo['callback'];
                $callback();
            } catch (\Throwable $e) {
                // Continue with remaining callbacks
            }
        }
    }

    private function processTimers(): void
    {
        $now = microtime(true);
        $expiredTimers = [];

        foreach ($this->timers as $timerId => $timer) {
            if ($now >= $timer['execute_at']) {
                $expiredTimers[$timerId] = $timer;
            }
        }

        foreach ($expiredTimers as $timerId => $timer) {
            try {
                $callback = $timer['callback'];
                $callback();

                if ($timer['repeating']) {
                    // Reschedule repeating timer
                    $this->timers[$timerId]['execute_at'] = $now + ($timer['interval_ms'] / 1000);
                } else {
                    unset($this->timers[$timerId]);
                }
            } catch (\Throwable $e) {
                unset($this->timers[$timerId]);
            }
        }
    }

    private function processOperationQueue(): void
    {
        if (count($this->operationQueue) === 0) {
            return;
        }

        // Process one operation per tick to maintain responsiveness
        $operationInfo = array_shift($this->operationQueue);

        try {
            $operation = $operationInfo['operation'];
            $operation();
        } catch (\Throwable $e) {
            // Continue processing operations
        }
    }

    private function shouldStop(): bool
    {
        return $this->state->isStopping() ||
               ($this->state->isIdle() && $this->isEmpty());
    }

    private function isEmpty(): bool
    {
        return count($this->timers) === 0 &&
               count($this->nextTickCallbacks) === 0 &&
               count($this->operationQueue) === 0;
    }

    private function updateTickStatistics(float $tickTime): void
    {
        $this->statistics['max_tick_time'] = max($this->statistics['max_tick_time'], $tickTime);

        $totalTicks = $this->statistics['total_ticks'];
        $currentAvg = $this->statistics['avg_tick_time'];
        $this->statistics['avg_tick_time'] = (($currentAvg * ($totalTicks - 1)) + $tickTime) / $totalTicks;
    }

    public function getHealthMetrics(): array
    {
        $stats = $this->getStatistics();

        return [
            'state' => $this->state->value,
            'is_healthy' => $this->isHealthy(),
            'performance' => [
                'avg_tick_time_ms' => $stats['avg_tick_time'] * 1000,
                'max_tick_time_ms' => $stats['max_tick_time'] * 1000,
                'ticks_per_second' => $stats['total_ticks'] / max($stats['uptime_seconds'], 1),
            ],
            'load' => [
                'pending_operations' => $stats['pending_operations'],
                'pending_timers' => $stats['pending_timers'],
                'pending_callbacks' => $stats['pending_callbacks'],
            ],
            'operation_distribution' => $this->getPendingOperationsByType(),
        ];
    }

    private function isHealthy(): bool
    {
        $stats = $this->getStatistics();

        // Consider unhealthy if:
        // - Average tick time is too high (> 10ms)
        // - Too many pending operations (> 1000)
        // - Event loop is stopped unexpectedly

        return $stats['avg_tick_time'] < 0.01 &&
               $stats['pending_operations'] < 1000 &&
               ($this->state->isActive() || $this->state->isStopped());
    }
}
