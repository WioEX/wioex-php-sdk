<?php

declare(strict_types=1);

namespace Wioex\SDK\Configuration;

use Wioex\SDK\Enums\Environment;
use Wioex\SDK\Enums\ConfigurationSource;
use Wioex\SDK\Enums\ValidationState;
use Wioex\SDK\Enums\LogLevel;
use Wioex\SDK\Config;

class ConfigurationManager
{
    private Environment $environment;
    private array $sources = [];
    private array $config = [];
    private array $validationResults = [];
    private ValidationState $validationState;
    private string $basePath;
    private array $watchers = [];

    public function __construct(Environment $environment = Environment::PRODUCTION, string $basePath = '')
    {
        $this->environment = $environment;
        $this->basePath = $basePath ?: getcwd();
        $this->validationState = ValidationState::UNKNOWN;

        // Register default configuration sources
        $this->registerDefaultSources();
    }

    public static function create(Environment $environment = Environment::PRODUCTION, string $basePath = ''): self
    {
        return new self($environment, $basePath);
    }

    public static function fromConfig(string $configPath): Config
    {
        $manager = new self();
        $manager->addSource(ConfigurationSource::fromPath($configPath), $configPath);
        $config = $manager->load();

        return Config::create($config);
    }

    public function setEnvironment(Environment $environment): self
    {
        $this->environment = $environment;
        $this->reloadConfiguration();
        return $this;
    }

    public function getEnvironment(): Environment
    {
        return $this->environment;
    }

    public function addSource(ConfigurationSource $source, string $path = '', int $priority = null): self
    {
        $priority = $priority ?? $source->getPriority();

        $this->sources[] = [
            'source' => $source,
            'path' => $path ?: $source->getDefaultPath($this->basePath),
            'priority' => $priority,
            'last_modified' => null,
            'cached_data' => null,
        ];

        // Sort by priority (highest first)
        usort($this->sources, fn($a, $b) => $b['priority'] <=> $a['priority']);

        return $this;
    }

    public function load(): array
    {
        $this->config = [];
        $this->validationResults = [];

        foreach ($this->sources as &$sourceInfo) {
            try {
                $data = $this->loadFromSource($sourceInfo);
                $this->config = array_merge_recursive($this->config, $data);

                $sourceInfo['last_modified'] = $this->getLastModified($sourceInfo);
                $this->validationResults[] = ValidationState::VALID;
            } catch (\Throwable $e) {
                $this->validationResults[] = ValidationState::fromException($e);

            }
        }

        // Apply environment-specific configuration
        $this->applyEnvironmentConfiguration();

        // Validate final configuration
        $this->validateConfiguration();

        return $this->config;
    }

    public function get(string $key, $default = null)
    {
        return $this->getNestedValue($this->config, $key) ?? $default;
    }

    public function set(string $key, $value): self
    {
        $this->setNestedValue($this->config, $key, $value);
        return $this;
    }

    public function has(string $key): bool
    {
        return $this->getNestedValue($this->config, $key) !== null;
    }

    public function getValidationState(): ValidationState
    {
        return $this->validationState;
    }

    public function isValid(): bool
    {
        return $this->validationState->isValid();
    }

    public function getValidationResults(): array
    {
        return [
            'state' => $this->validationState,
            'sources' => array_map(function ($source, $result) {
                return [
                    'source' => $source['source']->value,
                    'path' => $source['path'],
                    'result' => $result,
                ];
            }, $this->sources, $this->validationResults),
            'overall' => $this->validationState->toArray(),
        ];
    }

    public function watch(callable $callback): string
    {
        $watcherId = uniqid('watcher_', true);
        $this->watchers[$watcherId] = $callback;

        // Start file watching for hot reload
        if ($this->environment->isDevelopment()) {
            $this->startFileWatching();
        }

        return $watcherId;
    }

    public function unwatch(string $watcherId): bool
    {
        if (isset($this->watchers[$watcherId])) {
            unset($this->watchers[$watcherId]);
            return true;
        }
        return false;
    }

    public function reload(): array
    {
        $oldConfig = $this->config;
        $newConfig = $this->load();

        if ($oldConfig !== $newConfig) {
            $this->notifyWatchers($oldConfig, $newConfig);
        }

        return $newConfig;
    }

    public function export(ConfigurationSource $targetSource, string $targetPath = ''): bool
    {
        if (!$targetSource->isWritable()) {
            throw new \InvalidArgumentException("Target source {$targetSource->value} is not writable");
        }

        $targetPath = $targetPath ?: $targetSource->getDefaultPath($this->basePath);

        return match ($targetSource) {
            ConfigurationSource::PHP_FILE => $this->exportToPhp($targetPath),
            ConfigurationSource::JSON_FILE => $this->exportToJson($targetPath),
            ConfigurationSource::YAML_FILE => $this->exportToYaml($targetPath),
            ConfigurationSource::ENV_FILE => $this->exportToEnv($targetPath),
            default => throw new \InvalidArgumentException("Unsupported export target: {$targetSource->value}"),
        };
    }

    public function getStatistics(): array
    {
        return [
            'environment' => $this->environment->toArray(),
            'sources_count' => count($this->sources),
            'config_keys_count' => $this->countConfigKeys($this->config),
            'validation_state' => $this->validationState->toArray(),
            'watchers_count' => count($this->watchers),
            'sources' => array_map(fn($source) => [
                'source' => $source['source']->toArray(),
                'path' => $source['path'],
                'priority' => $source['priority'],
                'last_modified' => $source['last_modified'],
                'has_cached_data' => $source['cached_data'] !== null,
            ], $this->sources),
        ];
    }

    private function registerDefaultSources(): void
    {
        // Environment variables have highest priority
        $this->addSource(ConfigurationSource::ENVIRONMENT_VARIABLES);

        // .env file
        $envPath = $this->basePath . '/.env';
        if (file_exists($envPath)) {
            $this->addSource(ConfigurationSource::ENV_FILE, $envPath);
        }

        // Environment-specific .env file
        $envSpecificPath = $this->basePath . '/.env.' . $this->environment->value;
        if (file_exists($envSpecificPath)) {
            $this->addSource(ConfigurationSource::ENV_FILE, $envSpecificPath);
        }

        // PHP config file
        $phpConfigPath = $this->basePath . '/config/wioex.php';
        if (file_exists($phpConfigPath)) {
            $this->addSource(ConfigurationSource::PHP_FILE, $phpConfigPath);
        }
    }

    private function loadFromSource(array &$sourceInfo): array
    {
        $source = $sourceInfo['source'];
        $path = $sourceInfo['path'];

        return match ($source) {
            ConfigurationSource::ARRAY => $sourceInfo['cached_data'] ?? [],
            ConfigurationSource::ENVIRONMENT_VARIABLES => $this->loadFromEnvironmentVariables(),
            ConfigurationSource::ENV_FILE => $this->loadFromEnvFile($path),
            ConfigurationSource::PHP_FILE => $this->loadFromPhpFile($path),
            ConfigurationSource::JSON_FILE => $this->loadFromJsonFile($path),
            ConfigurationSource::YAML_FILE => $this->loadFromYamlFile($path),
            default => throw new \InvalidArgumentException("Unsupported configuration source: {$source->value}"),
        };
    }

    private function loadFromEnvironmentVariables(): array
    {
        $config = [];
        $prefix = 'WIOEX_';

        foreach ($_ENV as $key => $value) {
            if (strpos($key, $prefix) === 0) {
                $configKey = strtolower(str_replace($prefix, '', $key));
                $configKey = str_replace('_', '.', $configKey);
                $this->setNestedValue($config, $configKey, $this->parseEnvValue($value));
            }
        }

        return $config;
    }

    private function loadFromEnvFile(string $path): array
    {
        if (!file_exists($path)) {
            throw new \RuntimeException("Environment file not found: {$path}");
        }

        $config = [];
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            $line = trim($line);

            if (($line === null || $line === '' || $line === []) || $line[0] === '#') {
                continue;
            }

            if (strpos($line, '=') !== false) {
                [$key, $value] = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value, " \t\n\r\0\x0B\"'");

                if (strpos($key, 'WIOEX_') === 0) {
                    $configKey = strtolower(str_replace('WIOEX_', '', $key));
                    $configKey = str_replace('_', '.', $configKey);
                    $this->setNestedValue($config, $configKey, $this->parseEnvValue($value));
                }
            }
        }

        return $config;
    }

    private function loadFromPhpFile(string $path): array
    {
        if (!file_exists($path)) {
            throw new \RuntimeException("PHP config file not found: {$path}");
        }

        $config = include $path;

        if (!is_array($config)) {
            throw new \RuntimeException("PHP config file must return an array: {$path}");
        }

        return $config;
    }

    private function loadFromJsonFile(string $path): array
    {
        if (!file_exists($path)) {
            throw new \RuntimeException("JSON config file not found: {$path}");
        }

        $content = file_get_contents($path);
        $config = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException("Invalid JSON in config file: {$path} - " . json_last_error_msg());
        }

        return $config ?? [];
    }

    private function loadFromYamlFile(string $path): array
    {
        if (!file_exists($path)) {
            throw new \RuntimeException("YAML config file not found: {$path}");
        }

        if (!function_exists('yaml_parse_file')) {
            throw new \RuntimeException("YAML extension not available");
        }

        $config = yaml_parse_file($path);

        if ($config === false) {
            throw new \RuntimeException("Invalid YAML in config file: {$path}");
        }

        return $config ?? [];
    }

    private function applyEnvironmentConfiguration(): void
    {
        // Apply environment-specific defaults
        $envDefaults = [
            'base_url' => $this->environment->getBaseUrl(),
            'timeout' => $this->environment->getDefaultTimeout(),
            'debug' => $this->environment->shouldEnableDebug(),
            'logging' => [
                'enabled' => $this->environment->shouldEnableLogging(),
                'level' => $this->environment->getLogLevel(),
            ],
        ];

        // Apply performance profile
        $performanceProfile = $this->environment->getPerformanceProfile();
        foreach ($performanceProfile as $key => $value) {
            $envDefaults[$key] = $value;
        }

        // Merge with existing config (existing config takes precedence)
        $this->config = array_merge_recursive($envDefaults, $this->config);
    }

    private function validateConfiguration(): void
    {
        $this->validationState = ValidationState::fromValidationResults($this->validationResults);
    }

    private function getNestedValue(array $array, string $key)
    {
        $keys = explode('.', $key);
        $current = $array;

        foreach ($keys as $k) {
            if (!is_array($current) || !isset($current[$k])) {
                return null;
            }
            $current = $current[$k];
        }

        return $current;
    }

    private function setNestedValue(array &$array, string $key, $value): void
    {
        $keys = explode('.', $key);
        $current = &$array;

        foreach ($keys as $i => $k) {
            if ($i === count($keys) - 1) {
                $current[$k] = $value;
            } else {
                if (!isset($current[$k]) || !is_array($current[$k])) {
                    $current[$k] = [];
                }
                $current = &$current[$k];
            }
        }
    }

    private function parseEnvValue(string $value)
    {
        // Parse boolean values
        $lower = strtolower($value);
        if (in_array($lower, ['true', '1', 'yes', 'on'], true)) {
            return true;
        }
        if (in_array($lower, ['false', '0', 'no', 'off'], true)) {
            return false;
        }

        // Parse numeric values
        if (is_numeric($value)) {
            return strpos($value, '.') !== false ? (float) $value : (int) $value;
        }

        // Parse null
        if ($lower === 'null') {
            return null;
        }

        return $value;
    }

    private function getLastModified(array $sourceInfo): ?int
    {
        if (!$sourceInfo['source']->requiresFileSystem()) {
            return null;
        }

        $path = $sourceInfo['path'];
        return file_exists($path) ? filemtime($path) : null;
    }

    private function reloadConfiguration(): void
    {
        $this->config = [];
        $this->validationResults = [];
        $this->load();
    }

    private function startFileWatching(): void
    {
        // Implementation would use inotify or similar for file watching
        // This is a simplified version
    }

    private function notifyWatchers(array $oldConfig, array $newConfig): void
    {
        foreach ($this->watchers as $callback) {
            try {
                $callback($oldConfig, $newConfig);
            } catch (\Throwable $e) {
                // Log watcher errors but continue with other watchers
                if (class_exists('\Wioex\SDK\ErrorReporter')) {
                    (new \Wioex\SDK\ErrorReporter($this->config ?? []))->report($e, [
                        'context' => 'configuration_watcher_error',
                        'watcher_count' => count($this->watchers)
                    ]);
                }
            }
        }
    }

    private function countConfigKeys(array $config): int
    {
        $count = 0;
        foreach ($config as $value) {
            if (is_array($value)) {
                $count += $this->countConfigKeys($value);
            } else {
                $count++;
            }
        }
        return $count;
    }

    private function exportToPhp(string $path): bool
    {
        $content = "<?php\n\nreturn " . var_export($this->config, true) . ";\n";
        return file_put_contents($path, $content) !== false;
    }

    private function exportToJson(string $path): bool
    {
        $content = json_encode($this->config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        return file_put_contents($path, $content) !== false;
    }

    private function exportToYaml(string $path): bool
    {
        if (!function_exists('yaml_emit')) {
            throw new \RuntimeException("YAML extension not available");
        }

        $content = yaml_emit($this->config);
        return file_put_contents($path, $content) !== false;
    }

    private function exportToEnv(string $path): bool
    {
        $content = "";
        $this->arrayToEnv($this->config, $content);
        return file_put_contents($path, $content) !== false;
    }

    private function arrayToEnv(array $array, string &$content, string $prefix = 'WIOEX_'): void
    {
        foreach ($array as $key => $value) {
            $envKey = $prefix . strtoupper(str_replace('.', '_', $key));

            if (is_array($value)) {
                $this->arrayToEnv($value, $content, $envKey . '_');
            } else {
                $envValue = is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
                $content .= "{$envKey}={$envValue}\n";
            }
        }
    }
}
