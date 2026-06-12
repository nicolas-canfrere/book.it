<?php
declare(strict_types=1);

namespace App\Tests\ContextMap;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tools\ContextMap\MermaidWriter;

#[Group('unit')]
class MermaidWriterTest extends TestCase
{
    private string $tmpFile;

    protected function setUp(): void
    {
        $this->tmpFile = sys_get_temp_dir() . '/context-map_' . uniqid() . '.md';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tmpFile)) {
            unlink($this->tmpFile);
        }
    }

    private function contextMap(): array
    {
        return [
            'version' => '1.0',
            'contexts' => [
                'Booker' => [
                    'open_host_services' => [
                        'interfaces' => ['App\\Booker\\Application\\Contract\\BookerFinderInterface'],
                        'published_language' => ['App\\Booker\\Application\\Contract\\BookerView'],
                    ],
                    'consumed_by' => ['Reservation'],
                    'consumes' => [],
                ],
                'Reservation' => [
                    'open_host_services' => ['interfaces' => [], 'published_language' => []],
                    'consumed_by' => [],
                    'consumes' => [['context' => 'Booker']],
                ],
            ],
        ];
    }

    #[Test]
    public function itWritesMarkdownFile(): void
    {
        (new MermaidWriter())->write($this->contextMap(), $this->tmpFile);

        self::assertFileExists($this->tmpFile);
    }

    #[Test]
    public function itContainsMermaidBlock(): void
    {
        (new MermaidWriter())->write($this->contextMap(), $this->tmpFile);
        $content = file_get_contents($this->tmpFile);

        self::assertStringContainsString('```mermaid', $content);
        self::assertStringContainsString('graph LR', $content);
    }

    #[Test]
    public function itContainsEdgeWithInterfaceLabel(): void
    {
        (new MermaidWriter())->write($this->contextMap(), $this->tmpFile);

        self::assertStringContainsString(
            'Reservation -->|BookerFinderInterface| Booker',
            file_get_contents($this->tmpFile)
        );
    }

    #[Test]
    public function itContainsGeneratedComment(): void
    {
        (new MermaidWriter())->write($this->contextMap(), $this->tmpFile);

        self::assertStringContainsString('Generated', file_get_contents($this->tmpFile));
    }
}
