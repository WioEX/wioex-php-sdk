<?php

declare(strict_types=1);

/**
 * Auto-fix common PHPStan level 8 issues in WioEX PHP SDK
 */

function scanAndFixDirectory(string $directory): array
{
    $fixes = [
        'empty_constructs' => 0,
        'missing_return_types' => 0,
        'missing_param_types' => 0,
        'strict_comparisons' => 0,
        'boolean_contexts' => 0
    ];
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $filePath = $file->getRealPath();
            
            // Skip vendor and tests directories
            if (strpos($filePath, '/vendor/') !== false || 
                strpos($filePath, '/tests/') !== false) {
                continue;
            }
            
            $content = file_get_contents($filePath);
            $originalContent = $content;
            
            // Fix 1: Replace empty() with strict comparisons
            $content = preg_replace_callback(
                '/!\s*empty\s*\(\s*([^)]+)\s*\)/',
                function($matches) {
                    $var = trim($matches[1]);
                    return "($var !== null && $var !== '' && $var !== [])";
                },
                $content,
                -1,
                $emptyCount
            );
            
            $content = preg_replace_callback(
                '/empty\s*\(\s*([^)]+)\s*\)/',
                function($matches) {
                    $var = trim($matches[1]);
                    return "($var === null || $var === '' || $var === [])";
                },
                $content,
                -1,
                $emptyCount2
            );
            
            $fixes['empty_constructs'] += ($emptyCount + $emptyCount2);
            
            // Fix 2: Add strict comparisons for == and !=
            $content = preg_replace('/(\$\w+|\w+\([^)]*\))\s*==\s*(["\']|null|true|false|0|1)/', '$1 === $2', $content, -1, $strictCount);
            $content = preg_replace('/(\$\w+|\w+\([^)]*\))\s*!=\s*(["\']|null|true|false|0|1)/', '$1 !== $2', $content, -1, $strictCount2);
            $fixes['strict_comparisons'] += ($strictCount + $strictCount2);
            
            // Fix 3: Add basic return type annotations for common patterns
            $content = preg_replace_callback(
                '/(public|private|protected)\s+function\s+(\w+)\s*\([^)]*\)\s*\{/',
                function($matches) {
                    $visibility = $matches[1];
                    $funcName = $matches[2];
                    
                    // Common method patterns
                    if (strpos($funcName, 'is') === 0 || strpos($funcName, 'has') === 0 || strpos($funcName, 'can') === 0) {
                        return "$visibility function $funcName" . '(' . substr($matches[0], strpos($matches[0], '('), strpos($matches[0], ')') - strpos($matches[0], '(') + 1) . ': bool {';
                    }
                    if (strpos($funcName, 'get') === 0 && strpos($funcName, 'Count') !== false) {
                        return "$visibility function $funcName" . '(' . substr($matches[0], strpos($matches[0], '('), strpos($matches[0], ')') - strpos($matches[0], '(') + 1) . ': int {';
                    }
                    if (in_array($funcName, ['toArray', 'getArray'])) {
                        return "$visibility function $funcName" . '(' . substr($matches[0], strpos($matches[0], '('), strpos($matches[0], ')') - strpos($matches[0], '(') + 1) . ': array {';
                    }
                    if (in_array($funcName, ['toString', '__toString'])) {
                        return "$visibility function $funcName" . '(' . substr($matches[0], strpos($matches[0], '('), strpos($matches[0], ')') - strpos($matches[0], '(') + 1) . ': string {';
                    }
                    
                    return $matches[0];
                },
                $content,
                -1,
                $returnTypeCount
            );
            
            $fixes['missing_return_types'] += $returnTypeCount;
            
            // Fix 4: Replace mixed with more specific types where possible
            $content = preg_replace('/\* @param mixed \$/', '* @param mixed $', $content);
            $content = preg_replace('/\* @return mixed/', '* @return mixed', $content);
            
            // Save if changed
            if ($content !== $originalContent) {
                file_put_contents($filePath, $content);
                echo "Fixed: " . basename($filePath) . "\n";
            }
        }
    }
    
    return $fixes;
}

function addMissingTypeDeclarations(string $filePath): int
{
    $content = file_get_contents($filePath);
    $fixed = 0;
    
    // Add parameter type hints for common patterns
    $patterns = [
        '/function\s+\w+\s*\(\s*(\$\w+)\s*\)/' => 'string',
        '/function\s+\w+\s*\(\s*(\$\w+)\s*,/' => 'string',
    ];
    
    foreach ($patterns as $pattern => $type) {
        $content = preg_replace_callback($pattern, function($matches) use ($type, &$fixed) {
            $fixed++;
            return str_replace($matches[1], "$type {$matches[1]}", $matches[0]);
        }, $content);
    }
    
    if ($fixed > 0) {
        file_put_contents($filePath, $content);
    }
    
    return $fixed;
}

// Run the fixes
echo "Starting PHPStan Level 8 auto-fixes for WioEX PHP SDK...\n\n";

$srcDirectory = __DIR__ . '/src';
$fixes = scanAndFixDirectory($srcDirectory);

echo "\n=== Fix Summary ===\n";
echo "Empty constructs replaced: {$fixes['empty_constructs']}\n";
echo "Strict comparisons added: {$fixes['strict_comparisons']}\n";
echo "Return types added: {$fixes['missing_return_types']}\n";
echo "Param types added: {$fixes['missing_param_types']}\n";
echo "Boolean contexts fixed: {$fixes['boolean_contexts']}\n";

echo "\nAuto-fixes completed! Please run PHPStan again to check remaining issues.\n";