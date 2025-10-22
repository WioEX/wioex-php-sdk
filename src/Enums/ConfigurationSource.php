<?php

declare(strict_types=1);

namespace Wioex\SDK\Enums;

enum ConfigurationSource: string
{
    case ARRAY = 'array';
    case ENV_FILE = 'env_file';
    case PHP_FILE = 'php_file';
    case JSON_FILE = 'json_file';
    case YAML_FILE = 'yaml_file';
    case ENVIRONMENT_VARIABLES = 'environment_variables';
    case DATABASE = 'database';
    case REMOTE_CONFIG = 'remote_config';

    public function getDescription(): string
    {
        return match ($this) {
            self::ARRAY => 'Direct array configuration',
            self::ENV_FILE => '.env file configuration',
            self::PHP_FILE => 'PHP configuration file',
            self::JSON_FILE => 'JSON configuration file',
            self::YAML_FILE => 'YAML configuration file',
            self::ENVIRONMENT_VARIABLES => 'System environment variables',
            self::DATABASE => 'Database-stored configuration',
            self::REMOTE_CONFIG => 'Remote configuration service',
        };
    }

    public function getFileExtension(): ?string
    {
        return match ($this) {
            self::ENV_FILE => '.env',
            self::PHP_FILE => '.php',
            self::JSON_FILE => '.json',
            self::YAML_FILE => '.yaml',
            default => null,
        };
    }

    public function requiresFileSystem(): bool
    {
        return match ($this) {
            self::ENV_FILE, self::PHP_FILE, self::JSON_FILE, self::YAML_FILE => true,
            default => false,
        };
    }

    public function requiresNetwork(): bool
    {
        return match ($this) {
            self::REMOTE_CONFIG, self::DATABASE => true,
            default => false,
        };
    }

    public function supportsCaching(): bool
    {
        return match ($this) {
            self::DATABASE, self::REMOTE_CONFIG => true,
            default => false,
        };
    }

    public function getPriority(): int
    {
        return match ($this) {
            self::ARRAY => 10, // Highest priority
            self::ENVIRONMENT_VARIABLES => 9,
            self::ENV_FILE => 8,
            self::PHP_FILE => 7,
            self::JSON_FILE => 6,
            self::YAML_FILE => 5,
            self::DATABASE => 4,
            self::REMOTE_CONFIG => 3, // Lowest priority
        };
    }

    public function getDefaultPath(string $basePath = ''): string
    {
        return match ($this) {
            self::ENV_FILE => $basePath . '/.env',
            self::PHP_FILE => $basePath . '/config/wioex.php',
            self::JSON_FILE => $basePath . '/config/wioex.json',
            self::YAML_FILE => $basePath . '/config/wioex.yaml',
            default => $basePath,
        };
    }

    public function isWritable(): bool
    {
        return match ($this) {
            self::ARRAY, self::ENVIRONMENT_VARIABLES => false,
            default => true,
        };
    }

    public function supportsHotReload(): bool
    {
        return match ($this) {
            self::ENV_FILE, self::PHP_FILE, self::JSON_FILE, self::YAML_FILE => true,
            default => false,
        };
    }

    public static function fromFileExtension(string $extension): self
    {
        $extension = strtolower(ltrim($extension, '.'));

        return match ($extension) {
            'env' => self::ENV_FILE,
            'php' => self::PHP_FILE,
            'json' => self::JSON_FILE,
            'yaml', 'yml' => self::YAML_FILE,
            default => throw new \InvalidArgumentException("Unsupported file extension: {$extension}"),
        };
    }

    public static function fromPath(string $path): self
    {
        $extension = pathinfo($path, PATHINFO_EXTENSION);

        if (basename($path) === '.env') {
            return self::ENV_FILE;
        }

        return self::fromFileExtension($extension);
    }

    public function toArray(): array
    {
        return [
            'value' => $this->value,
            'name' => $this->name,
            'description' => $this->getDescription(),
            'file_extension' => $this->getFileExtension(),
            'requires_filesystem' => $this->requiresFileSystem(),
            'requires_network' => $this->requiresNetwork(),
            'supports_caching' => $this->supportsCaching(),
            'priority' => $this->getPriority(),
            'is_writable' => $this->isWritable(),
            'supports_hot_reload' => $this->supportsHotReload(),
        ];
    }
}
