<?php

/**
 * Comprehensive Feature Test
 *
 * Tests all major SDK features including the new signals functionality
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Wioex\SDK\WioexClient;

$client = new WioexClient([
    'api_key' => '65c8a165-f368-41d5-ab9b-b50cef65d5e1'
]);

echo "\n";
echo "╔══════════════════════════════════════════════════════╗\n";
echo "║   WioEX PHP SDK v" . WioexClient::getVersion() . " - Feature Test Suite      ║\n";
echo "╚══════════════════════════════════════════════════════╝\n";
echo "\n";

$tests = [
    'Signals' => [
        'Active Signals' => function() use ($client) {
            $result = $client->signals()->active(['limit' => 5]);
            return $result->successful() && isset($result['success']);
        },
        'Signal History' => function() use ($client) {
            $result = $client->signals()->history(['days' => 7]);
            return $result->successful() && isset($result['success']);
        },
        'Symbol Filter' => function() use ($client) {
            $result = $client->signals()->active(['symbol' => 'SOUN']);
            return $result->successful() && isset($result['count']);
        }
    ],
    'Stocks' => [
        'Quote' => function() use ($client) {
            $result = $client->stocks()->quote('AAPL');
            return $result->successful() && isset($result['tickers']);
        },
        'Quote with Signal' => function() use ($client) {
            $result = $client->stocks()->quote('SOUN');
            $data = $result->data();
            return $result->successful() && isset($data['tickers'][0]['signal']);
        },
        'Info' => function() use ($client) {
            $result = $client->stocks()->info('AAPL');
            return $result->successful();
        },
        'Info with Signal' => function() use ($client) {
            $result = $client->stocks()->info('SOUN');
            $data = $result->data();
            return $result->successful() && isset($data['signal']);
        }
    ]
];

$totalTests = 0;
$passedTests = 0;
$failedTests = [];

foreach ($tests as $category => $categoryTests) {
    echo "🔍 Testing {$category}\n";
    echo str_repeat('─', 54) . "\n";

    foreach ($categoryTests as $testName => $testFunction) {
        $totalTests++;
        echo "  ├─ {$testName}... ";

        try {
            $result = $testFunction();
            if ($result) {
                echo "✅ PASS\n";
                $passedTests++;
            } else {
                echo "❌ FAIL\n";
                $failedTests[] = "{$category} > {$testName}";
            }
        } catch (\Exception $e) {
            echo "❌ ERROR: " . $e->getMessage() . "\n";
            $failedTests[] = "{$category} > {$testName}: " . $e->getMessage();
        }
    }
    echo "\n";
}

echo "╔══════════════════════════════════════════════════════╗\n";
echo "║                    TEST SUMMARY                      ║\n";
echo "╠══════════════════════════════════════════════════════╣\n";
printf("║  Total Tests:   %-33s ║\n", $totalTests);
printf("║  ✅ Passed:     %-33s ║\n", $passedTests);
printf("║  ❌ Failed:     %-33s ║\n", count($failedTests));
printf("║  Success Rate:  %-33s ║\n", round(($passedTests / $totalTests) * 100, 1) . '%');
echo "╚══════════════════════════════════════════════════════╝\n";

if (count($failedTests) > 0) {
    echo "\n❌ Failed Tests:\n";
    foreach ($failedTests as $failed) {
        echo "   • {$failed}\n";
    }
    exit(1);
} else {
    echo "\n✅ All tests passed successfully!\n";
    exit(0);
}
