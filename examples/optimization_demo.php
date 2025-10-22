<?php

/**
 * WioEX SDK - Bulk Operation Optimizer Demo
 * 
 * Demonstrates how to use the BulkOperationOptimizer to analyze
 * mixed operations and get cost/performance recommendations.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Wioex\SDK\WioexClient;

// Example scenarios for testing the optimizer
$scenarios = [
    'portfolio_monitoring' => [
        'name' => 'Portfolio Monitoring (Quotes Only)',
        'operations' => ['quotes' => 500],
        'description' => 'Real-time portfolio monitoring with quotes only'
    ],
    
    'mixed_analysis' => [
        'name' => 'Mixed Analysis',
        'operations' => ['quotes' => 500, 'timeline' => 100, 'info' => 50],
        'description' => 'Comprehensive analysis with quotes, timeline, and info'
    ],
    
    'research_heavy' => [
        'name' => 'Research Heavy (No Quotes)',
        'operations' => ['timeline' => 200, 'info' => 200, 'financials' => 100],
        'description' => 'Research-focused analysis without real-time quotes'
    ],
    
    'large_portfolio' => [
        'name' => 'Large Portfolio Analysis',
        'operations' => ['quotes' => 1000, 'timeline' => 500, 'info' => 300, 'financials' => 200],
        'description' => 'Institutional-scale portfolio analysis'
    ],
    
    'small_watchlist' => [
        'name' => 'Small Watchlist',
        'operations' => ['quotes' => 10, 'timeline' => 10, 'info' => 5],
        'description' => 'Small personal watchlist analysis'
    ]
];

echo "🔧 WioEX SDK - Bulk Operation Optimizer Demo\n";
echo "============================================\n\n";

// Create dummy client (API key not needed for optimizer)
$client = new WioexClient(['api_key' => 'demo-key']);
$optimizer = $client->optimizer();

foreach ($scenarios as $scenarioKey => $scenario) {
    echo "📊 Scenario: {$scenario['name']}\n";
    echo "Description: {$scenario['description']}\n";
    echo "Operations: " . json_encode($scenario['operations']) . "\n";
    echo str_repeat('-', 60) . "\n";
    
    // Analyze the operations
    $analysis = $optimizer->analyzeOperations($scenario['operations']);
    
    // Display results
    echo "💰 Cost Analysis:\n";
    echo "  Total Credits: {$analysis['total_credits']}\n";
    echo "  Individual Equivalent: {$analysis['individual_equivalent_credits']}\n";
    echo "  Credit Savings: {$analysis['overall_credit_savings_percent']}%\n";
    
    echo "\n⏱️  Performance Analysis:\n";
    echo "  Estimated Time: " . number_format($analysis['total_time_seconds'], 1) . " seconds\n";
    echo "  Individual Equivalent: " . number_format($analysis['individual_equivalent_time'], 1) . " seconds\n";
    echo "  Time Savings: {$analysis['overall_time_savings_percent']}%\n";
    
    echo "\n📈 Optimization Score: {$analysis['optimization_score']}/100\n";
    
    // Show detailed breakdown
    echo "\n🔍 Operation Breakdown:\n";
    foreach ($analysis['cost_analysis'] as $operation => $details) {
        $supportIcon = $details['has_real_bulk_support'] ? '✅' : '❌';
        echo "  {$supportIcon} {$operation}: {$details['symbol_count']} symbols → {$details['bulk_credits']} credits ({$details['credit_savings_percent']}% savings)\n";
    }
    
    // Show recommendations
    echo "\n💡 Recommendations:\n";
    foreach ($analysis['recommendations'] as $recommendation) {
        $typeIcon = match($recommendation['type']) {
            'optimal' => '🎯',
            'good' => '✅',
            'warning' => '⚠️',
            'inefficient' => '❌',
            'optimization' => '🔧',
            default => '•'
        };
        echo "  {$typeIcon} {$recommendation['message']}\n";
        
        if (isset($recommendation['suggestion'])) {
            echo "     Suggestion: {$recommendation['suggestion']}\n";
        }
    }
    
    // Show warning flags
    if (!empty($analysis['warning_flags'])) {
        echo "\n🚨 Warning Flags:\n";
        foreach ($analysis['warning_flags'] as $flag) {
            $severityIcon = match($flag['severity']) {
                'warning' => '⚠️',
                'info' => 'ℹ️',
                default => '•'
            };
            echo "  {$severityIcon} {$flag['message']}\n";
        }
    }
    
    // Get recommended strategy
    $strategy = $optimizer->getRecommendedStrategy($scenario['operations']);
    echo "\n🎯 Recommended Strategy: " . ucfirst(str_replace('_', ' ', $strategy['primary_recommendation'])) . "\n";
    echo "   Optimization Level: " . ucfirst($strategy['optimization_level']) . "\n";
    
    foreach ($strategy['execution_order'] as $step) {
        echo "   • {$step}\n";
    }
    
    echo "\n" . str_repeat('=', 80) . "\n\n";
}

// Comparison table
echo "📊 Quick Comparison Table\n";
echo "========================\n\n";
echo sprintf("%-25s | %-8s | %-8s | %-8s | %-12s | %-15s\n", 
    'Scenario', 'Credits', 'Savings%', 'Score', 'Time(s)', 'Level');
echo str_repeat('-', 85) . "\n";

foreach ($scenarios as $scenarioKey => $scenario) {
    $analysis = $optimizer->analyzeOperations($scenario['operations']);
    $strategy = $optimizer->getRecommendedStrategy($scenario['operations']);
    
    echo sprintf("%-25s | %-8d | %-7.1f%% | %-8d | %-12.1f | %-15s\n",
        substr($scenario['name'], 0, 25),
        $analysis['total_credits'],
        $analysis['overall_credit_savings_percent'],
        $analysis['optimization_score'],
        $analysis['total_time_seconds'],
        ucfirst($strategy['optimization_level'])
    );
}

echo "\n💡 Key Insights:\n";
echo "• Only quoteBulk() provides real credit savings (96-97%)\n";
echo "• Timeline/Info/Financials bulk operations are automation convenience only\n";
echo "• Mixed operations provide partial savings (only from quotes portion)\n";
echo "• Optimization score considers credit savings and operation efficiency\n";
echo "• High scores (80+) indicate excellent bulk strategy\n";
echo "• Low scores suggest reconsideration of operation mix\n";

echo "\n🔧 Usage in Your Code:\n";
echo "```php\n";
echo "\$client = new WioexClient(['api_key' => 'your-key']);\n";
echo "\$optimizer = \$client->optimizer();\n";
echo "\n";
echo "// Analyze your planned operations\n";
echo "\$analysis = \$optimizer->analyzeOperations([\n";
echo "    'quotes' => 500,\n";
echo "    'timeline' => 100\n";
echo "]);\n";
echo "\n";
echo "// Check if strategy is cost-effective\n";
echo "if (\$analysis['optimization_score'] < 60) {\n";
echo "    echo 'Consider optimizing your operation mix!';\n";
echo "}\n";
echo "```\n\n";

echo "Demo completed! ✨\n";