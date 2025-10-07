<?php

declare(strict_types=1);

namespace Wioex\SDK\Exceptions;

class ValidationException extends WioexException
{
    public static function invalidParameter(string $parameter, string $reason): self
    {
        return new self("Invalid parameter '{$parameter}': {$reason}");
    }

    public static function missingParameter(string $parameter): self
    {
        return new self("Missing required parameter: {$parameter}");
    }

    public static function fromResponse(string $message): self
    {
        return new self($message);
    }
}
