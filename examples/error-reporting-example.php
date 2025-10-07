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

// Example 2: SDK with error reporting enabled
echo "2. ERROR REPORTING ENABLED\n";
echo str_repeat('-', 60) . "\n";

$clientWithReporting = new WioexClient([
    'api_key' => 'test-api-key',
    'error_reporting' => true, // Enable automatic error reporting
]);

echo "Error reporting status: " .
    ($clientWithReporting->getConfig()->isErrorReportingEnabled() ? 'Enabled' : 'Disabled') .
    "\n";
echo "Errors WILL be sent to WioEX for analysis\n";
echo "\n";

// Example 3: Advanced error reporting configuration
echo "3. ADVANCED ERROR REPORTING CONFIGURATION\n";
echo str_repeat('-', 60) . "\n";

$clientAdvanced = new WioexClient([
    'api_key' => 'test-api-key',
    'error_reporting' => true,
    'include_stack_trace' => true, // Include detailed stack traces
    // 'error_reporting_endpoint' => 'https://custom-endpoint.com/errors' // Optional custom endpoint
]);

echo "Error reporting: Enabled\n";
echo "Stack traces: " .
    ($clientAdvanced->getConfig()->shouldIncludeStackTrace() ? 'Included' : 'Not included') .
    "\n";
echo "Endpoint: " . $clientAdvanced->getConfig()->getErrorReportingEndpoint() . "\n";
echo "\n";

// Example 4: What data is collected?
echo "4. COLLECTED DATA (Privacy-Safe)\n";
echo str_repeat('-', 60) . "\n";
echo "When an error occurs, the following information is sent:\n";
echo "  ✓ Error type and message\n";
echo "  ✓ HTTP status code\n";
echo "  ✓ SDK version\n";
echo "  ✓ PHP version\n";
echo "  ✓ Timestamp\n";
echo "  ✓ Request endpoint (without parameters)\n";
echo "  ✓ Stack trace (if enabled)\n";
echo "\n";
echo "NOT COLLECTED (Your privacy is protected):\n";
echo "  ✗ API keys\n";
echo "  ✗ Passwords or tokens\n";
echo "  ✗ Personal data\n";
echo "  ✗ Request/response payloads\n";
echo "  ✗ Absolute file paths\n";
echo "\n";

// Example 5: Simulating an error with reporting enabled
echo "5. SIMULATING ERROR WITH REPORTING\n";
echo str_repeat('-', 60) . "\n";

try {
    // This will trigger an authentication error
    $client = new WioexClient([
        'api_key' => 'invalid-test-key',
        'error_reporting' => true,
    ]);

    echo "Attempting API request with invalid key...\n";
    $response = $client->stocks()->quote('AAPL');
} catch (WioexException $e) {
    echo "✓ Error caught: " . get_class($e) . "\n";
    echo "  Message: " . $e->getMessage() . "\n";
    echo "  Error was automatically reported to WioEX (if API is available)\n";
}
echo "\n";

// Example 6: Production best practices
echo "6. PRODUCTION BEST PRACTICES\n";
echo str_repeat('-', 60) . "\n";
echo "Recommendation: Enable error reporting in production to help improve SDK:\n\n";
echo "<?php\n";
echo "\$client = new WioexClient([\n";
echo "    'api_key' => getenv('WIOEX_API_KEY'),\n";
echo "    'error_reporting' => true,\n";
echo "    'include_stack_trace' => false, // Disable in production for minimal data\n";
echo "]);\n";
echo "\n";
echo "Benefits:\n";
echo "  • WioEX can proactively fix SDK bugs\n";
echo "  • Better error messages and documentation\n";
echo "  • Faster support response for common issues\n";
echo "  • No performance impact (async reporting)\n";
echo "  • Privacy-safe (no sensitive data collected)\n";
echo "\n";

echo "=== Example completed ===\n";
