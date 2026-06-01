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
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class DeclareHotelAmenitiesCommandHandlerTest extends TestCase
{
    private HotelRepositoryInterface&MockObject $repository;
    private FakeEventDispatcher $dispatcher;
    private DeclareHotelAmenitiesCommandHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(HotelRepositoryInterface::class);
        $this->dispatcher = new FakeEventDispatcher();
        $this->handler = new DeclareHotelAmenitiesCommandHandler($this->repository, $this->dispatcher);
    }

    public function test_throws_when_hotel_not_found(): void
    {
        $this->repository->method('get')->willReturn(null);

        try {
            ($this->handler)(new DeclareHotelAmenitiesCommand('unknown-id', []));
            self::fail('Expected HotelNotFoundException was not thrown');
        } catch (HotelNotFoundException) {
            // expected
        }

        self::assertEmpty($this->dispatcher->getDispatched());
    }

    public function test_saves_declared_amenities(): void
    {
        $hotel = new Hotel(
            'hotel-id',
            'Test Hotel',
            new Address('1 rue Test', '75001', 'Paris', 'FR'),
            new \DateTimeImmutable(),
        );
        $this->repository->method('get')->willReturn($hotel);
        $this->repository
            ->expects(self::once())
            ->method('save')
            ->with(self::callback(
                static fn(Hotel $h) => $h->amenities === [HotelAmenity::Pool, HotelAmenity::Gym]
            ));

        ($this->handler)(new DeclareHotelAmenitiesCommand('hotel-id', ['pool', 'gym']));

        $event = $this->dispatcher->getLastDispatched();
        self::assertInstanceOf(HotelAmenityDeclared::class, $event);
        self::assertSame('hotel-id', $event->hotelId);
        self::assertSame(['pool', 'gym'], $event->amenities);
    }

    public function test_saves_empty_list(): void
    {
        $hotel = new Hotel(
            'hotel-id',
            'Test Hotel',
            new Address('1 rue Test', '75001', 'Paris', 'FR'),
            new \DateTimeImmutable(),
            amenities: [HotelAmenity::Pool],
        );
        $this->repository->method('get')->willReturn($hotel);
        $this->repository
            ->expects(self::once())
            ->method('save')
            ->with(self::callback(
                static fn(Hotel $h) => [] === $h->amenities
            ));

        ($this->handler)(new DeclareHotelAmenitiesCommand('hotel-id', []));

        $event = $this->dispatcher->getLastDispatched();
        self::assertInstanceOf(HotelAmenityDeclared::class, $event);
        self::assertSame('hotel-id', $event->hotelId);
        self::assertSame([], $event->amenities);
    }
}
