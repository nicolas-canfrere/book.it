<?php

declare(strict_types=1);

namespace App\Tests\Hotel\Application\UseCase\ClassifyHotel;

use App\Hotel\Application\UseCase\ClassifyHotel\ClassifyHotelCommand;
use App\Hotel\Application\UseCase\ClassifyHotel\ClassifyHotelCommandHandler;
use App\Hotel\Domain\Model\Address;
use App\Hotel\Domain\Model\Hotel;
use App\Hotel\Domain\Port\HotelRepositoryInterface;
use App\Shared\Domain\Event\StarRatingClassified;
use App\Tests\Fake\FakeEventDispatcher;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class ClassifyHotelCommandHandlerTest extends TestCase
{
    private Hotel $hotel;

    protected function setUp(): void
    {
        $this->hotel = new Hotel(
            id: 'hotel-id-1',
            name: 'Le Grand Hôtel',
            address: new Address('1 rue de la Paix', '75001', 'Paris', 'FR'),
            createdAt: new \DateTimeImmutable('2026-01-01T00:00:00Z'),
        );
    }

    #[Test]
    public function itDispatchesStarRatingClassifiedWithStars(): void
    {
        $repository = $this->createMock(HotelRepositoryInterface::class);
        $dispatcher = new FakeEventDispatcher();

        $repository->method('get')->willReturn($this->hotel);

        $handler = new ClassifyHotelCommandHandler($repository, $dispatcher);
        ($handler)(new ClassifyHotelCommand(hotelId: 'hotel-id-1', stars: 4, superior: false));

        $event = $dispatcher->getLastDispatched();
        self::assertInstanceOf(StarRatingClassified::class, $event);
        self::assertSame('hotel-id-1', $event->hotelId);
        self::assertSame(4, $event->starRating);
    }

    #[Test]
    public function itDispatchesNullStarRatingWhenRatingRemoved(): void
    {
        $repository = $this->createMock(HotelRepositoryInterface::class);
        $dispatcher = new FakeEventDispatcher();

        $repository->method('get')->willReturn($this->hotel);

        $handler = new ClassifyHotelCommandHandler($repository, $dispatcher);
        ($handler)(new ClassifyHotelCommand(hotelId: 'hotel-id-1', stars: null));

        $event = $dispatcher->getLastDispatched();
        self::assertInstanceOf(StarRatingClassified::class, $event);
        self::assertSame('hotel-id-1', $event->hotelId);
        self::assertNull($event->starRating);
    }

    #[Test]
    public function itDoesNotDispatchWhenHotelNotFound(): void
    {
        $repository = $this->createMock(HotelRepositoryInterface::class);
        $dispatcher = new FakeEventDispatcher();

        $repository->method('get')->willReturn(null);

        $handler = new ClassifyHotelCommandHandler($repository, $dispatcher);

        $this->expectException(\App\Hotel\Domain\Exception\HotelNotFoundException::class);
        ($handler)(new ClassifyHotelCommand(hotelId: 'missing-id', stars: 3));

        self::assertEmpty($dispatcher->getDispatched());
    }
}
