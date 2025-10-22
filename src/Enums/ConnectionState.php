<?php

declare(strict_types=1);

namespace Wioex\SDK\Enums;

enum ConnectionState: string
{
    case IDLE = 'idle';
    case ACTIVE = 'active';
    case CONNECTING = 'connecting';
    case DISCONNECTED = 'disconnected';
    case ERROR = 'error';
    case EXPIRED = 'expired';
    case CLOSED = 'closed';

    public function getDescription(): string
    {
        return match ($this) {
            self::IDLE => 'Connection is available and ready for use',
            self::ACTIVE => 'Connection is currently being used',
            self::CONNECTING => 'Connection is being established',
            self::DISCONNECTED => 'Connection has been disconnected',
            self::ERROR => 'Connection encountered an error',
            self::EXPIRED => 'Connection has exceeded its lifetime',
            self::CLOSED => 'Connection has been explicitly closed',
        };
    }

    public function isAvailable(): bool
    {
        return $this === self::IDLE;
    }

    public function isInUse(): bool
    {
        return $this === self::ACTIVE || $this === self::CONNECTING;
    }

    public function isUnusable(): bool
    {
        return in_array($this, [self::DISCONNECTED, self::ERROR, self::EXPIRED, self::CLOSED]);
    }

    public function canBeReused(): bool
    {
        return $this === self::IDLE;
    }

    public function shouldBeRemoved(): bool
    {
        return $this->isUnusable();
    }

    public function getPriority(): int
    {
        return match ($this) {
            self::IDLE => 100,
            self::ACTIVE => 90,
            self::CONNECTING => 80,
            self::DISCONNECTED => 10,
            self::ERROR => 5,
            self::EXPIRED => 3,
            self::CLOSED => 1,
        };
    }

    public function getTransitionTimeout(): int
    {
        return match ($this) {
            self::CONNECTING => 30, // 30 seconds
            self::ACTIVE => 300,    // 5 minutes
            self::IDLE => 600,      // 10 minutes
            default => 0,
        };
    }

    public function canTransitionTo(self $newState): bool
    {
        return match ($this) {
            self::IDLE => in_array($newState, [self::ACTIVE, self::EXPIRED, self::CLOSED]),
            self::ACTIVE => in_array($newState, [self::IDLE, self::DISCONNECTED, self::ERROR, self::CLOSED]),
            self::CONNECTING => in_array($newState, [self::ACTIVE, self::ERROR, self::CLOSED]),
            self::DISCONNECTED => in_array($newState, [self::CONNECTING, self::CLOSED]),
            self::ERROR => in_array($newState, [self::CONNECTING, self::CLOSED]),
            self::EXPIRED => in_array($newState, [self::CLOSED]),
            self::CLOSED => false, // Closed connections cannot transition
        };
    }

    public static function getActiveStates(): array
    {
        return [self::IDLE, self::ACTIVE, self::CONNECTING];
    }

    public static function getInactiveStates(): array
    {
        return [self::DISCONNECTED, self::ERROR, self::EXPIRED, self::CLOSED];
    }

    public static function fromString(string $state): self
    {
        $state = strtolower(trim($state));

        return match ($state) {
            'idle' => self::IDLE,
            'active' => self::ACTIVE,
            'connecting' => self::CONNECTING,
            'disconnected' => self::DISCONNECTED,
            'error' => self::ERROR,
            'expired' => self::EXPIRED,
            'closed' => self::CLOSED,
            default => throw new \InvalidArgumentException("Invalid connection state: {$state}"),
        };
    }

    public function toArray(): array
    {
        return [
            'value' => $this->value,
            'name' => $this->name,
            'description' => $this->getDescription(),
            'is_available' => $this->isAvailable(),
            'is_in_use' => $this->isInUse(),
            'is_unusable' => $this->isUnusable(),
            'can_be_reused' => $this->canBeReused(),
            'should_be_removed' => $this->shouldBeRemoved(),
            'priority' => $this->getPriority(),
            'transition_timeout' => $this->getTransitionTimeout(),
        ];
    }
}
