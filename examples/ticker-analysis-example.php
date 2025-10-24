<?php

/**
 * WioEX PHP SDK - Ticker Analysis Example
 *
 * This example demonstrates comprehensive ticker analysis capabilities including:
 * - Analyst ratings and price targets
 * - Earnings insights and call analysis
 * - Insider activity tracking
 * - News sentiment analysis
 * - Options analysis (put/call ratios)
 * - Price movement explanations
 * - Financial metrics and valuation ratios
 * - Market overview and summary
 * 
 * Cost: 5 credits per analysis (premium endpoint)
 * Perfect for: Investment research, portfolio analysis, stock evaluation
 */

require __DIR__ . '/../vendor/autoload.php';

use Wioex\SDK\WioexClient;
use Wioex\SDK\Exceptions\{
    AuthenticationException,
    ValidationException,
    RateLimitException,
    RequestException,
    WioexException
};

$client = new WioexClient([
    'api_key' => 'your-api-key-here'
]);

echo "=== WioEX PHP SDK - Ticker Analysis Example ===\n\n";

// 1. Single Stock Comprehensive Analysis
echo "1. COMPREHENSIVE TICKER ANALYSIS\n";
echo str_repeat('-', 60) . "\n";

try {
    // Get comprehensive analysis for AAPL
    $analysis = $client->stocks()->tickerAnalysis('AAPL');
    
    if ($analysis->successful()) {
        // Display basic analysis information
        echo "✅ Analysis for: " . $analysis->getAnalysisSymbol() . "\n";
        echo "📊 Analysis Timestamp: " . $analysis->getAnalysisTimestamp() . "\n";
        echo "⚡ Response Time: " . $analysis->getPerformance()['total_time_ms'] . "ms\n";
        echo "💰 Credit Cost: " . $analysis->getCredits()['consumed'] . " credits\n\n";
        
        // Validate response structure
        $validation = $analysis->validateTickerAnalysisResponse();
        if ($validation->isValid()) {
            echo "✅ Analysis data validation passed\n\n";
        } else {
            echo "❌ Validation issues: " . $validation->getErrorSummary() . "\n\n";
        }
        
        // ======================
        // ANALYST RATINGS
        // ======================
        echo "📈 ANALYST RATINGS & PRICE TARGETS\n";
        echo str_repeat('=', 50) . "\n";
        
        $analystRatings = $analysis->getAnalystRatings();
        if ($analystRatings && isset($analystRatings['summary'])) {
            $summary = $analystRatings['summary'];
            echo "Summary: " . ($summary['tldr'] ?? 'Not available') . "\n";
            echo "Price Target: " . ($summary['price_target'] ?? 'Not available') . "\n";
            echo "Analyst Viewpoint: " . ($summary['viewpoint'] ?? 'Not available') . "\n";
            echo "Last Updated: " . ($analystRatings['updated_at'] ?? 'Unknown') . "\n\n";
            
            // Get structured price targets
            $priceTargets = $analysis->getAnalystPriceTargets();
            if ($priceTargets) {
                echo "Extracted Price Target: $" . ($priceTargets['current_price'] ?? 'N/A') . "\n\n";
            }
        } else {
            echo "No analyst ratings available for this symbol.\n\n";
        }
        
        // ======================
        // EARNINGS INSIGHTS
        // ======================
        echo "💼 EARNINGS INSIGHTS & ANALYSIS\n";
        echo str_repeat('=', 50) . "\n";
        
        $earnings = $analysis->getEarningsInsights();
        if ($earnings && isset($earnings['analysis'])) {
            $earningsAnalysis = $earnings['analysis'];
            echo "Summary: " . ($earningsAnalysis['tldr'] ?? 'Not available') . "\n";
            
            if (isset($earningsAnalysis['key_insights'])) {
                echo "\nKey Insights:\n";
                foreach ($earningsAnalysis['key_insights'] as $category => $insight) {
                    echo "  • {$category}: {$insight}\n";
                }
            }
            
            echo "\nFiscal Period: " . ($earnings['fiscal_period'] ?? 'Not specified') . "\n";
            echo "Fiscal Year: " . ($earnings['fiscal_year'] ?? 'Not specified') . "\n\n";
            
            // Get structured earnings performance
            $performance = $analysis->getEarningsPerformance();
            if ($performance) {
                echo "Performance Outlook: " . ($performance['outlook'] ?? 'Not available') . "\n\n";
            }
        } else {
            echo "No earnings insights available for this symbol.\n\n";
        }
        
        // ======================
        // INSIDER ACTIVITY
        // ======================
        echo "👥 INSIDER ACTIVITY & TRANSACTIONS\n";
        echo str_repeat('=', 50) . "\n";
        
        $insider = $analysis->getInsiderActivity();
        if ($insider) {
            echo "Highlights: " . ($insider['highlights'] ?? 'Not available') . "\n";
            echo "Key Takeaways: " . ($insider['key_takeaways'] ?? 'Not available') . "\n";
            echo "Last Updated: " . ($insider['analysis_update_time'] ?? 'Unknown') . "\n\n";
        } else {
            echo "No insider activity data available for this symbol.\n\n";
        }
        
        // ======================
        // NEWS & SENTIMENT
        // ======================
        echo "📰 NEWS ANALYSIS & MARKET SENTIMENT\n";
        echo str_repeat('=', 50) . "\n";
        
        $news = $analysis->getNewsAnalysis();
        if ($news) {
            echo "News Summary: " . ($news['summary'] ?? 'Not available') . "\n\n";
            
            if (isset($news['themes']) && is_array($news['themes'])) {
                echo "News Themes:\n";
                foreach ($news['themes'] as $theme) {
                    if (is_array($theme)) {
                        echo "  • " . ($theme['theme_name'] ?? 'Unknown') . ": " . ($theme['theme_description'] ?? 'No description') . "\n";
                    }
                }
                echo "\n";
            }
            
            if (isset($news['key_events']) && is_array($news['key_events'])) {
                echo "Key Events:\n";
                foreach (array_slice($news['key_events'], 0, 3) as $event) {
                    echo "  • {$event}\n";
                }
                echo "\n";
            }
        } else {
            echo "No news analysis available for this symbol.\n\n";
        }
        
        // ======================
        // OPTIONS ANALYSIS
        // ======================
        echo "📊 OPTIONS ANALYSIS & SENTIMENT\n";
        echo str_repeat('=', 50) . "\n";
        
        $options = $analysis->getOptionsAnalysis();
        if ($options) {
            if (isset($options['put_call_ratio'])) {
                $pcr = $options['put_call_ratio'];
                echo "Put/Call Ratio (Volume): " . ($pcr['pcr_volume'] ?? 'N/A') . "\n";
                echo "Put/Call Ratio (Open Interest): " . ($pcr['pcr_open_interest'] ?? 'N/A') . "\n";
            }
            
            if (isset($options['key_takeaways'])) {
                echo "Market Sentiment: " . ($options['key_takeaways']['tldr'] ?? 'Not available') . "\n";
            }
            
            echo "Analysis Date: " . ($options['date'] ?? 'Unknown') . "\n\n";
        } else {
            echo "No options analysis available for this symbol.\n\n";
        }
        
        // ======================
        // PRICE MOVEMENT
        // ======================
        echo "📈 PRICE MOVEMENT & TECHNICAL ANALYSIS\n";
        echo str_repeat('=', 50) . "\n";
        
        $priceMovement = $analysis->getPriceMovement();
        if ($priceMovement) {
            echo "Beta: " . ($priceMovement['beta'] ?? 'N/A') . "\n";
            echo "Sector: " . ($priceMovement['sector'] ?? 'Unknown') . "\n";
            echo "Industry: " . ($priceMovement['industry'] ?? 'Unknown') . "\n";
            echo "Stock Change: " . number_format($priceMovement['stock_percentage_change'] ?? 0, 2) . "%\n";
            echo "Sector Change: " . number_format($priceMovement['sector_percentage_change'] ?? 0, 2) . "%\n";
            echo "Market Change: " . number_format($priceMovement['market_percentage_change'] ?? 0, 2) . "%\n\n";
        } else {
            echo "No price movement analysis available for this symbol.\n\n";
        }
        
        // ======================
        // MARKET OVERVIEW
        // ======================
        echo "🔍 MARKET OVERVIEW & SUMMARY\n";
        echo str_repeat('=', 50) . "\n";
        
        $overview = $analysis->getAnalysisOverview();
        if ($overview) {
            echo "Market Summary: " . ($overview['summary'] ?? 'Not available') . "\n";
            
            if (isset($overview['key_observations']) && is_array($overview['key_observations'])) {
                echo "\nKey Observations:\n";
                foreach ($overview['key_observations'] as $observation) {
                    echo "  • {$observation}\n";
                }
            }
            
            echo "\nLast Updated: " . ($overview['analysis_update_time'] ?? 'Unknown') . "\n\n";
        } else {
            echo "No market overview available for this symbol.\n\n";
        }
        
        // ======================
        // MARKET SENTIMENT SUMMARY
        // ======================
        echo "📊 OVERALL MARKET SENTIMENT\n";
        echo str_repeat('=', 50) . "\n";
        
        $sentiment = $analysis->getMarketSentiment();
        if ($sentiment) {
            echo "News Sentiment: " . ($sentiment['news_summary'] ?? 'Neutral') . "\n";
            echo "Options Sentiment: " . ($sentiment['options_sentiment'] ?? 'Neutral') . "\n";
            echo "Put/Call Ratio: " . ($sentiment['put_call_ratio'] ?? 'N/A') . "\n\n";
        } else {
            echo "Market sentiment data not available.\n\n";
        }
        
    } else {
        echo "❌ Failed to get ticker analysis. Status: " . $analysis->status() . "\n";
        if ($analysis->failed()) {
            $errorData = $analysis->data();
            echo "Error: " . ($errorData['error']['message'] ?? 'Unknown error') . "\n\n";
        }
    }
    
} catch (AuthenticationException $e) {
    echo "❌ Authentication Error: " . $e->getMessage() . "\n";
    echo "Please check your API key.\n\n";
} catch (ValidationException $e) {
    echo "❌ Validation Error: " . $e->getMessage() . "\n";
    echo "The symbol might not be valid or not found in database.\n\n";
} catch (RateLimitException $e) {
    echo "❌ Rate Limit Error: " . $e->getMessage() . "\n";
    echo "Please wait before making more requests.\n\n";
} catch (RequestException $e) {
    echo "❌ Request Error: " . $e->getMessage() . "\n\n";
} catch (WioexException $e) {
    echo "❌ WioEX API Error: " . $e->getMessage() . "\n\n";
}

// 2. Portfolio Analysis Example
echo "2. PORTFOLIO ANALYSIS EXAMPLE\n";
echo str_repeat('-', 60) . "\n";

$portfolioSymbols = ['AAPL', 'GOOGL', 'MSFT', 'TSLA', 'NVDA'];
$portfolioAnalyses = [];
$totalCredits = 0;

echo "Analyzing portfolio symbols: " . implode(', ', $portfolioSymbols) . "\n\n";

foreach ($portfolioSymbols as $symbol) {
    try {
        echo "Analyzing {$symbol}... ";
        $analysis = $client->stocks()->tickerAnalysis($symbol);
        
        if ($analysis->successful()) {
            $credits = $analysis->getCredits()['consumed'] ?? 5;
            $totalCredits += $credits;
            
            // Extract key metrics for portfolio summary
            $portfolioAnalyses[$symbol] = [
                'analyst_consensus' => null,
                'earnings_outlook' => null,
                'news_sentiment' => null,
                'options_sentiment' => null,
                'beta' => null,
                'sector' => null
            ];
            
            // Get analyst consensus
            $ratings = $analysis->getAnalystRatings();
            if ($ratings && isset($ratings['summary']['viewpoint'])) {
                $portfolioAnalyses[$symbol]['analyst_consensus'] = $ratings['summary']['viewpoint'];
            }
            
            // Get earnings outlook
            $earnings = $analysis->getEarningsInsights();
            if ($earnings && isset($earnings['analysis']['key_insights']['Outlook'])) {
                $portfolioAnalyses[$symbol]['earnings_outlook'] = $earnings['analysis']['key_insights']['Outlook'];
            }
            
            // Get news sentiment
            $news = $analysis->getNewsAnalysis();
            if ($news && isset($news['summary'])) {
                $portfolioAnalyses[$symbol]['news_sentiment'] = substr($news['summary'], 0, 100) . '...';
            }
            
            // Get options sentiment
            $options = $analysis->getOptionsAnalysis();
            if ($options && isset($options['key_takeaways']['tldr'])) {
                $portfolioAnalyses[$symbol]['options_sentiment'] = $options['key_takeaways']['tldr'];
            }
            
            // Get beta and sector
            $priceMovement = $analysis->getPriceMovement();
            if ($priceMovement) {
                $portfolioAnalyses[$symbol]['beta'] = $priceMovement['beta'] ?? null;
                $portfolioAnalyses[$symbol]['sector'] = $priceMovement['sector'] ?? null;
            }
            
            echo "✅ Success\n";
        } else {
            echo "❌ Failed\n";
        }
        
        // Add small delay to respect rate limits
        usleep(200000); // 200ms delay
        
    } catch (Exception $e) {
        echo "❌ Error: " . $e->getMessage() . "\n";
    }
}

// Generate Portfolio Report
echo "\n" . str_repeat('=', 60) . "\n";
echo "PORTFOLIO ANALYSIS REPORT\n";
echo str_repeat('=', 60) . "\n";
echo "Total Credits Used: {$totalCredits}\n";
echo "Symbols Analyzed: " . count($portfolioAnalyses) . "/" . count($portfolioSymbols) . "\n\n";

foreach ($portfolioAnalyses as $symbol => $data) {
    echo "📊 {$symbol}:\n";
    echo "  Sector: " . ($data['sector'] ?? 'Unknown') . "\n";
    echo "  Beta: " . ($data['beta'] ?? 'N/A') . "\n";
    echo "  Analyst View: " . ($data['analyst_consensus'] ?? 'No data') . "\n";
    echo "  Earnings Outlook: " . ($data['earnings_outlook'] ?? 'No data') . "\n";
    echo "  News Summary: " . ($data['news_sentiment'] ?? 'No data') . "\n";
    echo "  Options Sentiment: " . ($data['options_sentiment'] ?? 'No data') . "\n";
    echo "\n";
}

// 3. Error Handling and Validation Example
echo "3. ERROR HANDLING & VALIDATION EXAMPLE\n";
echo str_repeat('-', 60) . "\n";

try {
    // Test with invalid symbol
    echo "Testing with invalid symbol 'INVALID123'...\n";
    $invalidAnalysis = $client->stocks()->tickerAnalysis('INVALID123');
    
    if ($invalidAnalysis->failed()) {
        echo "❌ Expected failure for invalid symbol\n";
        $errorData = $invalidAnalysis->data();
        echo "Error Code: " . ($errorData['error']['error_code'] ?? 'Unknown') . "\n";
        echo "Error Message: " . ($errorData['error']['message'] ?? 'Unknown error') . "\n\n";
    }
    
} catch (Exception $e) {
    echo "❌ Exception caught: " . $e->getMessage() . "\n\n";
}

// 4. Using Enhanced Alias Method
echo "4. ENHANCED ALIAS METHOD EXAMPLE\n";
echo str_repeat('-', 60) . "\n";

try {
    echo "Using analysisDetailed() method for enhanced validation...\n";
    $detailedAnalysis = $client->stocks()->analysisDetailed('SOUN');
    
    if ($detailedAnalysis->successful()) {
        // Validate response structure
        $validation = $detailedAnalysis->validateTickerAnalysisResponse();
        if ($validation->isValid()) {
            echo "✅ Enhanced validation passed for SOUN\n";
            
            // Show summary
            $overview = $detailedAnalysis->getAnalysisOverview();
            if ($overview && isset($overview['summary'])) {
                echo "Summary: " . substr($overview['summary'], 0, 150) . "...\n";
            }
        } else {
            echo "❌ Validation failed: " . $validation->getErrorSummary() . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error with enhanced method: " . $e->getMessage() . "\n";
}

echo "\n" . str_repeat('=', 60) . "\n";
echo "💡 TICKER ANALYSIS FEATURES DEMONSTRATED:\n";
echo "✅ Comprehensive single stock analysis\n";
echo "✅ Portfolio analysis across multiple stocks\n";
echo "✅ Structured data access with helper methods\n";
echo "✅ Response validation and error handling\n";
echo "✅ Credit tracking and cost management\n";
echo "✅ Market sentiment analysis\n";
echo "✅ Professional investment research data\n";
echo "\n📋 USE CASES:\n";
echo "• Investment research and due diligence\n";
echo "• Portfolio analysis and optimization\n";
echo "• Market sentiment tracking\n";
echo "• Earnings analysis and forecasting\n";
echo "• Options trading strategy development\n";
echo "• Risk assessment and beta analysis\n";
echo "\n💰 COST: 5 credits per analysis (premium endpoint)\n";
echo "📊 DATA: Institutional-grade financial analysis\n";
echo "⚡ PERFORMANCE: Real-time analysis with metadata\n";
echo "\n=== Ticker Analysis Example Completed ===\n";