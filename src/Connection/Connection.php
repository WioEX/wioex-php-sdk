<?php

declare(strict_types=1);

namespace Wioex\SDK\Connection;

use GuzzleHttp\Client as GuzzleClient;
use Wioex\SDK\Enums\ConnectionState;

class Connection
{
    private string $id;
    private GuzzleClient $client;
    private ConnectionState $state;
    private array $config;
    private float $createdAt;
    private float $lastUsedAt;
    private float $lastConnectedAt;
    private int $requestCount = 0;
    private int $errorCount = 0;
    private float $totalRequestTime = 0;
    private array $metadata = [];
    private ?string $host = null;
    private ?int $port = null;
    private float $weight = 1.0;
    private int $priority = 100;

    public function __construct(string $id, GuzzleClient $client, array $config = [])
    {
        $this->id = $id;
        $this->client = $client;
        $this->config = array_merge($this->getDefaultConfig(), $config);
        $this->state = ConnectionState::IDLE;
        $this->createdAt = microtime(true);
        $this->lastUsedAt = $this->createdAt;
        $this->lastConnectedAt = $this->createdAt;

        $this->extractConnectionInfo();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getClient(): GuzzleClient
    {
        return $this->client;
    }

    public function getState(): ConnectionState
    {
        return $this->state;
    }

    public function setState(ConnectionState $state): self
    {
        if (!$this->state->canTransitionTo($state)) {
            throw new \InvalidArgumentException(
                "Cannot transition from {$this->state->value} to {$state->value}"
            );
        }

        $this->state = $state;
        $this->updateMetadataOnStateChange($state);

        return $this;
    }

    public function isAvailable(): bool
    {
        return $this->state->isAvailable() && !$this->isExpired();
    }

    public function isInUse(): bool
    {
        return $this->state->isInUse();
    }

    public function isExpired(): bool
    {
        $maxAge = $this->config['max_age'] ?? 3600; // 1 hour default
        return (microtime(true) - $this->createdAt) > $maxAge;
    }

    public function shouldBeRemoved(): bool
    {
        return $this->state->shouldBeRemoved() || $this->isExpired() || $this->hasExceededLimits();
    }

    public function hasExceededLimits(): bool
    {
        $maxRequests = $this->config['max_requests'] ?? 1000;
        $maxErrors = $this->config['max_errors'] ?? 10;

        return $this->requestCount >= $maxRequests || $this->errorCount >= $maxErrors;
    }

    public function acquire(): bool
    {
        if (!$this->isAvailable()) {
            return false;
        }

        $this->setState(ConnectionState::ACTIVE);
        $this->lastUsedAt = microtime(true);

        return true;
    }

    public function release(): self
    {
        if ($this->state === ConnectionState::ACTIVE) {
            $this->setState(ConnectionState::IDLE);
        }

        return $this;
    }

    public function close(): self
    {
        $this->setState(ConnectionState::CLOSED);
        return $this;
    }

    public function recordRequest(float $duration, bool $success = true): self
    {
        $this->requestCount++;
        $this->totalRequestTime += $duration;

        if (!$success) {
            $this->errorCount++;
        }

        $this->lastUsedAt = microtime(true);

        return $this;
    }

    public function getRequestCount(): int
    {
        return $this->requestCount;
    }

    public function getErrorCount(): int
    {
        return $this->errorCount;
    }

    public function getErrorRate(): float
    {
        if ($this->requestCount === 0) {
            return 0.0;
        }

        return ($this->errorCount / $this->requestCount) * 100;
    }

    public function getAverageRequestTime(): float
    {
        if ($this->requestCount === 0) {
            return 0.0;
        }

        return $this->totalRequestTime / $this->requestCount;
    }

    public function getCreatedAt(): float
    {
        return $this->createdAt;
    }

    public function getLastUsedAt(): float
    {
        return $this->lastUsedAt;
    }

    public function getAge(): float
    {
        return microtime(true) - $this->createdAt;
    }

    public function getIdleTime(): float
    {
        if ($this->state === ConnectionState::ACTIVE) {
            return 0.0;
        }

        return microtime(true) - $this->lastUsedAt;
    }

    public function getHost(): ?string
    {
        return $this->host;
    }

    public function getPort(): ?int
    {
        return $this->port;
    }

    public function getWeight(): float
    {
        return $this->weight;
    }

    public function setWeight(float $weight): self
    {
        $this->weight = max(0.1, $weight); // Minimum weight of 0.1
        return $this;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function setPriority(int $priority): self
    {
        $this->priority = $priority;
        return $this;
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function setConfig(array $config): self
    {
        $this->config = array_merge($this->config, $config);
        return $this;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function setMetadata(string $key, mixed $value): self
    {
        $this->metadata[$key] = $value;
        return $this;
    }

    public function getMetadataValue(string $key, $default = null)
    {
        return $this->metadata[$key] ?? $default;
    }

    public function getHealthScore(): float
    {
        $score = 100.0;

        // Deduct for errors
        $score -= $this->getErrorRate() * 2; // -2 points per % error rate

        // Deduct for age
        $ageHours = $this->getAge() / 3600;
        $score -= min($ageHours * 0.5, 20); // Max 20 points deduction for age

        // Deduct for high request count
        $requestRatio = $this->requestCount / max($this->config['max_requests'] ?? 1000, 1);
        $score -= $requestRatio * 10; // Up to 10 points for request count

        // Bonus for recent activity
        $idleHours = $this->getIdleTime() / 3600;
        if ($idleHours < 1) {
            $score += 5; // Bonus for recent activity
        }

        return max(0, min(100, $score));
    }

    public function getConnectionKey(): string
    {
        return $this->host . ':' . $this->port;
    }

    public function reset(): self
    {
        $this->requestCount = 0;
        $this->errorCount = 0;
        $this->totalRequestTime = 0;
        $this->createdAt = microtime(true);
        $this->lastUsedAt = $this->createdAt;
        $this->metadata = [];

        return $this;
    }

    private function getDefaultConfig(): array
    {
        return [
            'max_age' => 3600,      // 1 hour
            'max_requests' => 1000,  // Maximum requests per connection
            'max_errors' => 10,      // Maximum errors before marking unhealthy
            'keep_alive' => true,
            'verify_ssl' => true,
        ];
    }

    private function extractConnectionInfo(): void
    {
        $config = $this->client->getConfig();

        if (isset($config['base_uri'])) {
            $baseUri = $config['base_uri'];
            if (is_string($baseUri)) {
                $parsed = parse_url($baseUri);
                $this->host = $parsed['host'] ?? null;
                $this->port = $parsed['port'] ?? ($parsed['scheme'] === 'https' ? 443 : 80);
            } elseif (method_exists($baseUri, 'getHost')) {
                $this->host = $baseUri->getHost();
                $this->port = $baseUri->getPort() ?? ($baseUri->getScheme() === 'https' ? 443 : 80);
            }
        }
    }

    private function updateMetadataOnStateChange(ConnectionState $newState): void
    {
        $this->metadata['state_changes'][] = [
            'from' => $this->state->value,
            'to' => $newState->value,
            'timestamp' => microtime(true),
        ];

        // Keep only last 10 state changes to prevent memory bloat
        if (count($this->metadata['state_changes']) > 10) {
            $this->metadata['state_changes'] = array_slice($this->metadata['state_changes'], -10);
        }
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'state' => $this->state->toArray(),
            'host' => $this->host,
            'port' => $this->port,
            'weight' => $this->weight,
            'priority' => $this->priority,
            'created_at' => $this->createdAt,
            'last_used_at' => $this->lastUsedAt,
            'age_seconds' => $this->getAge(),
            'idle_time_seconds' => $this->getIdleTime(),
            'request_count' => $this->requestCount,
            'error_count' => $this->errorCount,
            'error_rate_percent' => $this->getErrorRate(),
            'average_request_time_ms' => $this->getAverageRequestTime(),
            'health_score' => $this->getHealthScore(),
            'is_available' => $this->isAvailable(),
            'is_in_use' => $this->isInUse(),
            'is_expired' => $this->isExpired(),
            'should_be_removed' => $this->shouldBeRemoved(),
            'config' => $this->config,
            'metadata' => $this->metadata,
        ];
    }

    public function __toString(): string
    {
        return sprintf(
            'Connection[%s] %s:%d (State: %s, Requests: %d, Errors: %d)',
            substr($this->id, 0, 8),
            $this->host ?? 'unknown',
            $this->port ?? 0,
            $this->state->value,
            $this->requestCount,
            $this->errorCount
        );
    }
}
