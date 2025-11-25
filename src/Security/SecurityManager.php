<?php

declare(strict_types=1);

namespace Wioex\SDK\Security;

use Wioex\SDK\Config;
use Wioex\SDK\Exceptions\SecurityException;

class SecurityManager
{
    private Config $config;
    private RequestSigner $requestSigner;
    private EncryptionManager $encryptionManager;
    private array $ipWhitelist = [];
    private array $blacklistedIps = [];
    private array $auditLog = [];

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->requestSigner = new RequestSigner($config);
        $this->encryptionManager = new EncryptionManager($config);
        
        $this->loadSecurityConfiguration();
    }

    private function loadSecurityConfiguration(): void
    {
        $securityConfig = $this->config->get('security', []);
        
        $this->ipWhitelist = $securityConfig['ip_whitelist'] ?? [];
        $this->blacklistedIps = $securityConfig['ip_blacklist'] ?? [];
    }

    public function validateRequest(string $method, string $url, array $headers, string $body = '', ?string $clientIp = null): array
    {
        $validationResult = [
            'valid' => true,
            'errors' => [],
            'warnings' => [],
            'security_level' => 'normal'
        ];

        try {
            // IP validation
            if ($clientIp && !$this->validateIpAccess($clientIp)) {
                $validationResult['valid'] = false;
                $validationResult['errors'][] = 'IP address not authorized';
                $this->auditLog[] = [
                    'timestamp' => time(),
                    'event' => 'ip_blocked',
                    'ip' => $clientIp,
                    'reason' => 'Not in whitelist or blacklisted'
                ];
                return $validationResult;
            }

            // Rate limiting check
            if (!$this->checkRateLimit($clientIp ?? 'unknown')) {
                $validationResult['valid'] = false;
                $validationResult['errors'][] = 'Rate limit exceeded';
                return $validationResult;
            }

            // Request signature validation
            if ($this->isSignatureRequired() && !$this->validateRequestSignature($method, $url, $headers, $body)) {
                $validationResult['valid'] = false;
                $validationResult['errors'][] = 'Invalid request signature';
                return $validationResult;
            }

            // Content validation
            $contentValidation = $this->validateRequestContent($headers, $body);
            if (!$contentValidation['valid']) {
                $validationResult['valid'] = false;
                $validationResult['errors'] = array_merge($validationResult['errors'], $contentValidation['errors']);
            }

            // Set security level based on validation results
            $validationResult['security_level'] = $this->determineSecurityLevel($headers, $clientIp);

        } catch (SecurityException $e) {
            $validationResult['valid'] = false;
            $validationResult['errors'][] = $e->getMessage();
        }

        return $validationResult;
    }

    public function secureRequest(string $method, string $url, array $headers = [], string $body = ''): array
    {
        $securityConfig = $this->config->get('security', []);
        $secureHeaders = $headers;

        // Add request signing
        if ($securityConfig['request_signing'] ?? false) {
            $secureHeaders = $this->requestSigner->signRequest($method, $url, $secureHeaders, $body);
        }

        // Add security headers
        $secureHeaders = array_merge($secureHeaders, $this->requestSigner->getSecurityHeaders());

        // Encrypt sensitive data in body if needed
        $secureBody = $body;
        if ($securityConfig['encrypt_request_body'] ?? false && ($body !== null && $body !== '' && $body !== [])) {
            $encryptedData = $this->encryptionManager->encrypt($body);
            if ($encryptedData['encrypted']) {
                $secureBody = json_encode($encryptedData);
                $secureHeaders['Content-Type'] = 'application/json';
                $secureHeaders['X-Wioex-Encrypted'] = 'true';
            }
        }

        return [
            'headers' => $secureHeaders,
            'body' => $secureBody
        ];
    }

    public function validateIpAccess(string $ip): bool
    {
        // Check blacklist first
        if (in_array($ip, $this->blacklistedIps)) {
            return false;
        }

        // If whitelist is empty, allow all (except blacklisted)
        if (($this->ipWhitelist === null || $this->ipWhitelist === '' || $this->ipWhitelist === [])) {
            return true;
        }

        // Check whitelist
        foreach ($this->ipWhitelist as $allowedIp) {
            if ($this->ipMatches($ip, $allowedIp)) {
                return true;
            }
        }

        return false;
    }

    private function ipMatches(string $ip, string $pattern): bool
    {
        // Exact match
        if ($ip === $pattern) {
            return true;
        }

        // CIDR notation
        if (strpos($pattern, '/') !== false) {
            list($subnet, $mask) = explode('/', $pattern);
            $ipLong = ip2long($ip);
            $subnetLong = ip2long($subnet);
            $maskLong = -1 << (32 - (int) $mask);
            
            return ($ipLong & $maskLong) === ($subnetLong & $maskLong);
        }

        // Wildcard pattern
        if (strpos($pattern, '*') !== false) {
            $pattern = str_replace('*', '.*', $pattern);
            return preg_match('/^' . $pattern . '$/', $ip) === 1;
        }

        return false;
    }

    private function checkRateLimit(string $identifier): bool
    {
        $securityConfig = $this->config->get('security', []);
        $rateLimiting = $securityConfig['rate_limiting'] ?? [];
        
        if (!($rateLimiting['enabled'] ?? false)) {
            return true;
        }

        $maxRequests = $rateLimiting['max_requests'] ?? 100;
        $timeWindow = $rateLimiting['time_window'] ?? 3600; // 1 hour
        
        $cacheKey = "rate_limit:{$identifier}";
        $cache = $this->config->get('cache_manager');
        
        if ($cache) {
            $requests = $cache->get($cacheKey) ?? [];
            $currentTime = time();
            
            // Remove old requests outside the time window
            $requests = array_filter($requests, fn($timestamp) => $currentTime - $timestamp < $timeWindow);
            
            // Check if limit exceeded
            if (count($requests) >= $maxRequests) {
                return false;
            }
            
            // Add current request
            $requests[] = $currentTime;
            $cache->set($cacheKey, $requests, $timeWindow);
        }

        return true;
    }

    private function isSignatureRequired(): bool
    {
        $securityConfig = $this->config->get('security', []);
        return $securityConfig['request_signing'] ?? false;
    }

    private function validateRequestSignature(string $method, string $url, array $headers, string $body): bool
    {
        $signature = $headers['X-Wioex-Signature'] ?? null;
        
        if (!$signature) {
            return false;
        }

        return $this->requestSigner->verifySignature($signature, $method, $url, $headers, $body);
    }

    private function validateRequestContent(array $headers, string $body): array
    {
        $validation = ['valid' => true, 'errors' => []];
        $securityConfig = $this->config->get('security', []);
        
        // Check content type
        $contentType = $headers['Content-Type'] ?? '';
        $allowedContentTypes = $securityConfig['allowed_content_types'] ?? ['application/json', 'application/x-www-form-urlencoded'];
        
        if (($body !== null && $body !== '' && $body !== []) && ($allowedContentTypes !== null && $allowedContentTypes !== '' && $allowedContentTypes !== [])) {
            $isAllowed = false;
            foreach ($allowedContentTypes as $allowed) {
                if (strpos($contentType, $allowed) === 0) {
                    $isAllowed = true;
                    break;
                }
            }
            
            if (!$isAllowed) {
                $validation['valid'] = false;
                $validation['errors'][] = "Content type '{$contentType}' not allowed";
            }
        }

        // Check content length
        $maxContentLength = $securityConfig['max_content_length'] ?? 1048576; // 1MB
        if (strlen($body) > $maxContentLength) {
            $validation['valid'] = false;
            $validation['errors'][] = 'Request body too large';
        }

        // Check for malicious patterns
        if ($this->containsMaliciousContent($body)) {
            $validation['valid'] = false;
            $validation['errors'][] = 'Potentially malicious content detected';
        }

        return $validation;
    }

    private function containsMaliciousContent(string $content): bool
    {
        $securityConfig = $this->config->get('security', []);
        $maliciousPatterns = $securityConfig['malicious_patterns'] ?? [
            '/<script[^>]*>/i',
            '/javascript:/i',
            '/on\w+\s*=/i',
            '/union.*select/i',
            '/drop\s+table/i'
        ];

        foreach ($maliciousPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }

        return false;
    }

    private function determineSecurityLevel(array $headers, ?string $clientIp): string
    {
        $score = 0;

        // Check for security headers
        if (isset($headers['X-Wioex-Signature'])) $score += 3;
        if (isset($headers['X-Wioex-Encrypted'])) $score += 2;
        if (isset($headers['X-CSRF-Token'])) $score += 1;

        // Check IP reputation (simplified)
        if ($clientIp && $this->isPrivateIp($clientIp)) $score += 1;

        return match (true) {
            $score >= 5 => 'high',
            $score >= 3 => 'medium',
            default => 'low'
        };
    }

    private function isPrivateIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }

    public function getRequestSigner(): RequestSigner
    {
        return $this->requestSigner;
    }

    public function getEncryptionManager(): EncryptionManager
    {
        return $this->encryptionManager;
    }

    public function addToAuditLog(string $event, array $data = []): void
    {
        $this->auditLog[] = [
            'timestamp' => time(),
            'event' => $event,
            'data' => $data
        ];
    }

    public function getAuditLog(): array
    {
        return $this->auditLog;
    }

    public function getSecurityStatus(): array
    {
        $securityConfig = $this->config->get('security', []);
        
        return [
            'request_signing_enabled' => $securityConfig['request_signing'] ?? false,
            'encryption_enabled' => $securityConfig['encryption']['enabled'] ?? false,
            'ip_whitelist_count' => count($this->ipWhitelist),
            'ip_blacklist_count' => count($this->blacklistedIps),
            'rate_limiting_enabled' => $securityConfig['rate_limiting']['enabled'] ?? false,
            'csrf_protection' => $securityConfig['csrf_protection'] ?? false,
            'content_validation' => ($securityConfig['allowed_content_types'] ?? [] !== null && $securityConfig['allowed_content_types'] ?? [] !== '' && $securityConfig['allowed_content_types'] ?? [] !== []),
            'malicious_content_detection' => ($securityConfig['malicious_patterns'] ?? [] !== null && $securityConfig['malicious_patterns'] ?? [] !== '' && $securityConfig['malicious_patterns'] ?? [] !== []),
            'security_headers' => ($securityConfig['headers'] ?? [] !== null && $securityConfig['headers'] ?? [] !== '' && $securityConfig['headers'] ?? [] !== []),
            'audit_log_entries' => count($this->auditLog),
            'openssl_available' => extension_loaded('openssl'),
            'hash_functions_available' => function_exists('hash_hmac')
        ];
    }

    public function clearAuditLog(): void
    {
        $this->auditLog = [];
    }
}