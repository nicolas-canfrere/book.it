<?php

declare(strict_types=1);

namespace App\Tests\ContextMap;

use App\Shared\Infrastructure\ContextMap\ContextMapBuilder;
use App\Shared\Infrastructure\ContextMap\ContextMapConfigLoader;
use App\Shared\Infrastructure\ContextMap\ContractScanner;
use App\Shared\Infrastructure\ContextMap\DeptracRulesetParser;
use App\Shared\Infrastructure\ContextMap\MermaidWriter;
use App\Shared\Infrastructure\ContextMap\YamlWriter;
use App\Shared\UI\Console\GenerateContextMapCommand;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[Group('unit')]
class GenerateContextMapCommandTest extends TestCase
{
    private string $tmpDir;
    private CommandTester $tester;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/cmcmd_' . uniqid();
        mkdir($this->tmpDir);
        mkdir($this->tmpDir . '/src');
        mkdir($this->tmpDir . '/docs');

        // Minimal valid deptrac-contexts.yaml
        file_put_contents($this->tmpDir . '/deptrac-contexts.yaml', "deptrac:\n  ruleset: {}\n");

        $this->tester = $this->buildTester($this->tmpDir);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    #[Test]
    public function itReturnsSuccessAndWritesBothFiles(): void
    {
        $exitCode = $this->tester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('Generated', $this->tester->getDisplay());
        self::assertFileExists($this->tmpDir . '/contextmap.yaml');
        self::assertFileExists($this->tmpDir . '/docs/context-map.md');
    }

    #[Test]
    public function itPassesExcludedLayersToParser(): void
    {
        // With default excluded layers the deptrac ruleset is filtered; an empty ruleset produces an empty contexts map
        $exitCode = $this->tester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        $yaml = file_get_contents($this->tmpDir . '/contextmap.yaml');
        self::assertNotFalse($yaml);
        self::assertStringContainsString('contexts', $yaml);
    }

    #[Test]
    public function itOverridesOutputYamlFromOption(): void
    {
        $exitCode = $this->tester->execute(['--output-yaml' => 'custom.yaml']);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertFileExists($this->tmpDir . '/custom.yaml');
        self::assertFileDoesNotExist($this->tmpDir . '/contextmap.yaml');
    }

    #[Test]
    public function itReturnsFailureWhenDeptracFileMissing(): void
    {
        unlink($this->tmpDir . '/deptrac-contexts.yaml');

        $exitCode = $this->tester->execute([]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('deptrac-contexts.yaml not found', $this->tester->getDisplay());
    }

    #[Test]
    public function itReturnsFailureWhenSrcDirMissing(): void
    {
        rmdir($this->tmpDir . '/src');

        $exitCode = $this->tester->execute([]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('src directory not found', $this->tester->getDisplay());
    }

    private function buildTester(string $projectDir): CommandTester
    {
        $command = new GenerateContextMapCommand(
            new ContractScanner(),
            new DeptracRulesetParser(),
            new ContextMapBuilder(),
            new YamlWriter(),
            new MermaidWriter(),
            new ContextMapConfigLoader(),
            $projectDir,
        );

        return new CommandTester($command);
    }

    private function removeDir(string $dir): void
    {
        foreach (glob($dir . '/*') ?: [] as $f) {
            is_dir($f) ? $this->removeDir($f) : unlink($f);
        }
        rmdir($dir);
    }
}
