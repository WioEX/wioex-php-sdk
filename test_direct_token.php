<?php

declare(strict_types=1);

echo "=== Direct Stream Token Test (PHP SDK Fix Verification) ===\n\n";

// Test 1: Direct API call with correct POST body format
echo "1. Testing CORRECTED POST body format:\n";
echo str_repeat('-', 50) . "\n";

$apiKey = 'd8541dc2-13c6-45c1-9419-512f1240039f';

$ch = curl_init('http://localhost/api/stream/token');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

// CORRECT: API key in POST body
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'api_key' => $apiKey
]));

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
if ($error) {
    echo "cURL Error: $error\n";
}

if ($response) {
    echo "Response: $response\n";
    
    $data = json_decode($response, true);
    if ($data && isset($data['token'])) {
        $token = $data['token'];
        echo "\n🔍 Token Analysis:\n";
        echo "  Format: " . (str_starts_with($token, 'eyJ') ? 'JWT ✅' : 'Unknown ❌') . "\n";
        echo "  Is Demo: " . (str_starts_with($token, 'demo_') ? 'YES ❌' : 'NO ✅') . "\n";
        echo "  Type: " . ($data['type'] ?? 'unknown') . "\n";
    }
} else {
    echo "No response received\n";
}

echo "\n" . str_repeat('=', 60) . "\n";

// Test 2: Wrong format (query parameter) for comparison
echo "2. Testing WRONG query parameter format (for comparison):\n";
echo str_repeat('-', 50) . "\n";

$ch = curl_init('http://localhost/api/stream/token?api_key=' . urlencode($apiKey));
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response: $response\n";

if ($response) {
    $data = json_decode($response, true);
    if ($data && isset($data['token'])) {
        echo "Token would be: " . substr($data['token'], 0, 20) . "...\n";
    }
}

echo "\n🎯 CONCLUSION:\n";
echo "The PHP SDK fix should make it use the FIRST method (POST body).\n";
echo "This will generate production JWT tokens instead of demo tokens.\n";