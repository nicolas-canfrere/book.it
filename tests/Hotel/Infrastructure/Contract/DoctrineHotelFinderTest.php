<?php

declare(strict_types=1);

namespace Tests\Hotel\Infrastructure\Contract;

use App\Hotel\Application\Contract\HotelFinderInterface;
use App\Hotel\Application\Contract\HotelView;
use App\Hotel\Domain\Model\Address;
use App\Hotel\Domain\Model\Hotel;
use App\Hotel\Domain\Port\HotelPublicReaderInterface;
use App\Hotel\Infrastructure\Contract\DoctrineHotelFinder;
use App\Shared\Domain\ValueObject\HotelId;
use App\Shared\Domain\ValueObject\OrganizationId;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class DoctrineHotelFinderTest extends TestCase
{
    private HotelPublicReaderInterface&Stub $publicReader;
    private HotelFinderInterface $finder;

    protected function setUp(): void
    {
        $this->publicReader = $this->createStub(HotelPublicReaderInterface::class);
        $this->finder = new DoctrineHotelFinder($this->publicReader);
    }

    #[Test]
    public function itReturnsNullWhenHotelDoesNotExist(): void
    {
        $this->publicReader->method('get')->willReturn(null);

        self::assertNull($this->finder->find(new HotelId('unknown')));
    }

    #[Test]
    public function itReturnsHotelViewWhenHotelExists(): void
    {
        $hotel = new Hotel(
            id: new HotelId('hotel-1'),
            name: 'Test Hotel',
            address: new Address('1 rue Test', '75001', 'Paris', 'FR'),
            createdAt: new \DateTimeImmutable(),
            organizationId: new OrganizationId('00000000-0000-0000-0000-000000000001'),
        );
        $this->publicReader->method('get')->willReturn($hotel);

        $view = $this->finder->find(new HotelId('hotel-1'));

        self::assertInstanceOf(HotelView::class, $view);
        self::assertSame('hotel-1', $view->id);
    }
}
