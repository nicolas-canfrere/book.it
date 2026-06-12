<?php
declare(strict_types=1);

namespace App\Tests\ContextMap;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;
use Tools\ContextMap\ContextMapChecker;

#[Group('unit')]
class ContextMapCheckerTest extends TestCase
{
    private string $tmpDir;
    private string $tmpYaml;

    protected function setUp(): void
    {
        $this->tmpDir  = sys_get_temp_dir() . '/checker_' . uniqid();
        $this->tmpYaml = $this->tmpDir . '/contextmap.yaml';

        mkdir($this->tmpDir . '/src/Booker/Application/Contract', 0777, true);
        mkdir($this->tmpDir . '/src/Booker/Infrastructure/Service', 0777, true);
        file_put_contents(
            $this->tmpDir . '/src/Booker/Application/Contract/BookerFinderInterface.php',
            '<?php'
        );
        file_put_contents(
            $this->tmpDir . '/src/Booker/Application/Contract/BookerView.php',
            '<?php'
        );
        mkdir($this->tmpDir . '/src/Reservation/Infrastructure/Service', 0777, true);
        file_put_contents(
            $this->tmpDir . '/src/Reservation/Infrastructure/Service/BookerContactFetcher.php',
            '<?php use App\Booker\Application\Contract\BookerFinderInterface;'
        );
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

    private function writeContextMap(array $data): void
    {
        file_put_contents($this->tmpYaml, Yaml::dump($data));
    }

    #[Test]
    public function itReportsOkWhenInterfaceClassExists(): void
    {
        $this->writeContextMap([
            'version' => '1.0',
            'contexts' => [
                'Booker' => [
                    'open_host_services' => [
                        'interfaces' => ['App\\Booker\\Application\\Contract\\BookerFinderInterface'],
                        'published_language' => [],
                    ],
                    'consumed_by' => [],
                    'consumes' => [],
                ],
            ],
        ]);

        $result = (new ContextMapChecker())->check($this->tmpYaml, $this->tmpDir . '/src');

        self::assertCount(1, $result['ok']);
        self::assertCount(0, $result['fail']);
        self::assertStringContainsString('BookerFinderInterface', $result['ok'][0]);
    }

    #[Test]
    public function itReportsFailWhenInterfaceClassMissing(): void
    {
        $this->writeContextMap([
            'version' => '1.0',
            'contexts' => [
                'Booker' => [
                    'open_host_services' => [
                        'interfaces' => ['App\\Booker\\Application\\Contract\\MissingInterface'],
                        'published_language' => [],
                    ],
                    'consumed_by' => [],
                    'consumes' => [],
                ],
            ],
        ]);

        $result = (new ContextMapChecker())->check($this->tmpYaml, $this->tmpDir . '/src');

        self::assertCount(1, $result['fail']);
        self::assertStringContainsString('MissingInterface', $result['fail'][0]);
    }

    #[Test]
    public function itReportsOkWhenAdapterFound(): void
    {
        $this->writeContextMap([
            'version' => '1.0',
            'contexts' => [
                'Booker' => [
                    'open_host_services' => ['interfaces' => [], 'published_language' => []],
                    'consumed_by' => ['Reservation'],
                    'consumes' => [],
                ],
                'Reservation' => [
                    'open_host_services' => ['interfaces' => [], 'published_language' => []],
                    'consumed_by' => [],
                    'consumes' => [['context' => 'Booker']],
                ],
            ],
        ]);

        $result = (new ContextMapChecker())->check($this->tmpYaml, $this->tmpDir . '/src');

        $adapterOk = array_filter($result['ok'], static fn($m) => str_contains($m, 'consumes'));
        self::assertNotEmpty($adapterOk);
    }

    #[Test]
    public function itReportsFailWhenNoAdapterFound(): void
    {
        mkdir($this->tmpDir . '/src/Room/Infrastructure', 0777, true);
        $this->writeContextMap([
            'version' => '1.0',
            'contexts' => [
                'Hotel' => [
                    'open_host_services' => ['interfaces' => [], 'published_language' => []],
                    'consumed_by' => ['Room'],
                    'consumes' => [],
                ],
                'Room' => [
                    'open_host_services' => ['interfaces' => [], 'published_language' => []],
                    'consumed_by' => [],
                    'consumes' => [['context' => 'Hotel']],
                ],
            ],
        ]);

        $result = (new ContextMapChecker())->check($this->tmpYaml, $this->tmpDir . '/src');

        $adapterFail = array_filter($result['fail'], static fn($m) => str_contains($m, 'consumes'));
        self::assertNotEmpty($adapterFail);
    }
}
