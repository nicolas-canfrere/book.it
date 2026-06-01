<?php

declare(strict_types=1);

namespace App\Tests\Hotel\Application\UseCase\ClassifyHotel;

use App\Hotel\Application\UseCase\ClassifyHotel\ClassifyHotelCommand;
use App\Hotel\Application\UseCase\ClassifyHotel\ClassifyHotelCommandHandler;
use App\Hotel\Domain\Model\Address;
use App\Hotel\Domain\Model\Hotel;
use App\Hotel\Domain\Port\HotelRepositoryInterface;
use App\Shared\Domain\Event\StarRatingClassified;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

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
        $dispatcher = $this->createMock(EventDispatcherInterface::class);

        $repository->method('get')->willReturn($this->hotel);

        $dispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(static function (object $event): bool {
                return $event instanceof StarRatingClassified
                    && 'hotel-id-1' === $event->hotelId
                    && 4 === $event->starRating;
            }));

        $handler = new ClassifyHotelCommandHandler($repository, $dispatcher);

        ($handler)(new ClassifyHotelCommand(hotelId: 'hotel-id-1', stars: 4, superior: false));
    }

    #[Test]
    public function itDispatchesNullStarRatingWhenRatingRemoved(): void
    {
        $repository = $this->createMock(HotelRepositoryInterface::class);
        $dispatcher = $this->createMock(EventDispatcherInterface::class);

        $repository->method('get')->willReturn($this->hotel);

        $dispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(static function (object $event): bool {
                return $event instanceof StarRatingClassified
                    && 'hotel-id-1' === $event->hotelId
                    && null === $event->starRating;
            }));

        $handler = new ClassifyHotelCommandHandler($repository, $dispatcher);

        ($handler)(new ClassifyHotelCommand(hotelId: 'hotel-id-1', stars: null));
    }

    #[Test]
    public function itDoesNotDispatchWhenHotelNotFound(): void
    {
        $repository = $this->createMock(HotelRepositoryInterface::class);
        $dispatcher = $this->createMock(EventDispatcherInterface::class);

        $repository->method('get')->willReturn(null);
        $dispatcher->expects($this->never())->method('dispatch');

        $handler = new ClassifyHotelCommandHandler($repository, $dispatcher);

        $this->expectException(\App\Hotel\Domain\Exception\HotelNotFoundException::class);

        ($handler)(new ClassifyHotelCommand(hotelId: 'missing-id', stars: 3));
    }
}
