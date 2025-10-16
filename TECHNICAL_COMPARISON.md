# Public vs Authenticated Endpoints - Technical Comparison

## Route Configuration

### Authenticated Endpoint
```php
// routes.php:254-257
$marketGroup->get('/status', function (Request $request, Response $response) {
    $controller = new \Controllers\MarketStatusController();
    return $controller->status($request, $response);
})->add(new ApiKeyMiddleware('stocks', 1, 1));
```

### Public Endpoint
```php
// routes.php:260-263
$marketGroup->get('/status/public', function (Request $request, Response $response) {
    $controller = new \Controllers\MarketStatusController();
    return $controller->statusPublic($request, $response);
});
```

**Key Difference**: Authenticated has `ApiKeyMiddleware`, Public has none.

---

## Request Processing Flow

### Authenticated Endpoint Flow

```
1. Request arrives → Slim Router
2. ApiKeyMiddleware::process() executed
   ├─ Extract API key from query/header
   ├─ Validate API key exists (401 if missing)
   ├─ Tools::processApiRequest()
   │  ├─ getApiIPRestrictions() - Check IP whitelist/blacklist
   │  │  └─ Query: SELECT ip_allowed, ip_restriction FROM Api_Keys
   │  │  └─ If blocked → Insert to Api_Keys_Abuses table
   │  ├─ ApiCheck() - Validate API key status
   │  │  └─ Query: SELECT * FROM Api_Keys LEFT JOIN Members WHERE api_key=? AND type=? AND credit >= 0
   │  ├─ changerUserCredit() - Deduct credits
   │  │  └─ Query: UPDATE Members SET credit = credit - 1 WHERE member_id IN (SELECT member_id FROM Api_Keys WHERE api_key=?) AND credit >= 1
   │  │  └─ If insufficient credits → return false (Error 100825)
   │  └─ processConnectionCounts() - Log usage
   │     └─ Query: INSERT/UPDATE Api_Connection_Counts (date, connection_count, connection_requests)
   └─ If all checks pass → Continue to controller
3. MarketStatusController::status()
   └─ Calls getMarketStatus() (shared with public)
4. Return response
```

**Database Queries**: 4-5 queries per request
**Tables Affected**:
- `Api_Keys` (1 read, possible 1 write to Api_Keys_Abuses)
- `Members` (1 read, 1 write for credit deduction)
- `Api_Connection_Counts` (1 insert/update)

---

### Public Endpoint Flow

```
1. Request arrives → Slim Router
2. No middleware → Direct to controller
3. MarketStatusController::statusPublic()
   ├─ getClientIP() - Extract IP from headers
   ├─ Redis: GET rate_limit:market_status_public:{IP}
   ├─ If count >= 100 → Return 429 Too Many Requests
   ├─ Redis: INCR rate_limit:market_status_public:{IP}
   ├─ Redis: EXPIRE rate_limit:market_status_public:{IP} 60
   └─ Calls getMarketStatus() (shared with authenticated)
4. Return response
```

**Database Queries**: 0 queries per request
**Cache Operations**: 3 Redis operations (GET, INCR, EXPIRE)

---

## Technical Comparison Table

| Feature | Authenticated | Public |
|---------|--------------|--------|
| **Middleware Layer** | ApiKeyMiddleware | None |
| **Database Queries** | 4-5 queries | 0 queries |
| **MySQL Tables Used** | Api_Keys, Members, Api_Connection_Counts, Api_Keys_Abuses | None |
| **Redis Operations** | 1 (shared cache) | 4 (rate limit + shared cache) |
| **IP Restrictions** | ✅ Whitelist/Blacklist support | ❌ No restrictions |
| **Credit System** | ✅ Deducts 1 credit | ❌ No credit cost |
| **Usage Tracking** | ✅ Logs to Api_Connection_Counts | ❌ No tracking |
| **Abuse Detection** | ✅ Logs to Api_Keys_Abuses | ❌ No logging |
| **Rate Limiting** | Based on API plan | 100 req/min per IP |
| **Authentication** | Required (401 if missing) | Not required |
| **Authorization** | Checks API key type & status | None |
| **Account Validation** | Checks member credit >= 0 | None |

---

## Rate Limiting Mechanisms

### Authenticated Endpoint
- **Implementation**: Application-level (via API plan settings)
- **Storage**: Database (Members table, Api_Keys table)
- **Granularity**: Per API key
- **Limit**: Configured per account (not enforced by default)
- **Tracking**: Api_Connection_Counts table
- **Bypass**: Not possible

### Public Endpoint
- **Implementation**: Redis counter
- **Storage**: Redis with TTL
- **Granularity**: Per IP address
- **Limit**: 100 requests per 60 seconds
- **Tracking**: Not logged to database
- **Bypass**: Possible with IP rotation (but cache limits external API calls)

**Redis Keys Used by Public**:
```
rate_limit:market_status_public:{IP_ADDRESS}
Value: Counter (0-100)
TTL: 60 seconds
```

---

## Credit Management

### Authenticated Endpoint Credit Deduction Flow

```sql
-- Step 1: Get current credit
SELECT credit FROM Members
WHERE member_id IN (SELECT member_id FROM Api_Keys WHERE api_key = ?)

-- Step 2: Attempt deduction (atomic operation)
UPDATE Members
SET credit = credit - 1
WHERE member_id IN (SELECT member_id FROM Api_Keys WHERE api_key = ?)
  AND credit >= 1

-- Step 3: Check affected rows
-- If 0 rows affected → Insufficient credit (Error 100825)
-- If 1 row affected → Success
```

**Important**: Uses `AND credit >= 1` constraint to prevent negative credits (atomic operation).

### Public Endpoint
- No credit system
- No database updates
- Free to use

---

## IP Restrictions & Security

### Authenticated Endpoint IP Validation

```php
// Tools.php:500-560
public function getApiIPRestrictions(string $apiKey, string $clientIP, string $userAgent = null): bool
{
    // Query API key IP settings
    SELECT ip_allowed, ip_restriction FROM Api_Keys WHERE api_key = ? AND status = 1

    // If ip_allowed is set (whitelist mode)
    if (!empty($allowedIPs)) {
        // Check if client IP matches any allowed IP (supports wildcards: 192.168.*.*)
        if (!ipMatches($clientIP, $allowedIP)) {
            // LOG ABUSE
            INSERT INTO Api_Keys_Abuses (api_key, type, data)
            VALUES (?, 'ip_restricted', JSON_OBJECT('ip', ?, 'userAgent', ?))
            return false;
        }
    }

    // If ip_restriction is set (blacklist mode)
    if (!empty($restrictedIPs)) {
        // Check if client IP matches any restricted IP
        if (ipMatches($clientIP, $restrictedIP)) {
            // LOG ABUSE
            INSERT INTO Api_Keys_Abuses (...)
            return false;
        }
    }

    return true;
}
```

**Features**:
- Whitelist mode: Only specified IPs allowed
- Blacklist mode: Specified IPs blocked
- Wildcard support: `192.168.*.*` matches entire subnet
- Abuse logging: All blocked attempts logged
- Cloudflare IP detection: Uses `HTTP_CF_CONNECTING_IP` header

### Public Endpoint IP Handling

```php
// MarketStatusController.php:169-190
private function getClientIP(Request $request): string
{
    // 1. Check Cloudflare header
    if (!empty($serverParams['HTTP_CF_CONNECTING_IP'])) {
        return $serverParams['HTTP_CF_CONNECTING_IP'];
    }

    // 2. Check proxy header
    if (!empty($serverParams['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $serverParams['HTTP_X_FORWARDED_FOR']);
        return trim($ips[0]);
    }

    // 3. Direct connection
    if (!empty($serverParams['REMOTE_ADDR'])) {
        return $serverParams['REMOTE_ADDR'];
    }

    return 'UNKNOWN';
}
```

**Features**:
- Only used for rate limiting
- No whitelist/blacklist
- No abuse logging
- Cloudflare support

---

## Shared Cache Mechanism

Both endpoints share the same cache key:

```php
// MarketStatusController.php:74
$cacheKey = 'market_status_data';
$cacheTTL = 60; // 60 seconds
```

**Cache Flow**:
```
1. Check Redis: GET market_status_data
2. If exists → Return cached data (both endpoints benefit)
3. If not exists:
   ├─ Fetch from markethours.io API
   ├─ Process data (NYSE + NASDAQ)
   ├─ Store in Redis: SETEX market_status_data 60 {JSON}
   └─ Return data
```

**Benefits**:
- Reduces external API calls
- Both endpoints share cache
- If authenticated endpoint called, public benefits from cache
- If public endpoint called, authenticated benefits from cache

---

## Performance Comparison

### Authenticated Endpoint (Cold Cache)
```
1. Middleware processing: ~50-100ms
   ├─ DB query (Api_Keys + Members): ~10-20ms
   ├─ IP restriction check: ~5-10ms
   ├─ Credit deduction: ~20-30ms
   └─ Connection count update: ~10-20ms
2. Controller processing: ~10ms
3. External API call: ~200-500ms (markethours.io)
4. Cache write: ~5ms
Total: ~275-615ms
```

### Authenticated Endpoint (Hot Cache)
```
1. Middleware processing: ~50-100ms
2. Controller processing: ~10ms
3. Cache read: ~2-5ms
Total: ~62-115ms
```

### Public Endpoint (Cold Cache)
```
1. Rate limit check: ~2-5ms
2. Controller processing: ~10ms
3. External API call: ~200-500ms (markethours.io)
4. Cache write: ~5ms
Total: ~217-520ms
```

### Public Endpoint (Hot Cache)
```
1. Rate limit check: ~2-5ms
2. Controller processing: ~10ms
3. Cache read: ~2-5ms
Total: ~14-20ms
```

**Performance Winner**: Public endpoint (when cached) is ~4-5x faster due to no database queries.

---

## Error Handling Differences

### Authenticated Endpoint Errors

| Error Code | Cause | HTTP Status |
|------------|-------|-------------|
| 100621 | IP restriction failed | 401 |
| 100967 | API key missing/invalid | 401 |
| 100825 | Insufficient credit | 401 |
| 100047 | Exception in validation | 401 |

### Public Endpoint Errors

| Error | Cause | HTTP Status |
|-------|-------|-------------|
| Rate limit exceeded | > 100 requests/min | 429 |
| Service unavailable | markethours.io down | 503 |
| Internal server error | Exception in controller | 500 |

---

## Security Considerations

### Authenticated Endpoint Security

**Strengths**:
- ✅ API key required (prevents anonymous abuse)
- ✅ Credit system (prevents unlimited usage)
- ✅ IP restrictions (prevents unauthorized access)
- ✅ Abuse logging (tracks malicious activity)
- ✅ Per-account tracking (accountability)

**Weaknesses**:
- ❌ API key visible in requests (can be intercepted)
- ❌ No request signing (HMAC/JWT not used)
- ❌ Key exposure risk in frontend

### Public Endpoint Security

**Strengths**:
- ✅ No credentials to expose
- ✅ Rate limiting (prevents DoS)
- ✅ Shared cache (reduces external API load)
- ✅ Safe for frontend use

**Weaknesses**:
- ❌ No authentication (anyone can access)
- ❌ No abuse logging (harder to track malicious users)
- ❌ IP-based rate limiting (bypassable with proxies)
- ❌ No per-user tracking (no analytics)

---

## Use Case Recommendations

### Use Authenticated Endpoint When:

1. **Backend Applications**
   - Server-to-server communication
   - API key can be securely stored in environment variables
   - Need usage tracking and analytics

2. **Account Management Required**
   - Need to track per-account usage
   - Credit-based billing
   - Generate usage reports

3. **IP Restrictions Needed**
   - Security requirement for specific IP ranges
   - Corporate network restrictions
   - Prevent unauthorized access

4. **High Volume Usage**
   - Need higher rate limits than 100/min
   - Can negotiate custom API plans
   - Predictable, tracked usage patterns

### Use Public Endpoint When:

1. **Frontend Applications**
   - React, Vue, Angular, Next.js apps
   - Mobile applications
   - Browser extensions
   - Cannot safely store API keys

2. **Public Information Display**
   - Public websites showing market status
   - No authentication requirement
   - Market hours widgets

3. **Prototyping & Testing**
   - Quick integration testing
   - Demo applications
   - No account setup needed

4. **Low Volume Usage**
   - < 100 requests per minute sufficient
   - Occasional checks
   - User-triggered requests

---

## Implementation Code Comparison

### PHP SDK - Authenticated
```php
$client = new WioexClient(['api_key' => 'your-api-key-here']);
$status = $client->markets()->status();

// Behind the scenes:
// GET /v2/market/status?api_key=your-api-key-here
// → ApiKeyMiddleware validates
// → Database queries (4-5)
// → Credit deducted (-1)
// → Usage logged
```

### PHP SDK - Public
```php
$client = new WioexClient(['api_key' => '']); // Empty key
$status = $client->markets()->statusPublic();

// Behind the scenes:
// GET /v2/market/status/public
// → No middleware
// → No database queries
// → Rate limit check (Redis)
// → Free access
```

### Direct API Call - Public
```bash
curl https://api.wioex.com/v2/market/status/public
```

```javascript
fetch('https://api.wioex.com/v2/market/status/public')
  .then(res => res.json())
  .then(data => console.log(data));
```

No authentication, no SDK required.

---

## Monitoring & Analytics

### Authenticated Endpoint Metrics Available

```sql
-- Usage by date
SELECT date, SUM(connection_count) as total_requests
FROM Api_Connection_Counts
WHERE api_key = ?
GROUP BY date;

-- Credit usage
SELECT credit FROM Members
WHERE member_id = (SELECT member_id FROM Api_Keys WHERE api_key = ?);

-- Abuse attempts
SELECT COUNT(*) as abuse_count
FROM Api_Keys_Abuses
WHERE api_key = ?;
```

### Public Endpoint Metrics Available

```bash
# Current rate limit status (Redis)
redis-cli GET "rate_limit:market_status_public:123.45.67.89"
# Returns: "23" (23 requests used this minute)

# No persistent analytics
# No usage tracking
# No account-level metrics
```

---

## Cost Analysis

### Per-Request Cost

| Metric | Authenticated | Public |
|--------|--------------|--------|
| Credits | 1 | 0 |
| Database queries | 4-5 | 0 |
| Database writes | 2-3 | 0 |
| Redis operations | 1-2 | 3-4 |
| External API calls | 0-1 (cached) | 0-1 (cached) |

### Infrastructure Cost

**Authenticated**:
- Higher database load
- More complex middleware
- Requires user management system
- Credit system overhead

**Public**:
- Minimal infrastructure
- Redis-only rate limiting
- No user management needed
- Lower operational cost

---

## Conclusion

The **technical difference** between the two endpoints is primarily in the **middleware layer**:

**Authenticated** = Slim Router → **ApiKeyMiddleware** → Controller → Response
- 4-5 database queries
- Credit management
- IP restrictions
- Usage tracking
- Abuse detection

**Public** = Slim Router → Controller → Response
- 0 database queries
- Redis rate limiting only
- No tracking
- No authentication

Both share the same cache and external API, but authenticated adds significant overhead for security, tracking, and billing purposes.
