<?php

declare(strict_types=1);

namespace Wioex\SDK\Resources;

use Wioex\SDK\Http\Response;

class Streaming extends Resource
{
    private ?array $cachedTokenData = null;

    /**
     * Get WebSocket streaming authentication token
     *
     * This endpoint provides a temporary token for authenticating WebSocket connections
     * to WioEX's real-time streaming service for live market data.
     *
     * @return Response Returns authentication token and connection details
     *
     * @example
     * ```php
     * $token = $client->streaming()->getToken();
     * if ($token->successful()) {
     *     $auth = $token['token'];
     *     $wsUrl = $token['websocket_url'];
     *     echo "Token: {$auth}\n";
     *     echo "WebSocket URL: {$wsUrl}\n";
     * }
     * ```
     */
    public function getToken(): Response
    {
        $response = parent::post('/v1/stream/token');

        // Cache token data for helper methods
        if ($response->successful()) {
            $this->cachedTokenData = $response->data();
        }

        return $response;
    }

    /**
     * Refresh the current WebSocket authentication token
     *
     * Obtains a new token and invalidates the previous one. Use this method
     * when the current token is about to expire or has been compromised.
     *
     * @return Response Returns new authentication token and connection details
     *
     * @example
     * ```php
     * // Refresh token before expiry
     * $newToken = $client->streaming()->refreshToken();
     * if ($newToken->successful()) {
     *     $auth = $newToken['token'];
     *     echo "New Token: {$auth}\n";
     * }
     * ```
     */
    public function refreshToken(): Response
    {
        // Clear cached data before refreshing
        $this->cachedTokenData = null;

        $response = parent::post('/v1/stream/token/refresh');

        // Cache new token data
        if ($response->successful()) {
            $this->cachedTokenData = $response->data();
        }

        return $response;
    }

    /**
     * Validate a WebSocket authentication token
     *
     * Checks if the provided token is valid and returns its status,
     * expiry time, and associated permissions.
     *
     * @param string $token The token to validate
     * @return Response Returns token validation status and metadata
     *
     * @example
     * ```php
     * $validation = $client->streaming()->validateToken($myToken);
     * if ($validation->successful()) {
     *     $isValid = $validation['valid'];
     *     $expiresAt = $validation['expires_at'];
     *     $permissions = $validation['permissions'];
     *
     *     if ($isValid) {
     *         echo "Token is valid until: {$expiresAt}\n";
     *     } else {
     *         echo "Token is invalid or expired\n";
     *     }
     * }
     * ```
     */
    public function validateToken(string $token): Response
    {
        return parent::post('/v1/stream/token/validate', ['token' => $token]);
    }

    /**
     * Get the WebSocket URL for streaming connections
     *
     * Returns the current WebSocket endpoint URL for establishing
     * real-time streaming connections. WioEX automatically handles
     * multi-source failover transparently in the background.
     *
     * @param array $options Optional configuration:
     *   - data_types: array - Filter by data types (e.g., ['stocks', 'crypto'])
     *   - region: string - Preferred region (e.g., 'us-east', 'eu-west')
     *   - protocol: string - WebSocket protocol version (default: 'v1')
     * @return Response Returns WebSocket URL and connection parameters
     *
     * @example
     * ```php
     * // Get WebSocket URL for stocks data
     * $wsInfo = $client->streaming()->getWebSocketUrl([
     *     'data_types' => ['stocks', 'indices'],
     *     'region' => 'us-east'
     * ]);
     *
     * if ($wsInfo->successful()) {
     *     $url = $wsInfo['websocket_url'];
     *     $protocols = $wsInfo['supported_protocols'];
     *     echo "WebSocket URL: {$url}\n";
     * }
     * ```
     */
    public function getWebSocketUrl(array $options = []): Response
    {
        return parent::get('/v1/stream/websocket-url', $options);
    }

    /**
     * Get WebSocket connection status and health information
     *
     * Returns the current status of WebSocket servers, including
     * availability, latency, and active connection counts.
     *
     * @param array $options Optional filters:
     *   - region: string - Check specific region status
     *   - detailed: bool - Include detailed metrics (default: false)
     * @return Response Returns connection status and health metrics
     *
     * @example
     * ```php
     * $status = $client->streaming()->getConnectionStatus(['detailed' => true]);
     * if ($status->successful()) {
     *     $isOnline = $status['online'];
     *     $latency = $status['avg_latency_ms'];
     *     $connections = $status['active_connections'];
     *
     *     echo "Streaming Status: " . ($isOnline ? 'Online' : 'Offline') . "\n";
     *     echo "Average Latency: {$latency}ms\n";
     *     echo "Active Connections: {$connections}\n";
     * }
     * ```
     */
    public function getConnectionStatus(array $options = []): Response
    {
        return parent::get('/v1/stream/status', $options);
    }

    /**
     * Get token expiry information
     *
     * Returns detailed information about when the current or specified
     * token will expire, including time remaining and renewal recommendations.
     *
     * @param string|null $token Token to check (uses cached token if null)
     * @return Response Returns expiry information and recommendations
     *
     * @example
     * ```php
     * // Check current token expiry
     * $expiry = $client->streaming()->getTokenExpiry();
     * if ($expiry->successful()) {
     *     $expiresAt = $expiry['expires_at'];
     *     $timeRemaining = $expiry['time_remaining_seconds'];
     *     $shouldRenew = $expiry['should_renew_soon'];
     *
     *     if ($shouldRenew) {
     *         echo "Token expires in {$timeRemaining} seconds - consider refreshing\n";
     *     }
     * }
     * ```
     */
    public function getTokenExpiry(?string $token = null): Response
    {
        $params = [];

        if ($token !== null) {
            $params['token'] = $token;
        } elseif ($this->cachedTokenData && isset($this->cachedTokenData['token'])) {
            $params['token'] = $this->cachedTokenData['token'];
        } else {
            // If no token provided and none cached, get a new one first
            $tokenResponse = $this->getToken();
            if ($tokenResponse->successful() && isset($tokenResponse['token'])) {
                $params['token'] = $tokenResponse['token'];
            }
        }

        return parent::post('/v1/stream/token/expiry', $params);
    }

    /**
     * Revoke a WebSocket authentication token
     *
     * Immediately invalidates the specified token, preventing its use
     * for future WebSocket connections. Useful for security purposes.
     *
     * @param string|null $token Token to revoke (uses cached token if null)
     * @return Response Returns revocation confirmation
     *
     * @example
     * ```php
     * // Revoke current token
     * $revocation = $client->streaming()->revokeToken();
     * if ($revocation->successful()) {
     *     echo "Token successfully revoked\n";
     * }
     *
     * // Revoke specific token
     * $revocation = $client->streaming()->revokeToken($specificToken);
     * ```
     */
    public function revokeToken(?string $token = null): Response
    {
        $params = [];

        if ($token !== null) {
            $params['token'] = $token;
        } elseif ($this->cachedTokenData && isset($this->cachedTokenData['token'])) {
            $params['token'] = $this->cachedTokenData['token'];
            // Clear cached data since we're revoking it
            $this->cachedTokenData = null;
        } else {
            throw new \InvalidArgumentException('No token provided and no cached token available');
        }

        return parent::post('/v1/stream/token/revoke', $params);
    }

    /**
     * Get available streaming data channels
     *
     * Returns a list of available data channels that can be subscribed to
     * via WebSocket connections, including their descriptions and requirements.
     *
     * @param array $options Optional filters:
     *   - category: string - Filter by category (e.g., 'stocks', 'crypto', 'forex')
     *   - subscription_level: string - Filter by subscription level (e.g., 'basic', 'premium')
     * @return Response Returns available channels and their metadata
     *
     * @example
     * ```php
     * $channels = $client->streaming()->getAvailableChannels([
     *     'category' => 'stocks'
     * ]);
     *
     * if ($channels->successful()) {
     *     foreach ($channels['channels'] as $channel) {
     *         echo "Channel: {$channel['name']} - {$channel['description']}\n";
     *     }
     * }
     * ```
     */
    public function getAvailableChannels(array $options = []): Response
    {
        return parent::get('/v1/stream/channels', $options);
    }

    /**
     * Test WebSocket connection with ping
     *
     * Sends a ping request to test WebSocket connectivity and measure
     * round-trip latency to the streaming servers.
     *
     * @param array $options Optional parameters:
     *   - region: string - Test specific region
     *   - timeout: int - Timeout in seconds (default: 5)
     * @return Response Returns ping results and latency metrics
     *
     * @example
     * ```php
     * $ping = $client->streaming()->ping(['region' => 'us-east']);
     * if ($ping->successful()) {
     *     $latency = $ping['latency_ms'];
     *     $success = $ping['success'];
     *
     *     if ($success) {
     *         echo "Ping successful: {$latency}ms\n";
     *     } else {
     *         echo "Ping failed\n";
     *     }
     * }
     * ```
     */
    public function ping(array $options = []): Response
    {
        return parent::post('/v1/stream/ping', $options);
    }

    /**
     * Get streaming usage statistics
     *
     * Returns detailed statistics about WebSocket usage, including
     * connection time, data transferred, and quota consumption.
     *
     * @param array $options Optional parameters:
     *   - period: string - Time period ('today', 'week', 'month') (default: 'today')
     *   - detailed: bool - Include detailed breakdown (default: false)
     * @return Response Returns usage statistics and quota information
     *
     * @example
     * ```php
     * $usage = $client->streaming()->getUsageStats([
     *     'period' => 'week',
     *     'detailed' => true
     * ]);
     *
     * if ($usage->successful()) {
     *     $connections = $usage['total_connections'];
     *     $dataTransferred = $usage['data_transferred_mb'];
     *     $quotaUsed = $usage['quota_used_percent'];
     *
     *     echo "Weekly connections: {$connections}\n";
     *     echo "Data transferred: {$dataTransferred}MB\n";
     *     echo "Quota used: {$quotaUsed}%\n";
     * }
     * ```
     */
    public function getUsageStats(array $options = []): Response
    {
        return parent::get('/v1/stream/usage', $options);
    }

    /**
     * Check if cached token is available and valid
     *
     * Helper method to determine if there's a cached token that can be used
     * without making an additional API call.
     *
     * @return bool True if cached token exists and appears valid
     */
    public function hasCachedToken(): bool
    {
        return $this->cachedTokenData !== null && isset($this->cachedTokenData['token']);
    }

    /**
     * Get cached token data without making API call
     *
     * Returns the cached token data from the last successful getToken() or
     * refreshToken() call. Returns null if no token is cached.
     *
     * @return array|null Cached token data or null if not available
     */
    public function getCachedTokenData(): ?array
    {
        return $this->cachedTokenData;
    }

    /**
     * Clear cached token data
     *
     * Removes any cached token data. Useful when you want to force
     * a fresh token request on the next operation.
     *
     * @return void
     */
    public function clearTokenCache(): void
    {
        $this->cachedTokenData = null;
    }
}
