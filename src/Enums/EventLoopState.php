<?php

declare(strict_types=1);

namespace Wioex\SDK\Enums;

enum EventLoopState: string
{
    case IDLE = 'idle';
    case RUNNING = 'running';
    case STOPPING = 'stopping';
    case STOPPED = 'stopped';

    public function isIdle(): bool
    {
        return $this === self::IDLE;
    }

    public function isRunning(): bool
    {
        return $this === self::RUNNING;
    }

    public function isStopping(): bool
    {
        return $this === self::STOPPING;
    }

    public function isStopped(): bool
    {
        return $this === self::STOPPED;
    }

    public function isActive(): bool
    {
        return $this === self::RUNNING || $this === self::IDLE;
    }

    public function canStart(): bool
    {
        return $this === self::IDLE || $this === self::STOPPED;
    }

    public function canStop(): bool
    {
        return $this === self::RUNNING || $this === self::IDLE;
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::IDLE => 'Event loop is idle, waiting for events',
            self::RUNNING => 'Event loop is actively processing events',
            self::STOPPING => 'Event loop is stopping gracefully',
            self::STOPPED => 'Event loop has been stopped',
        };
    }

    public static function fromString(string $state): self
    {
        return match (strtolower($state)) {
            'idle' => self::IDLE,
            'running' => self::RUNNING,
            'stopping' => self::STOPPING,
            'stopped' => self::STOPPED,
            default => throw new \InvalidArgumentException("Invalid event loop state: {$state}"),
        };
    }

    public function toArray(): array
    {
        return [
            'value' => $this->value,
            'name' => $this->name,
            'description' => $this->getDescription(),
            'is_idle' => $this->isIdle(),
            'is_running' => $this->isRunning(),
            'is_stopping' => $this->isStopping(),
            'is_stopped' => $this->isStopped(),
            'is_active' => $this->isActive(),
            'can_start' => $this->canStart(),
            'can_stop' => $this->canStop(),
        ];
    }
}