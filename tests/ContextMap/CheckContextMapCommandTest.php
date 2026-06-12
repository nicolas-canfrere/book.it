<?php

declare(strict_types=1);

namespace App\Tests\ContextMap;

use App\Shared\Infrastructure\ContextMap\ContextMapChecker;
use App\Shared\Infrastructure\ContextMap\ContextMapConfigLoader;
use App\Shared\UI\Console\CheckContextMapCommand;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[Group('unit')]
class CheckContextMapCommandTest extends TestCase
{
    private string $tmpDir;
    private CommandTester $tester;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir().'/cmcheck_'.uniqid();
        mkdir($this->tmpDir);
        mkdir($this->tmpDir.'/src');

        $this->tester = $this->buildTester($this->tmpDir);
    }

    private function buildTester(string $projectDir): CommandTester
    {
        $command = new CheckContextMapCommand(
            new ContextMapChecker(),
            new ContextMapConfigLoader(),
            $projectDir,
        );

        return new CommandTester($command);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    private function removeDir(string $dir): void
    {
        foreach (glob($dir.'/*') ?: [] as $f) {
            is_dir($f) ? $this->removeDir($f) : unlink($f);
        }
        rmdir($dir);
    }

    #[Test]
    public function itReturnsFailureWhenContextmapYamlMissing(): void
    {
        $exitCode = $this->tester->execute([]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('contextmap.yaml not found', $this->tester->getDisplay());
        self::assertStringContainsString('app:contextmap:generate', $this->tester->getDisplay());
    }

    #[Test]
    public function itReturnsSuccessWhenAllChecksPass(): void
    {
        // Write a valid contextmap.yaml with no contexts (empty map = all ok, no fails)
        file_put_contents($this->tmpDir.'/contextmap.yaml', "contexts: {}\n");

        $exitCode = $this->tester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
    }

    #[Test]
    public function itReturnsFailureWhenChecksHaveFails(): void
    {
        // Write a contextmap with a context referencing a non-existent class to trigger a fail
        $yaml = <<<YAML
contexts:
    Booker:
        open_host_services:
            interfaces:
                - App\Booker\Application\Contract\MissingInterface
YAML;
        file_put_contents($this->tmpDir.'/contextmap.yaml', $yaml);

        $exitCode = $this->tester->execute([]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('[FAIL]', $this->tester->getDisplay());
    }
}
