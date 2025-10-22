<?php

declare(strict_types=1);

namespace Wioex\SDK\Optimization;

/**
 * Smart Bulk Operation Optimizer
 * 
 * Analyzes mixed operations and recommends the most cost-effective strategy
 * for portfolio operations based on real API behavior and credit consumption.
 */
class BulkOperationOptimizer
{
    // API endpoint limits (validated through testing)
    private const ENDPOINT_LIMITS = [
        'quotes' => 30,      // Real bulk processing available
        'timeline' => 1,     // Single requests only
        'info' => 1,         // Single requests only
        'financials' => 1    // Single requests only
    ];

    // Performance estimates (seconds per operation)
    private const PERFORMANCE_ESTIMATES = [
        'quotes_per_chunk' => 1.0,    // ~1 second per 30-symbol chunk
        'individual_call' => 0.2      // ~200ms per individual call
    ];

    /**
     * Analyze mixed operations and recommend optimal strategy
     * 
     * @param array $operations Format: ['quotes' => 500, 'timeline' => 100, 'info' => 50]
     * @return array Optimization recommendations
     */
    public function analyzeOperations(array $operations): array
    {
        $analysis = [
            'total_symbols' => 0,
            'total_operations' => count($operations),
            'cost_analysis' => [],
            'time_analysis' => [],
            'recommendations' => [],
            'optimization_score' => 0,
            'warning_flags' => []
        ];

        // Calculate total unique symbols
        $analysis['total_symbols'] = max($operations);

        // Analyze each operation type
        foreach ($operations as $operation => $symbolCount) {
            $analysis['cost_analysis'][$operation] = $this->analyzeCost($operation, $symbolCount);
            $analysis['time_analysis'][$operation] = $this->analyzeTime($operation, $symbolCount);
        }

        // Calculate totals
        $analysis['total_credits'] = array_sum(array_column($analysis['cost_analysis'], 'bulk_credits'));
        $analysis['total_time_seconds'] = array_sum(array_column($analysis['time_analysis'], 'bulk_time'));
        $analysis['individual_equivalent_credits'] = array_sum(array_column($analysis['cost_analysis'], 'individual_credits'));
        $analysis['individual_equivalent_time'] = array_sum(array_column($analysis['time_analysis'], 'individual_time'));

        // Calculate overall savings
        $analysis['overall_credit_savings_percent'] = $this->calculateSavingsPercent(
            $analysis['individual_equivalent_credits'],
            $analysis['total_credits']
        );

        $analysis['overall_time_savings_percent'] = $this->calculateSavingsPercent(
            $analysis['individual_equivalent_time'],
            $analysis['total_time_seconds']
        );

        // Generate recommendations
        $analysis['recommendations'] = $this->generateRecommendations($operations, $analysis);
        
        // Calculate optimization score (0-100)
        $analysis['optimization_score'] = $this->calculateOptimizationScore($analysis);

        // Identify warning flags
        $analysis['warning_flags'] = $this->identifyWarningFlags($operations, $analysis);

        return $analysis;
    }

    /**
     * Analyze cost for specific operation
     */
    private function analyzeCost(string $operation, int $symbolCount): array
    {
        $limit = self::ENDPOINT_LIMITS[$operation] ?? 1;
        
        if ($operation === 'quotes') {
            // Real bulk processing with savings
            $bulkCredits = (int)ceil($symbolCount / $limit);
            $savings = $this->calculateSavingsPercent($symbolCount, $bulkCredits);
        } else {
            // No bulk processing available
            $bulkCredits = $symbolCount;
            $savings = 0;
        }

        return [
            'operation' => $operation,
            'symbol_count' => $symbolCount,
            'bulk_credits' => $bulkCredits,
            'individual_credits' => $symbolCount,
            'credit_savings_percent' => $savings,
            'has_real_bulk_support' => $operation === 'quotes'
        ];
    }

    /**
     * Analyze time for specific operation
     */
    private function analyzeTime(string $operation, int $symbolCount): array
    {
        if ($operation === 'quotes') {
            // Real bulk processing with time savings
            $chunks = ceil($symbolCount / self::ENDPOINT_LIMITS[$operation]);
            $bulkTime = $chunks * self::PERFORMANCE_ESTIMATES['quotes_per_chunk'];
        } else {
            // Same as individual calls (automation only)
            $bulkTime = $symbolCount * self::PERFORMANCE_ESTIMATES['individual_call'];
        }

        $individualTime = $symbolCount * self::PERFORMANCE_ESTIMATES['individual_call'];
        $timeSavings = $this->calculateSavingsPercent($individualTime, $bulkTime);

        return [
            'operation' => $operation,
            'symbol_count' => $symbolCount,
            'bulk_time' => $bulkTime,
            'individual_time' => $individualTime,
            'time_savings_percent' => $timeSavings,
            'estimated_chunks' => $operation === 'quotes' ? (int)ceil($symbolCount / self::ENDPOINT_LIMITS[$operation]) : $symbolCount
        ];
    }

    /**
     * Generate optimization recommendations
     */
    private function generateRecommendations(array $operations, array $analysis): array
    {
        $recommendations = [];

        // Analyze quotes-heavy vs mixed strategies
        $quotesCount = $operations['quotes'] ?? 0;
        $nonQuotesCount = array_sum(array_filter($operations, fn($count, $op) => $op !== 'quotes', ARRAY_FILTER_USE_BOTH));

        if ($quotesCount > 0 && $nonQuotesCount === 0) {
            $recommendations[] = [
                'type' => 'optimal',
                'message' => 'Excellent choice! Using only quoteBulk() provides maximum savings.',
                'action' => 'continue_current_strategy',
                'expected_savings' => $analysis['overall_credit_savings_percent']
            ];
        } elseif ($quotesCount > $nonQuotesCount) {
            $recommendations[] = [
                'type' => 'good',
                'message' => 'Good strategy. Quotes dominate your operations, providing significant savings.',
                'action' => 'optimize_non_quotes',
                'suggestion' => 'Consider if you need all non-quote data, or use caching for less time-sensitive data.'
            ];
        } elseif ($quotesCount > 0 && $nonQuotesCount > $quotesCount * 2) {
            $recommendations[] = [
                'type' => 'warning',
                'message' => 'Heavy non-quote operations limit your savings potential.',
                'action' => 'reconsider_strategy',
                'suggestion' => 'Consider tiered approach: bulk quotes first, then selective detailed data.'
            ];
        } elseif ($quotesCount === 0) {
            $recommendations[] = [
                'type' => 'inefficient',
                'message' => 'No credit savings possible - all operations are individual calls.',
                'action' => 'add_quotes_bulk',
                'suggestion' => 'Consider adding quoteBulk() for real-time monitoring before detailed analysis.'
            ];
        }

        // Add specific operation recommendations
        foreach ($operations as $operation => $count) {
            if ($operation !== 'quotes' && $count > 100) {
                $recommendations[] = [
                    'type' => 'optimization',
                    'message' => "Large {$operation} operation ({$count} symbols) provides no bulk savings.",
                    'action' => 'consider_alternatives',
                    'suggestion' => 'Consider individual calls with caching or reduced dataset for cost efficiency.'
                ];
            }
        }

        return $recommendations;
    }

    /**
     * Calculate optimization score (0-100)
     */
    private function calculateOptimizationScore(array $analysis): int
    {
        // Base score from credit savings
        $creditScore = min(100, $analysis['overall_credit_savings_percent']);
        
        // Penalty for inefficient operations
        $inefficiencyPenalty = 0;
        foreach ($analysis['cost_analysis'] as $costAnalysis) {
            if (!$costAnalysis['has_real_bulk_support'] && $costAnalysis['symbol_count'] > 50) {
                $inefficiencyPenalty += 10; // Penalty for large non-bulk operations
            }
        }

        // Bonus for quotes-heavy strategies
        $quotesRatio = 0;
        if (isset($analysis['cost_analysis']['quotes'])) {
            $totalSymbols = array_sum(array_column($analysis['cost_analysis'], 'symbol_count'));
            $quotesSymbols = $analysis['cost_analysis']['quotes']['symbol_count'];
            $quotesRatio = $totalSymbols > 0 ? ($quotesSymbols / $totalSymbols) : 0;
        }
        $quotesBonus = $quotesRatio * 20; // Up to 20 point bonus for quotes-heavy strategies

        $finalScore = $creditScore + $quotesBonus - $inefficiencyPenalty;
        return max(0, min(100, (int)round($finalScore)));
    }

    /**
     * Identify warning flags
     */
    private function identifyWarningFlags(array $operations, array $analysis): array
    {
        $flags = [];

        // Check for high cost operations
        if ($analysis['total_credits'] > 1000) {
            $flags[] = [
                'type' => 'high_cost',
                'message' => "High credit consumption: {$analysis['total_credits']} credits",
                'severity' => 'warning'
            ];
        }

        // Check for low savings
        if ($analysis['overall_credit_savings_percent'] < 10) {
            $flags[] = [
                'type' => 'low_savings',
                'message' => "Low optimization benefit: only {$analysis['overall_credit_savings_percent']}% savings",
                'severity' => 'warning'
            ];
        }

        // Check for non-bulk heavy operations
        $nonBulkCredits = 0;
        foreach ($analysis['cost_analysis'] as $operation => $data) {
            if (!$data['has_real_bulk_support']) {
                $nonBulkCredits += $data['bulk_credits'];
            }
        }

        if ($nonBulkCredits > $analysis['total_credits'] * 0.8) {
            $flags[] = [
                'type' => 'bulk_inefficient',
                'message' => 'Most operations cannot benefit from bulk processing',
                'severity' => 'info'
            ];
        }

        return $flags;
    }

    /**
     * Calculate savings percentage
     */
    private function calculateSavingsPercent(float $original, float $optimized): float
    {
        if ($original <= 0) return 0;
        return round((1 - $optimized / $original) * 100, 1);
    }

    /**
     * Get recommended strategy for specific scenario
     */
    public function getRecommendedStrategy(array $operations): array
    {
        $analysis = $this->analyzeOperations($operations);
        
        $strategy = [
            'primary_recommendation' => '',
            'execution_order' => [],
            'expected_total_credits' => $analysis['total_credits'],
            'expected_total_time' => $analysis['total_time_seconds'],
            'optimization_level' => $this->getOptimizationLevel($analysis['optimization_score'])
        ];

        // Determine primary recommendation
        if ($analysis['optimization_score'] >= 80) {
            $strategy['primary_recommendation'] = 'excellent_strategy';
            $strategy['execution_order'] = ['Execute operations as planned - excellent optimization'];
        } elseif ($analysis['optimization_score'] >= 60) {
            $strategy['primary_recommendation'] = 'good_with_improvements';
            $strategy['execution_order'] = [
                'Execute quoteBulk() operations first for maximum savings',
                'Consider optimizing non-bulk operations'
            ];
        } elseif ($analysis['optimization_score'] >= 40) {
            $strategy['primary_recommendation'] = 'needs_optimization';
            $strategy['execution_order'] = [
                'Prioritize quoteBulk() operations',
                'Reconsider necessity of large non-bulk operations',
                'Consider tiered approach with caching'
            ];
        } else {
            $strategy['primary_recommendation'] = 'requires_rethinking';
            $strategy['execution_order'] = [
                'Current strategy provides minimal optimization benefits',
                'Consider quotes-first approach',
                'Implement caching for repetitive data requests'
            ];
        }

        return $strategy;
    }

    /**
     * Get optimization level description
     */
    private function getOptimizationLevel(int $score): string
    {
        if ($score >= 80) return 'excellent';
        if ($score >= 60) return 'good';
        if ($score >= 40) return 'moderate';
        if ($score >= 20) return 'poor';
        return 'inefficient';
    }
}