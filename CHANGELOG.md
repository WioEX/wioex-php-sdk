# Changelog

All notable changes to the WioEX PHP SDK will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.2.1] - 2025-10-22

### Fixed
- **API Parameter Alignment** - Updated stocks endpoint to use correct parameter name
  - `quote()` method now sends `stocks` parameter instead of `ticker` 
  - Aligns with `/v2/stocks/get` endpoint expecting `stocks` parameter
  - **Breaking Change**: Direct API calls should use `?stocks=` instead of `?ticker=`
  - SDK method usage remains unchanged (backward compatible)

### Enhanced  
- **Error Handling** - Improved support for new centralized error format
  - Handles nested error responses: `{"error": {"message": "...", "code": "..."}}`
  - Maintains backward compatibility with legacy error format
  - Better error message extraction for user-friendly error reporting

### Technical
- **Response Processing** - Enhanced error message parsing for both formats
- **API Compatibility** - Ensures SDK works with latest API error responses

## [1.2.0] - 2025-10-16

### Added
- **Advanced Timeline Features** - Enhanced historical data capabilities
  - **Session Filtering** - Filter 1-minute data by trading sessions:
    - `regular`: Standard market hours (9:30 AM - 4:00 PM EST)
    - `pre_market`: Pre-market trading (4:00 AM - 9:30 AM EST)
    - `after_hours`: After-hours trading (4:00 PM - 8:00 PM EST)
    - `extended`: All extended hours combined (4:00 AM - 8:00 PM EST)
  - **Date Filtering** - Start timeline data from specific date
    - `started_date`: Date string format (e.g., '2024-10-16')
    - `timestamp`: Unix timestamp alternative
  - **Convenience Methods** for common use cases:
    - `intradayTimeline()`: Regular trading hours only
    - `extendedHoursTimeline()`: All extended hours data
    - `timelineFromDate()`: Data from specific date onwards
    - `timelineBySession()`: Session-specific data

- **Improved Data Source** - Primary integration with Investing.com API
  - More reliable data availability for major stocks
  - Finbold API maintained as fallback
  - Response includes `data_source` metadata

- **New Example File** - `timeline-advanced-example.php` demonstrating all new features

### Enhanced
- **Timeline Documentation** - Comprehensive parameter documentation in `timeline()` method
- **README.md** - Updated with session filtering examples and trading hours reference
- **Type Safety** - All new methods include full PHPDoc annotations

### Technical
- **Backend API Integration** - Session filtering implemented server-side
- **Manual Date Filtering** - Client-side filtering for precise date ranges
- **Backward Compatibility** - All existing timeline usage remains unchanged

## [1.1.0] - 2025-10-14

### Added
- **Trading Signals Support** - New `signals()` resource for accessing trading signals
  - `signals()->active()` - Get active trading signals with filters
  - `signals()->history()` - Get signal history (triggered/expired signals)
  - Signal types: BUY, SELL, HOLD, STRONG_BUY, STRONG_SELL
  - Signal trigger types: entry, target, stop_loss, expired
  - Confidence levels, timeframes, and detailed reasoning

- **Auto-included Signals in Stock Data**
  - `stocks()->quote()` now automatically includes active signals
  - `stocks()->info()` now automatically includes active signals
  - No additional API calls needed
  - Signals appear under `signal` key in response

- **New Examples**
  - `examples/test_signals.php` - Signal API examples
  - `examples/test_stock_with_signal.php` - Integrated signal examples

### Changed
- Updated README.md with comprehensive trading signals documentation
- Added signal integration notes to stock data sections
- Updated Quick Start examples to show signal usage
- Enhanced composer.json description and keywords

### Performance
- Stock data + signals in single API call (50% reduction in API calls)
- Optimized response structure for better developer experience

## [1.0.0] - 2024-10-07

### Added
- Initial release of WioEX PHP SDK
- Stocks resource with methods:
  - `search()` - Search stocks by symbol or name
  - `quote()` - Get real-time stock quotes
  - `info()` - Get detailed company information
  - `timeline()` - Get historical price data
  - `list()` - Get list of available stocks
  - `financials()` - Get financial statements
  - `heatmap()` - Get market heatmap data
  - `minimalChart()` - Get lightweight chart data

- Screens resource for market movers:
  - `gainers()`, `losers()`, `active()`
  - Pre-market and post-market data
  - IPO information

- News resource:
  - `latest()` - Get latest news
  - `companyAnalysis()` - Get company analysis

- Currency resource:
  - `baseUsd()` - Get USD exchange rates
  - `allRates()` - Get all rates for a base currency
  - `calculator()` - Currency conversion
  - `graph()` - Historical exchange rates

- Account resource:
  - `balance()` - Check API credit balance
  - `usage()` - Get usage statistics
  - `analytics()` - Get usage analytics
  - `keys()` - List API keys

- Core features:
  - PSR-4 autoloading
  - Automatic retry with exponential backoff
  - Rate limit handling
  - Comprehensive error handling
  - Response wrapper with array access
  - Full IDE autocomplete support
