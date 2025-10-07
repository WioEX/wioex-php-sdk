<?php

declare(strict_types=1);

namespace Wioex\SDK\Exceptions;

class RequestException extends WioexException
{
    public static function connectionFailed(string $reason): self
    {
        return new self("Connection failed: {$reason}");
    }

    public static function timeout(): self
    {
        return new self('Request timeout. Please try again.');
    }

    public static function networkError(string $message): self
    {
        return new self("Network error: {$message}");
    }
}
