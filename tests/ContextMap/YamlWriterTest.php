<?php
declare(strict_types=1);

namespace App\Tests\ContextMap;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tools\ContextMap\YamlWriter;

#[Group('unit')]
class YamlWriterTest extends TestCase
{
    private string $tmpFile;

    protected function setUp(): void
    {
        $this->tmpFile = sys_get_temp_dir() . '/contextmap_' . uniqid() . '.yaml';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tmpFile)) {
            unlink($this->tmpFile);
        }
    }

    #[Test]
    public function itWritesYamlFile(): void
    {
        (new YamlWriter())->write(['version' => '1.0', 'contexts' => []], $this->tmpFile);

        self::assertFileExists($this->tmpFile);
    }

    #[Test]
    public function itStartsWithGeneratedComment(): void
    {
        (new YamlWriter())->write(['version' => '1.0', 'contexts' => []], $this->tmpFile);

        self::assertStringStartsWith('# Generated', file_get_contents($this->tmpFile));
    }

    #[Test]
    public function itContainsVersion(): void
    {
        (new YamlWriter())->write(['version' => '1.0', 'contexts' => []], $this->tmpFile);

        self::assertStringContainsString("version: '1.0'", file_get_contents($this->tmpFile));
    }
}
