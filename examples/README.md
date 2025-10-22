# WioEX PHP SDK Examples

This directory contains comprehensive examples demonstrating the advanced features of the WioEX PHP SDK.

## Overview

These examples showcase the enhanced capabilities including:
- **WebSocket Integration** - Real-time streaming with token management
- **Bulk Operations** - Efficient batch processing with intelligent batching
- **Advanced Caching** - Multi-driver caching with tagging and prefixing
- **Data Transformers** - Powerful data transformation pipelines
- **Complete Integration** - All features working together

## Prerequisites

1. **PHP 8.1+** - Required for the SDK
2. **Composer** - For dependency management
3. **WioEX API Key** - Get one from [WioEX Developer Portal](https://developer.wioex.com)

## Setup

1. Install dependencies:
```bash
composer install
```

2. Create cache directory:
```bash
mkdir -p cache
chmod 755 cache
```

3. Update API key in examples:
```php
'api_key' => 'your_actual_api_key_here'
```

## Examples

### 1. WebSocket Integration (`websocket_example.php`)

Demonstrates comprehensive WebSocket functionality:
- Token management and validation
- Connection status monitoring
- Real-time data streaming
- Automatic token refresh
- Error handling and reconnection

**Run:**
```bash
php websocket_example.php
```

**Features Shown:**
- `getStreamingToken()` - Get WebSocket authentication token
- `validateToken()` - Check token validity
- `refreshToken()` - Refresh expired tokens
- `getWebSocketUrl()` - Get connection URL
- `getConnectionStatus()` - Monitor connection health
- `ping()` - Test connection
- `getUsageStats()` - Monitor usage metrics

### 2. Bulk Operations (`bulk_operations_example.php`)

Shows efficient processing of large datasets:
- Intelligent batching strategies
- Mixed operation types
- Performance optimization
- Error handling and resilience

**Run:**
```bash
php bulk_operations_example.php
```

**Features Shown:**
- `quoteBulk()` - Bulk quote requests
- `timelineBulk()` - Bulk historical data
- `infoBulk()` - Bulk company information
- `batchProcess()` - Mixed operation batching
- `searchBulk()` - Bulk search operations

### 3. Advanced Caching (`caching_example.php`)

Comprehensive caching system demonstration:
- Multiple cache drivers (Memory, File)
- Tagged cache operations
- Prefixed cache namespacing
- Bulk operations and statistics

**Run:**
```bash
php caching_example.php
```

**Features Shown:**
- Multi-driver configuration
- Tagged cache for organized data
- Prefixed cache for namespacing
- Bulk set/get operations
- Cache statistics and health monitoring
- Performance comparisons

### 4. Data Transformers (`transformers_example.php`)

Powerful data transformation pipelines:
- Built-in transformers (Filter, Map, Validation, Normalization)
- Custom transformer creation
- Pipeline composition and optimization
- Error handling and resilience

**Run:**
```bash
php transformers_example.php
```

**Features Shown:**
- `FilterTransformer` - Data filtering and cleaning
- `MapTransformer` - Field mapping and value transformation
- `ValidationTransformer` - Data validation and sanitization
- `NormalizationTransformer` - Data structure normalization
- Pipeline composition and statistics

### 5. Complete Integration (`complete_integration_example.php`)

Real-world application showing all features working together:
- Portfolio monitoring system
- Real-time data processing
- Trading signal generation
- System health monitoring

**Run:**
```bash
php complete_integration_example.php
```

**Features Shown:**
- Integrated WebSocket + Caching + Transformers
- Portfolio monitoring service
- Historical data analysis
- Trading signal generation
- Performance monitoring

## Configuration Examples

### Basic Configuration
```php
$config = Config::create([
    'api_key' => 'your_api_key',
    'base_url' => 'https://api.wioex.com',
]);
```

### Advanced Configuration
```php
$config = Config::create([
    'api_key' => 'your_api_key',
    'base_url' => 'https://api.wioex.com',
    'timeout' => 60,
    
    // Rate Limiting
    'rate_limiting' => [
        'enabled' => true,
        'requests' => 100,
        'window' => 60,
        'strategy' => 'sliding_window',
    ],
    
    // Retry Logic
    'retry' => [
        'max_attempts' => 3,
        'backoff_strategy' => 'exponential',
        'jitter' => true,
    ],
    
    // Caching
    'cache' => [
        'default' => 'file',
        'drivers' => [
            'file' => [
                'driver' => 'file',
                'config' => ['cache_dir' => '/tmp/wioex_cache']
            ]
        ]
    ]
]);
```

## Common Usage Patterns

### 1. Cached API Calls
```php
$cache = $client->getCache();
$quotes = $cache->remember('portfolio_quotes', function() use ($client) {
    return $client->stocks()->quoteBulk(['AAPL', 'GOOGL', 'MSFT']);
}, 300); // 5 minutes
```

### 2. WebSocket with Token Management
```php
// Check token validity before connecting
if (!$client->streaming()->validateToken()) {
    $client->streaming()->refreshToken();
}

$websocketUrl = $client->streaming()->getWebSocketUrl();
// Use $websocketUrl to establish WebSocket connection
```

### 3. Data Transformation Pipeline
```php
$response = $client->stocks()->quote('AAPL')
    ->withTransformer(new NormalizationTransformer(['key_case' => 'camelCase']))
    ->withTransformer(new FilterTransformer(['fields' => ['symbol', 'price', 'change']]))
    ->transform();
```

### 4. Bulk Operations with Error Handling
```php
$results = $client->stocks()->quoteBulk($symbols, [
    'batch_size' => 5,
    'continue_on_error' => true,
    'delay_between_batches' => 100,
]);

// Check for failed requests
if ($results['metadata']['failed_requests'] > 0) {
    // Handle failures
}
```

## Error Handling

All examples include comprehensive error handling:

```php
try {
    // SDK operations
} catch (\Wioex\SDK\Exceptions\RateLimitException $e) {
    // Handle rate limiting
} catch (\Wioex\SDK\Exceptions\AuthenticationException $e) {
    // Handle authentication errors
} catch (\Wioex\SDK\Exceptions\ValidationException $e) {
    // Handle validation errors
} catch (\Exception $e) {
    // Handle general errors
}
```

## Performance Tips

1. **Use Caching**: Cache frequently accessed data to reduce API calls
2. **Batch Operations**: Use bulk methods for multiple requests
3. **Rate Limiting**: Configure appropriate rate limits for your use case
4. **Transformation Pipelines**: Reuse pipelines for consistent data processing
5. **WebSocket Tokens**: Cache tokens and refresh before expiration

## Production Considerations

1. **API Key Security**: Store API keys securely (environment variables)
2. **Error Logging**: Implement proper error logging and monitoring
3. **Cache Storage**: Use appropriate cache drivers for your infrastructure
4. **Rate Limiting**: Monitor rate limits and implement backoff strategies
5. **WebSocket Reliability**: Implement reconnection logic for production use

## Support

For questions or issues with these examples:
1. Check the [SDK Documentation](https://docs.wioex.com/sdk/php)
2. Visit the [GitHub Repository](https://github.com/wioex/php-sdk)
3. Contact [Support](mailto:support@wioex.com)

## License

These examples are provided under the same license as the WioEX PHP SDK.