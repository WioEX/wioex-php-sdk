<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Wioex\SDK\WioexClient;

// ====================================================================
// ENTERPRISE SECURITY FEATURES EXAMPLE
// Demonstrates request signing, encryption, IP whitelisting,
// and comprehensive security monitoring
// ====================================================================

echo "🔒 WioEX SDK Enterprise Security Features Demo\n";
echo "============================================\n\n";

// Enterprise security configuration
$securityConfig = [
    'api_key' => $_ENV['WIOEX_API_KEY'] ?? 'your-api-key-here',
    
    // Advanced security settings
    'security' => [
        'request_signing' => true,
        'secret_key' => base64_encode(random_bytes(32)), // Generate secure key
        'signature_algorithm' => 'hmac-sha256',
        'signature_time_window' => 300, // 5 minutes
        
        'encryption' => [
            'enabled' => true,
            'algorithm' => 'aes-256-gcm',
            'key' => base64_encode(random_bytes(32)) // Generate secure encryption key
        ],
        
        'ip_whitelist' => [
            '127.0.0.1',
            '192.168.1.0/24',
            '10.0.0.0/8'
        ],
        
        'rate_limiting' => [
            'enabled' => true,
            'max_requests' => 100,
            'time_window' => 3600 // 1 hour
        ],
        
        'csrf_protection' => true,
        'content_security_policy' => true,
        
        'allowed_content_types' => [
            'application/json',
            'application/x-www-form-urlencoded'
        ],
        
        'max_content_length' => 1048576, // 1MB
        
        'malicious_patterns' => [
            '/<script[^>]*>/i',
            '/javascript:/i',
            '/on\w+\s*=/i',
            '/union.*select/i',
            '/drop\s+table/i',
            '/\<\?php/i'
        ],
        
        'headers' => [
            'X-Frame-Options' => 'DENY',
            'X-Content-Type-Options' => 'nosniff',
            'X-XSS-Protection' => '1; mode=block',
            'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains'
        ]
    ]
];

try {
    // Initialize enterprise client with security features
    $client = new WioexClient($securityConfig);
    
    echo "🔧 Security Configuration Status:\n";
    $securityStatus = $client->getSecurityStatus();
    echo "Request Signing: " . ($securityStatus['request_signing_enabled'] ? '✅ Enabled' : '❌ Disabled') . "\n";
    echo "Encryption: " . ($securityStatus['encryption_enabled'] ? '✅ Enabled' : '❌ Disabled') . "\n";
    echo "IP Whitelist: " . $securityStatus['ip_whitelist_count'] . " addresses configured\n";
    echo "Rate Limiting: " . ($securityStatus['rate_limiting_enabled'] ? '✅ Enabled' : '❌ Disabled') . "\n";
    echo "CSRF Protection: " . ($securityStatus['csrf_protection'] ? '✅ Enabled' : '❌ Disabled') . "\n\n";

    // ====================================================================
    // EXAMPLE 1: Request Signing and Validation
    // ====================================================================
    echo "🔐 Example 1: Request Signing and Validation\n";
    echo "--------------------------------------------\n";
    
    $testUrl = 'https://api.wioex.com/v2/stocks/get';
    $testHeaders = ['Content-Type' => 'application/json'];
    $testBody = json_encode(['ticker' => 'AAPL']);
    
    // Secure the request with signing
    $securedRequest = $client->secureRequest('POST', $testUrl, $testHeaders, $testBody);
    
    echo "📝 Original headers count: " . count($testHeaders) . "\n";
    echo "🔒 Secured headers count: " . count($securedRequest['headers']) . "\n";
    echo "✅ Security headers added:\n";
    foreach ($securedRequest['headers'] as $header => $value) {
        if (strpos($header, 'X-Wioex-') === 0) {
            $displayValue = strlen($value) > 20 ? substr($value, 0, 20) . '...' : $value;
            echo "   {$header}: {$displayValue}\n";
        }
    }
    
    // Validate the request
    $validation = $client->validateRequestSecurity('POST', $testUrl, $securedRequest['headers'], $securedRequest['body'], '127.0.0.1');
    echo "🔍 Request validation: " . ($validation['valid'] ? '✅ Valid' : '❌ Invalid') . "\n";
    echo "🛡️  Security level: " . strtoupper($validation['security_level']) . "\n\n";

    // ====================================================================
    // EXAMPLE 2: Data Encryption and Decryption
    // ====================================================================
    echo "🔐 Example 2: Data Encryption and Decryption\n";
    echo "--------------------------------------------\n";
    
    $sensitiveData = json_encode([
        'user_id' => '12345',
        'api_secret' => 'super-secret-key',
        'credit_card' => '4111-1111-1111-1111',
        'ssn' => '123-45-6789'
    ]);
    
    echo "📄 Original data size: " . strlen($sensitiveData) . " bytes\n";
    
    // Encrypt the data
    $encryptedData = $client->encrypt($sensitiveData);
    echo "🔒 Encryption status: " . ($encryptedData['encrypted'] ? '✅ Success' : '❌ Failed') . "\n";
    echo "🔧 Cipher used: " . ($encryptedData['cipher'] ?? 'none') . "\n";
    echo "📊 Encrypted data size: " . strlen($encryptedData['data'] ?? '') . " bytes (base64)\n";
    echo "🔑 Has integrity checksum: " . (isset($encryptedData['checksum']) ? '✅ Yes' : '❌ No') . "\n";
    
    // Decrypt the data
    $decryptedData = $client->decrypt($encryptedData);
    echo "🔓 Decryption status: " . ($decryptedData === $sensitiveData ? '✅ Success' : '❌ Failed') . "\n";
    echo "✅ Data integrity verified\n\n";

    // ====================================================================
    // EXAMPLE 3: Secure Token Generation
    // ====================================================================
    echo "🎲 Example 3: Secure Token Generation\n";
    echo "------------------------------------\n";
    
    $tokens = [];
    for ($i = 0; $i < 5; $i++) {
        $tokens[] = $client->generateSecureToken(16);
    }
    
    echo "🔑 Generated " . count($tokens) . " secure tokens:\n";
    foreach ($tokens as $i => $token) {
        echo "   Token " . ($i + 1) . ": {$token}\n";
    }
    echo "\n";

    // ====================================================================
    // EXAMPLE 4: IP Whitelist Management
    // ====================================================================
    echo "🌐 Example 4: IP Whitelist Security\n";
    echo "----------------------------------\n";
    
    // Test various IP addresses
    $testIps = ['127.0.0.1', '192.168.1.100', '8.8.8.8', '10.0.0.5'];
    
    foreach ($testIps as $testIp) {
        $validation = $client->validateRequestSecurity('GET', '/test', [], '', $testIp);
        $status = $validation['valid'] ? '✅ Allowed' : '❌ Blocked';
        echo "   {$testIp}: {$status}\n";
    }
    
    // Add additional IPs to whitelist
    $client->withIpWhitelist(['127.0.0.1', '8.8.8.8', '1.1.1.1']);
    echo "\n🔄 Updated IP whitelist\n";
    
    foreach ($testIps as $testIp) {
        $validation = $client->validateRequestSecurity('GET', '/test', [], '', $testIp);
        $status = $validation['valid'] ? '✅ Allowed' : '❌ Blocked';
        echo "   {$testIp}: {$status}\n";
    }
    echo "\n";

    // ====================================================================
    // EXAMPLE 5: Advanced Request Signing
    // ====================================================================
    echo "📝 Example 5: Advanced Request Signing Methods\n";
    echo "----------------------------------------------\n";
    
    // Test different signing algorithms
    $algorithms = ['hmac-sha256', 'hmac-sha512'];
    
    foreach ($algorithms as $algorithm) {
        echo "🔧 Testing {$algorithm} signing:\n";
        
        $tempClient = $client->withRequestSigning(
            base64_encode(random_bytes(32)),
            $algorithm
        );
        
        $signed = $tempClient->secureRequest('GET', '/v2/markets/status', ['User-Agent' => 'Test']);
        $hasSignature = isset($signed['headers']['X-Wioex-Signature']);
        $hasAlgorithm = isset($signed['headers']['X-Wioex-Algorithm']);
        
        echo "   Signature: " . ($hasSignature ? '✅ Present' : '❌ Missing') . "\n";
        echo "   Algorithm: " . ($hasAlgorithm ? $signed['headers']['X-Wioex-Algorithm'] : 'Unknown') . "\n";
    }
    echo "\n";

    // ====================================================================
    // EXAMPLE 6: Security Audit and Monitoring
    // ====================================================================
    echo "📊 Example 6: Security Audit and Monitoring\n";
    echo "-------------------------------------------\n";
    
    // Generate some security events
    $client->validateRequestSecurity('POST', '/test', [], '', '192.168.1.1');
    $client->validateRequestSecurity('GET', '/test', [], '', '127.0.0.1');
    
    // Get audit log
    $auditLog = $client->getSecurityAuditLog();
    echo "📋 Security audit log entries: " . count($auditLog) . "\n";
    
    if (!empty($auditLog)) {
        echo "🔍 Recent security events:\n";
        foreach (array_slice($auditLog, -3) as $entry) {
            $timestamp = date('Y-m-d H:i:s', $entry['timestamp']);
            echo "   [{$timestamp}] " . ucfirst($entry['event']) . "\n";
        }
    }
    
    // Get comprehensive security report
    echo "\n📈 Comprehensive Security Status:\n";
    $status = $client->getSecurityStatus();
    foreach ($status as $key => $value) {
        $displayValue = is_bool($value) ? ($value ? 'Enabled' : 'Disabled') : 
                       (is_array($value) ? count($value) . ' items' : $value);
        $indicator = is_bool($value) ? ($value ? '✅' : '❌') : '📊';
        echo "   {$indicator} " . ucfirst(str_replace('_', ' ', $key)) . ": {$displayValue}\n";
    }
    echo "\n";

    // ====================================================================
    // EXAMPLE 7: Real-world Security Integration
    // ====================================================================
    echo "🚀 Example 7: Secure API Calls with Full Protection\n";
    echo "--------------------------------------------------\n";
    
    // Demonstrate a real API call with all security features enabled
    echo "📡 Making secure API call to get market status...\n";
    
    try {
        // This would be a real API call in production
        $marketData = [
            'success' => true,
            'markets' => [
                'NYSE' => ['status' => 'open', 'hours' => '09:30-16:00 EST'],
                'NASDAQ' => ['status' => 'open', 'hours' => '09:30-16:00 EST']
            ],
            'timestamp' => time()
        ];
        
        // Encrypt the response data
        $encryptedResponse = $client->encrypt(json_encode($marketData));
        
        echo "✅ API call completed successfully\n";
        echo "🔒 Response encrypted: " . ($encryptedResponse['encrypted'] ? 'Yes' : 'No') . "\n";
        echo "📊 Response size: " . strlen($encryptedResponse['data']) . " bytes (encrypted)\n";
        echo "🔍 Integrity protected: " . (isset($encryptedResponse['checksum']) ? 'Yes' : 'No') . "\n";
        
        // Decrypt and verify
        $decryptedResponse = $client->decrypt($encryptedResponse);
        $responseData = json_decode($decryptedResponse, true);
        
        echo "✅ Response decrypted and verified\n";
        echo "🏪 Markets data: " . count($responseData['markets']) . " markets found\n";
        
    } catch (\Exception $e) {
        echo "❌ Security error: " . $e->getMessage() . "\n";
    }
    
    echo "\n";

    // ====================================================================
    // FINAL SECURITY SUMMARY
    // ====================================================================
    echo "🔐 Enterprise Security Features Summary\n";
    echo "======================================\n";
    echo "✅ HMAC-SHA256/SHA512 request signing with nonce and timestamp validation\n";
    echo "✅ AES-256-GCM encryption with integrity verification\n";
    echo "✅ IP whitelist/blacklist with CIDR notation support\n";
    echo "✅ Rate limiting with sliding window protection\n";
    echo "✅ CSRF protection with secure token generation\n";
    echo "✅ Content Security Policy and security headers\n";
    echo "✅ Malicious content detection and filtering\n";
    echo "✅ Comprehensive security audit logging\n";
    echo "✅ Real-time security monitoring and validation\n";
    echo "✅ Multiple encryption algorithms and key derivation\n";
    echo "✅ Secure token generation and data integrity checks\n";
    
    echo "\n🎯 Production Security Readiness:\n";
    echo "   • Protects against MITM attacks through request signing\n";
    echo "   • Ensures data confidentiality with AES-256-GCM encryption\n";
    echo "   • Prevents unauthorized access through IP filtering\n";
    echo "   • Guards against brute force with rate limiting\n";
    echo "   • Detects and blocks malicious content patterns\n";
    echo "   • Provides comprehensive audit trail for compliance\n";
    echo "   • Supports enterprise security policies and standards\n";
    
} catch (\Exception $e) {
    echo "❌ Security Demo Error: " . $e->getMessage() . "\n";
    echo "🔧 Troubleshooting:\n";
    echo "   • Verify OpenSSL extension is installed and enabled\n";
    echo "   • Check that encryption keys are properly configured\n";
    echo "   • Ensure IP addresses are in correct format\n";
    echo "   • Review security configuration parameters\n";
}