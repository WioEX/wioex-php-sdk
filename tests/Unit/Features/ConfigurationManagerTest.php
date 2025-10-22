<?php

declare(strict_types=1);

namespace Wioex\SDK\Tests\Unit\Features;

use PHPUnit\Framework\TestCase;
use Wioex\SDK\Configuration\ConfigurationManager;
use Wioex\SDK\Enums\ConfigurationSource;
use Wioex\SDK\Enums\Environment;

class ConfigurationManagerTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/wioex_test_' . uniqid();
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    public function testBasicConfigurationManager(): void
    {
        $manager = ConfigurationManager::create();
        
        $this->assertInstanceOf(ConfigurationManager::class, $manager);
        $config = $manager->load();
        $this->assertIsArray($config);
    }

    public function testConfigurationManagerForEnvironment(): void
    {
        $manager = ConfigurationManager::create(Environment::DEVELOPMENT);
        
        $this->assertInstanceOf(ConfigurationManager::class, $manager);
        $this->assertEquals(Environment::DEVELOPMENT, $manager->getEnvironment());
        $config = $manager->load();
        $this->assertIsArray($config);
    }

    public function testAddSource(): void
    {
        $manager = ConfigurationManager::create();
        
        $result = $manager->addSource(ConfigurationSource::ENV_FILE, '.env.test');
        
        $this->assertInstanceOf(ConfigurationManager::class, $result);
    }

    public function testArrayConfiguration(): void
    {
        // Create a temporary file to simulate array-based config
        $configFile = $this->tempDir . '/array_config.php';
        $testConfig = [
            'api_key' => 'test-key',
            'timeout' => 60,
            'debug' => true
        ];
        file_put_contents($configFile, "<?php\n\nreturn " . var_export($testConfig, true) . ";\n");
        
        $manager = ConfigurationManager::create();
        $manager->addSource(ConfigurationSource::PHP_FILE, $configFile);
        $config = $manager->load();
        
        $this->assertEquals('test-key', $config['api_key']);
        $this->assertEquals(60, $config['timeout']);
        $this->assertTrue($config['debug']);
    }

    public function testEnvFileConfiguration(): void
    {
        // Create test .env file
        $envContent = "API_KEY=env-test-key\nTIMEOUT=30\nDEBUG=true\n";
        $envFile = $this->tempDir . '/.env';
        file_put_contents($envFile, $envContent);
        
        $manager = ConfigurationManager::create();
        $manager->addSource(ConfigurationSource::ENV_FILE, $envFile);
        $config = $manager->load();
        
        $this->assertEquals('env-test-key', $config['API_KEY']);
        $this->assertEquals('30', $config['TIMEOUT']);
        $this->assertEquals('true', $config['DEBUG']);
    }

    public function testPhpFileConfiguration(): void
    {
        // Create test PHP config file
        $phpConfig = [
            'api_key' => 'php-test-key',
            'timeout' => 45,
            'features' => ['caching', 'logging']
        ];
        $phpFile = $this->tempDir . '/config.php';
        file_put_contents($phpFile, "<?php\n\nreturn " . var_export($phpConfig, true) . ";\n");
        
        $manager = ConfigurationManager::create();
        $manager->addSource(ConfigurationSource::PHP_FILE, $phpFile);
        $config = $manager->load();
        
        $this->assertEquals('php-test-key', $config['api_key']);
        $this->assertEquals(45, $config['timeout']);
        $this->assertEquals(['caching', 'logging'], $config['features']);
    }

    public function testJsonFileConfiguration(): void
    {
        // Create test JSON config file
        $jsonConfig = [
            'api_key' => 'json-test-key',
            'timeout' => 90,
            'endpoints' => [
                'stocks' => '/v1/stocks',
                'news' => '/v1/news'
            ]
        ];
        $jsonFile = $this->tempDir . '/config.json';
        file_put_contents($jsonFile, json_encode($jsonConfig, JSON_PRETTY_PRINT));
        
        $manager = ConfigurationManager::create();
        $manager->addSource(ConfigurationSource::JSON_FILE, $jsonFile);
        $config = $manager->load();
        
        $this->assertEquals('json-test-key', $config['api_key']);
        $this->assertEquals(90, $config['timeout']);
        $this->assertEquals('/v1/stocks', $config['endpoints']['stocks']);
        $this->assertEquals('/v1/news', $config['endpoints']['news']);
    }

    public function testYamlFileConfiguration(): void
    {
        // Create test YAML config file
        $yamlContent = "api_key: yaml-test-key\ntimeout: 120\nfeatures:\n  - monitoring\n  - validation\n";
        $yamlFile = $this->tempDir . '/config.yaml';
        file_put_contents($yamlFile, $yamlContent);
        
        $manager = ConfigurationManager::create();
        $manager->addSource(ConfigurationSource::YAML_FILE, $yamlFile);
        $config = $manager->load();
        
        $this->assertEquals('yaml-test-key', $config['api_key']);
        $this->assertEquals(120, $config['timeout']);
        $this->assertEquals(['monitoring', 'validation'], $config['features']);
    }

    public function testConfigurationPriority(): void
    {
        // Test that later sources override earlier ones
        $baseConfigFile = $this->tempDir . '/base_config.php';
        $overrideConfigFile = $this->tempDir . '/override_config.php';
        
        file_put_contents($baseConfigFile, "<?php\n\nreturn ['api_key' => 'base-key', 'timeout' => 30];");
        file_put_contents($overrideConfigFile, "<?php\n\nreturn ['api_key' => 'override-key', 'debug' => true];");
        
        $manager = ConfigurationManager::create();
        $manager->addSource(ConfigurationSource::PHP_FILE, $baseConfigFile, 1);
        $manager->addSource(ConfigurationSource::PHP_FILE, $overrideConfigFile, 2);
        $config = $manager->load();
        
        $this->assertEquals('override-key', $config['api_key']); // Overridden
        $this->assertEquals(30, $config['timeout']); // From base
        $this->assertTrue($config['debug']); // From override
    }

    public function testNestedConfigurationMerging(): void
    {
        $config1File = $this->tempDir . '/config1.php';
        $config2File = $this->tempDir . '/config2.php';
        
        $config1 = [
            'database' => [
                'host' => 'localhost',
                'port' => 3306
            ]
        ];
        $config2 = [
            'database' => [
                'port' => 3307,
                'name' => 'test_db'
            ]
        ];
        
        file_put_contents($config1File, "<?php\n\nreturn " . var_export($config1, true) . ";");
        file_put_contents($config2File, "<?php\n\nreturn " . var_export($config2, true) . ";");
        
        $manager = ConfigurationManager::create();
        $manager->addSource(ConfigurationSource::PHP_FILE, $config1File, 1);
        $manager->addSource(ConfigurationSource::PHP_FILE, $config2File, 2);
        $config = $manager->load();
        
        $this->assertEquals('localhost', $config['database']['host']); // From config1
        $this->assertEquals(3307, $config['database']['port']); // Overridden by config2
        $this->assertEquals('test_db', $config['database']['name']); // From config2
    }

    public function testGetConfigValue(): void
    {
        $testConfig = [
            'api_key' => 'test-key',
            'nested' => [
                'value' => 'nested-value'
            ]
        ];
        
        $manager = ConfigurationManager::create()
            ->addSource(ConfigurationSource::ARRAY, $testConfig)
            ->load();
        
        $this->assertEquals('test-key', $manager->get('api_key'));
        $this->assertEquals('nested-value', $manager->get('nested.value'));
        $this->assertEquals('default', $manager->get('non_existent', 'default'));
        $this->assertNull($manager->get('non_existent'));
    }

    public function testSetConfigValue(): void
    {
        $manager = ConfigurationManager::create()
            ->addSource(ConfigurationSource::ARRAY, ['existing' => 'value'])
            ->load();
        
        $manager->set('new_key', 'new_value');
        $manager->set('nested.new_key', 'nested_value');
        
        $this->assertEquals('new_value', $manager->get('new_key'));
        $this->assertEquals('nested_value', $manager->get('nested.new_key'));
    }

    public function testHasConfigValue(): void
    {
        $testConfig = [
            'existing_key' => 'value',
            'nested' => [
                'key' => 'value'
            ]
        ];
        
        $manager = ConfigurationManager::create()
            ->addSource(ConfigurationSource::ARRAY, $testConfig)
            ->load();
        
        $this->assertTrue($manager->has('existing_key'));
        $this->assertTrue($manager->has('nested.key'));
        $this->assertFalse($manager->has('non_existent'));
        $this->assertFalse($manager->has('nested.non_existent'));
    }

    public function testValidation(): void
    {
        $validConfig = [
            'api_key' => 'valid-key',
            'timeout' => 30
        ];
        
        $manager = ConfigurationManager::create()
            ->addSource(ConfigurationSource::ARRAY, $validConfig)
            ->load();
        
        $this->assertTrue($manager->validate());
    }

    public function testGetSources(): void
    {
        $manager = ConfigurationManager::create()
            ->addSource(ConfigurationSource::ARRAY, [])
            ->addSource(ConfigurationSource::ENV_FILE, '/nonexistent/.env');
        
        $sources = $manager->getSources();
        
        $this->assertIsArray($sources);
        $this->assertCount(2, $sources);
    }

    public function testToArray(): void
    {
        $testConfig = ['key' => 'value'];
        
        $manager = ConfigurationManager::create()
            ->addSource(ConfigurationSource::ARRAY, $testConfig)
            ->load();
        
        $array = $manager->toArray();
        
        $this->assertIsArray($array);
        $this->assertArrayHasKey('config', $array);
        $this->assertArrayHasKey('sources', $array);
        $this->assertArrayHasKey('validation', $array);
    }
}