<?php
declare(strict_types=1);

namespace App\Tests\ContextMap;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tools\ContextMap\ContextMapBuilder;

#[Group('unit')]
class ContextMapBuilderTest extends TestCase
{
    private array $contracts = [
        'Booker' => [
            'interfaces' => ['App\\Booker\\Application\\Contract\\BookerFinderInterface'],
            'published_language' => ['App\\Booker\\Application\\Contract\\BookerView'],
        ],
        'Hotel' => [
            'interfaces' => ['App\\Hotel\\Application\\Contract\\HotelFinderInterface'],
            'published_language' => ['App\\Hotel\\Application\\Contract\\HotelView'],
        ],
    ];

    private array $consumes = [
        'Room' => ['Hotel'],
        'Reservation' => ['Booker'],
    ];

    #[Test]
    public function itIncludesAllContexts(): void
    {
        $result = (new ContextMapBuilder())->build($this->contracts, $this->consumes);

        self::assertArrayHasKey('Booker', $result['contexts']);
        self::assertArrayHasKey('Hotel', $result['contexts']);
        self::assertArrayHasKey('Room', $result['contexts']);
        self::assertArrayHasKey('Reservation', $result['contexts']);
    }

    #[Test]
    public function itSetsOpenHostServices(): void
    {
        $result = (new ContextMapBuilder())->build($this->contracts, $this->consumes);

        self::assertSame(
            ['App\\Booker\\Application\\Contract\\BookerFinderInterface'],
            $result['contexts']['Booker']['open_host_services']['interfaces']
        );
    }

    #[Test]
    public function itBuildsConsumedByFromConsumes(): void
    {
        $result = (new ContextMapBuilder())->build($this->contracts, $this->consumes);

        self::assertContains('Reservation', $result['contexts']['Booker']['consumed_by']);
        self::assertContains('Room', $result['contexts']['Hotel']['consumed_by']);
    }

    #[Test]
    public function itBuildsConsumesEntries(): void
    {
        $result = (new ContextMapBuilder())->build($this->contracts, $this->consumes);

        self::assertSame([['context' => 'Hotel']], $result['contexts']['Room']['consumes']);
    }

    #[Test]
    public function itSetsEmptyOpenHostServicesForContextsWithoutContracts(): void
    {
        $result = (new ContextMapBuilder())->build($this->contracts, $this->consumes);

        self::assertSame([], $result['contexts']['Room']['open_host_services']['interfaces']);
        self::assertSame([], $result['contexts']['Room']['open_host_services']['published_language']);
    }

    #[Test]
    public function itSetsVersionAndGeneratedAt(): void
    {
        $result = (new ContextMapBuilder())->build($this->contracts, $this->consumes);

        self::assertSame('1.0', $result['version']);
        self::assertArrayHasKey('generated_at', $result);
    }
}
