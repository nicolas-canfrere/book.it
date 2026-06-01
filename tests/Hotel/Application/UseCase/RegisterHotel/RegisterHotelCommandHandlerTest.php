<?php

declare(strict_types=1);

namespace App\Tests\Hotel\Application\UseCase\RegisterHotel;

use App\Hotel\Application\UseCase\RegisterHotel\RegisterHotelCommand;
use App\Hotel\Application\UseCase\RegisterHotel\RegisterHotelCommandHandler;
use App\Hotel\Domain\Model\Address;
use App\Hotel\Domain\Port\HotelRepositoryInterface;
use App\Shared\Domain\Event\HotelRegistered;
use App\Tests\Fake\FakeEventDispatcher;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class RegisterHotelCommandHandlerTest extends TestCase
{
    #[Test]
    public function itDispatchesHotelRegisteredOnSuccess(): void
    {
        $repository = $this->createMock(HotelRepositoryInterface::class);
        $dispatcher = new FakeEventDispatcher();

        $repository->method('existsByNameAndAddress')->willReturn(false);

        $handler = new RegisterHotelCommandHandler($repository, $dispatcher);
        ($handler)(new RegisterHotelCommand(
            id: 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11',
            name: 'Le Grand Hôtel',
            address: new Address('1 rue de la Paix', '75001', 'Paris', 'FR'),
            createdAt: new \DateTimeImmutable('2026-01-01T00:00:00Z'),
        ));

        $event = $dispatcher->getLastDispatched();
        self::assertInstanceOf(HotelRegistered::class, $event);
        self::assertSame('a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11', $event->hotelId);
        self::assertSame('Le Grand Hôtel', $event->name);
        self::assertSame('Paris', $event->city);
        self::assertSame('FR', $event->country);
        self::assertNull($event->starRating);
    }

    #[Test]
    public function itDispatchesStarRatingWhenProvided(): void
    {
        $repository = $this->createMock(HotelRepositoryInterface::class);
        $dispatcher = new FakeEventDispatcher();

        $repository->method('existsByNameAndAddress')->willReturn(false);

        $handler = new RegisterHotelCommandHandler($repository, $dispatcher);
        ($handler)(new RegisterHotelCommand(
            id: 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a12',
            name: 'Luxury Palace',
            address: new Address('5 avenue Foch', '75016', 'Paris', 'FR'),
            createdAt: new \DateTimeImmutable('2026-01-01T00:00:00Z'),
            starRating: new \App\Hotel\Domain\ValueObject\StarRating(4, false),
        ));

        $event = $dispatcher->getLastDispatched();
        self::assertInstanceOf(HotelRegistered::class, $event);
        self::assertSame(4, $event->starRating);
    }

    #[Test]
    public function itDoesNotDispatchWhenHotelAlreadyExists(): void
    {
        $repository = $this->createMock(HotelRepositoryInterface::class);
        $dispatcher = new FakeEventDispatcher();

        $repository->method('existsByNameAndAddress')->willReturn(true);

        $handler = new RegisterHotelCommandHandler($repository, $dispatcher);

        try {
            ($handler)(new RegisterHotelCommand(
                id: 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a13',
                name: 'Le Grand Hôtel',
                address: new Address('1 rue de la Paix', '75001', 'Paris', 'FR'),
                createdAt: new \DateTimeImmutable('2026-01-01T00:00:00Z'),
            ));
            self::fail('Expected HotelAlreadyExistsException was not thrown');
        } catch (\App\Hotel\Domain\Exception\HotelAlreadyExistsException) {
            // expected
        }

        self::assertEmpty($dispatcher->getDispatched());
    }
}
