<?php

declare(strict_types=1);

namespace Wioex\SDK\Exceptions;

use InvalidArgumentException as BaseInvalidArgumentException;

/**
 * Exception thrown when an argument is invalid
 *
 * This exception is thrown when invalid arguments are passed to SDK methods,
 * such as invalid stock symbols, malformed parameters, or out-of-range values.
 */
class InvalidArgumentException extends WioexException
{
    private ?string $argumentName = null;
    private $argumentValue = null;
    private ?string $expectedType = null;

    public function __construct(
        string $message = 'Invalid argument provided',
        int $code = 0,
        ?\Throwable $previous = null,
        ?string $argumentName = null,
        $argumentValue = null,
        ?string $expectedType = null
    ) {
        parent::__construct($message, $code, $previous);
        
        $this->argumentName = $argumentName;
        $this->argumentValue = $argumentValue;
        $this->expectedType = $expectedType;
    }

    /**
     * Get the name of the invalid argument
     */
    public function getArgumentName(): ?string
    {
        return $this->argumentName;
    }

    /**
     * Get the invalid argument value
     */
    public function getArgumentValue(): mixed
    {
        return $this->argumentValue;
    }

    /**
     * Get the expected type for the argument
     */
    public function getExpectedType(): ?string
    {
        return $this->expectedType;
    }

    /**
     * Create exception for invalid stock symbol
     */
    public static function invalidSymbol(string $symbol): self
    {
        return new self(
            message: "Invalid stock symbol: '{$symbol}'. Symbols must be 1-5 uppercase letters.",
            argumentName: 'symbol',
            argumentValue: $symbol,
            expectedType: 'valid stock symbol (1-5 uppercase letters)'
        );
    }

    /**
     * Create exception for invalid API key
     */
    public static function invalidApiKey(string $apiKey): self
    {
        $maskedKey = substr($apiKey, 0, 8) . str_repeat('*', max(0, strlen($apiKey) - 8));
        
        return new self(
            message: "Invalid API key format: '{$maskedKey}'. API key must be a valid UUID.",
            argumentName: 'api_key',
            argumentValue: $maskedKey,
            expectedType: 'UUID format'
        );
    }

    /**
     * Create exception for invalid date range
     */
    public static function invalidDateRange(string $start, string $end): self
    {
        return new self(
            message: "Invalid date range: start date '{$start}' must be before end date '{$end}'.",
            argumentName: 'date_range',
            argumentValue: ['start' => $start, 'end' => $end],
            expectedType: 'start date before end date'
        );
    }

    /**
     * Create exception for invalid enum value
     */
    public static function invalidEnumValue(string $value, string $enumClass): self
    {
        return new self(
            message: "Invalid enum value: '{$value}' is not a valid {$enumClass} value.",
            argumentName: 'enum_value',
            argumentValue: $value,
            expectedType: $enumClass
        );
    }

    /**
     * Create exception for invalid parameter count
     */
    public static function invalidParameterCount(int $provided, int $expected, string $parameterName): self
    {
        return new self(
            message: "Invalid parameter count for '{$parameterName}': provided {$provided}, expected {$expected}.",
            argumentName: $parameterName,
            argumentValue: $provided,
            expectedType: "exactly {$expected} parameters"
        );
    }

    /**
     * Create exception for invalid parameter range
     */
    public static function invalidRange(string $parameterName, $value, $min, $max): self
    {
        return new self(
            message: "Parameter '{$parameterName}' value {$value} is out of range. Must be between {$min} and {$max}.",
            argumentName: $parameterName,
            argumentValue: $value,
            expectedType: "value between {$min} and {$max}"
        );
    }
}