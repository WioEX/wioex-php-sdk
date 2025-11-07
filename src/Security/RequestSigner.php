<?php

declare(strict_types=1);

namespace Wioex\SDK\Security;

use Wioex\SDK\Config;
use Wioex\SDK\Exceptions\SecurityException;

class RequestSigner
{
    private Config $config;
    private array $algorithms = [
        'hmac-sha256' => 'sha256',
        'hmac-sha512' => 'sha512',
        'rsa-sha256' => 'rsa-sha256'
    ];

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    public function signRequest(string $method, string $url, array $headers = [], string $body = ''): array
    {
        $securityConfig = $this->config->get('security', []);
        
        if (!($securityConfig['request_signing'] ?? false)) {
            return $headers;
        }

        $algorithm = $securityConfig['signature_algorithm'] ?? 'hmac-sha256';
        $secretKey = $securityConfig['secret_key'] ?? null;
        
        if (!$secretKey) {
            throw new SecurityException('Secret key required for request signing');
        }

        $timestamp = time();
        $nonce = $this->generateNonce();
        
        // Create canonical request
        $canonicalRequest = $this->createCanonicalRequest($method, $url, $headers, $body, $timestamp, $nonce);
        
        // Generate signature
        $signature = $this->generateSignature($canonicalRequest, $secretKey, $algorithm);
        
        // Add security headers
        $headers['X-Wioex-Timestamp'] = (string) $timestamp;
        $headers['X-Wioex-Nonce'] = $nonce;
        $headers['X-Wioex-Signature'] = $signature;
        $headers['X-Wioex-Algorithm'] = $algorithm;
        
        return $headers;
    }

    public function verifySignature(string $receivedSignature, string $method, string $url, array $headers, string $body): bool
    {
        $securityConfig = $this->config->get('security', []);
        $secretKey = $securityConfig['secret_key'] ?? null;
        $algorithm = $headers['X-Wioex-Algorithm'] ?? 'hmac-sha256';
        $timestamp = $headers['X-Wioex-Timestamp'] ?? '';
        $nonce = $headers['X-Wioex-Nonce'] ?? '';

        if (!$secretKey || !$timestamp || !$nonce) {
            return false;
        }

        // Check timestamp window (5 minutes)
        $timeWindow = $securityConfig['signature_time_window'] ?? 300;
        if (abs(time() - (int) $timestamp) > $timeWindow) {
            return false;
        }

        // Recreate canonical request
        $canonicalRequest = $this->createCanonicalRequest($method, $url, $headers, $body, (int) $timestamp, $nonce);
        
        // Generate expected signature
        $expectedSignature = $this->generateSignature($canonicalRequest, $secretKey, $algorithm);
        
        return hash_equals($expectedSignature, $receivedSignature);
    }

    private function createCanonicalRequest(string $method, string $url, array $headers, string $body, int $timestamp, string $nonce): string
    {
        $parsedUrl = parse_url($url);
        $canonicalUri = $parsedUrl['path'] ?? '/';
        $canonicalQueryString = isset($parsedUrl['query']) ? $this->canonicalizeQueryString($parsedUrl['query']) : '';
        
        // Sort headers for canonical representation
        ksort($headers);
        $canonicalHeaders = '';
        foreach ($headers as $key => $value) {
            if (strpos(strtolower($key), 'x-wioex-') === 0 || strtolower($key) === 'content-type') {
                $canonicalHeaders .= strtolower($key) . ':' . trim($value) . "\n";
            }
        }

        $bodyHash = hash('sha256', $body);
        
        return implode("\n", [
            strtoupper($method),
            $canonicalUri,
            $canonicalQueryString,
            $canonicalHeaders,
            $timestamp,
            $nonce,
            $bodyHash
        ]);
    }

    private function canonicalizeQueryString(string $queryString): string
    {
        parse_str($queryString, $params);
        ksort($params);
        return http_build_query($params);
    }

    private function generateSignature(string $canonicalRequest, string $secretKey, string $algorithm): string
    {
        switch ($algorithm) {
            case 'hmac-sha256':
                return base64_encode(hash_hmac('sha256', $canonicalRequest, $secretKey, true));
                
            case 'hmac-sha512':
                return base64_encode(hash_hmac('sha512', $canonicalRequest, $secretKey, true));
                
            case 'rsa-sha256':
                if (!function_exists('openssl_sign')) {
                    throw new SecurityException('OpenSSL extension required for RSA signatures');
                }
                
                $privateKey = openssl_pkey_get_private($secretKey);
                if (!$privateKey) {
                    throw new SecurityException('Invalid RSA private key');
                }
                
                openssl_sign($canonicalRequest, $signature, $privateKey, OPENSSL_ALGO_SHA256);
                return base64_encode($signature);
                
            default:
                throw new SecurityException("Unsupported signature algorithm: {$algorithm}");
        }
    }

    private function generateNonce(): string
    {
        return bin2hex(random_bytes(16));
    }

    public function getSecurityHeaders(): array
    {
        $securityConfig = $this->config->get('security', []);
        $headers = [];

        if ($securityConfig['csrf_protection'] ?? false) {
            $headers['X-CSRF-Token'] = $this->generateCsrfToken();
        }

        if ($securityConfig['content_security_policy'] ?? false) {
            $headers['Content-Security-Policy'] = $securityConfig['csp_header'] ?? "default-src 'self'";
        }

        return $headers;
    }

    private function generateCsrfToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    public function hashApiKey(string $apiKey): string
    {
        $securityConfig = $this->config->get('security', []);
        $algorithm = $securityConfig['api_key_hash_algorithm'] ?? 'sha256';
        $salt = $securityConfig['api_key_salt'] ?? 'wioex-sdk';
        
        return hash($algorithm, $salt . $apiKey);
    }

    public function validateApiKey(string $providedKey, string $hashedKey): bool
    {
        return hash_equals($hashedKey, $this->hashApiKey($providedKey));
    }
}