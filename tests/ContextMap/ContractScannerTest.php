<?php
declare(strict_types=1);

namespace App\Tests\ContextMap;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tools\ContextMap\ContractScanner;

#[Group('unit')]
class ContractScannerTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/contextmap_' . uniqid();
        mkdir($this->tmpDir . '/Booker/Application/Contract', 0777, true);
        file_put_contents($this->tmpDir . '/Booker/Application/Contract/BookerFinderInterface.php', '<?php');
        file_put_contents($this->tmpDir . '/Booker/Application/Contract/BookerView.php', '<?php');
        file_put_contents($this->tmpDir . '/Booker/Application/Contract/AccountRegistrationFailedException.php', '<?php');
        mkdir($this->tmpDir . '/Hotel/Application/Contract', 0777, true);
        file_put_contents($this->tmpDir . '/Hotel/Application/Contract/HotelFinderInterface.php', '<?php');
        file_put_contents($this->tmpDir . '/Hotel/Application/Contract/HotelView.php', '<?php');
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    private function removeDir(string $dir): void
    {
        foreach (glob($dir . '/*') ?: [] as $file) {
            is_dir($file) ? $this->removeDir($file) : unlink($file);
        }
        rmdir($dir);
    }

    public function test_scans_interfaces_per_context(): void
    {
        $result = (new ContractScanner())->scan($this->tmpDir);

        self::assertSame(
            ['App\\Booker\\Application\\Contract\\BookerFinderInterface'],
            $result['Booker']['interfaces']
        );
    }

    public function test_scans_views_per_context(): void
    {
        $result = (new ContractScanner())->scan($this->tmpDir);

        self::assertSame(
            ['App\\Booker\\Application\\Contract\\BookerView'],
            $result['Booker']['published_language']
        );
    }

    public function test_ignores_non_interface_non_view_files(): void
    {
        $result = (new ContractScanner())->scan($this->tmpDir);

        self::assertCount(1, $result['Booker']['interfaces']);
        self::assertCount(1, $result['Booker']['published_language']);
    }

    public function test_scans_multiple_contexts(): void
    {
        $result = (new ContractScanner())->scan($this->tmpDir);

        self::assertArrayHasKey('Booker', $result);
        self::assertArrayHasKey('Hotel', $result);
    }
}
