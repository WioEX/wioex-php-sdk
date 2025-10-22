<?php

declare(strict_types=1);

namespace Wioex\SDK\Exceptions;

/**
 * Bulk Operation Exception
 *
 * Thrown when bulk operations encounter errors, providing detailed
 * information about partial failures and successful operations.
 */
class BulkOperationException extends WioexException
{
    /** @var array<array<string, mixed>> */
    private array $errors;
    
    /** @var array<mixed> */
    private array $successfulResponses;

    /**
     * @param string $message
     * @param array<array<string, mixed>> $errors
     * @param array<mixed> $successfulResponses
     * @param int $code
     * @param \Throwable|null $previous
     */
    public function __construct(
        string $message,
        array $errors = [],
        array $successfulResponses = [],
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->errors = $errors;
        $this->successfulResponses = $successfulResponses;
    }

    /**
     * Get detailed error information for failed chunks
     *
     * @return array<array<string, mixed>>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get successful responses
     *
     * @return array<mixed>
     */
    public function getSuccessfulResponses(): array
    {
        return $this->successfulResponses;
    }

    /**
     * Check if operation was partially successful
     */
    public function hasPartialSuccess(): bool
    {
        return !empty($this->successfulResponses);
    }

    /**
     * Get failure rate as percentage
     */
    public function getFailureRate(): float
    {
        $totalChunks = count($this->errors) + count($this->successfulResponses);
        if ($totalChunks === 0) {
            return 0.0;
        }
        
        return (count($this->errors) / $totalChunks) * 100;
    }

    /**
     * Get human-readable summary of the bulk operation failure
     */
    public function getSummary(): string
    {
        $errorCount = count($this->errors);
        $successCount = count($this->successfulResponses);
        $totalChunks = $errorCount + $successCount;
        
        if ($totalChunks === 0) {
            return "Bulk operation failed: {$this->getMessage()}";
        }
        
        $failureRate = round($this->getFailureRate(), 1);
        
        return sprintf(
            "Bulk operation completed with %d/%d chunks failed (%.1f%% failure rate): %s",
            $errorCount,
            $totalChunks,
            $failureRate,
            $this->getMessage()
        );
    }

    /**
     * Get detailed context for debugging
     *
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return array_merge(parent::getContext(), [
            'bulk_operation' => [
                'total_errors' => count($this->errors),
                'total_successful' => count($this->successfulResponses),
                'failure_rate_percent' => $this->getFailureRate(),
                'errors' => $this->errors,
                'has_partial_success' => $this->hasPartialSuccess()
            ]
        ]);
    }
}