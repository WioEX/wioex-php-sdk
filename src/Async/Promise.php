<?php

declare(strict_types=1);

namespace Wioex\SDK\Async;

use Wioex\SDK\Enums\PromiseState;

class Promise
{
    private PromiseState $state;
    private mixed $value = null;
    private mixed $reason = null;
    private array $onFulfilled = [];
    private array $onRejected = [];

    public function __construct()
    {
        $this->state = PromiseState::PENDING;
    }

    public function then(?callable $onFulfilled = null, ?callable $onRejected = null): self
    {
        $promise = new self();

        $this->onFulfilled[] = function ($value) use ($promise, $onFulfilled) {
            if ($onFulfilled === null) {
                $promise->resolve($value);
                return;
            }

            try {
                $result = $onFulfilled($value);
                if ($result instanceof Promise) {
                    $result->then(
                        fn($v) => $promise->resolve($v),
                        fn($r) => $promise->reject($r)
                    );
                } else {
                    $promise->resolve($result);
                }
            } catch (\Throwable $e) {
                $promise->reject($e);
            }
        };

        $this->onRejected[] = function ($reason) use ($promise, $onRejected) {
            if ($onRejected === null) {
                $promise->reject($reason);
                return;
            }

            try {
                $result = $onRejected($reason);
                if ($result instanceof Promise) {
                    $result->then(
                        fn($v) => $promise->resolve($v),
                        fn($r) => $promise->reject($r)
                    );
                } else {
                    $promise->resolve($result);
                }
            } catch (\Throwable $e) {
                $promise->reject($e);
            }
        };

        if ($this->state->isFulfilled()) {
            $this->handleFulfilled();
        } elseif ($this->state->isRejected()) {
            $this->handleRejected();
        }

        return $promise;
    }

    public function catch(callable $onRejected): self
    {
        return $this->then(null, $onRejected);
    }

    public function finally(callable $onFinally): self
    {
        return $this->then(
            function ($value) use ($onFinally) {
                $onFinally();
                return $value;
            },
            function ($reason) use ($onFinally) {
                $onFinally();
                throw $reason;
            }
        );
    }

    public function resolve(mixed $value): void
    {
        if (!$this->state->isPending()) {
            return;
        }

        $this->state = PromiseState::FULFILLED;
        $this->value = $value;
        $this->handleFulfilled();
    }

    public function reject(mixed $reason): void
    {
        if (!$this->state->isPending()) {
            return;
        }

        $this->state = PromiseState::REJECTED;
        $this->reason = $reason;
        $this->handleRejected();
    }

    public function getState(): PromiseState
    {
        return $this->state;
    }

    public function getValue(): mixed
    {
        return $this->value;
    }

    public function getReason(): mixed
    {
        return $this->reason;
    }

    public function isPending(): bool
    {
        return $this->state->isPending();
    }

    public function isFulfilled(): bool
    {
        return $this->state->isFulfilled();
    }

    public function isRejected(): bool
    {
        return $this->state->isRejected();
    }

    public function isSettled(): bool
    {
        return $this->state->isSettled();
    }

    private function handleFulfilled(): void
    {
        foreach ($this->onFulfilled as $handler) {
            $handler($this->value);
        }
        $this->onFulfilled = [];
    }

    private function handleRejected(): void
    {
        foreach ($this->onRejected as $handler) {
            $handler($this->reason);
        }
        $this->onRejected = [];
    }

    public static function resolved(mixed $value): self
    {
        $promise = new self();
        $promise->resolve($value);
        return $promise;
    }

    public static function rejected(mixed $reason): self
    {
        $promise = new self();
        $promise->reject($reason);
        return $promise;
    }

    public static function all(array $promises): self
    {
        $promise = new self();
        $results = [];
        $remaining = count($promises);

        if ($remaining === 0) {
            $promise->resolve([]);
            return $promise;
        }

        foreach ($promises as $index => $p) {
            if (!$p instanceof Promise) {
                $p = Promise::resolved($p);
            }

            $p->then(
                function ($value) use (&$results, &$remaining, $index, $promise) {
                    $results[$index] = $value;
                    $remaining--;

                    if ($remaining === 0) {
                        ksort($results);
                        $promise->resolve(array_values($results));
                    }
                },
                function ($reason) use ($promise) {
                    $promise->reject($reason);
                }
            );
        }

        return $promise;
    }

    public static function allSettled(array $promises): self
    {
        $promise = new self();
        $results = [];
        $remaining = count($promises);

        if ($remaining === 0) {
            $promise->resolve([]);
            return $promise;
        }

        foreach ($promises as $index => $p) {
            if (!$p instanceof Promise) {
                $p = Promise::resolved($p);
            }

            $p->then(
                function ($value) use (&$results, &$remaining, $index, $promise) {
                    $results[$index] = ['status' => 'fulfilled', 'value' => $value];
                    $remaining--;

                    if ($remaining === 0) {
                        ksort($results);
                        $promise->resolve(array_values($results));
                    }
                },
                function ($reason) use (&$results, &$remaining, $index, $promise) {
                    $results[$index] = ['status' => 'rejected', 'reason' => $reason];
                    $remaining--;

                    if ($remaining === 0) {
                        ksort($results);
                        $promise->resolve(array_values($results));
                    }
                }
            );
        }

        return $promise;
    }

    public static function race(array $promises): self
    {
        $promise = new self();

        foreach ($promises as $p) {
            if (!$p instanceof Promise) {
                $p = Promise::resolved($p);
            }

            $p->then(
                fn($value) => $promise->resolve($value),
                fn($reason) => $promise->reject($reason)
            );
        }

        return $promise;
    }

    public static function any(array $promises): self
    {
        $promise = new self();
        $errors = [];
        $remaining = count($promises);

        if ($remaining === 0) {
            $promise->reject(new \Exception('No promises provided'));
            return $promise;
        }

        foreach ($promises as $index => $p) {
            if (!$p instanceof Promise) {
                $p = Promise::resolved($p);
            }

            $p->then(
                function ($value) use ($promise) {
                    $promise->resolve($value);
                },
                function ($reason) use (&$errors, &$remaining, $index, $promise) {
                    $errors[$index] = $reason;
                    $remaining--;

                    if ($remaining === 0) {
                        $promise->reject(new AggregateException('All promises rejected', $errors));
                    }
                }
            );
        }

        return $promise;
    }
}

class AggregateException extends \Exception
{
    private array $errors;

    public function __construct(string $message, array $errors = [])
    {
        parent::__construct($message);
        $this->errors = $errors;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
