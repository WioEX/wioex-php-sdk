<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Wioex\SDK\Client;
use Wioex\SDK\Config;
use Wioex\SDK\Transformers\TransformerPipeline;
use Wioex\SDK\Transformers\BuiltIn\FilterTransformer;
use Wioex\SDK\Transformers\BuiltIn\MapTransformer;
use Wioex\SDK\Transformers\BuiltIn\ValidationTransformer;
use Wioex\SDK\Transformers\BuiltIn\NormalizationTransformer;

/**
 * Data Transformers Example
 * 
 * This example demonstrates the comprehensive data transformation pipeline
 * including filtering, mapping, validation, normalization, and custom transformations.
 */

// Initialize the WioEX SDK
$config = Config::create([
    'api_key' => 'your_api_key_here',
    'base_url' => 'https://api.wioex.com',
]);

$client = new Client($config);

echo "=== WioEX Data Transformers Example ===\n\n";

try {
    // 1. Basic Transformation Pipeline
    echo "1. Basic Transformation Pipeline:\n";
    
    // Create a transformation pipeline
    $pipeline = new TransformerPipeline();
    
    // Sample API response data
    $rawData = [
        'symbol' => 'AAPL',
        'name' => 'Apple Inc.',
        'current_price' => '150.25',
        'previous_close' => '148.50',
        'change_amount' => '1.75',
        'change_percent' => '1.18',
        'volume' => '45000000',
        'market_cap' => '2450000000000',
        'pe_ratio' => '28.5',
        'dividend_yield' => '0.82',
        'last_updated' => '2024-01-15 16:00:00',
        'sector' => 'Technology',
        'industry' => 'Consumer Electronics',
        'empty_field' => '',
        'null_field' => null,
        'extra_data' => 'not needed',
    ];
    
    echo "   Original data keys: " . implode(', ', array_keys($rawData)) . "\n";
    
    // Add normalization transformer
    $normalizer = new NormalizationTransformer([
        'normalize_keys' => true,
        'key_case' => 'snake_case',
        'convert_numeric_strings' => true,
        'remove_null_values' => true,
        'remove_empty_arrays' => false,
    ]);
    
    $pipeline->add($normalizer, 10); // High priority
    
    // Transform the data
    $normalizedData = $pipeline->transform($rawData);
    
    echo "   ✓ Normalization completed\n";
    echo "   📊 current_price type: " . gettype($normalizedData['current_price']) . " (was string)\n";
    echo "   📊 volume type: " . gettype($normalizedData['volume']) . " (was string)\n";
    echo "   🗑️  null_field removed: " . (isset($normalizedData['null_field']) ? 'No' : 'Yes') . "\n\n";

    // 2. Filtering Transformations
    echo "2. Filtering Transformations:\n";
    
    // Create filter transformer
    $filter = new FilterTransformer([
        'mode' => 'whitelist',
        'fields' => ['symbol', 'name', 'current_price', 'change_percent', 'volume', 'market_cap'],
        'include_empty' => false,
        'include_null' => false,
    ]);
    
    $filteredData = $filter->transform($normalizedData);
    
    echo "   Original fields: " . count($normalizedData) . "\n";
    echo "   Filtered fields: " . count($filteredData) . "\n";
    echo "   ✓ Keeping only essential stock data\n";
    echo "   📋 Filtered keys: " . implode(', ', array_keys($filteredData)) . "\n\n";

    // 3. Field Mapping Transformations
    echo "3. Field Mapping Transformations:\n";
    
    // Create map transformer
    $mapper = new MapTransformer([
        'field_mappings' => [
            'current_price' => 'price',
            'change_percent' => 'change_pct',
            'market_cap' => 'mkt_cap',
        ],
        'value_mappings' => [
            'change_pct' => function($value) {
                return round($value, 2) . '%';
            },
            'volume' => function($value) {
                return number_format($value);
            },
            'mkt_cap' => function($value) {
                return '$' . number_format($value / 1000000000, 2) . 'B';
            },
        ],
        'preserve_unmapped' => true,
    ]);
    
    $mappedData = $mapper->transform($filteredData);
    
    echo "   ✓ Field mapping completed\n";
    echo "   📈 Price: \$" . $mappedData['price'] . "\n";
    echo "   📊 Change: " . $mappedData['change_pct'] . "\n";
    echo "   📊 Volume: " . $mappedData['volume'] . "\n";
    echo "   💰 Market Cap: " . $mappedData['mkt_cap'] . "\n\n";

    // 4. Validation Transformations
    echo "4. Validation Transformations:\n";
    
    // Create validation transformer
    $validator = new ValidationTransformer([
        'required_fields' => ['symbol', 'name', 'price'],
        'rules' => [
            'symbol' => [
                'type' => 'string',
                'max_length' => 10,
                'pattern' => '/^[A-Z]+$/',
            ],
            'price' => [
                'type' => 'numeric',
                'min_value' => 0,
                'max_value' => 10000,
            ],
            'name' => [
                'type' => 'string',
                'min_length' => 1,
                'max_length' => 100,
            ],
        ],
        'throw_on_validation_error' => false,
        'sanitize_data' => true,
    ]);
    
    $validatedData = $validator->transform($mappedData);
    
    echo "   ✓ Validation completed\n";
    echo "   ✅ Symbol format: Valid\n";
    echo "   ✅ Price range: Valid\n";
    echo "   ✅ Required fields: Present\n\n";

    // 5. Complete Pipeline Integration
    echo "5. Complete Pipeline Integration:\n";
    
    // Create comprehensive pipeline
    $completePipeline = new TransformerPipeline([
        'stop_on_error' => false,
        'validate_input' => true,
    ]);
    
    // Add all transformers in order
    $completePipeline
        ->add(new NormalizationTransformer([
            'key_case' => 'camelCase',
            'convert_numeric_strings' => true,
            'remove_null_values' => true,
        ]), 100)
        ->add(new FilterTransformer([
            'mode' => 'blacklist',
            'exclude_fields' => ['extraData', 'internalId', 'debug'],
        ]), 90)
        ->add(new MapTransformer([
            'field_mappings' => [
                'currentPrice' => 'price',
                'changePercent' => 'changePct',
            ],
            'value_mappings' => [
                'changePct' => fn($val) => round($val, 2),
                'volume' => fn($val) => (int) $val,
            ],
        ]), 80)
        ->add(new ValidationTransformer([
            'required_fields' => ['symbol', 'price'],
            'throw_on_validation_error' => false,
        ]), 70);
    
    // Transform with complete pipeline
    $finalData = $completePipeline->transform($rawData);
    
    echo "   ✓ Complete pipeline transformation\n";
    echo "   🔄 Transformers executed: " . count($completePipeline->getTransformers()) . "\n";
    
    // Show final result
    echo "   📋 Final data structure:\n";
    foreach ($finalData as $key => $value) {
        $valueStr = is_numeric($value) ? $value : (is_string($value) ? "'{$value}'" : gettype($value));
        echo "     • {$key}: {$valueStr}\n";
    }
    echo "\n";

    // 6. Response Integration
    echo "6. Response Integration:\n";
    
    // Simulate API response
    $mockResponse = $client->stocks()->quote('AAPL');
    
    // Add transformers to response
    $transformedResponse = $mockResponse
        ->withTransformer(new NormalizationTransformer(['key_case' => 'camelCase']))
        ->withTransformer(new FilterTransformer([
            'mode' => 'whitelist',
            'fields' => ['symbol', 'price', 'change', 'volume'],
        ]));
    
    echo "   ✓ Transformers added to response\n";
    echo "   🔄 Active transformers: " . ($transformedResponse->hasTransformations() ? 'Yes' : 'No') . "\n";
    
    if ($transformedResponse->hasTransformations()) {
        $transformedData = $transformedResponse->transform();
        echo "   📊 Transformed response keys: " . implode(', ', array_keys($transformedData)) . "\n";
    }
    echo "\n";

    // 7. Custom Transformer Example
    echo "7. Custom Transformer Example:\n";
    
    // Create custom transformer
    $customTransformer = new class extends \Wioex\SDK\Transformers\AbstractTransformer {
        public function transform(array $data, array $context = []): array {
            $result = $data;
            
            // Add computed fields
            if (isset($data['price']) && isset($data['changePct'])) {
                $result['previousPrice'] = $data['price'] - ($data['price'] * $data['changePct'] / 100);
                $result['priceTarget'] = $data['price'] * 1.1; // 10% target
            }
            
            // Add metadata
            $result['_metadata'] = [
                'transformed_at' => date('Y-m-d H:i:s'),
                'transformer' => $this->getName(),
            ];
            
            return $result;
        }
        
        public function getName(): string {
            return 'custom_enhancer';
        }
        
        public function getDescription(): string {
            return 'Adds computed fields and metadata';
        }
    };
    
    $enhancedData = $customTransformer->transform($finalData);
    
    echo "   ✓ Custom transformer applied\n";
    echo "   📊 Previous price: \$" . number_format($enhancedData['previousPrice'], 2) . "\n";
    echo "   🎯 Price target: \$" . number_format($enhancedData['priceTarget'], 2) . "\n";
    echo "   📅 Transformed at: " . $enhancedData['_metadata']['transformed_at'] . "\n\n";

    // 8. Pipeline Statistics and Monitoring
    echo "8. Pipeline Statistics and Monitoring:\n";
    
    $stats = $completePipeline->getStatistics();
    
    echo "   📊 Pipeline Statistics:\n";
    echo "     • Total executions: " . $stats['total_executions'] . "\n";
    echo "     • Successful executions: " . $stats['successful_executions'] . "\n";
    echo "     • Success rate: " . number_format($stats['success_rate'], 1) . "%\n";
    echo "     • Average processing time: " . number_format($stats['average_processing_time'] * 1000, 2) . "ms\n";
    echo "     • Transformer count: " . $stats['transformer_count'] . "\n";
    
    if (!empty($stats['transformers_executed'])) {
        echo "     • Transformers executed:\n";
        foreach ($stats['transformers_executed'] as $name => $count) {
            echo "       - {$name}: {$count} times\n";
        }
    }
    echo "\n";

    // 9. Error Handling and Resilience
    echo "9. Error Handling and Resilience:\n";
    
    // Create pipeline with error handling
    $resilientPipeline = new TransformerPipeline([
        'stop_on_error' => false,
        'validate_input' => false,
    ]);
    
    // Add transformers that might fail
    $resilientPipeline
        ->add(new ValidationTransformer([
            'required_fields' => ['nonexistent_field'],
            'throw_on_validation_error' => false,
        ]))
        ->add(new NormalizationTransformer())
        ->add(new FilterTransformer(['mode' => 'whitelist', 'fields' => ['symbol', 'price']]));
    
    $resilientResult = $resilientPipeline->transform($rawData);
    
    echo "   ✓ Resilient pipeline completed\n";
    echo "   🛡️  Continued despite validation failure\n";
    echo "   📊 Result keys: " . implode(', ', array_keys($resilientResult)) . "\n\n";

    // 10. Performance Optimization
    echo "10. Performance Optimization:\n";
    
    $iterations = 1000;
    $testData = $rawData;
    
    // Test simple transformation
    $start = microtime(true);
    for ($i = 0; $i < $iterations; $i++) {
        $normalizer->transform($testData);
    }
    $simpleTime = (microtime(true) - $start) * 1000;
    
    // Test pipeline transformation
    $start = microtime(true);
    for ($i = 0; $i < $iterations; $i++) {
        $completePipeline->transform($testData);
    }
    $pipelineTime = (microtime(true) - $start) * 1000;
    
    echo "   Performance test ({$iterations} iterations):\n";
    echo "   ⚡ Single transformer: " . number_format($simpleTime, 2) . "ms\n";
    echo "   🔄 Complete pipeline: " . number_format($pipelineTime, 2) . "ms\n";
    echo "   📊 Overhead per transformation: " . number_format(($pipelineTime - $simpleTime) / $iterations, 3) . "ms\n\n";

    // 11. Advanced Usage Patterns
    echo "11. Advanced Usage Patterns:\n\n";
    
    echo "```php\n";
    echo "// Conditional transformation based on data\n";
    echo "\$conditionalPipeline = new TransformerPipeline();\n";
    echo "\$conditionalPipeline->addMiddleware(function(\$data, \$context, \$stage) {\n";
    echo "    if (\$stage === 'before' && isset(\$data['type'])) {\n";
    echo "        // Add type-specific transformers\n";
    echo "        \$context['data_type'] = \$data['type'];\n";
    echo "    }\n";
    echo "    return \$data;\n";
    echo "});\n\n";
    
    echo "// Transformation caching\n";
    echo "class CachedTransformerPipeline extends TransformerPipeline {\n";
    echo "    private \$cache;\n";
    echo "    \n";
    echo "    public function transform(array \$data, array \$context = []): array {\n";
    echo "        \$key = 'transform_' . md5(serialize(\$data));\n";
    echo "        \n";
    echo "        return \$this->cache->remember(\$key, function() use (\$data, \$context) {\n";
    echo "            return parent::transform(\$data, \$context);\n";
    echo "        }, 300);\n";
    echo "    }\n";
    echo "}\n\n";
    
    echo "// Transformer composition\n";
    echo "\$stockTransformer = (new TransformerPipeline())\n";
    echo "    ->add(new NormalizationTransformer(['key_case' => 'camelCase']))\n";
    echo "    ->add(new FilterTransformer(['fields' => ['symbol', 'price', 'change']]))\n";
    echo "    ->add(new MapTransformer(['value_mappings' => ['price' => fn(\$p) => round(\$p, 2)]]));\n\n";
    
    echo "\$newsTransformer = (new TransformerPipeline())\n";
    echo "    ->add(new NormalizationTransformer(['key_case' => 'snake_case']))\n";
    echo "    ->add(new FilterTransformer(['fields' => ['title', 'content', 'publishedAt']]))\n";
    echo "    ->add(new ValidationTransformer(['required_fields' => ['title']]));\n";
    echo "```\n\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    
    if ($e instanceof \Wioex\SDK\Exceptions\TransformationException) {
        echo "💡 Transformation error. Check:\n";
        echo "   - Data structure compatibility\n";
        echo "   - Transformer configuration\n";
        echo "   - Field mappings and validations\n";
    }
}

echo "\n=== Example completed ===\n";
echo "\n💡 Data Transformation Best Practices:\n";
echo "- Design transformers to be composable and reusable\n";
echo "- Use appropriate transformer order (normalize → filter → map → validate)\n";
echo "- Implement error handling for production resilience\n";
echo "- Cache transformation results for repeated operations\n";
echo "- Monitor pipeline performance and optimize bottlenecks\n";
echo "- Use validation transformers to ensure data quality\n";
echo "- Consider creating domain-specific transformer combinations\n";
echo "- Test transformers with various data scenarios\n";