<?php

declare(strict_types=1);

namespace Wioex\SDK\Security;

use Wioex\SDK\Config;
use Wioex\SDK\Exceptions\SecurityException;

class EncryptionManager
{
    private Config $config;
    private array $supportedCiphers = [
        'aes-256-gcm',
        'aes-256-cbc',
        'aes-128-gcm',
        'chacha20-poly1305'
    ];

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    public function encrypt(string $plaintext, ?string $key = null): array
    {
        $securityConfig = $this->config->get('security', []);
        
        if (!($securityConfig['encryption']['enabled'] ?? false)) {
            return ['encrypted' => false, 'data' => $plaintext];
        }

        $cipher = $securityConfig['encryption']['algorithm'] ?? 'aes-256-gcm';
        $encryptionKey = $key ?? $securityConfig['encryption']['key'] ?? null;

        if (!$encryptionKey) {
            throw new SecurityException('Encryption key required');
        }

        if (!in_array($cipher, $this->supportedCiphers)) {
            throw new SecurityException("Unsupported encryption cipher: {$cipher}");
        }

        $iv = $this->generateIv($cipher);
        $tag = '';

        if (in_array($cipher, ['aes-256-gcm', 'aes-128-gcm', 'chacha20-poly1305'])) {
            $encrypted = openssl_encrypt($plaintext, $cipher, $encryptionKey, OPENSSL_RAW_DATA, $iv, $tag);
        } else {
            $encrypted = openssl_encrypt($plaintext, $cipher, $encryptionKey, OPENSSL_RAW_DATA, $iv);
        }

        if ($encrypted === false) {
            throw new SecurityException('Encryption failed: ' . openssl_error_string());
        }

        return [
            'encrypted' => true,
            'cipher' => $cipher,
            'data' => base64_encode($encrypted),
            'iv' => base64_encode($iv),
            'tag' => $tag ? base64_encode($tag) : null,
            'checksum' => hash('sha256', $plaintext)
        ];
    }

    public function decrypt(array $encryptedData, ?string $key = null): string
    {
        if (!($encryptedData['encrypted'] ?? false)) {
            return $encryptedData['data'] ?? '';
        }

        $securityConfig = $this->config->get('security', []);
        $encryptionKey = $key ?? $securityConfig['encryption']['key'] ?? null;

        if (!$encryptionKey) {
            throw new SecurityException('Decryption key required');
        }

        $cipher = $encryptedData['cipher'] ?? 'aes-256-gcm';
        $data = base64_decode($encryptedData['data']);
        $iv = base64_decode($encryptedData['iv']);
        $tag = isset($encryptedData['tag']) ? base64_decode($encryptedData['tag']) : '';

        if (in_array($cipher, ['aes-256-gcm', 'aes-128-gcm', 'chacha20-poly1305'])) {
            $decrypted = openssl_decrypt($data, $cipher, $encryptionKey, OPENSSL_RAW_DATA, $iv, $tag);
        } else {
            $decrypted = openssl_decrypt($data, $cipher, $encryptionKey, OPENSSL_RAW_DATA, $iv);
        }

        if ($decrypted === false) {
            throw new SecurityException('Decryption failed: ' . openssl_error_string());
        }

        // Verify checksum if available
        if (isset($encryptedData['checksum'])) {
            $expectedChecksum = hash('sha256', $decrypted);
            if (!hash_equals($encryptedData['checksum'], $expectedChecksum)) {
                throw new SecurityException('Data integrity check failed');
            }
        }

        return $decrypted;
    }

    public function encryptCredentials(array $credentials): array
    {
        $encryptedCredentials = [];
        
        foreach ($credentials as $key => $value) {
            if (in_array($key, ['api_key', 'secret_key', 'password', 'token'])) {
                $encryptedCredentials[$key] = $this->encrypt((string) $value);
            } else {
                $encryptedCredentials[$key] = $value;
            }
        }

        return $encryptedCredentials;
    }

    public function decryptCredentials(array $encryptedCredentials): array
    {
        $credentials = [];
        
        foreach ($encryptedCredentials as $key => $value) {
            if (is_array($value) && ($value['encrypted'] ?? false)) {
                $credentials[$key] = $this->decrypt($value);
            } else {
                $credentials[$key] = $value;
            }
        }

        return $credentials;
    }

    public function generateKey(string $cipher = 'aes-256-gcm'): string
    {
        $keyLength = match ($cipher) {
            'aes-256-gcm', 'aes-256-cbc' => 32,
            'aes-128-gcm' => 16,
            'chacha20-poly1305' => 32,
            default => 32
        };

        return base64_encode(random_bytes($keyLength));
    }

    public function deriveKeyFromPassword(string $password, string $salt = '', string $algorithm = 'sha256', int $iterations = 10000): string
    {
        if (empty($salt)) {
            $salt = 'wioex-sdk-' . bin2hex(random_bytes(16));
        }

        return base64_encode(hash_pbkdf2($algorithm, $password, $salt, $iterations, 32, true));
    }

    private function generateIv(string $cipher): string
    {
        $ivLength = openssl_cipher_iv_length($cipher);
        if ($ivLength === false) {
            throw new SecurityException("Unable to determine IV length for cipher: {$cipher}");
        }

        return random_bytes($ivLength);
    }

    public function secureCompare(string $a, string $b): bool
    {
        return hash_equals($a, $b);
    }

    public function hashSensitiveData(string $data, string $algorithm = 'sha256'): string
    {
        $securityConfig = $this->config->get('security', []);
        $salt = $securityConfig['hash_salt'] ?? 'wioex-default-salt';
        
        return hash_hmac($algorithm, $data, $salt);
    }

    public function validateDataIntegrity(string $data, string $expectedHash, string $algorithm = 'sha256'): bool
    {
        $actualHash = $this->hashSensitiveData($data, $algorithm);
        return $this->secureCompare($expectedHash, $actualHash);
    }

    public function createSecureToken(int $length = 32): string
    {
        return bin2hex(random_bytes($length));
    }

    public function isSecureConnection(): bool
    {
        return isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    }

    public function getSecurityReport(): array
    {
        $securityConfig = $this->config->get('security', []);
        
        return [
            'encryption_enabled' => $securityConfig['encryption']['enabled'] ?? false,
            'encryption_algorithm' => $securityConfig['encryption']['algorithm'] ?? 'none',
            'request_signing_enabled' => $securityConfig['request_signing'] ?? false,
            'signature_algorithm' => $securityConfig['signature_algorithm'] ?? 'none',
            'csrf_protection' => $securityConfig['csrf_protection'] ?? false,
            'secure_connection' => $this->isSecureConnection(),
            'openssl_available' => extension_loaded('openssl'),
            'supported_ciphers' => $this->supportedCiphers,
            'available_ciphers' => openssl_get_cipher_methods(),
            'security_headers_enabled' => !empty($securityConfig['headers'] ?? [])
        ];
    }
}