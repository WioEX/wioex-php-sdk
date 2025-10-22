# Changelog

All notable changes to the WioEX PHP SDK will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.4.0] - 2025-10-22

### Added
- **Enhanced Timeline Intervals** - Comprehensive support for 17 different interval types
  - **Minute Intervals**: `1min`, `5min`, `15min`, `30min` (high frequency, optimized caching)
  - **Hour Intervals**: `1hour`, `5hour` (medium frequency)  
  - **Daily/Weekly/Monthly**: `1day`, `1week`, `1month` (low frequency)
  - **Period-Based Intervals**: `1d`, `1w`, `1m`, `3m`, `6m`, `1y`, `5y`, `max` (optimized for specific timeframes)

- **New Convenience Methods** - Simplified access to common timeline use cases
  - `timelineFiveMinute()` - 5-minute detailed analysis for day trading
  - `timelineHourly()` - Hourly data for swing trading
  - `timelineWeekly()` - Weekly trends for medium-term analysis
  - `timelineMonthly()` - Monthly overview for long-term investing
  - `timelineOneYear()` - Optimized 1-year view with automatic weekly intervals
  - `timelineMax()` - Maximum historical data with automatic monthly intervals

- **Two-Branch JSON Response** - Professional API response structure
  - **Metadata Branch**: API information, caching status, usage tracking, UTC timestamps
  - **Data Branch**: Business data with symbol info, market status, timeline points
  - Clean separation for better client integration and data processing

- **Intelligent Caching** - Interval-based cache optimization
  - **Real-time intervals** (1min): 60 seconds cache
  - **Short-term intervals** (5min-30min): 5-30 minutes cache  
  - **Medium-term intervals** (1hour-1day): 1 hour cache
  - **Long-term intervals** (1week-1month): 2-4 hours cache
  - **Historical intervals** (1y-max): 8-48 hours cache

- **Period-Based Optimization** - Automatic interval selection for optimal data retrieval
  - `1d` period uses 5-minute intervals for intraday detail
  - `1y` period uses weekly intervals for efficiency
  - `max` period uses monthly intervals for complete history
  - Balances data detail with API performance

- **Enhanced Documentation** - Comprehensive timeline guide with trading examples
  - Updated README.md with 17 interval types and usage patterns
  - New example files: `timeline-enhanced-example.php` and `timeline-convenience-methods.php`
  - Trading strategy examples for different timeframes and methods

### Enhanced
- **Timeline Method** - Expanded `timeline()` documentation with all 17 intervals
  - Detailed parameter descriptions for optimal usage
  - Session filtering compatibility with minute-level intervals
  - Period-based interval mapping for automatic optimization

- **API Branding** - Professional WioEX response templating
  - Clear WioEX branding in all timeline responses
  - UTC timestamp standardization for global compatibility
  - Cache transparency with status and TTL information

### Technical
- **Backend Integration** - Period-based interval optimization server-side
- **Provider Abstraction** - Clean separation of data sources from SDK interface
- **Cache Strategy** - Frequency-based TTL optimization for different use cases
- **Response Standardization** - Consistent two-branch structure across all timeline endpoints

### Performance
- **Reduced API Calls** - Period-based optimization minimizes unnecessary requests
- **Optimized Caching** - Intelligent TTL based on data frequency needs
- **Efficient Data Retrieval** - Automatic interval selection for best performance/detail balance

## [1.3.1] - 2025-10-22

### Fixed
- **HTTP Method Correction** - Fixed streaming token endpoint to use correct HTTP method
  - `streaming()->getToken()` now uses POST instead of GET for `/v1/stream/token`
  - Resolves API compatibility issue where endpoint expects POST request
  - Maintains backward compatibility in SDK usage

## [1.3.0] - 2025-10-22

### Added
- **WebSocket Streaming Support** - New streaming resource for real-time market data
  - `streaming()->getToken()` method for WebSocket authentication
  - JWT-based token authentication for secure streaming connections
  - Complete WebSocket integration examples and documentation
  - Support for `/v1/stream/token` endpoint

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
- **Code Quality** - PSR12 compliance improvements across all source files
  - Fixed line length violations and improved type hints
  - Enhanced documentation and code readability

### Technical
- **Response Processing** - Enhanced error message parsing for both formats
- **API Compatibility** - Ensures SDK works with latest API error responses
- **Documentation** - Comprehensive streaming integration guide added

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
