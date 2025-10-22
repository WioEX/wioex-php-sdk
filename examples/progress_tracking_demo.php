<?php

/**
 * WioEX SDK - Progress Tracking Demo
 * 
 * Demonstrates real-time progress tracking and ETA calculation
 * for bulk operations with various scenarios.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Wioex\SDK\WioexClient;
use Wioex\SDK\Monitoring\ProgressTracker;

echo "🔄 WioEX SDK - Progress Tracking Demo\n";
echo "=====================================\n\n";

// Demo 1: Basic Progress Tracking
echo "📊 Demo 1: Basic Progress Tracking Simulation\n";
echo "----------------------------------------------\n";

$progressTracker = new ProgressTracker(100);

// Simulate chunk processing with varying speeds
$chunkSizes = [30, 30, 30, 10]; // Total: 100 items
$chunkTimes = [1.2, 1.5, 0.8, 0.5]; // Varying processing times

foreach ($chunkSizes as $index => $size) {
    $progressTracker->reportChunkProgress($index, $size, $chunkTimes[$index], true);
    
    $progress = $progressTracker->getCurrentProgress();
    
    echo sprintf(
        "Chunk %d: %d items processed | %.1f%% complete | Speed: %.1f items/s | ETA: %s\n",
        $index + 1,
        $progress['processed_items'],
        $progress['progress_percent'],
        $progress['processing_speed'],
        ProgressTracker::formatDuration($progress['estimated_remaining_time'])
    );
    
    sleep(1); // Simulate processing time
}

$finalStatus = $progressTracker->getCompletionStatus();
echo "\nFinal Status: {$finalStatus['summary']}\n";
echo "Success Rate: {$finalStatus['success_rate']}%\n";
echo "Total Time: " . ProgressTracker::formatDuration($finalStatus['total_time']) . "\n\n";

// Demo 2: Progress Tracking with Callback
echo "📈 Demo 2: Real-time Progress Callback\n";
echo "--------------------------------------\n";

$callbackTracker = new ProgressTracker(500, function($progress) {
    // Real-time progress callback
    $bar = str_repeat('█', (int)($progress['progress_percent'] / 2));
    $spaces = str_repeat('░', 50 - (int)($progress['progress_percent'] / 2));
    
    $eta = $progress['estimated_remaining_time'] !== null 
        ? ProgressTracker::formatDuration($progress['estimated_remaining_time'])
        : 'Calculating...';
    
    echo sprintf(
        "\r[%s%s] %.1f%% | %d/%d | Speed: %.1f/s | ETA: %s",
        $bar,
        $spaces,
        $progress['progress_percent'],
        $progress['processed_items'],
        $progress['total_items'],
        $progress['processing_speed'],
        $eta
    );
});

// Simulate large bulk operation
$chunks = [30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 20]; // 500 total
foreach ($chunks as $index => $size) {
    // Simulate varying processing times
    $processingTime = rand(80, 150) / 100; // 0.8-1.5 seconds
    usleep(100000); // 100ms delay for demo
    
    $callbackTracker->reportChunkProgress($index, $size, $processingTime, true);
}

echo "\n\nCompleted!\n\n";

// Demo 3: Error Handling and Recovery
echo "🚨 Demo 3: Progress Tracking with Errors\n";
echo "----------------------------------------\n";

$errorTracker = new ProgressTracker(200);

// Simulate bulk operation with some failures
$errorChunks = [
    ['size' => 30, 'time' => 1.0, 'success' => true],
    ['size' => 30, 'time' => 1.2, 'success' => true],
    ['size' => 30, 'time' => 2.5, 'success' => false], // Failed chunk
    ['size' => 30, 'time' => 1.1, 'success' => true],
    ['size' => 30, 'time' => 1.8, 'success' => false], // Failed chunk
    ['size' => 30, 'time' => 0.9, 'success' => true],
    ['size' => 20, 'time' => 0.8, 'success' => true]
];

foreach ($errorChunks as $index => $chunk) {
    $errorTracker->reportChunkProgress($index, $chunk['size'], $chunk['time'], $chunk['success']);
    
    $progress = $errorTracker->getCurrentProgress();
    $status = $chunk['success'] ? '✅' : '❌';
    
    echo sprintf(
        "%s Chunk %d: %d items | Success Rate: %.1f%% | ETA: %s\n",
        $status,
        $index + 1,
        $chunk['size'],
        $progress['success_rate'],
        ProgressTracker::formatDuration($progress['estimated_remaining_time'])
    );
}

$errorStatus = $errorTracker->getCompletionStatus();
echo "\nOperation completed with errors:\n";
echo "- Processed: {$errorStatus['items_processed']} items\n";
echo "- Failed: {$errorStatus['items_failed']} items\n";
echo "- Success Rate: {$errorStatus['success_rate']}%\n\n";

// Demo 4: Integration with Bulk Operations (Simulated)
echo "🔧 Demo 4: Integration with Bulk Operations\n";
echo "-------------------------------------------\n";

// Simulate how progress tracking would work with actual API calls
echo "Simulating quoteBulk() operation with 500 stocks...\n\n";

$symbols = array_map(fn($i) => "STOCK{$i}", range(1, 500));

// Progress callback for bulk operation
$progressCallback = function($progress) {
    static $lastPercent = -1;
    
    // Only update display every 5% to avoid spam
    $currentPercent = (int)($progress['progress_percent'] / 5) * 5;
    if ($currentPercent > $lastPercent) {
        echo sprintf(
            "Progress: %d%% (%d/%d stocks) | Speed: %.1f stocks/s | ETA: %s\n",
            (int)$progress['progress_percent'],
            $progress['processed_items'],
            $progress['total_items'],
            $progress['processing_speed'],
            ProgressTracker::formatDuration($progress['estimated_remaining_time'])
        );
        $lastPercent = $currentPercent;
    }
};

// Simulate bulk operation with progress tracking
$bulkTracker = new ProgressTracker(500, $progressCallback);

// Simulate chunks (500 stocks = 17 chunks of 30, last chunk of 20)
$totalChunks = ceil(500 / 30);
for ($i = 0; $i < $totalChunks; $i++) {
    $chunkSize = min(30, 500 - ($i * 30));
    $processingTime = rand(90, 130) / 100; // 0.9-1.3 seconds per chunk
    
    // Simulate actual API call delay
    usleep(50000); // 50ms for demo
    
    $bulkTracker->reportChunkProgress($i, $chunkSize, $processingTime, true);
}

echo "\nBulk operation completed successfully!\n";
$finalStats = $bulkTracker->getChunkStatistics();
echo "Chunk Statistics:\n";
echo "- Total Chunks: {$finalStats['total_chunks']}\n";
echo "- Average Chunk Time: " . number_format($finalStats['average_chunk_time'], 2) . "s\n";
echo "- Fastest Chunk: " . number_format($finalStats['fastest_chunk_time'], 2) . "s\n";
echo "- Slowest Chunk: " . number_format($finalStats['slowest_chunk_time'], 2) . "s\n\n";

// Demo 5: Export Progress Data
echo "📊 Demo 5: Progress Data Export\n";
echo "-------------------------------\n";

$exportData = $bulkTracker->exportProgressData();
echo "Progress data exported with " . count($exportData['chunk_details']) . " chunk records\n";
echo "Operation duration: " . ProgressTracker::formatDuration($exportData['operation_info']['duration']) . "\n";
echo "Final optimization score: " . json_encode($exportData['completion_status'], JSON_PRETTY_PRINT) . "\n\n";

// Demo 6: Usage Example in Real Code
echo "💡 Demo 6: Usage in Your Code\n";
echo "-----------------------------\n";

echo "```php\n";
echo "// Example: Using progress tracking with quoteBulk()\n";
echo "\$client = new WioexClient(['api_key' => 'your-key']);\n";
echo "\n";
echo "// Define progress callback\n";
echo "\$progressCallback = function(\$progress) {\n";
echo "    echo \"Progress: {\$progress['progress_percent']}% | ETA: \" . \n";
echo "         ProgressTracker::formatDuration(\$progress['estimated_remaining_time']) . \"\\n\";\n";
echo "};\n";
echo "\n";
echo "// Execute bulk operation with progress tracking\n";
echo "\$response = \$client->stocks()->quoteBulk(\$symbols, [\n";
echo "    'progress_callback' => \$progressCallback\n";
echo "]);\n";
echo "\n";
echo "// Access final progress information\n";
echo "\$progressInfo = \$response['bulk_operation']['progress'];\n";
echo "echo \"Operation completed in: \" . \$progressInfo['total_time'] . \" seconds\\n\";\n";
echo "echo \"Success rate: \" . \$progressInfo['success_rate'] . \"%\\n\";\n";
echo "```\n\n";

echo "✨ Progress tracking features:\n";
echo "• Real-time progress updates with callbacks\n";
echo "• ETA calculation based on processing speed\n";
echo "• Error tracking and success rate monitoring\n";
echo "• Detailed chunk statistics and timing\n";
echo "• Exportable progress data for analysis\n";
echo "• Integration with all bulk operations\n\n";

echo "Demo completed! 🎉\n";