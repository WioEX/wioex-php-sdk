<?php

/**
 * WioEX PHP SDK - Error Reporting Example
 *
 * This example demonstrates how to enable automatic error reporting
 * to help WioEX improve SDK quality and provide better support.
 */

require __DIR__ . '/../vendor/autoload.php';

use Wioex\SDK\WioexClient;
use Wioex\SDK\Exceptions\WioexException;

echo "=== WioEX PHP SDK - Error Reporting Example ===\n\n";

// Example 1: SDK without error reporting (default)
echo "1. DEFAULT CONFIGURATION (Error reporting disabled)\n";
echo str_repeat('-', 60) . "\n";

$clientWithoutReporting = new WioexClient([
    'api_key' => 'test-api-key'
]);

echo "Error reporting status: " .
    ($clientWithoutReporting->getConfig()->isErrorReportingEnabled() ? 'Enabled' : 'Disabled') .
    "\n";
echo "Errors will NOT be sent to WioEX\n";
echo "\n";

// Example 2: SDK with MINIMAL error reporting
echo "2. MINIMAL ERROR REPORTING LEVEL\n";
echo str_repeat('-', 60) . "\n";

$clientMinimal = new WioexClient([
    'api_key' => 'test-api-key',
    'error_reporting' => true,
    'error_reporting_level' => 'minimal', // Minimal data
]);

echo "Error reporting: Enabled\n";
echo "Level: " . $clientMinimal->getConfig()->getErrorReportingLevel() . "\n";
echo "Collected data:\n";
echo "  • Exception type and message\n";
echo "  • HTTP status code\n";
echo "  • SDK & PHP version\n";
echo "  • API key identification (hashed)\n";
echo "  • Timestamp\n";
echo "  • NO stack traces\n";
echo "  • NO request/response payloads\n";
echo "\n";

// Example 3: SDK with STANDARD error reporting (recommended for production)
echo "3. STANDARD ERROR REPORTING LEVEL (Recommended)\n";
echo str_repeat('-', 60) . "\n";

$clientStandard = new WioexClient([
    'api_key' => 'test-api-key',
    'error_reporting' => true,
    'error_reporting_level' => 'standard', // Default level
]);

echo "Error reporting: Enabled\n";
echo "Level: " . $clientStandard->getConfig()->getErrorReportingLevel() . "\n";
echo "Collected data:\n";
echo "  • All minimal level data\n";
echo "  • Stack traces (top 10 frames)\n";
echo "  • Relative file paths for debugging\n";
echo "  • Request endpoint and method\n";
echo "  • Error categorization\n";
echo "\n";

// Example 4: SDK with DETAILED error reporting (for debugging complex issues)
echo "4. DETAILED ERROR REPORTING LEVEL (For debugging)\n";
echo str_repeat('-', 60) . "\n";

$clientDetailed = new WioexClient([
    'api_key' => 'test-api-key',
    'error_reporting' => true,
    'error_reporting_level' => 'detailed', // Maximum detail
]);

echo "Error reporting: Enabled\n";
echo "Level: " . $clientDetailed->getConfig()->getErrorReportingLevel() . "\n";
echo "Collected data:\n";
echo "  • All standard level data\n";
echo "  • Full stack traces\n";
echo "  • Request payloads (sanitized)\n";
echo "  • Response payloads (sanitized)\n";
echo "  • Partial sensitive data (e.g., 'key1...key2' for API keys)\n";
echo "\n";

// Example 5: Custom error reporting configuration
echo "5. CUSTOM ERROR REPORTING CONFIGURATION\n";
echo str_repeat('-', 60) . "\n";

$clientCustom = new WioexClient([
    'api_key' => 'test-api-key',
    'error_reporting' => true,
    'error_reporting_level' => 'standard',
    'include_request_data' => true,  // Opt-in for request payloads
    'include_response_data' => true, // Opt-in for response payloads
    'include_stack_trace' => true,   // Force include stack traces
]);

echo "Error reporting: Enabled\n";
echo "Level: " . $clientCustom->getConfig()->getErrorReportingLevel() . "\n";
echo "Request data: " . ($clientCustom->getConfig()->shouldIncludeRequestData() ? 'Yes' : 'No') . "\n";
echo "Response data: " . ($clientCustom->getConfig()->shouldIncludeResponseData() ? 'Yes' : 'No') . "\n";
echo "Stack traces: " . ($clientCustom->getConfig()->shouldIncludeStackTrace() ? 'Yes' : 'No') . "\n";
echo "\n";

// Example 6: API Key Identification
echo "6. API KEY IDENTIFICATION (Privacy-Safe)\n";
echo str_repeat('-', 60) . "\n";
echo "Your API key is never sent in plain text!\n";
echo "Instead, we send a hashed identifier:\n";
echo "  Original API key: test-api-key-12345\n";
echo "  Sent to WioEX: " . substr(hash('sha256', 'test-api-key-12345'), 0, 16) . "\n";
echo "\n";
echo "This allows WioEX to:\n";
echo "  • Identify which customer is experiencing issues\n";
echo "  • Provide proactive support\n";
echo "  • Never expose your actual API key\n";
echo "\n";

// Example 7: Data Sanitization Examples
echo "7. DATA SANITIZATION EXAMPLES\n";
echo str_repeat('-', 60) . "\n";
echo "Sensitive data is automatically sanitized:\n\n";

echo "MINIMAL Level:\n";
echo "  api_key: 'secret123' → [REDACTED]\n";
echo "  password: 'pass123' → [REDACTED]\n";
echo "  request_body: {...} → Not included\n\n";

echo "STANDARD Level:\n";
echo "  api_key: 'secret123' → [REDACTED]\n";
echo "  password: 'pass123' → [REDACTED]\n";
echo "  request_body: {...} → Not included (unless opt-in)\n";
echo "  stack_trace: [...] → Included (relative paths only)\n\n";

echo "DETAILED Level:\n";
echo "  api_key: 'secret123456' → 'secr...3456' (partial)\n";
echo "  password: 'password123' → 'pass...d123' (partial)\n";
echo "  request_body: {...} → Included (sanitized)\n";
echo "  response_body: {...} → Included (sanitized)\n";
echo "\n";

// Example 8: Production best practices
echo "8. PRODUCTION BEST PRACTICES\n";
echo str_repeat('-', 60) . "\n";
echo "Recommended configuration for production:\n\n";
echo "<?php\n";
echo "\$client = new WioexClient([\n";
echo "    'api_key' => getenv('WIOEX_API_KEY'),\n";
echo "    'error_reporting' => true,\n";
echo "    'error_reporting_level' => 'standard', // Good balance\n";
echo "    // Don't include payloads in production unless debugging:\n";
echo "    'include_request_data' => false,\n";
echo "    'include_response_data' => false,\n";
echo "]);\n";
echo "\n";
echo "For debugging specific issues, temporarily use:\n";
echo "    'error_reporting_level' => 'detailed',\n";
echo "    'include_request_data' => true,\n";
echo "    'include_response_data' => true,\n";
echo "\n";

// Example 9: Benefits Summary
echo "9. BENEFITS OF ERROR REPORTING\n";
echo str_repeat('-', 60) . "\n";
echo "Benefits for you:\n";
echo "  ✓ Faster issue resolution\n";
echo "  ✓ Proactive notifications about SDK issues\n";
echo "  ✓ Better error messages and documentation\n";
echo "  ✓ Priority support for reported issues\n";
echo "\n";
echo "Benefits for WioEX:\n";
echo "  ✓ Identify and fix SDK bugs quickly\n";
echo "  ✓ Improve API reliability\n";
echo "  ✓ Better understand customer needs\n";
echo "  ✓ Provide proactive support\n";
echo "\n";

// Example 10: Simulating an error with reporting enabled
echo "10. SIMULATING ERROR WITH REPORTING\n";
echo str_repeat('-', 60) . "\n";

try {
    // This will trigger an authentication error
    $client = new WioexClient([
        'api_key' => 'invalid-test-key',
        'error_reporting' => true,
        'error_reporting_level' => 'standard',
    ]);

    echo "Attempting API request with invalid key...\n";
    $response = $client->stocks()->quote('AAPL');
} catch (WioexException $e) {
    echo "✓ Error caught: " . get_class($e) . "\n";
    echo "  Message: " . $e->getMessage() . "\n";
    echo "  Error was automatically reported to WioEX with:\n";
    echo "    - Hashed API key for identification\n";
    echo "    - Exception details\n";
    echo "    - Stack trace (relative paths)\n";
    echo "    - HTTP status code\n";
    echo "    - Request endpoint\n";
}
echo "\n";

echo "=== Example completed ===\n";
