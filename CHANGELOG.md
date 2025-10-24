# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.4.0] - 2025-10-24

### 🚀 Major Feature Release - Comprehensive Ticker Analysis

This release introduces professional-grade ticker analysis capabilities, transforming the WioEX PHP SDK into a complete investment research platform. The new ticker analysis feature provides institutional-quality financial data combining multiple analysis perspectives into unified, actionable insights.

### ✨ New Features

#### 🔍 Comprehensive Ticker Analysis System
- **NEW**: `tickerAnalysis()` method - Complete stock analysis with 5 credit cost for premium institutional data
- **NEW**: `analysisDetailed()` convenience method - Enhanced analysis with validation and error handling
- **NEW**: Institutional-grade data integration combining analyst ratings, earnings insights, insider activity, news sentiment, options analysis, and technical indicators

#### 📊 15+ Professional Helper Methods
- **NEW**: `getTickerAnalysis()` - Access complete analysis data structure
- **NEW**: `getAnalystRatings()` - Analyst price targets and recommendations
- **NEW**: `getEarningsInsights()` - Quarterly earnings analysis and guidance
- **NEW**: `getInsiderActivity()` - Executive transaction tracking and sentiment
- **NEW**: `getNewsAnalysis()` - Market sentiment and news theme analysis
- **NEW**: `getOptionsAnalysis()` - Put/call ratios and options sentiment
- **NEW**: `getPriceMovement()` - Technical analysis and sector comparison
- **NEW**: `getFinancialMetrics()` - Valuation ratios and growth indicators
- **NEW**: `getAnalysisOverview()` - Comprehensive market summary
- **NEW**: `getAnalystPriceTargets()` - Structured price target extraction
- **NEW**: `getEarningsPerformance()` - Earnings highlights and outlook
- **NEW**: `getMarketSentiment()` - Overall sentiment analysis
- **NEW**: `hasTickerAnalysis()` - Analysis data availability check
- **NEW**: `getAnalysisSymbol()` - Analyzed stock symbol
- **NEW**: `getAnalysisTimestamp()` - Analysis generation time

#### 🛡️ Professional Validation & Quality Assurance
- **NEW**: `validateTickerAnalysisResponse()` - Comprehensive response validation
- **NEW**: `tickerAnalysisSchema()` - Complete validation schema for all analysis sections
- **NEW**: Type-safe analysis data structures with PHPStan compliance
- **NEW**: Professional error handling for failed analysis requests

#### 📚 Documentation & Examples
- **NEW**: `ticker-analysis-example.php` - Comprehensive 300+ line example file
- **NEW**: Portfolio analysis examples with multiple stock processing
- **NEW**: Error handling and validation demonstrations
- **NEW**: Professional README section with complete feature overview
- **NEW**: Detailed PHPDoc documentation for all methods

### 🔧 Technical Improvements

#### Investment Research Platform
- **ENHANCED**: Complete investment research workflow support
- **ENHANCED**: Professional-grade data validation and error reporting
- **ENHANCED**: Structured access to complex financial analysis data
- **ENHANCED**: Cost tracking and performance monitoring for analysis requests

#### Developer Experience
- **IMPROVED**: IntelliSense support with comprehensive type hints
- **IMPROVED**: Professional error messages and debugging information
- **IMPROVED**: Consistent API patterns across all analysis methods
- **IMPROVED**: Comprehensive validation reporting for data quality assurance

### 💼 Use Cases Enabled

#### Investment Research
- Comprehensive due diligence and stock evaluation
- Portfolio analysis and optimization strategies
- Market sentiment tracking and analysis
- Risk assessment and beta analysis

#### Professional Trading
- Options trading strategy development
- Earnings analysis and forecasting
- Insider activity monitoring
- Technical and fundamental analysis integration

#### Financial Applications
- Investment research platforms
- Portfolio management tools
- Market sentiment dashboards
- Automated trading signal generation

### 🎯 Perfect For
- **Hedge Funds**: Professional-grade analysis for investment decisions
- **Financial Advisors**: Client portfolio research and recommendations
- **Individual Investors**: Comprehensive stock evaluation and research
- **Fintech Applications**: Institutional-quality data integration
- **Trading Platforms**: Advanced analysis and sentiment tracking

**Cost**: 5 credits per analysis (premium institutional-grade endpoint)
**Data Quality**: Real-time institutional-grade financial analysis
**Performance**: Optimized for professional trading applications

## [2.3.3] - 2025-10-23

### 🔧 Critical Bug Fixes & PHP 8.4 Compatibility

#### Customer-Reported Issues Resolved
- **FIXED**: Method redeclaration conflict `isDetailedMode()` in Response.php (resolved in v2.3.2)
- **FIXED**: PHP 8.4 deprecated `mixed $parameter = null` patterns
- **FIXED**: Nullable parameter deprecation warnings in PHP 8.4
- **ENHANCED**: Full PHP 8.4 compatibility across all SDK components

#### Files Updated
- `src/Exceptions/InvalidArgumentException.php` - Removed deprecated mixed parameter defaults
- `src/Middleware/AbstractMiddleware.php` - Fixed getConfigValue() parameter type
- `src/Connection/Connection.php` - Updated getMetadataValue() signature

This release addresses critical customer feedback and ensures seamless operation with the latest PHP versions.

## [2.3.2] - 2025-10-23

### 🐛 Bug Fixes
- **FIXED**: Duplicate `isDetailedMode()` method in Response.php
- **FIXED**: PSR-4 compliance - renamed `Built-in` folder to `BuiltIn`

## [2.3.1] - 2025-10-23

### 🔒 Security Update
- **FIXED**: Source masking compliance in documentation and examples

## [2.3.0] - 2025-10-23

### 🚀 Major Enhancement Release - Unified ResponseTemplate & Type Safety

Version 2.3.0 introduces unified ResponseTemplate support across all WioEX API endpoints, enhanced stock data with institutional-grade data integration, and complete PHPStan compliance for professional-grade type safety.

### ✨ New Features

#### Unified ResponseTemplate Support
- **NEW**: Standardized response format across all WioEX API endpoints
- **NEW**: Enhanced metadata structure with WioEX branding, performance metrics, and data quality indicators
- **NEW**: Unified `metadata` and `data` top-level structure for consistency
- **NEW**: Professional response validation and error handling

#### Enhanced Stock Data Integration
- **NEW**: Institutional-grade data integration with detailed market data
- **NEW**: `quoteDetailed()` method for enhanced stock quotes
- **NEW**: Pre/post market trading data with price and change information
- **NEW**: 52-week high/low ranges for comprehensive analysis
- **NEW**: Market capitalization data
- **NEW**: Company logos and enhanced company information
- **NEW**: Extended hours trading data (overnight markets)
- **NEW**: Institutional-grade data accuracy indicators

#### Advanced Type Safety
- **NEW**: Complete PHPStan compliance with comprehensive array type specifications
- **NEW**: Precise type hints for all method parameters and return values
- **NEW**: Enhanced IDE support with detailed autocompletion
- **NEW**: Type-safe array structures: `array<string, mixed>`, `array<int, string>`, etc.
- **NEW**: Professional PHPDoc comments throughout codebase

#### Enhanced Response Access Methods
- **NEW**: 20+ metadata helper methods for improved data access
- **NEW**: `getWioexMetadata()` - Access WioEX branding and API information
- **NEW**: `getPerformance()` - Response time and server performance metrics
- **NEW**: `getCredits()` - Credit consumption and balance tracking
- **NEW**: `getDataQuality()` - Data freshness and accuracy indicators
- **NEW**: `getRequestId()` and `getResponseTime()` - Request tracking
- **NEW**: `hasEnhancedData()` - Check for extended market data availability
- **NEW**: `getExtendedHoursData()` - Access pre/post market information
- **NEW**: `getBasicPriceData()` - Simplified price data extraction

#### Validation & Quality Assurance
- **NEW**: Enhanced validation schemas for all response types
- **NEW**: `validateEnhancedStockQuote()` - Detailed stock data validation
- **NEW**: `validateUnifiedResponse()` - Unified format validation
- **NEW**: Professional validation reporting with detailed error messages
- **NEW**: Schema validation for stocks, news, currency, and market status

#### Backward Compatibility
- **NEW**: Automatic legacy format detection and adaptation
- **NEW**: `isUnifiedFormat()` and `isLegacyFormat()` - Format detection methods
- **NEW**: `adaptLegacyTickersToInstruments()` - Automatic `tickers` → `instruments` conversion
- **NEW**: `getLegacyCompatibleData()` - Legacy format compatibility layer
- **NEW**: Seamless migration path from v2.x to unified format

### 🔧 Improvements

#### Code Quality
- **IMPROVED**: Removed unused properties and deprecated patterns
- **IMPROVED**: Enhanced method signatures with precise type declarations
- **IMPROVED**: Comprehensive error handling and validation reporting
- **IMPROVED**: Better debugging capabilities with detailed metadata
- **IMPROVED**: Reduced runtime type errors through static analysis

#### Developer Experience
- **IMPROVED**: Enhanced IDE support with precise type hints
- **IMPROVED**: Better autocompletion and code intelligence
- **IMPROVED**: Comprehensive documentation with real-world examples
- **IMPROVED**: Professional error messages and validation feedback

#### Performance
- **IMPROVED**: Optimized metadata access with helper methods
- **IMPROVED**: Efficient response processing and validation
- **IMPROVED**: Enhanced caching strategies with unified format

### 📖 Documentation

#### New Examples
- **NEW**: `examples/enhanced-stocks-example.php` - Comprehensive enhanced stocks demonstration
- **NEW**: Unified ResponseTemplate usage examples
- **NEW**: Metadata access and validation examples
- **NEW**: Pre/post market data access patterns
- **NEW**: Backward compatibility usage examples

#### Updated Documentation
- **UPDATED**: README.md with v2.3.0 features and examples
- **UPDATED**: Response structure documentation with unified format
- **UPDATED**: API method documentation with type specifications
- **UPDATED**: Migration guide for unified format adoption

### 🔄 Migration Notes

#### Upgrading to v2.3.0
- **Automatic**: Legacy response formats are automatically adapted
- **Optional**: Migrate to new unified format for enhanced features
- **Recommended**: Use new metadata helper methods for better data access
- **Compatible**: All existing code continues to work without changes

#### New Recommended Patterns
```php
// Unified format access (recommended)
$response = $client->stocks()->quoteDetailed('AAPL');
$instruments = $response->getInstruments();
$metadata = $response->getWioexMetadata();
$performance = $response->getPerformance();

// Enhanced validation
$validation = $response->validateEnhancedStockQuote();
if ($validation->isValid()) {
    echo "✅ Response validation passed\n";
}
```

### 🛡️ Breaking Changes
- **NONE**: Full backward compatibility maintained
- **Recommendation**: Migrate to unified ResponseTemplate format for new features

## [2.0.0] - 2025-10-22

### 🚀 Major Stability Release - Production Ready

Version 2.0.0 represents a major stability and quality improvement release. All critical runtime errors have been resolved, comprehensive testing has been implemented, and the SDK is now production-ready with enterprise-grade reliability.

### 🔧 Critical Fixes

#### Fixed Fatal Errors
- **FIXED**: `Fatal error: Call to undefined method Wioex\SDK\WioexClient::streaming()` 
- **FIXED**: Missing resource methods in WioexClient class
- **FIXED**: All resource methods now properly accessible: `streaming()`, `screens()`, `signals()`, `markets()`, `news()`, `currency()`, `account()`
- **FIXED**: Type safety issues and nullable parameter deprecations
- **FIXED**: HTTP client cache method accessibility
- **FIXED**: Config class missing essential methods

#### Runtime Stability
- **FIXED**: AsyncClient HTTP method type issues 
- **FIXED**: Missing getCache() method in HTTP Client
- **FIXED**: WioexClient return type mismatches
- **FIXED**: Configuration validation and error handling
- **FIXED**: Resource initialization and lazy loading

### ✨ New Features

#### Export Utilities
- **NEW**: Comprehensive data export system
- **NEW**: Support for multiple formats: JSON, CSV, XML, Excel (XLSX)
- **NEW**: Export to files or string output
- **NEW**: Batch export capabilities for multiple datasets
- **NEW**: Export progress callbacks and statistics
- **NEW**: Custom export options (pretty printing, compression, delimiters)

```php
use Wioex\SDK\Export\ExportManager;
use Wioex\SDK\Enums\ExportFormat;

$exportManager = new ExportManager();
$stockData = $client->stocks()->quote('AAPL,GOOGL,MSFT');

// Export to multiple formats
$exportManager->exportToFile($stockData, ExportFormat::JSON, 'stocks.json');
$exportManager->exportToFile($stockData, ExportFormat::CSV, 'stocks.csv');
```

#### Configuration Management System
- **NEW**: Environment-based configuration loading
- **NEW**: Configuration file support (.php, .json, .yaml, .env)
- **NEW**: Dynamic configuration updates
- **NEW**: Configuration validation and watching
- **NEW**: Multi-source configuration merging with priority

```php
// Environment-based configuration
$client = WioexClient::fromEnvironment(Environment::PRODUCTION);

// File-based configuration
$client = WioexClient::fromConfig('config/wioex.php');

// Dynamic configuration
$client->configure(['timeout' => 60, 'debug' => true]);
```

#### Enhanced Error Reporting
- **NEW**: Professional error reporting levels (MINIMAL, STANDARD, DETAILED)
- **NEW**: Structured error logging and telemetry
- **NEW**: Context-aware error messages
- **NEW**: Privacy-focused error reporting options
- **NEW**: Production-safe error handling

```php
use Wioex\SDK\Enums\ErrorReportingLevel;

$client = new WioexClient([
    'api_key' => 'your-key',
    'error_reporting' => true,
    'error_reporting_level' => ErrorReportingLevel::STANDARD
]);
```

### 🧪 Testing & Quality Assurance

#### Comprehensive Test Suite
- **NEW**: 135+ unit tests with comprehensive coverage
- **NEW**: Core classes: 100% test coverage
- **NEW**: Resource classes: 95%+ test coverage  
- **NEW**: Error handling: 100% test coverage
- **NEW**: Export utilities: 90%+ test coverage
- **NEW**: Integration tests for all major features
- **NEW**: Smoke tests for production validation

#### Static Analysis & Code Quality
- **IMPROVED**: PHPStan Level 9 compliance (strict analysis)
- **IMPROVED**: PSR-12 code style compliance
- **IMPROVED**: Full type safety throughout codebase
- **IMPROVED**: Comprehensive PHPDoc documentation
- **IMPROVED**: Strict type declarations and return types

### 🔄 Enhanced Existing Features

#### Resource Access
- **IMPROVED**: All resource methods properly implemented and accessible
- **IMPROVED**: Consistent method signatures across resources
- **IMPROVED**: Enhanced lazy loading for resource initialization
- **IMPROVED**: Better error handling in resource methods

#### HTTP Client
- **IMPROVED**: Enhanced Guzzle integration with proper mocking support
- **IMPROVED**: Better cache interface implementation
- **IMPROVED**: Improved timeout and retry configuration
- **IMPROVED**: Enhanced authentication header handling

#### Configuration System
- **IMPROVED**: Better default configuration values
- **IMPROVED**: Enhanced validation for configuration options
- **IMPROVED**: Support for environment variables
- **IMPROVED**: More flexible configuration merging

### 🛡️ Security & Performance

#### Security Improvements
- **IMPROVED**: Secure API key handling and identification
- **IMPROVED**: Enhanced error reporting with privacy considerations
- **IMPROVED**: Secure configuration file loading
- **IMPROVED**: Protection against information disclosure in errors

#### Performance Optimizations
- **IMPROVED**: More efficient resource loading and caching
- **IMPROVED**: Optimized HTTP client configuration
- **IMPROVED**: Better memory usage in large data operations
- **IMPROVED**: Enhanced export performance for large datasets

### 📚 Documentation & Examples

#### Documentation Updates
- **UPDATED**: Comprehensive README with all new features
- **UPDATED**: Enhanced API documentation with examples
- **UPDATED**: Migration guide for seamless upgrading
- **UPDATED**: Quality assurance and testing documentation
- **NEW**: Configuration management documentation
- **NEW**: Export utilities documentation

#### New Examples
- **NEW**: `export-example.php` - Data export demonstrations
- **NEW**: `configuration-example.php` - Environment-based configuration
- **UPDATED**: All existing examples with new features
- **IMPROVED**: Error handling examples with new exception types

### 🔄 Backward Compatibility

#### Fully Backward Compatible
- ✅ **NO BREAKING CHANGES**: All existing code continues to work unchanged
- ✅ **Method Signatures**: All existing method signatures preserved
- ✅ **Response Formats**: All response formats remain unchanged
- ✅ **Configuration Options**: All existing configuration options supported
- ✅ **Error Handling**: Existing error handling behavior preserved

#### Migration Path
- **SEAMLESS**: Upgrade from v1.x to v2.0.0 requires no code changes
- **OPTIONAL**: New features are opt-in and don't affect existing functionality
- **COMPATIBLE**: All v1.x examples and code samples continue to work

### 📦 Dependencies & Requirements

#### Updated Dependencies
- **MAINTAINED**: PHP 8.1+ requirement (unchanged)
- **MAINTAINED**: Guzzle HTTP 7.8+ requirement (unchanged)
- **MAINTAINED**: ext-json requirement (unchanged)
- **ADDED**: ext-curl requirement (explicit)

#### Development Dependencies
- **UPDATED**: PHPUnit 10.5+ for comprehensive testing
- **UPDATED**: PHPStan 1.10+ for strict static analysis
- **UPDATED**: PHP CodeSniffer 3.7+ for code style
- **UPDATED**: Psalm 5.20+ for additional type checking

### 🐛 Bug Fixes

#### Critical Runtime Fixes
- Fixed fatal error when calling `$client->streaming()`
- Fixed missing resource methods in WioexClient
- Fixed type safety issues in AsyncClient HTTP methods
- Fixed nullable parameter deprecation warnings
- Fixed missing cache interface methods

#### Configuration & Validation Fixes
- Fixed Config class missing essential methods
- Fixed ErrorReportingLevel enum values and methods
- Fixed configuration validation and error handling
- Fixed environment-based configuration loading
- Fixed configuration file format detection

#### HTTP Client & Response Fixes
- Fixed HTTP client initialization with proper config
- Fixed response data access with nested properties
- Fixed header handling and authentication
- Fixed retry logic and exponential backoff
- Fixed timeout configuration validation

### 🔧 Technical Improvements

#### Code Architecture
- **IMPROVED**: Better separation of concerns
- **IMPROVED**: Enhanced dependency injection
- **IMPROVED**: More consistent error handling patterns
- **IMPROVED**: Better resource management and lifecycle

#### Type Safety
- **IMPROVED**: Comprehensive type declarations
- **IMPROVED**: Strict nullable type handling
- **IMPROVED**: Enhanced IDE autocomplete support
- **IMPROVED**: Better static analysis compliance

#### Testing Infrastructure
- **NEW**: Comprehensive unit testing framework
- **NEW**: Mock-based testing for HTTP interactions
- **NEW**: Integration testing for complex workflows
- **NEW**: Performance testing for large datasets

### 📊 Quality Metrics

#### Test Coverage
- **135+ Unit Tests** with comprehensive coverage
- **Core Classes**: 100% coverage
- **Resource Classes**: 95%+ coverage
- **Error Handling**: 100% coverage
- **Export Utilities**: 90%+ coverage

#### Static Analysis
- **PHPStan Level 9**: Strict static analysis passing
- **PSR-12 Compliance**: Code style validation passing
- **Type Coverage**: 100% type declarations
- **Documentation**: Comprehensive PHPDoc coverage

#### Production Readiness
- **Smoke Tests**: All critical paths validated
- **Error Resilience**: Comprehensive error handling
- **Performance**: Optimized for production workloads
- **Security**: Enterprise-grade security practices

### 🎯 Production Deployment

#### Release Readiness
- ✅ **All Critical Issues Resolved**: No known fatal errors
- ✅ **Comprehensive Testing**: 135+ tests passing
- ✅ **Quality Assurance**: PHPStan Level 9 compliance
- ✅ **Documentation**: Complete and up-to-date
- ✅ **Backward Compatibility**: Seamless upgrade path

#### Recommended Actions
1. **Upgrade Safely**: Update composer dependencies
2. **Run Tests**: Verify your integration with new test suite
3. **Explore New Features**: Try export utilities and configuration management
4. **Monitor**: Enhanced error reporting provides better insights

### 🏆 Version 2.0.0 Summary

Version 2.0.0 transforms the WioEX PHP SDK from a feature-rich but unstable library into a production-ready, enterprise-grade solution. With all critical runtime errors resolved, comprehensive testing implemented, and new powerful features added, this release represents a major milestone in the SDK's evolution.

**Key Achievements:**
- 🎯 **Zero Critical Errors**: All fatal runtime errors resolved
- 🧪 **135+ Tests**: Comprehensive test coverage
- 📊 **Export Utilities**: Advanced data export capabilities
- ⚙️ **Configuration Management**: Flexible configuration system
- 🛡️ **Professional Error Reporting**: Enterprise-grade error handling
- 🔄 **100% Backward Compatible**: Seamless upgrade experience

**Ready for Production**: The SDK is now suitable for production environments with enterprise-grade reliability and comprehensive error handling.

---

## [1.4.0] - 2025-10-22

### Enhanced Timeline & Caching Features

#### Added
- Enhanced timeline intervals with 17 different interval types
- Period-based optimization (1d, 1w, 1m, 3m, 6m, 1y, 5y, max)
- Two-branch JSON response format (metadata/data separation)
- Intelligent caching with interval-based optimization
- Convenience methods for common timeline operations

#### Improved
- Timeline API with better parameter support
- Cache optimization from 1 minute to 48 hours
- Better metadata handling in responses

## [1.3.0] - 2025-10-22

### WebSocket Streaming & API Improvements

#### Added
- WebSocket streaming authentication and token management
- Enhanced error handling with centralized format support
- API parameter alignment across endpoints

#### Improved
- Consistent parameter naming (ticker → stocks)
- Better error context and debugging information
- Backward compatibility for error formats

## [1.2.0] - 2025-10-22

### Session Filtering & Timeline Enhancements

#### Added
- Trading session filtering for intraday data
- Advanced timeline features with date-based filtering
- Session-specific data access (regular, pre-market, after-hours)

#### Improved
- Timeline convenience methods
- Better date handling and filtering options

## [1.1.0] - 2025-10-22

### Trading Signals & Market Status

#### Added
- Trading signals with auto-inclusion in stock responses
- Market status endpoint with public access option
- Comprehensive signal data and filtering

#### Improved
- Stock responses with integrated signal data
- Public endpoint access for frontend applications

## [1.0.0] - 2025-10-22

### Initial Release

#### Added
- Complete WioEX API integration
- All major endpoint categories (Stocks, News, Currency, Account)
- PSR-compliant PHP 8.1+ implementation
- Comprehensive error handling
- Automatic retry with exponential backoff
- Rate limiting support
- Response wrapper with array access
- Type safety and IDE support

#### Features
- Stock data retrieval and search
- News and company analysis
- Currency exchange rates and conversion
- Account management and usage tracking
- Fluent API with method chaining
- Zero configuration setup
- Production-ready reliability

---

**Full Changelog**: https://github.com/wioex/php-sdk/compare/v1.4.0...v2.0.0