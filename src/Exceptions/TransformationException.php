<?php

declare(strict_types=1);

namespace Wioex\SDK\Exceptions;

class TransformationException extends WioexException
{
    public static function failed(string $message, \Throwable $previous = null): self
    {
        return new self($message, 0, $previous);
    }

    public static function validationFailed(string $transformer, string $reason): self
    {
        return new self("Validation failed in transformer '{$transformer}': {$reason}");
    }

    public static function unsupportedData(string $transformer, string $dataType): self
    {
        return new self("Transformer '{$transformer}' does not support data type: {$dataType}");
    }

    public static function pipelineError(string $stage, string $error): self
    {
        return new self("Pipeline error in stage '{$stage}': {$error}");
    }
}
