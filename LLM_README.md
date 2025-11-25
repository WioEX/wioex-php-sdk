# WioEX PHP SDK - AI Agent Integration Guide

## Overview

The WioEX PHP SDK provides a comprehensive financial data platform specifically designed for AI agents and Large Language Models (LLMs). This SDK offers intelligent news management, automated provider routing, and structured financial data access optimized for AI-driven applications.

## Key Features for AI Agents

### 🤖 Intelligent News Provider Routing
- **Automatic Provider Selection**: AI agents can request data by type, and the SDK automatically routes to the best provider
- **Multi-Source Aggregation**: Compare data from multiple providers simultaneously
- **Fallback Mechanisms**: Automatic failover ensures data availability

### 📊 Structured Financial Data
- **Standardized Response Format**: Consistent JSON structure across all providers
- **Sentiment Analysis**: Pre-processed sentiment data with confidence scores
- **Impact Classification**: Event impact levels (low/medium/high/major)
- **Temporal Data**: Time-series support for trend analysis

### ⚡ Performance Optimized for AI
- **Intelligent Caching**: Reduces API calls and improves response times
- **Batch Operations**: Process multiple symbols efficiently
- **Provider Health Monitoring**: Real-time availability checks
- **Rate Limiting Awareness**: Respects API limits automatically

## Quick Start for AI Agents

### Basic Setup

```php
<?php
use Wioex\SDK\WioexClient;

// Initialize the client
$client = new WioexClient([
    'api_key' => 'your-wioex-api-key',
    'timeout' => 30
]);

// Get the unified news manager
$newsManager = $client->newsManager();
```

### Intelligent Content Routing

The SDK automatically routes requests to the optimal provider based on content type:

```php
// News: Routes to native provider (fastest, most comprehensive)
$news = $newsManager->get('TSLA', ['type' => 'news']);

// Analysis: Routes to analysis provider (AI-powered, detailed insights)
$analysis = $newsManager->get('TSLA', ['type' => 'analysis']);

// Sentiment: Routes to sentiment provider (market sentiment, mood)
$sentiment = $newsManager->get('TSLA', ['type' => 'sentiment']);

// Events: Routes to native provider (structured event data)
$events = $newsManager->get('TSLA', ['type' => 'events']);
```

## Advanced AI Integration Patterns

### 1. Multi-Source Data Fusion

Perfect for AI agents that need to compare and validate information from multiple sources:

```php
// Get the same data from all providers for comparison
$multiSource = $newsManager->getFromMultipleSources('AAPL', 
    ['native', 'analysis', 'sentiment'],
    ['type' => 'analysis', 'limit' => 50]
);

// Process results
foreach ($multiSource->data()['results'] as $provider => $data) {
    echo "Provider {$provider}: " . count($data['events'] ?? []) . " events\n";
    
    // AI can now compare confidence levels, sentiment alignment, etc.
    if (isset($data['sentiment_summary'])) {
        $confidence = $data['confidence_score'] ?? 0.5;
        echo "Confidence: " . ($confidence * 100) . "%\n";
    }
}
```

### 2. Sentiment-Driven Decision Making

AI agents can use structured sentiment data for trading decisions or market analysis:

```php
$sentimentData = $newsManager->get('TSLA', [
    'type' => 'sentiment',
    'timeframe' => '24h',
    'limit' => 100
]);

if ($sentimentData->successful()) {
    $data = $sentimentData->data();
    
    // Get overall market sentiment
    $overallSentiment = $data['overall_sentiment']; // 'positive', 'negative', 'neutral'
    $moodIndex = $data['mood_index']; // 0.0 to 1.0 (bearish to bullish)
    
    // AI decision logic
    if ($moodIndex > 0.7 && $overallSentiment === 'positive') {
        echo "Strong bullish sentiment detected\n";
        // AI agent can trigger buy signals, alerts, etc.
    }
    
    // Analyze sentiment distribution
    $distribution = $data['sentiment_metrics']['distribution'];
    echo "Positive: {$distribution['positive']}%\n";
    echo "Negative: {$distribution['negative']}%\n";
    echo "Neutral: {$distribution['neutral']}%\n";
}
```

### 3. Event-Driven Analysis

AI agents can process structured financial events for automated analysis:

```php
$events = $newsManager->get('AAPL', [
    'type' => 'events',
    'event_types' => ['earnings', 'announcements', 'dividends'],
    'timeframe' => '30d'
]);

if ($events->successful()) {
    $data = $events->data();
    
    foreach ($data['events'] as $event) {
        $impact = $event['impact_level']; // 'low', 'medium', 'high', 'major'
        $type = $event['type']; // 'earnings', 'announcement', etc.
        $sentiment = $event['sentiment']; // 'positive', 'negative', 'neutral'
        
        // AI can classify and respond to events
        if ($impact === 'major' && $type === 'earnings') {
            echo "Major earnings event detected for {$event['symbol']}\n";
            echo "Sentiment: {$sentiment}\n";
            echo "Date: {$event['date']}\n";
            
            // AI agent can trigger alerts, analysis, portfolio rebalancing
        }
    }
}
```

### 4. Provider-Specific Data Access

For AI agents that need specific data characteristics:

```php
// High-frequency trading AI might prefer native provider for speed
$nativeProvider = $newsManager->provider('native');
$fastNews = $nativeProvider->getNews('TSLA');

// Research AI might prefer analysis provider for depth
$analysisProvider = $newsManager->provider('analysis');
$deepAnalysis = $analysisProvider->getAnalysis('TSLA');

// Market sentiment AI might prefer sentiment provider
$sentimentProvider = $newsManager->provider('sentiment');
$sentimentData = $sentimentProvider->getSentiment('TSLA');
```

## AI Agent Optimization Patterns

### 1. Intelligent Caching Strategy

```php
// AI agents can implement smart caching based on data volatility
$cacheTime = $this->determineCacheTime($symbol, $contentType);

$data = $newsManager->get('TSLA', [
    'type' => 'news',
    'cache' => true,
    'cache_ttl' => $cacheTime // AI-determined cache duration
]);
```

### 2. Health-Aware Provider Selection

```php
// AI agents can check provider health before making requests
$health = $newsManager->getProvidersHealth();

$availableProviders = [];
foreach ($health as $provider => $status) {
    if ($status['status'] === 'healthy') {
        $availableProviders[] = $provider;
    }
}

// Use only healthy providers
if (!empty($availableProviders)) {
    $data = $newsManager->get('TSLA', [
        'source' => $availableProviders[0], // Use best available
        'fallback' => true
    ]);
}
```

### 3. Batch Processing for AI Training

```php
// AI agents can efficiently process multiple symbols
$symbols = ['TSLA', 'AAPL', 'MSFT', 'GOOGL', 'AMZN'];
$trainingData = [];

foreach ($symbols as $symbol) {
    $data = $newsManager->get($symbol, [
        'type' => 'analysis',
        'limit' => 200,
        'timeframe' => '90d'
    ]);
    
    if ($data->successful()) {
        $trainingData[$symbol] = $this->extractFeatures($data->data());
    }
}

// Now AI has structured training data from multiple symbols
```

## Response Structures for AI Processing

### News Response
```json
{
    "symbol": "TSLA",
    "provider": "native",
    "status": "success",
    "data": [
        {
            "id": "news_123",
            "title": "Tesla Reports Record Quarterly Earnings",
            "summary": "Tesla exceeded expectations...",
            "date": "2024-01-15",
            "sentiment": "positive",
            "impact_level": "high",
            "source": "native"
        }
    ],
    "total": 25,
    "timestamp": 1704067200
}
```

### Analysis Response
```json
{
    "symbol": "TSLA",
    "provider": "analysis",
    "status": "success",
    "events": [
        {
            "id": "evt_456",
            "title": "Quarterly Earnings Beat",
            "date": "2024-01-15",
            "sentiment": "positive",
            "impact_level": "high",
            "confidence": 0.89,
            "sectors": ["Technology", "Automotive"],
            "affected_securities": [
                {"ticker": "TSLA", "name": "Tesla Inc"}
            ]
        }
    ],
    "sentiment_summary": {
        "positive": 65.2,
        "neutral": 25.8,
        "negative": 9.0
    },
    "timestamp": 1704067200
}
```

### Sentiment Response
```json
{
    "symbol": "TSLA",
    "provider": "sentiment",
    "overall_sentiment": "positive",
    "mood_index": 0.72,
    "sentiment_metrics": {
        "distribution": {
            "positive": 68.5,
            "neutral": 22.1,
            "negative": 9.4
        },
        "confidence": 0.84,
        "volatility": "medium",
        "trending_direction": "improving"
    },
    "post_analysis": {
        "total_posts": 1247,
        "positive_posts": 854,
        "negative_posts": 117,
        "neutral_posts": 276
    },
    "timestamp": 1704067200
}
```

## Error Handling for AI Agents

```php
try {
    $data = $newsManager->get('INVALID_SYMBOL', ['type' => 'analysis']);
    
    if (!$data->successful()) {
        $error = $data->data();
        
        // AI agents can handle different error types
        switch ($error['error']) {
            case 'symbol_not_found':
                // AI can try symbol variations or skip
                break;
                
            case 'rate_limit_exceeded':
                // AI can implement backoff strategy
                sleep(60);
                break;
                
            case 'provider_unavailable':
                // AI can try different provider
                $fallbackData = $newsManager->get('TSLA', [
                    'type' => 'analysis',
                    'source' => 'sentiment', // Try different provider
                    'fallback' => true
                ]);
                break;
        }
    }
} catch (Exception $e) {
    // AI agents can log errors and continue processing
    error_log("AI Agent Error: " . $e->getMessage());
}
```

## Best Practices for AI Agents

### 1. Respect Rate Limits
```php
// Check provider capabilities before making requests
$capabilities = $newsManager->provider('analysis')->getCapabilities();
$rateLimit = $capabilities['limits']['requests_per_minute'];

// AI agents should implement rate limiting
$this->respectRateLimit($rateLimit);
```

### 2. Use Appropriate Content Types
```php
// Match request type to AI use case
$requests = [
    'breaking_news_ai' => ['type' => 'news'],
    'sentiment_analysis_ai' => ['type' => 'sentiment'],
    'fundamental_analysis_ai' => ['type' => 'analysis'],
    'event_driven_ai' => ['type' => 'events']
];
```

### 3. Implement Intelligent Fallbacks
```php
// AI agents should have fallback strategies
$primaryData = $newsManager->get('TSLA', [
    'type' => 'analysis',
    'source' => 'analysis'
]);

if (!$primaryData->successful()) {
    // Fallback to different provider or content type
    $fallbackData = $newsManager->get('TSLA', [
        'type' => 'news', // Different content type
        'source' => 'native' // Different provider
    ]);
}
```

### 4. Data Quality Validation
```php
// AI agents should validate data quality
function validateDataQuality($data) {
    $issues = [];
    
    if (!isset($data['sentiment_summary'])) {
        $issues[] = 'Missing sentiment data';
    }
    
    if (isset($data['confidence']) && $data['confidence'] < 0.6) {
        $issues[] = 'Low confidence score';
    }
    
    if (empty($data['events'])) {
        $issues[] = 'No events found';
    }
    
    return $issues;
}

$data = $newsManager->get('TSLA', ['type' => 'analysis']);
$qualityIssues = validateDataQuality($data->data());

if (!empty($qualityIssues)) {
    // AI can request different data or adjust confidence
    echo "Data quality issues: " . implode(', ', $qualityIssues) . "\n";
}
```

## Integration with Popular AI Frameworks

### LangChain Integration Example
```php
// Custom WioEX tool for LangChain agents
class WioexNewsAnalysisTool {
    private $newsManager;
    
    public function __construct($apiKey) {
        $client = new WioexClient(['api_key' => $apiKey]);
        $this->newsManager = $client->newsManager();
    }
    
    public function getMarketSentiment($symbol) {
        $data = $this->newsManager->get($symbol, ['type' => 'sentiment']);
        
        return [
            'sentiment' => $data->data()['overall_sentiment'],
            'confidence' => $data->data()['sentiment_metrics']['confidence'],
            'summary' => "Market sentiment for {$symbol} is " . 
                        $data->data()['overall_sentiment'] . 
                        " with " . ($data->data()['sentiment_metrics']['confidence'] * 100) . "% confidence"
        ];
    }
}
```

### OpenAI Function Calling
```php
// Function definition for OpenAI function calling
$functions = [
    [
        'name' => 'get_stock_sentiment',
        'description' => 'Get current market sentiment for a stock symbol',
        'parameters' => [
            'type' => 'object',
            'properties' => [
                'symbol' => [
                    'type' => 'string',
                    'description' => 'Stock symbol (e.g., TSLA, AAPL)'
                ],
                'timeframe' => [
                    'type' => 'string',
                    'enum' => ['1h', '1d', '7d', '30d'],
                    'description' => 'Time range for sentiment analysis'
                ]
            ],
            'required' => ['symbol']
        ]
    ]
];

function get_stock_sentiment($symbol, $timeframe = '1d') {
    global $newsManager;
    
    $data = $newsManager->get($symbol, [
        'type' => 'sentiment',
        'timeframe' => $timeframe
    ]);
    
    return $data->data();
}
```

## Support and Resources

### Provider Capabilities
Each provider has specific strengths for different AI use cases:

- **Native Provider**: Fast, comprehensive news data, structured events
- **Analysis Provider**: AI-powered insights, sentiment analysis, impact classification  
- **Sentiment Provider**: Market sentiment, mood indicators, trend analysis

### Documentation
- Full API documentation in `/examples/unified_news_manager_example.php`
- Provider-specific examples and capabilities
- Error handling and best practices

### Rate Limits and Performance
- Native Provider: 1000 requests/minute
- Analysis Provider: 100 requests/minute  
- Sentiment Provider: 300 requests/minute

AI agents should implement appropriate rate limiting and caching strategies based on their use case and provider selection.

---

*This SDK is specifically designed for AI agents and automated trading systems. All data is provided in structured, machine-readable formats optimized for algorithmic processing.*