<?php

declare(strict_types=1);

namespace App\Tests\ContextMap;

use App\Shared\Infrastructure\ContextMap\ContextMapChecker;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
class ContextMapCheckerTest extends TestCase
{
    #[Test]
    public function itChecksContextMapClasses(): void
    {
        // Create temporary test files
        $tmpDir = sys_get_temp_dir() . '/contextmap_test_' . uniqid();
        mkdir($tmpDir, 0777, true);
        mkdir($tmpDir . '/Booker/Application/Contract', 0777, true);
        mkdir($tmpDir . '/Hotel/Application/Contract', 0777, true);

        // Create test class files
        file_put_contents(
            $tmpDir . '/Booker/Application/Contract/BookerFinderInterface.php',
            '<?php declare(strict_types=1); namespace App\Booker\Application\Contract; interface BookerFinderInterface {}'
        );
        file_put_contents(
            $tmpDir . '/Booker/Application/Contract/BookerView.php',
            '<?php declare(strict_types=1); namespace App\Booker\Application\Contract; class BookerView {}'
        );
        file_put_contents(
            $tmpDir . '/Hotel/Application/Contract/HotelFinderInterface.php',
            '<?php declare(strict_types=1); namespace App\Hotel\Application\Contract; interface HotelFinderInterface {}'
        );

        // Create context map YAML
        $contextMapPath = $tmpDir . '/contextmap.yaml';
        file_put_contents(
            $contextMapPath,
            <<<'YAML'
version: '1.0'
contexts:
  Booker:
    open_host_services:
      interfaces:
        - App\Booker\Application\Contract\BookerFinderInterface
      published_language:
        - App\Booker\Application\Contract\BookerView
    consumes: []
  Hotel:
    open_host_services:
      interfaces:
        - App\Hotel\Application\Contract\HotelFinderInterface
      published_language: []
    consumes: []
  Room:
    open_host_services:
      interfaces: []
      published_language: []
    consumes:
      - context: Hotel
YAML
        );

        // Create adapter directory structure to test adapter detection
        mkdir($tmpDir . '/Room/Infrastructure', 0777, true);
        file_put_contents(
            $tmpDir . '/Room/Infrastructure/HotelAdapter.php',
            '<?php declare(strict_types=1); namespace App\Room\Infrastructure; use App\Hotel\Application\Contract\HotelFinderInterface; class HotelAdapter {}'
        );

        $checker = new ContextMapChecker();
        $result = $checker->check($contextMapPath, $tmpDir);

        self::assertGreaterThan(0, count($result['ok']));

        // Cleanup
        $this->rmdir($tmpDir);
    }

    #[Test]
    public function itFailsWhenInterfaceClassFileIsMissing(): void
    {
        // Create temporary test files
        $tmpDir = sys_get_temp_dir() . '/contextmap_test_missing_class_' . uniqid();
        mkdir($tmpDir, 0777, true);
        mkdir($tmpDir . '/Booker/Application/Contract', 0777, true);

        // Create context map YAML with an interface listed but NO corresponding PHP file
        $contextMapPath = $tmpDir . '/contextmap.yaml';
        file_put_contents(
            $contextMapPath,
            <<<'YAML'
version: '1.0'
contexts:
  Booker:
    open_host_services:
      interfaces:
        - App\Booker\Application\Contract\BookerFinderInterface
      published_language: []
    consumes: []
YAML
        );

        $checker = new ContextMapChecker();
        $result = $checker->check($contextMapPath, $tmpDir);

        self::assertCount(1, $result['fail']);
        self::assertStringContainsString('BookerFinderInterface', $result['fail'][0]);
        self::assertStringContainsString('not found', $result['fail'][0]);

        // Cleanup
        $this->rmdir($tmpDir);
    }

    #[Test]
    public function itFailsWhenNoAdapterFoundForConsumption(): void
    {
        // Create temporary test files
        $tmpDir = sys_get_temp_dir() . '/contextmap_test_no_adapter_' . uniqid();
        mkdir($tmpDir, 0777, true);
        mkdir($tmpDir . '/Hotel/Application/Contract', 0777, true);
        mkdir($tmpDir . '/Room/Infrastructure', 0777, true);

        // Create a minimal adapter file that does NOT reference Hotel's contract
        file_put_contents(
            $tmpDir . '/Room/Infrastructure/SomeAdapter.php',
            '<?php declare(strict_types=1); namespace App\Room\Infrastructure; class SomeAdapter {}'
        );

        // Create context map YAML with a consumes relationship but no valid adapter
        $contextMapPath = $tmpDir . '/contextmap.yaml';
        file_put_contents(
            $contextMapPath,
            <<<'YAML'
version: '1.0'
contexts:
  Hotel:
    open_host_services:
      interfaces: []
      published_language: []
    consumes: []
  Room:
    open_host_services:
      interfaces: []
      published_language: []
    consumes:
      - context: Hotel
YAML
        );

        $checker = new ContextMapChecker();
        $result = $checker->check($contextMapPath, $tmpDir);

        self::assertCount(1, $result['fail']);
        self::assertStringContainsString('Room consumes Hotel', $result['fail'][0]);
        self::assertStringContainsString('no adapter found', $result['fail'][0]);

        // Cleanup
        $this->rmdir($tmpDir);
    }

    private function rmdir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $file) {
            /** @var \SplFileInfo $file */
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }
        rmdir($dir);
    }
}
