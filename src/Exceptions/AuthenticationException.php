<?php

declare(strict_types=1);

namespace Wioex\SDK\Exceptions;

class AuthenticationException extends WioexException
{
    public static function invalidApiKey(): self
    {
        return new self('Invalid or missing API key. Please check your credentials.');
    }

    public static function unauthorized(string $message = 'Unauthorized'): self
    {
        return new self($message);
    }
}
