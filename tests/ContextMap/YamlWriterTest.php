<?php
declare(strict_types=1);

namespace App\Tests\ContextMap;

use App\Shared\Infrastructure\ContextMap\YamlWriter;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
class YamlWriterTest extends TestCase
{
    #[Test]
    public function itWritesContextMapAsYaml(): void
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

        $outputPath = tempnam(sys_get_temp_dir(), 'yaml_');
        (new YamlWriter())->write($contextMap, $outputPath);

        $content = file_get_contents($outputPath);
        self::assertStringContainsString('# Generated', $content);
        self::assertStringContainsString('version: \'1.0\'', $content);
        self::assertStringContainsString('Hotel:', $content);
        self::assertStringContainsString('Room:', $content);

        unlink($outputPath);
    }
}
