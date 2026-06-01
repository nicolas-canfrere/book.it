<?php

declare(strict_types=1);

namespace App\Tests\Hotel\Application\UseCase\RegisterHotel;

use App\Hotel\Application\UseCase\RegisterHotel\RegisterHotelCommand;
use App\Hotel\Application\UseCase\RegisterHotel\RegisterHotelCommandHandler;
use App\Hotel\Domain\Model\Address;
use App\Hotel\Domain\Port\HotelRepositoryInterface;
use App\Shared\Domain\Event\HotelRegistered;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

#[Group('unit')]
final class RegisterHotelCommandHandlerTest extends TestCase
{
    #[Test]
    public function itDispatchesHotelRegisteredOnSuccess(): void
    {
        $repository = $this->createMock(HotelRepositoryInterface::class);
        $dispatcher = $this->createMock(EventDispatcherInterface::class);

        $repository->method('existsByNameAndAddress')->willReturn(false);

        $dispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(static function (object $event): bool {
                return $event instanceof HotelRegistered
                    && 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11' === $event->hotelId
                    && 'Le Grand Hôtel' === $event->name
                    && 'Paris' === $event->city
                    && 'FR' === $event->country
                    && null === $event->starRating;
            }));

        $handler = new RegisterHotelCommandHandler($repository, $dispatcher);

        ($handler)(new RegisterHotelCommand(
            id: 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11',
            name: 'Le Grand Hôtel',
            address: new Address('1 rue de la Paix', '75001', 'Paris', 'FR'),
            createdAt: new \DateTimeImmutable('2026-01-01T00:00:00Z'),
        ));
    }

    #[Test]
    public function itDispatchesStarRatingWhenProvided(): void
    {
        $repository = $this->createMock(HotelRepositoryInterface::class);
        $dispatcher = $this->createMock(EventDispatcherInterface::class);

        $repository->method('existsByNameAndAddress')->willReturn(false);

        $dispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(static function (object $event): bool {
                return $event instanceof HotelRegistered && 4 === $event->starRating;
            }));

        $handler = new RegisterHotelCommandHandler($repository, $dispatcher);

        ($handler)(new RegisterHotelCommand(
            id: 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a12',
            name: 'Luxury Palace',
            address: new Address('5 avenue Foch', '75016', 'Paris', 'FR'),
            createdAt: new \DateTimeImmutable('2026-01-01T00:00:00Z'),
            starRating: new \App\Hotel\Domain\ValueObject\StarRating(4, false),
        ));
    }

    #[Test]
    public function itDoesNotDispatchWhenHotelAlreadyExists(): void
    {
        $repository = $this->createMock(HotelRepositoryInterface::class);
        $dispatcher = $this->createMock(EventDispatcherInterface::class);

        $repository->method('existsByNameAndAddress')->willReturn(true);

        $dispatcher->expects($this->never())->method('dispatch');

        $handler = new RegisterHotelCommandHandler($repository, $dispatcher);

        $this->expectException(\App\Hotel\Domain\Exception\HotelAlreadyExistsException::class);

        ($handler)(new RegisterHotelCommand(
            id: 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a13',
            name: 'Le Grand Hôtel',
            address: new Address('1 rue de la Paix', '75001', 'Paris', 'FR'),
            createdAt: new \DateTimeImmutable('2026-01-01T00:00:00Z'),
        ));
    }
}
