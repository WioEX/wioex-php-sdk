<?php

declare(strict_types=1);

namespace Wioex\SDK\Transformers;

use Wioex\SDK\Exceptions\TransformationException;

class TransformerPipeline
{
    private array $transformers = [];
    private array $middleware = [];
    private bool $stopOnError = true;
    private bool $validateInput = true;
    private array $statistics = [
        'total_executions' => 0,
        'successful_executions' => 0,
        'failed_executions' => 0,
        'total_processing_time' => 0.0,
        'transformers_executed' => [],
    ];

    public function __construct(array $options = [])
    {
        $this->stopOnError = $options['stop_on_error'] ?? true;
        $this->validateInput = $options['validate_input'] ?? true;
    }

    public function add(TransformerInterface $transformer, int $priority = 0): self
    {
        $this->transformers[] = [
            'transformer' => $transformer,
            'priority' => $priority,
        ];

        // Sort by priority (higher priority first)
        usort($this->transformers, fn($a, $b) => $b['priority'] <=> $a['priority']);

        return $this;
    }

    public function addMiddleware(callable $middleware): self
    {
        $this->middleware[] = $middleware;
        return $this;
    }

    public function remove(string $transformerName): self
    {
        $this->transformers = array_filter(
            $this->transformers,
            fn($item) => $item['transformer']->getName() !== $transformerName
        );

        return $this;
    }

    public function clear(): self
    {
        $this->transformers = [];
        return $this;
    }

    public function transform(array $data, array $context = []): array
    {
        $startTime = microtime(true);
        $originalData = $data;
        $this->statistics['total_executions']++;

        try {
            // Apply middleware before transformation
            foreach ($this->middleware as $middleware) {
                $data = $middleware($data, $context, 'before') ?? $data;
            }

            // Execute transformers
            foreach ($this->transformers as $item) {
                $transformer = $item['transformer'];

                // Check if transformer supports this data
                if (!$transformer->supports($data, $context)) {
                    continue;
                }

                // Validate input if enabled
                if ($this->validateInput && !$transformer->validate($data, $context)) {
                    if ($this->stopOnError) {
                        throw new TransformationException(
                            "Input validation failed for transformer: {$transformer->getName()}"
                        );
                    }
                    continue;
                }

                try {
                    $transformedData = $transformer->transform($data, $context);
                    $data = $transformedData;

                    // Track successful execution
                    $this->statistics['transformers_executed'][$transformer->getName()] =
                        ($this->statistics['transformers_executed'][$transformer->getName()] ?? 0) + 1;
                } catch (\Throwable $e) {
                    if ($this->stopOnError) {
                        throw new TransformationException(
                            "Transformation failed in {$transformer->getName()}: {$e->getMessage()}",
                            0,
                            $e
                        );
                    }
                    // Continue with original data if error handling is lenient
                }
            }

            // Apply middleware after transformation
            foreach (array_reverse($this->middleware) as $middleware) {
                $data = $middleware($data, $context, 'after') ?? $data;
            }

            $this->statistics['successful_executions']++;
        } catch (\Throwable $e) {
            $this->statistics['failed_executions']++;
            throw $e;
        } finally {
            $this->statistics['total_processing_time'] += microtime(true) - $startTime;
        }

        return $data;
    }

    public function pipe(array $data, array $context = []): PipelineResult
    {
        $startTime = microtime(true);
        $steps = [];
        $errors = [];

        try {
            $result = $this->transform($data, $context);

            return new PipelineResult(
                $result,
                true,
                $steps,
                $errors,
                microtime(true) - $startTime
            );
        } catch (\Throwable $e) {
            return new PipelineResult(
                $data,
                false,
                $steps,
                [$e->getMessage()],
                microtime(true) - $startTime
            );
        }
    }

    public function getTransformers(): array
    {
        return array_map(
            fn($item) => $item['transformer'],
            $this->transformers
        );
    }

    public function hasTransformer(string $name): bool
    {
        foreach ($this->transformers as $item) {
            if ($item['transformer']->getName() === $name) {
                return true;
            }
        }
        return false;
    }

    public function getTransformer(string $name): ?TransformerInterface
    {
        foreach ($this->transformers as $item) {
            if ($item['transformer']->getName() === $name) {
                return $item['transformer'];
            }
        }
        return null;
    }

    public function getStatistics(): array
    {
        $totalTime = $this->statistics['total_processing_time'];
        $totalExecutions = $this->statistics['total_executions'];

        return array_merge($this->statistics, [
            'average_processing_time' => $totalExecutions > 0 ? $totalTime / $totalExecutions : 0,
            'success_rate' => $totalExecutions > 0
                ? ($this->statistics['successful_executions'] / $totalExecutions) * 100
                : 0,
            'transformer_count' => count($this->transformers),
            'middleware_count' => count($this->middleware),
        ]);
    }

    public function resetStatistics(): self
    {
        $this->statistics = [
            'total_executions' => 0,
            'successful_executions' => 0,
            'failed_executions' => 0,
            'total_processing_time' => 0.0,
            'transformers_executed' => [],
        ];

        return $this;
    }

    public function setStopOnError(bool $stopOnError): self
    {
        $this->stopOnError = $stopOnError;
        return $this;
    }

    public function setValidateInput(bool $validateInput): self
    {
        $this->validateInput = $validateInput;
        return $this;
    }

    public function clone(): self
    {
        $clone = new self([
            'stop_on_error' => $this->stopOnError,
            'validate_input' => $this->validateInput,
        ]);

        $clone->transformers = $this->transformers;
        $clone->middleware = $this->middleware;

        return $clone;
    }

    public function merge(TransformerPipeline $other): self
    {
        foreach ($other->transformers as $item) {
            $this->transformers[] = $item;
        }

        foreach ($other->middleware as $middleware) {
            $this->middleware[] = $middleware;
        }

        // Re-sort by priority
        usort($this->transformers, fn($a, $b) => $b['priority'] <=> $a['priority']);

        return $this;
    }

    public function __debugInfo(): array
    {
        return [
            'transformer_count' => count($this->transformers),
            'middleware_count' => count($this->middleware),
            'stop_on_error' => $this->stopOnError,
            'validate_input' => $this->validateInput,
            'statistics' => $this->getStatistics(),
        ];
    }
}

class PipelineResult
{
    private array $data;
    private bool $success;
    private array $steps;
    private array $errors;
    private float $processingTime;

    public function __construct(
        array $data,
        bool $success,
        array $steps,
        array $errors,
        float $processingTime
    ) {
        $this->data = $data;
        $this->success = $success;
        $this->steps = $steps;
        $this->errors = $errors;
        $this->processingTime = $processingTime;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getSteps(): array
    {
        return $this->steps;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getProcessingTime(): float
    {
        return $this->processingTime;
    }

    public function hasErrors(): bool
    {
        return count($this->errors) > 0;
    }

    public function toArray(): array
    {
        return [
            'data' => $this->data,
            'success' => $this->success,
            'steps' => $this->steps,
            'errors' => $this->errors,
            'processing_time' => $this->processingTime,
        ];
    }
}
