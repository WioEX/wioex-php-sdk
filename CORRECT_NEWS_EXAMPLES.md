# Correct News API Examples for README.md

## Current Problems in README

❌ **Wrong examples in README.md:**
```php
// WRONG - These methods don't exist
$latestNews = $client->news()->latest($symbol, ['limit' => 10]);
$marketNews = $client->news()->marketNews(['categories' => ['earnings']]);
$advancedAnalysis = $client->news()->advancedAnalysis([]);

// WRONG - Response structure is incorrect
foreach ($latestNews['articles'] as $article) {
    echo $article['title'];
}
```

## Correct Usage

✅ **Correct examples that should replace README section:**

```php
### News & Analysis

// 1. Get latest news for stocks
$portfolioSymbols = ['AAPL', 'GOOGL', 'MSFT', 'TSLA'];

foreach ($portfolioSymbols as $symbol) {
    try {
        $response = $client->news()->latest($symbol);
        
        if ($response->successful()) {
            $newsData = $response->getData();
            $articles = $newsData['news'] ?? [];
            
            echo "Latest News for {$symbol}:\n";
            echo "Found " . count($articles) . " articles\n";
            
            foreach (array_slice($articles, 0, 5) as $article) {
                echo "• " . $article['title'] . "\n";
                echo "  Source: " . $article['source'] . "\n";
                echo "  Published: " . $article['publish_date'] . "\n";
                
                // Show related stocks if available
                if (!empty($article['stocks'])) {
                    $relatedStocks = array_column($article['stocks'], 'symbol');
                    echo "  Related: " . implode(', ', $relatedStocks) . "\n";
                }
                echo "\n";
            }
        }
    } catch (Exception $e) {
        echo "Error fetching news for {$symbol}: " . $e->getMessage() . "\n";
    }
}

// 2. Get company analysis data
foreach ($portfolioSymbols as $symbol) {
    try {
        $response = $client->news()->companyAnalysis($symbol);
        
        if ($response->successful()) {
            $analysisData = $response->getData();
            $analysis = $analysisData['companyAnalysis'] ?? [];
            
            echo "{$symbol} Company Analysis:\n";
            echo "Company: " . ($analysis['company_name'] ?? 'N/A') . "\n";
            echo "Market Cap: $" . number_format(($analysis['market_cap'] ?? 0)) . "M\n";
            echo "Sector: " . ($analysis['sector_description'] ?? 'N/A') . "\n";
            echo "Employees: " . number_format(($analysis['employee_count'] ?? 0)) . "\n";
            
            if (isset($analysis['current_recommendation'])) {
                $recommendation = $analysis['current_recommendation'];
                $recText = match(true) {
                    $recommendation <= 1.5 => 'Strong Buy',
                    $recommendation <= 2.5 => 'Buy',
                    $recommendation <= 3.5 => 'Hold', 
                    $recommendation <= 4.5 => 'Sell',
                    default => 'Strong Sell'
                };
                echo "Analyst Rating: {$recText} ({$recommendation})\n";
            }
            echo "\n";
        }
    } catch (Exception $e) {
        echo "Error fetching analysis for {$symbol}: " . $e->getMessage() . "\n";
    }
}

// 3. News-based portfolio insights
echo "News-Based Portfolio Summary:\n";
echo "============================\n";

$totalArticles = 0;
$companiesWithNews = 0;

foreach ($portfolioSymbols as $symbol) {
    $response = $client->news()->latest($symbol);
    if ($response->successful()) {
        $newsData = $response->getData();
        $articleCount = count($newsData['news'] ?? []);
        
        if ($articleCount > 0) {
            $companiesWithNews++;
            $totalArticles += $articleCount;
            
            // Get most recent article
            $latestArticle = $newsData['news'][0] ?? null;
            if ($latestArticle) {
                echo "{$symbol}: {$articleCount} articles\n";
                echo "  Latest: \"" . substr($latestArticle['title'], 0, 60) . "...\"\n";
                echo "  Source: " . $latestArticle['source'] . "\n\n";
            }
        }
    }
}

echo "Portfolio News Summary:\n";
echo "Total companies with news: {$companiesWithNews}/{" . count($portfolioSymbols) . "}\n";
echo "Total articles: {$totalArticles}\n";
echo "Average per company: " . number_format($totalArticles / count($portfolioSymbols), 1) . "\n";
```

## Response Format Reference

### news()->latest() Response:
```json
{
  "status": "success",
  "data": {
    "wioex": {
      "server": "SarexWay",
      "service": "news",
      "requests": {"ticker": "AAPL", "real_time": false, "delay": "5min"}
    },
    "news": [
      {
        "source": "CNBC",
        "title": "Apple begins shipping American-made AI servers from Texas",
        "publish_date": "Thu, 23 Oct 2025 17:26:11 -0400", 
        "symbols": "AAPL",
        "link": "https://www.cnbc.com/2025/10/23/apple-american-made-ai-servers-texas.html",
        "stocks": [
          {
            "symbol": "AAPL",
            "price": "262.85", 
            "change": "+3.27",
            "change_percent": "+1.26%",
            "timestamp": "1761335847"
          }
        ]
      }
    ]
  }
}
```

### news()->companyAnalysis() Response:
```json
{
  "status": "success", 
  "data": {
    "companyAnalysis": {
      "company_name": "Apple",
      "market_cap": 3835500,
      "sector_description": "Computer and Technology", 
      "employee_count": 164000,
      "current_recommendation": 2.01,
      "pe_ratio_next_year": 32.89,
      "dividend_yield": 0.4
    }
  }
}
```

## What Needs to be Fixed

1. **Remove fake methods** from README:
   - `marketNews()`
   - `advancedAnalysis()`

2. **Fix method signatures**:
   - `latest($ticker)` only takes ticker parameter
   - `companyAnalysis($ticker)` only takes ticker parameter

3. **Update response structure**:
   - Use `$response->getData()['news']` instead of `['articles']`
   - Use correct field names from actual API response

4. **Add proper error handling**:
   - Check `$response->successful()`
   - Use try-catch blocks

This will prevent customers from getting 500 errors due to incorrect usage examples.