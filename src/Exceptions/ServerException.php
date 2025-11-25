<?php

declare(strict_types=1);

namespace Wioex\SDK\Exceptions;

class ServerException extends WioexException
{
    public static function internalError(string $message = 'Internal server error'): self
    {
        return new self($message);
    }

    public static function serviceUnavailable(string $message = 'Service temporarily unavailable. Please try again later.'): self
    {
        return new self($message);
    }

    public static function backendServiceError(string $service, string $details = ''): self
    {
        $message = "Backend service '{$service}' is currently unavailable";
        if ($details) {
            $message .= ": {$details}";
        }
        return new self($message);
    }
}
