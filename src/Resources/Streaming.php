<?php

declare(strict_types=1);

namespace Wioex\SDK\Resources;

use Wioex\SDK\Http\Response;

class Streaming extends Resource
{
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
        return parent::post('/v1/stream/token');
    }
}
