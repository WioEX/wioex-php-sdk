<?php

declare(strict_types=1);

namespace Wioex\SDK\Exceptions;

class SecurityException extends WioexException
{
    public static function invalidSignature(string $message = 'Invalid request signature'): self
    {
        return new self($message, 401);
    }

    public static function encryptionFailed(string $message = 'Encryption operation failed'): self
    {
        return new self($message, 500);
    }

    public static function decryptionFailed(string $message = 'Decryption operation failed'): self
    {
        return new self($message, 500);
    }

    public static function unauthorizedIp(string $ip): self
    {
        return new self("IP address {$ip} is not authorized", 403);
    }

    public static function rateLimitExceeded(string $message = 'Rate limit exceeded'): self
    {
        return new self($message, 429);
    }

    public static function maliciousContent(string $message = 'Potentially malicious content detected'): self
    {
        return new self($message, 400);
    }

    public static function missingSecurityKey(string $keyType = 'security'): self
    {
        return new self("Missing {$keyType} key", 500);
    }

    public static function invalidCipher(string $cipher): self
    {
        return new self("Unsupported or invalid cipher: {$cipher}", 500);
    }
}