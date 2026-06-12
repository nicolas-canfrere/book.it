<?php
declare(strict_types=1);

namespace App\Tests\ContextMap;

use App\Shared\Infrastructure\ContextMap\ContextMapConfigLoader;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
class ContextMapConfigLoaderTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/cmconfig_' . uniqid();
        mkdir($this->tmpDir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tmpDir . '/*') ?: [] as $f) {
            unlink($f);
        }
        rmdir($this->tmpDir);
    }

    #[Test]
    public function itReturnsDefaultsWhenConfigFileAbsent(): void
    {
        $config = (new ContextMapConfigLoader())->load($this->tmpDir);

        self::assertSame($this->tmpDir . '/src/', $config->getSrcDir());
        self::assertSame($this->tmpDir . '/deptrac-contexts.yaml', $config->getDeptracFile());
        self::assertSame($this->tmpDir . '/contextmap.yaml', $config->getOutputYaml());
        self::assertSame($this->tmpDir . '/docs/context-map.md', $config->getOutputMermaid());
        self::assertContains('Shared', $config->getExcludedLayers());
        self::assertContains('Vendor', $config->getExcludedLayers());
    }

    #[Test]
    public function itMergesPartialConfig(): void
    {
        file_put_contents($this->tmpDir . '/contextmap.config.yaml', <<<'YAML'
        contextmap:
          output_yaml: custom/map.yaml
        YAML);

        $config = (new ContextMapConfigLoader())->load($this->tmpDir);

        self::assertSame($this->tmpDir . '/custom/map.yaml', $config->getOutputYaml());
        self::assertSame($this->tmpDir . '/src/', $config->getSrcDir());
    }

    #[Test]
    public function itOverridesExcludedLayers(): void
    {
        file_put_contents($this->tmpDir . '/contextmap.config.yaml', <<<'YAML'
        contextmap:
          excluded_layers:
            - MyCustomLayer
        YAML);

        $config = (new ContextMapConfigLoader())->load($this->tmpDir);

        self::assertSame(['MyCustomLayer'], $config->getExcludedLayers());
    }
}
