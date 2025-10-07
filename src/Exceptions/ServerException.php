<?php

declare(strict_types=1);

namespace Wioex\SDK\Exceptions;

class ServerException extends WioexException
{
    public static function internalError(string $message = 'Internal server error'): self
    {
        return new self($message);
    }

    public static function serviceUnavailable(): self
    {
        return new self('Service temporarily unavailable. Please try again later.');
    }
}
