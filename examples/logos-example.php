<?php

/**
 * WioEX PHP SDK - Logos Example
 *
 * This example demonstrates how to use the Logos resource to access stock logos.
 * Shows various ways to retrieve, check, and use stock logos in your applications.
 */

require __DIR__ . '/../vendor/autoload.php';

use Wioex\SDK\WioexClient;
use Wioex\SDK\Exceptions\ValidationException;
use Wioex\SDK\Exceptions\RequestException;

// Initialize the client (replace with your actual API key)
$client = new WioexClient([
    'api_key' => 'your-api-key-here'
]);

echo "=== WioEX PHP SDK - Logos Example ===\n\n";

try {
    // 1. Simple Logo URL Generation
    echo "1. SIMPLE LOGO URL GENERATION\n";
    echo str_repeat('-', 50) . "\n";
    
    $symbols = ['AAPL', 'GOOGL', 'MSFT', 'TSLA', 'AMZN'];
    foreach ($symbols as $symbol) {
        $logoUrl = $client->logos()->getUrl($symbol);
        echo sprintf("%-6s Logo URL: %s\n", $symbol, $logoUrl);
    }
    echo "\n";

    // 2. Check if logos exist
    echo "2. CHECK LOGO AVAILABILITY\n";
    echo str_repeat('-', 50) . "\n";
    
    $testSymbols = ['AAPL', 'GOOGL', 'INVALID_SYMBOL', 'TESLA'];
    foreach ($testSymbols as $symbol) {
        $exists = $client->logos()->exists($symbol);
        echo sprintf("%-15s Logo available: %s\n", 
            $symbol, 
            $exists ? '✓ Yes' : '✗ No'
        );
    }
    echo "\n";

    // 3. Get detailed logo information
    echo "3. DETAILED LOGO INFORMATION\n";
    echo str_repeat('-', 50) . "\n";
    
    $logoInfo = $client->logos()->getInfo('AAPL');
    $info = $logoInfo->getData();
    
    echo "AAPL Logo Information:\n";
    echo "  Available: " . ($info['logo_available'] ? 'Yes' : 'No') . "\n";
    echo "  URL: " . ($info['logo_url'] ?? 'N/A') . "\n";
    echo "  File Size: " . ($info['file_size'] ? number_format($info['file_size']) . ' bytes' : 'N/A') . "\n";
    echo "  Last Updated: " . ($info['last_updated'] ?? 'N/A') . "\n";
    echo "  Format: " . ($info['format'] ?? 'N/A') . "\n";
    echo "\n";

    // 4. Batch logo URL retrieval
    echo "4. BATCH LOGO RETRIEVAL\n";
    echo str_repeat('-', 50) . "\n";
    
    $batchSymbols = ['AAPL', 'GOOGL', 'MSFT', 'TSLA', 'NVDA', 'META'];
    $logoUrls = $client->logos()->getBatch($batchSymbols);
    
    echo "Batch Logo URLs:\n";
    foreach ($logoUrls as $symbol => $url) {
        echo sprintf("  %-6s %s\n", 
            $symbol, 
            $url ? $url : 'No logo available'
        );
    }
    echo "\n";

    // 5. Get available logos only (filter out missing ones)
    echo "5. AVAILABLE LOGOS ONLY\n";
    echo str_repeat('-', 50) . "\n";
    
    $mixedSymbols = ['AAPL', 'GOOGL', 'INVALID_SYM', 'MSFT', 'FAKE_STOCK'];
    $availableLogos = $client->logos()->getAvailableOnly($mixedSymbols);
    
    echo "Available logos from mixed list:\n";
    foreach ($availableLogos as $symbol => $url) {
        echo sprintf("  %-6s %s\n", $symbol, $url);
    }
    echo "\n";

    // 6. Download and save logo
    echo "6. DOWNLOAD AND SAVE LOGO\n";
    echo str_repeat('-', 50) . "\n";
    
    try {
        $downloadPath = '/tmp/aapl_logo.png';
        $success = $client->logos()->saveToFile('AAPL', $downloadPath);
        
        if ($success) {
            echo "✓ AAPL logo saved to: {$downloadPath}\n";
            echo "  File size: " . number_format(filesize($downloadPath)) . " bytes\n";
            
            // Clean up the downloaded file
            unlink($downloadPath);
            echo "  (Cleaned up downloaded file)\n";
        } else {
            echo "✗ Failed to save AAPL logo\n";
        }
    } catch (RequestException $e) {
        echo "✗ Download failed: " . $e->getMessage() . "\n";
    }
    echo "\n";

    // 7. Batch detailed information
    echo "7. BATCH DETAILED INFORMATION\n";
    echo str_repeat('-', 50) . "\n";
    
    $batchInfo = $client->logos()->getBatchInfo(['AAPL', 'GOOGL', 'INVALID_SYMBOL']);
    $batchData = $batchInfo->getData();
    
    echo "Batch Information Summary:\n";
    echo "  Total Requested: " . $batchData['summary']['total_requested'] . "\n";
    echo "  Available Count: " . $batchData['summary']['available_count'] . "\n";
    echo "  Missing Count: " . $batchData['summary']['missing_count'] . "\n\n";
    
    echo "Details:\n";
    foreach ($batchData['logos'] as $symbol => $info) {
        echo sprintf("  %-15s %s %s\n", 
            $symbol,
            $info['logo_available'] ? '✓' : '✗',
            $info['logo_available'] ? 
                '(' . number_format($info['file_size']) . ' bytes)' : 
                '(No logo)'
        );
    }
    echo "\n";

    // 8. Get system statistics
    echo "8. SYSTEM STATISTICS\n";
    echo str_repeat('-', 50) . "\n";
    
    $availableLogos = $client->logos()->getAvailable();
    $stats = $availableLogos->getData();
    
    echo "Total Available Logos: " . number_format($stats['count']) . "\n";
    echo "Sample Symbols: " . implode(', ', array_slice($stats['symbols'], 0, 10)) . "...\n";
    echo "\n";

    // 9. Practical usage example - Generate HTML
    echo "9. PRACTICAL USAGE - HTML GENERATION\n";
    echo str_repeat('-', 50) . "\n";
    
    $portfolioSymbols = ['AAPL', 'GOOGL', 'MSFT', 'TSLA'];
    $availablePortfolioLogos = $client->logos()->getAvailableOnly($portfolioSymbols, true);
    
    echo "Generated HTML for portfolio display:\n";
    echo "<div class=\"portfolio-logos\">\n";
    foreach ($availablePortfolioLogos as $symbol => $logoUrl) {
        if ($logoUrl) {
            echo "  <img src=\"{$logoUrl}\" alt=\"{$symbol} Logo\" class=\"stock-logo\" />\n";
        } else {
            echo "  <div class=\"no-logo\">{$symbol}</div>\n";
        }
    }
    echo "</div>\n\n";

    // 10. Error handling demonstration
    echo "10. ERROR HANDLING\n";
    echo str_repeat('-', 50) . "\n";
    
    try {
        // Try invalid symbol
        $client->logos()->getInfo('');
        echo "This should not print\n";
    } catch (ValidationException $e) {
        echo "✓ Validation error handled: " . $e->getMessage() . "\n";
    }
    
    try {
        // Try downloading non-existent logo
        $client->logos()->download('INVALID_SYMBOL_12345');
        echo "This should not print\n";
    } catch (RequestException $e) {
        echo "✓ Request error handled: " . $e->getMessage() . "\n";
    }
    
    echo "\n";

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Please check your API key and internet connection.\n";
}

echo "=== Example completed ===\n";

/**
 * Real-world usage patterns:
 * 
 * 1. Portfolio Display:
 * ```php
 * $portfolioSymbols = ['AAPL', 'GOOGL', 'MSFT'];
 * $logos = $client->logos()->getBatch($portfolioSymbols);
 * foreach ($logos as $symbol => $url) {
 *     if ($url) echo "<img src='{$url}' alt='{$symbol}'/>";
 * }
 * ```
 * 
 * 2. Cache-friendly approach:
 * ```php
 * $logoUrl = $client->logos()->getUrl('AAPL');
 * // URLs are consistent and can be cached
 * ```
 * 
 * 3. Validation before use:
 * ```php
 * if ($client->logos()->exists('AAPL')) {
 *     $logoUrl = $client->logos()->getUrl('AAPL');
 *     // Use logo URL safely
 * }
 * ```
 * 
 * 4. Bulk operations:
 * ```php
 * $symbols = ['AAPL', 'GOOGL', 'MSFT', 'TSLA'];
 * $availableLogos = $client->logos()->getAvailableOnly($symbols);
 * // Only get URLs for logos that actually exist
 * ```
 */