<?php

declare(strict_types=1);

namespace Wioex\SDK\Exceptions;

class RateLimitException extends WioexException
{
    private ?int $retryAfter = null;

    public static function exceeded(?int $retryAfter = null): self
    {
        $message = 'Rate limit exceeded.';
        if ($retryAfter) {
            $message .= " Retry after {$retryAfter} seconds.";
        }

        $exception = new self($message);
        $exception->retryAfter = $retryAfter;

        return $exception;
    }

    public function getRetryAfter(): ?int
    {
        return $this->retryAfter;
    }
}
