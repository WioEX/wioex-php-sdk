# WioEX PHP SDK v2.12.0 Migration Guide

## 🚀 Major New Feature: Unified NewsManager Architecture

Version 2.12.0 introduces a revolutionary unified news management system that intelligently routes requests to the best provider based on content type while maintaining full backwards compatibility.

## 📋 Summary of Changes

### ✅ What's New
- **Unified NewsManager**: Single interface for all news sources
- **Provider Architecture**: Modular, extensible provider system
- **Intelligent Routing**: Auto-selects best provider for each content type
- **Multi-Source Aggregation**: Compare data from multiple providers
- **Advanced Fallbacks**: Automatic failover between providers
- **Health Monitoring**: Real-time provider availability tracking
- **Performance Optimization**: Enhanced caching and error handling

### 🔄 Backwards Compatibility
- **100% Compatible**: All existing code continues to work
- **No Breaking Changes**: Existing APIs remain unchanged
- **Gradual Migration**: Adopt new features at your own pace

## 🗺️ Migration Paths

### Current API (Still Supported)
```php
// These continue to work exactly as before
$client->news()->latest('TSLA');
$client->news()->companyAnalysis('TSLA');
$client->news()->trumpEffect(['sentiment' => ['trumpy']]);
$client->newsAnalysis()->getFromExternal('TSLA');
$client->newsAnalysis()->getFromWioex('TSLA');
```

### New Unified API (Recommended)
```php
// Unified interface with intelligent routing
$newsManager = $client->newsManager();

// Auto-select best provider for each content type
$news = $newsManager->get('TSLA', ['type' => 'news']);
$analysis = $newsManager->get('TSLA', ['type' => 'analysis']);
$sentiment = $newsManager->get('TSLA', ['type' => 'sentiment']);
$events = $newsManager->get('TSLA', ['type' => 'events']);
```

## 📊 Provider Mapping

### Content Type → Best Provider
| Content Type | Auto-Selected Provider | Alternative |
|--------------|----------------------|------------|
| `news` | Native (primary API) | Analysis |
| `analysis` | Analysis (AI-powered) | Native |
| `sentiment` | Sentiment (social media) | Analysis |
| `events` | Native (structured) | Analysis |

### Explicit Provider Selection
```php
// Force specific provider
$nativeNews = $newsManager->get('TSLA', ['source' => 'native']);
$analysisData = $newsManager->get('TSLA', ['source' => 'analysis']);
$sentimentData = $newsManager->get('TSLA', ['source' => 'sentiment']);
```

## 🔧 Advanced Features

### Multi-Source Comparison
```php
// Get data from multiple providers simultaneously
$multiSource = $newsManager->getFromMultipleSources('TSLA', 
    ['native', 'analysis', 'sentiment'],
    ['type' => 'analysis']
);

foreach ($multiSource['results'] as $provider => $data) {
    echo "Data from {$provider}: " . count($data['events']) . " events\n";
}
```

### Provider Health Monitoring
```php
// Check which providers are available
$health = $newsManager->getProvidersHealth();

foreach ($health as $provider => $status) {
    if ($status['status'] === 'healthy') {
        echo "{$provider} is available\n";
    }
}
```

### Advanced Filtering
```php
// Sophisticated filtering options
$filteredSentiment = $newsManager->get('TSLA', [
    'type' => 'sentiment',
    'sentiment' => ['positive', 'negative'], // Exclude neutral
    'timeframe' => '7d',
    'limit' => 100,
    'cache' => true
]);
```

### Fallback Configuration
```php
// Control fallback behavior
$reliableRequest = $newsManager->get('TSLA', [
    'type' => 'analysis',
    'fallback' => true,  // Enable automatic fallback
    'cache' => true      // Use caching for performance
]);
```

## 📈 Performance Improvements

### Caching Enhancement
```php
// Automatic caching with configurable TTL
$cachedRequest = $newsManager->get('TSLA', [
    'type' => 'news',
    'cache' => true  // Automatically cached for 5 minutes
]);

// Second request will be served from cache (much faster)
$fastRequest = $newsManager->get('TSLA', ['type' => 'news']);
```

### Error Handling
```php
try {
    $result = $newsManager->get('TSLA', ['type' => 'analysis']);
    
    if ($result->successful()) {
        $data = $result->json();
        echo "Provider used: " . $data['provider'];
    }
} catch (\Exception $e) {
    // Comprehensive error reporting with context
    echo "Error: " . $e->getMessage();
}
```

## 🎯 Migration Strategies

### 1. Gradual Migration (Recommended)
```php
// Start using new API for new features
$newsManager = $client->newsManager();

// Keep existing code unchanged
$oldNews = $client->news()->latest('TSLA');      // Still works
$newNews = $newsManager->get('TSLA', ['type' => 'news']); // New way
```

### 2. Feature-by-Feature Migration
```php
// Migrate specific use cases
class NewsService {
    private $client;
    
    public function getLatestNews($symbol) {
        // Old way (still works)
        return $this->client->news()->latest($symbol);
    }
    
    public function getAdvancedAnalysis($symbol) {
        // New way (better performance + fallbacks)
        return $this->client->newsManager()->get($symbol, [
            'type' => 'analysis',
            'source' => 'auto',
            'fallback' => true
        ]);
    }
}
```

### 3. Complete Migration
```php
// Wrapper class for easy transition
class UnifiedNewsClient {
    private $newsManager;
    
    public function __construct(WioexClient $client) {
        $this->newsManager = $client->newsManager();
    }
    
    public function getNews($symbol, $options = []) {
        return $this->newsManager->get($symbol, array_merge([
            'type' => 'news'
        ], $options));
    }
    
    public function getAnalysis($symbol, $options = []) {
        return $this->newsManager->get($symbol, array_merge([
            'type' => 'analysis'
        ], $options));
    }
}
```

## ⚡ Quick Start Examples

### Basic Usage
```php
use Wioex\SDK\WioexClient;

$client = new WioexClient(['api_key' => 'your-key']);
$newsManager = $client->newsManager();

// Get latest news (auto-routed to best provider)
$news = $newsManager->get('TSLA', ['type' => 'news']);

// Get AI analysis (auto-routed to Perplexity)
$analysis = $newsManager->get('TSLA', ['type' => 'analysis']);

// Get social sentiment (auto-routed to Sentiment provider)
$sentiment = $newsManager->get('TSLA', ['type' => 'sentiment']);
```

### Advanced Usage
```php
// Multi-provider comparison
$comparison = $newsManager->getFromMultipleSources('AAPL', 
    ['native', 'analysis', 'sentiment'],
    ['type' => 'sentiment', 'limit' => 20]
);

// Direct provider access
$nativeProvider = $newsManager->provider('native');
$directNews = $nativeProvider->getNews('GOOGL');

// Health monitoring
$health = $newsManager->getProvidersHealth();
$availableProviders = array_keys(array_filter($health, 
    fn($h) => $h['status'] === 'healthy'
));
```

## 🔍 Testing Your Migration

### Verification Script
```php
// Test script to verify migration
$client = new WioexClient(['api_key' => 'your-key']);

// Test old API still works
$oldNews = $client->news()->latest('TSLA');
echo $oldNews->successful() ? "✅ Old API works\n" : "❌ Old API failed\n";

// Test new API
$newsManager = $client->newsManager();
$newNews = $newsManager->get('TSLA', ['type' => 'news']);
echo $newNews->successful() ? "✅ New API works\n" : "❌ New API failed\n";

// Test provider health
$health = $newsManager->getProvidersHealth();
$healthyCount = count(array_filter($health, fn($h) => $h['status'] === 'healthy'));
echo "✅ {$healthyCount} providers available\n";
```

## 🎉 Benefits of Migration

### Immediate Benefits (No Code Changes)
- **Improved Reliability**: Automatic fallbacks prevent failures
- **Better Performance**: Enhanced caching and optimization
- **Health Monitoring**: Real-time provider availability

### Benefits After Migration
- **Unified Interface**: Single API for all news sources
- **Intelligent Routing**: Always get data from the best source
- **Multi-Source Data**: Compare and aggregate from multiple providers
- **Advanced Filtering**: More sophisticated query options
- **Future-Proof**: Easily add new providers as they become available

## 📚 Additional Resources

- **Full Documentation**: See `examples/unified_news_manager_example.php`
- **Provider Guide**: Learn about each provider's capabilities
- **Performance Tips**: Optimization strategies for high-volume usage
- **Troubleshooting**: Common issues and solutions

## 🆘 Support

If you encounter any issues during migration:

1. **Check Compatibility**: All existing code should work without changes
2. **Review Examples**: See comprehensive examples in the examples directory
3. **Test Gradually**: Migrate feature by feature, not all at once
4. **Monitor Health**: Use provider health checks to identify issues

## 📝 Changelog Summary

- ✅ **Added**: Unified NewsManager with intelligent routing
- ✅ **Added**: Provider-based architecture (WioEX, Perplexity, Social)
- ✅ **Added**: Multi-source data aggregation
- ✅ **Added**: Advanced fallback mechanisms
- ✅ **Added**: Provider health monitoring
- ✅ **Enhanced**: Caching and performance optimization
- ✅ **Enhanced**: Error handling and reporting
- ✅ **Maintained**: 100% backwards compatibility
- ✅ **Updated**: Version to 2.12.0 with comprehensive feature set

---

**Ready to get started?** The new NewsManager is available immediately with `$client->newsManager()` while all your existing code continues to work exactly as before!