<?php

declare(strict_types=1);

namespace App\Tests\Hotel\Application\UseCase\DeclareHotelAmenities;

use App\Hotel\Application\UseCase\DeclareHotelAmenities\DeclareHotelAmenitiesCommand;
use App\Hotel\Application\UseCase\DeclareHotelAmenities\DeclareHotelAmenitiesCommandHandler;
use App\Hotel\Domain\Exception\HotelNotFoundException;
use App\Hotel\Domain\Model\Address;
use App\Hotel\Domain\Model\Hotel;
use App\Hotel\Domain\Port\HotelRepositoryInterface;
use App\Hotel\Domain\ValueObject\HotelAmenity;
use App\Shared\Domain\Event\HotelAmenityDeclared;
use App\Tests\Fake\FakeEventDispatcher;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class DeclareHotelAmenitiesCommandHandlerTest extends TestCase
{
    private FakeEventDispatcher $dispatcher;

    protected function setUp(): void
    {
        $this->dispatcher = new FakeEventDispatcher();
    }

    #[Test]
    public function itThrowsWhenHotelNotFound(): void
    {
        $repository = $this->createStub(HotelRepositoryInterface::class);
        $repository->method('get')->willReturn(null);

        $handler = new DeclareHotelAmenitiesCommandHandler($repository, $this->dispatcher);

        try {
            ($handler)(new DeclareHotelAmenitiesCommand('unknown-id', []));
            self::fail('Expected HotelNotFoundException was not thrown');
        } catch (HotelNotFoundException) {
            // expected
        }

        self::assertEmpty($this->dispatcher->getDispatched());
    }

    #[Test]
    public function itSavesDeclaredAmenities(): void
    {
        /** @var HotelRepositoryInterface&MockObject $repository */
        $repository = $this->createMock(HotelRepositoryInterface::class);
        $hotel = new Hotel(
            'hotel-id',
            'Test Hotel',
            new Address('1 rue Test', '75001', 'Paris', 'FR'),
            new \DateTimeImmutable(),
        );
        $repository->method('get')->willReturn($hotel);
        $repository
            ->expects(self::once())
            ->method('save')
            ->with(self::callback(
                static fn(Hotel $h) => $h->amenities === [HotelAmenity::Pool, HotelAmenity::Gym]
            ));

        $handler = new DeclareHotelAmenitiesCommandHandler($repository, $this->dispatcher);

        ($handler)(new DeclareHotelAmenitiesCommand('hotel-id', ['pool', 'gym']));

        $event = $this->dispatcher->getLastDispatched();
        self::assertInstanceOf(HotelAmenityDeclared::class, $event);
        self::assertSame('hotel-id', $event->hotelId);
        self::assertSame(['pool', 'gym'], $event->amenities);
    }

    #[Test]
    public function itSavesEmptyList(): void
    {
        /** @var HotelRepositoryInterface&MockObject $repository */
        $repository = $this->createMock(HotelRepositoryInterface::class);
        $hotel = new Hotel(
            'hotel-id',
            'Test Hotel',
            new Address('1 rue Test', '75001', 'Paris', 'FR'),
            new \DateTimeImmutable(),
            amenities: [HotelAmenity::Pool],
        );
        $repository->method('get')->willReturn($hotel);
        $repository
            ->expects(self::once())
            ->method('save')
            ->with(self::callback(
                static fn(Hotel $h) => [] === $h->amenities
            ));

        $handler = new DeclareHotelAmenitiesCommandHandler($repository, $this->dispatcher);

        ($handler)(new DeclareHotelAmenitiesCommand('hotel-id', []));

        $event = $this->dispatcher->getLastDispatched();
        self::assertInstanceOf(HotelAmenityDeclared::class, $event);
        self::assertSame('hotel-id', $event->hotelId);
        self::assertSame([], $event->amenities);
    }
}
