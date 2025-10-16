<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Wioex\SDK\WioexClient;

// Test script for price changes endpoint
$client = new WioexClient([
    'api_key' => '65c8a165-f368-41d5-ab9b-b50cef65d5e1' // Demo API key
]);

echo "Testing Price Changes Endpoint\n";
echo "================================\n\n";

// Test symbols
$symbols = ['TSLA', 'AAPL', 'INVALID_SYMBOL'];

foreach ($symbols as $symbol) {
    echo "Testing symbol: {$symbol}\n";
    echo str_repeat('-', 30) . "\n";
    
    try {
        $response = $client->stocks()->priceChanges($symbol);
        $data = $response->data();
        
        if (isset($data['symbol'])) {
            echo "✅ Success for {$symbol}\n";
            
            // Display short-term price changes
            $shortTerm = $data['price_changes']['short_term'];
            echo "Short-term price changes:\n";
            foreach ($shortTerm as $period => $change) {
                if ($change['available'] && $change['percentage'] !== null) {
                    $percent = round($change['percentage'], 2);
                    echo "  {$change['label']}: {$percent}%\n";
                }
            }
            
            // Display medium-term price changes
            $mediumTerm = $data['price_changes']['medium_term'];
            echo "\nMedium-term price changes:\n";
            foreach ($mediumTerm as $period => $change) {
                if ($change['available'] && $change['percentage'] !== null) {
                    $percent = round($change['percentage'], 2);
                    echo "  {$change['label']}: {$percent}%\n";
                }
            }
            
            // Display long-term price changes
            $longTerm = $data['price_changes']['long_term'];
            echo "\nLong-term price changes:\n";
            foreach ($longTerm as $period => $change) {
                if ($change['available'] && $change['percentage'] !== null) {
                    $percent = round($change['percentage'], 2);
                    echo "  {$change['label']}: {$percent}%\n";
                }
            }
            
            echo "\nLast updated: " . ($data['updated_at'] ?? 'Unknown') . "\n";
            
        } else {
            echo "❌ Error for {$symbol}: " . $data['error'] . "\n";
            echo "Error code: " . $data['error_code'] . "\n";
        }
        
    } catch (Exception $e) {
        echo "❌ Exception for {$symbol}: " . $e->getMessage() . "\n";
    }
    
    echo "\n" . str_repeat('=', 50) . "\n\n";
}

echo "Test completed!\n";
?>