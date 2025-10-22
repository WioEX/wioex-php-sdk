<?php

declare(strict_types=1);

namespace Wioex\SDK\Logging;

use Wioex\SDK\Enums\LogLevel;
use Wioex\SDK\Enums\LogDriver;
use Wioex\SDK\Enums\Environment;

class Logger
{
    private LogDriver $driver;
    private LogLevel $level;
    private array $config;
    private array $processors;
    private ?object $monologInstance = null;
    private array $context;
    private bool $enabled;

    public function __construct(
        LogDriver $driver = LogDriver::FILE,
        LogLevel $level = LogLevel::INFO,
        array $config = [],
        bool $enabled = true
    ) {
        $this->driver = $driver;
        $this->level = $level;
        $this->config = array_merge($driver->getDefaultConfig(), $config);
        $this->processors = [];
        $this->context = [];
        $this->enabled = $enabled;

        $this->initialize();
    }

    public static function create(array $config = []): self
    {
        $driver = LogDriver::fromString($config['driver'] ?? 'file');
        $level = LogLevel::fromString($config['level'] ?? 'info');
        $enabled = $config['enabled'] ?? true;

        return new self($driver, $level, $config, $enabled);
    }

    public static function forEnvironment(Environment $environment, array $config = []): self
    {
        $defaultDriver = $environment->isProduction() ? LogDriver::SYSLOG : LogDriver::FILE;
        $defaultLevel = LogLevel::fromString($environment->getLogLevel());

        $driver = isset($config['driver']) ? LogDriver::fromString($config['driver']) : $defaultDriver;
        $level = isset($config['level']) ? LogLevel::fromString($config['level']) : $defaultLevel;
        $enabled = $config['enabled'] ?? $environment->shouldEnableLogging();

        return new self($driver, $level, $config, $enabled);
    }

    public function emergency(string $message, array $context = []): void
    {
        $this->log(LogLevel::EMERGENCY, $message, $context);
    }

    public function alert(string $message, array $context = []): void
    {
        $this->log(LogLevel::ALERT, $message, $context);
    }

    public function critical(string $message, array $context = []): void
    {
        $this->log(LogLevel::CRITICAL, $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->log(LogLevel::ERROR, $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->log(LogLevel::WARNING, $message, $context);
    }

    public function notice(string $message, array $context = []): void
    {
        $this->log(LogLevel::NOTICE, $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->log(LogLevel::INFO, $message, $context);
    }

    public function debug(string $message, array $context = []): void
    {
        $this->log(LogLevel::DEBUG, $message, $context);
    }

    public function log(LogLevel $level, string $message, array $context = []): void
    {
        if (!$this->enabled || !$level->shouldLog($this->level)) {
            return;
        }

        $context = array_merge($this->context, $context);
        $logEntry = $this->createLogEntry($level, $message, $context);

        // Apply processors
        foreach ($this->processors as $processor) {
            $logEntry = $processor($logEntry);
        }

        $this->write($logEntry);
    }

    public function withContext(array $context): self
    {
        $clone = clone $this;
        $clone->context = array_merge($this->context, $context);
        return $clone;
    }

    public function addProcessor(callable $processor): self
    {
        $this->processors[] = $processor;
        return $this;
    }

    public function setLevel(LogLevel $level): self
    {
        $this->level = $level;
        return $this;
    }

    public function getLevel(): LogLevel
    {
        return $this->level;
    }

    public function getDriver(): LogDriver
    {
        return $this->driver;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function enable(): self
    {
        $this->enabled = true;
        return $this;
    }

    public function disable(): self
    {
        $this->enabled = false;
        return $this;
    }

    private function initialize(): void
    {
        // Add default processors
        $this->addProcessor([$this, 'addTimestamp']);
        $this->addProcessor([$this, 'addMemoryUsage']);
        $this->addProcessor([$this, 'addRequestId']);

        // Initialize driver-specific setup
        if ($this->driver->requiresMonolog()) {
            $this->initializeMonolog();
        }
    }

    private function initializeMonolog(): void
    {
        if (!class_exists('Monolog\Logger')) {
            throw new \RuntimeException('Monolog is required for driver: ' . $this->driver->value);
        }

        $this->monologInstance = new \Monolog\Logger('wioex-sdk');

        // Add handlers based on configuration
        $handlers = $this->config['handlers'] ?? ['stream'];
        foreach ($handlers as $handler) {
            $this->addMonologHandler($handler);
        }
    }

    private function addMonologHandler(string $handlerType): void
    {
        switch ($handlerType) {
            case 'stream':
                $path = $this->config['path'] ?? sys_get_temp_dir() . '/wioex.log';
                $handler = new \Monolog\Handler\StreamHandler($path, $this->level->getMonologLevel());
                break;

            case 'rotating':
                $path = $this->config['path'] ?? sys_get_temp_dir() . '/wioex.log';
                $maxFiles = $this->config['max_files'] ?? 30;
                $handler = new \Monolog\Handler\RotatingFileHandler($path, $maxFiles, $this->level->getMonologLevel());
                break;

            case 'syslog':
                $facility = $this->config['facility'] ?? 'user';
                $handler = new \Monolog\Handler\SyslogHandler('wioex-sdk', $facility, $this->level->getMonologLevel());
                break;

            default:
                return;
        }

        $formatter = new \Monolog\Formatter\LineFormatter(
            "[%datetime%] %channel%.%level_name%: %message% %context%\n",
            'Y-m-d H:i:s'
        );
        $handler->setFormatter($formatter);

        $this->monologInstance->pushHandler($handler);
    }

    private function createLogEntry(LogLevel $level, string $message, array $context): array
    {
        return [
            'timestamp' => microtime(true),
            'level' => $level,
            'message' => $message,
            'context' => $context,
            'channel' => 'wioex-sdk',
            'extra' => [],
        ];
    }

    private function write(array $logEntry): void
    {
        switch ($this->driver) {
            case LogDriver::MONOLOG:
                if ($this->monologInstance) {
                    $this->monologInstance->log(
                        $logEntry['level']->getMonologLevel(),
                        $logEntry['message'],
                        $logEntry['context']
                    );
                }
                break;

            case LogDriver::FILE:
            case LogDriver::SINGLE:
                $this->writeToFile($logEntry);
                break;

            case LogDriver::ERROR_LOG:
                $this->writeToErrorLog($logEntry);
                break;

            case LogDriver::SYSLOG:
                $this->writeToSyslog($logEntry);
                break;

            case LogDriver::STDERR:
                $this->writeToStderr($logEntry);
                break;

            case LogDriver::NULL:
                // Do nothing
                break;

            default:
                $this->writeToErrorLog($logEntry);
        }
    }

    private function writeToFile(array $logEntry): void
    {
        $path = $this->config['path'] ?? sys_get_temp_dir() . '/wioex.log';
        $formatted = $this->formatLogEntry($logEntry);

        // Ensure directory exists
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($path, $formatted . "\n", FILE_APPEND | LOCK_EX);
    }

    private function writeToErrorLog(array $logEntry): void
    {
        $formatted = $this->formatLogEntry($logEntry);
        error_log($formatted);
    }

    private function writeToSyslog(array $logEntry): void
    {
        $facility = $this->config['facility'] ?? LOG_USER;
        $flags = $this->config['flags'] ?? LOG_PID;

        openlog('wioex-sdk', $flags, $facility);

        $priority = match ($logEntry['level']) {
            LogLevel::EMERGENCY => LOG_EMERG,
            LogLevel::ALERT => LOG_ALERT,
            LogLevel::CRITICAL => LOG_CRIT,
            LogLevel::ERROR => LOG_ERR,
            LogLevel::WARNING => LOG_WARNING,
            LogLevel::NOTICE => LOG_NOTICE,
            LogLevel::INFO => LOG_INFO,
            LogLevel::DEBUG => LOG_DEBUG,
        };

        $formatted = $this->formatLogEntry($logEntry, false);
        syslog($priority, $formatted);
        closelog();
    }

    private function writeToStderr(array $logEntry): void
    {
        $formatted = $this->formatLogEntry($logEntry);
        fwrite(STDERR, $formatted . "\n");
    }

    private function formatLogEntry(array $logEntry, bool $includeTimestamp = true): string
    {
        $level = $logEntry['level'];
        $timestamp = $includeTimestamp ? date('Y-m-d H:i:s', (int) $logEntry['timestamp']) : '';
        $message = $logEntry['message'];
        $context = !empty($logEntry['context']) ? ' ' . json_encode($logEntry['context']) : '';

        $parts = array_filter([
            $timestamp,
            $level->getShortName(),
            $message . $context
        ]);

        return implode(' ', $parts);
    }

    // Default processors
    public function addTimestamp(array $logEntry): array
    {
        $logEntry['extra']['timestamp'] = date('c', (int) $logEntry['timestamp']);
        return $logEntry;
    }

    public function addMemoryUsage(array $logEntry): array
    {
        $logEntry['extra']['memory_usage'] = memory_get_usage(true);
        $logEntry['extra']['memory_peak'] = memory_get_peak_usage(true);
        return $logEntry;
    }

    public function addRequestId(array $logEntry): array
    {
        static $requestId = null;
        if ($requestId === null) {
            $requestId = uniqid('req_', true);
        }
        $logEntry['extra']['request_id'] = $requestId;
        return $logEntry;
    }

    public function getStatistics(): array
    {
        return [
            'driver' => $this->driver->toArray(),
            'level' => $this->level->toArray(),
            'enabled' => $this->enabled,
            'processors_count' => count($this->processors),
            'context_keys' => array_keys($this->context),
            'has_monolog' => $this->monologInstance !== null,
            'config' => $this->config,
        ];
    }
}
