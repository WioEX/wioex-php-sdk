<?php

declare(strict_types=1);

namespace Wioex\SDK\Middleware;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Wioex\SDK\Enums\MiddlewareType;
use Wioex\SDK\Enums\LogLevel;
use Wioex\SDK\Logging\Logger;

class LoggingMiddleware extends AbstractMiddleware
{
    private Logger $logger;

    public function __construct(Logger $logger, array $config = [])
    {
        parent::__construct(MiddlewareType::LOGGING, $config);
        $this->logger = $logger;
    }

    protected function getDefaultConfig(): array
    {
        return [
            'log_requests' => true,
            'log_responses' => true,
            'log_request_body' => false,
            'log_response_body' => false,
            'log_headers' => false,
            'mask_sensitive_data' => true,
            'sensitive_headers' => ['Authorization', 'X-API-Key', 'Cookie'],
            'sensitive_params' => ['api_key', 'password', 'token', 'secret'],
            'max_body_length' => 1000,
            'log_level' => 'info',
        ];
    }

    protected function processRequestCustom(RequestInterface $request, array $context = []): RequestInterface
    {
        if ($this->getConfigValue('log_requests', true)) {
            $this->logRequest($request, $context);
        }

        return $request;
    }

    protected function processResponseCustom(ResponseInterface $response, RequestInterface $request, array $context = []): ResponseInterface
    {
        if ($this->getConfigValue('log_responses', true)) {
            $this->logResponse($response, $request, $context);
        }

        return $response;
    }

    protected function handleErrorCustom(\Throwable $error, RequestInterface $request, array $context = []): ?\Throwable
    {
        $this->logError($error, $request, $context);
        return $error;
    }

    private function logRequest(RequestInterface $request, array $context = []): void
    {
        $logData = [
            'type' => 'http_request',
            'method' => $request->getMethod(),
            'uri' => (string) $request->getUri(),
            'protocol_version' => $request->getProtocolVersion(),
            'request_id' => $context['request_id'] ?? uniqid('req_'),
        ];

        if ($this->getConfigValue('log_headers', false)) {
            $logData['headers'] = $this->sanitizeHeaders($request->getHeaders());
        }

        if ($this->getConfigValue('log_request_body', false)) {
            $body = (string) $request->getBody();
            if (!empty($body)) {
                $logData['body'] = $this->sanitizeBody($body);
            }
        }

        $logLevel = LogLevel::fromString($this->getConfigValue('log_level', 'info'));
        $this->logger->log($logLevel, 'HTTP Request', $logData);
    }

    private function logResponse(ResponseInterface $response, RequestInterface $request, array $context = []): void
    {
        $statusCode = $response->getStatusCode();
        $logLevel = $this->getResponseLogLevel($statusCode);

        $logData = [
            'type' => 'http_response',
            'status_code' => $statusCode,
            'reason_phrase' => $response->getReasonPhrase(),
            'request_method' => $request->getMethod(),
            'request_uri' => (string) $request->getUri(),
            'request_id' => $context['request_id'] ?? uniqid('req_'),
            'response_size' => strlen((string) $response->getBody()),
        ];

        if (isset($context['duration_ms'])) {
            $logData['duration_ms'] = $context['duration_ms'];
        }

        if ($this->getConfigValue('log_headers', false)) {
            $logData['headers'] = $this->sanitizeHeaders($response->getHeaders());
        }

        if ($this->getConfigValue('log_response_body', false)) {
            $body = (string) $response->getBody();
            if (!empty($body)) {
                $logData['body'] = $this->sanitizeBody($body);
            }
        }

        $message = $statusCode >= 400 ? 'HTTP Response (Error)' : 'HTTP Response';
        $this->logger->log($logLevel, $message, $logData);
    }

    private function logError(\Throwable $error, RequestInterface $request, array $context = []): void
    {
        $this->logger->error('HTTP Request Error', [
            'type' => 'http_error',
            'error_class' => get_class($error),
            'error_message' => $error->getMessage(),
            'error_code' => $error->getCode(),
            'error_file' => $error->getFile(),
            'error_line' => $error->getLine(),
            'request_method' => $request->getMethod(),
            'request_uri' => (string) $request->getUri(),
            'request_id' => $context['request_id'] ?? uniqid('req_'),
            'stack_trace' => $this->getConfigValue('log_stack_trace', false) ? $error->getTraceAsString() : null,
        ]);
    }

    private function getResponseLogLevel(int $statusCode): LogLevel
    {
        return match (true) {
            $statusCode >= 500 => LogLevel::ERROR,
            $statusCode >= 400 => LogLevel::WARNING,
            $statusCode >= 300 => LogLevel::INFO,
            default => LogLevel::fromString($this->getConfigValue('log_level', 'info')),
        };
    }

    private function sanitizeHeaders(array $headers): array
    {
        if (!$this->getConfigValue('mask_sensitive_data', true)) {
            return $headers;
        }

        $sensitiveHeaders = array_map('strtolower', $this->getConfigValue('sensitive_headers', []));
        $sanitized = [];

        foreach ($headers as $name => $values) {
            if (in_array(strtolower($name), $sensitiveHeaders)) {
                $sanitized[$name] = ['[REDACTED]'];
            } else {
                $sanitized[$name] = $values;
            }
        }

        return $sanitized;
    }

    private function sanitizeBody(string $body): string
    {
        $maxLength = $this->getConfigValue('max_body_length', 1000);

        if (strlen($body) > $maxLength) {
            $body = substr($body, 0, $maxLength) . '... [TRUNCATED]';
        }

        if (!$this->getConfigValue('mask_sensitive_data', true)) {
            return $body;
        }

        // Try to decode as JSON and sanitize
        $decoded = json_decode($body, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return json_encode($this->sanitizeArray($decoded));
        }

        // For non-JSON bodies, just return the truncated version
        return $body;
    }

    private function sanitizeArray(array $data): array
    {
        $sensitiveParams = $this->getConfigValue('sensitive_params', []);
        $sanitized = [];

        foreach ($data as $key => $value) {
            if (in_array(strtolower($key), array_map('strtolower', $sensitiveParams))) {
                $sanitized[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->sanitizeArray($value);
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }
}
