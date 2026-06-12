<?php
declare(strict_types=1);

namespace App\Tests\ContextMap;

use App\Shared\Infrastructure\ContextMap\MermaidWriter;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
class MermaidWriterTest extends TestCase
{
    #[Test]
    public function itWritesMermaidDiagram(): void
    {
        $contextMap = [
            'version' => '1.0',
            'generated_at' => '2024-01-01T00:00:00+00:00',
            'contexts' => [
                'Hotel' => [
                    'open_host_services' => [
                        'interfaces' => ['App\\Hotel\\Application\\Contract\\HotelFinderInterface'],
                        'published_language' => ['App\\Hotel\\Application\\Contract\\HotelView'],
                    ],
                    'consumed_by' => ['Room'],
                    'consumes' => [],
                ],
                'Room' => [
                    'open_host_services' => [
                        'interfaces' => [],
                        'published_language' => [],
                    ],
                    'consumed_by' => [],
                    'consumes' => [['context' => 'Hotel']],
                ],
            ],
        ];

        $outputPath = tempnam(sys_get_temp_dir(), 'mermaid_');
        (new MermaidWriter())->write($contextMap, $outputPath);

        $content = (string) file_get_contents($outputPath);
        self::assertStringContainsString('# Context Map', $content);
        self::assertStringContainsString('```mermaid', $content);
        self::assertStringContainsString('graph LR', $content);
        self::assertStringContainsString('Room -->|HotelFinderInterface| Hotel', $content);

        unlink($outputPath);
    }

    #[Test]
    public function itUsesContextNameWhenNoInterfacesAvailable(): void
    {
        $contextMap = [
            'version' => '1.0',
            'generated_at' => '2024-01-01T00:00:00+00:00',
            'contexts' => [
                'Producer' => [
                    'open_host_services' => [
                        'interfaces' => [],
                        'published_language' => [],
                    ],
                    'consumed_by' => ['Consumer'],
                    'consumes' => [],
                ],
                'Consumer' => [
                    'open_host_services' => [
                        'interfaces' => [],
                        'published_language' => [],
                    ],
                    'consumed_by' => [],
                    'consumes' => [['context' => 'Producer']],
                ],
            ],
        ];

        $outputPath = tempnam(sys_get_temp_dir(), 'mermaid_');
        (new MermaidWriter())->write($contextMap, $outputPath);

        $content = (string) file_get_contents($outputPath);
        self::assertStringContainsString('Consumer -->|Producer| Producer', $content);

        unlink($outputPath);
    }

    #[Test]
    public function itShowsMultipleInterfaceNamesCommaSeparated(): void
    {
        $contextMap = [
            'version' => '1.0',
            'generated_at' => '2024-01-01T00:00:00+00:00',
            'contexts' => [
                'Producer' => [
                    'open_host_services' => [
                        'interfaces' => [
                            'App\\Producer\\Application\\Contract\\FinderInterface',
                            'App\\Producer\\Application\\Contract\\CheckerInterface',
                        ],
                        'published_language' => [],
                    ],
                    'consumed_by' => ['Consumer'],
                    'consumes' => [],
                ],
                'Consumer' => [
                    'open_host_services' => [
                        'interfaces' => [],
                        'published_language' => [],
                    ],
                    'consumed_by' => [],
                    'consumes' => [['context' => 'Producer']],
                ],
            ],
        ];

        $outputPath = tempnam(sys_get_temp_dir(), 'mermaid_');
        (new MermaidWriter())->write($contextMap, $outputPath);

        $content = (string) file_get_contents($outputPath);
        self::assertStringContainsString('Consumer -->|FinderInterface, CheckerInterface| Producer', $content);

        unlink($outputPath);
    }
}
