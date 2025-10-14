# Changelog

All notable changes to the WioEX PHP SDK will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
