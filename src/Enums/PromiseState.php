<?php

declare(strict_types=1);

namespace Wioex\SDK\Enums;

enum PromiseState: string
{
    case PENDING = 'pending';
    case FULFILLED = 'fulfilled';
    case REJECTED = 'rejected';

    public function isPending(): bool
    {
        return $this === self::PENDING;
    }

    public function isFulfilled(): bool
    {
        return $this === self::FULFILLED;
    }

    public function isRejected(): bool
    {
        return $this === self::REJECTED;
    }

    public function isSettled(): bool
    {
        return $this !== self::PENDING;
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::PENDING => 'Promise is waiting for resolution',
            self::FULFILLED => 'Promise has been resolved successfully',
            self::REJECTED => 'Promise has been rejected with an error',
        };
    }

    public static function fromString(string $state): self
    {
        return match (strtolower($state)) {
            'pending' => self::PENDING,
            'fulfilled', 'resolved' => self::FULFILLED,
            'rejected', 'failed' => self::REJECTED,
            default => throw new \InvalidArgumentException("Invalid promise state: {$state}"),
        };
    }

    public function canTransitionTo(self $newState): bool
    {
        return match ($this) {
            self::PENDING => $newState->isSettled(),
            self::FULFILLED, self::REJECTED => false,
        };
    }

    public function toArray(): array
    {
        return [
            'value' => $this->value,
            'name' => $this->name,
            'description' => $this->getDescription(),
            'is_pending' => $this->isPending(),
            'is_fulfilled' => $this->isFulfilled(),
            'is_rejected' => $this->isRejected(),
            'is_settled' => $this->isSettled(),
        ];
    }
}