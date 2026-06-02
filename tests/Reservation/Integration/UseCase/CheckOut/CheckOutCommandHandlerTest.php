<?php

declare(strict_types=1);

namespace App\Tests\Reservation\Integration\UseCase\CheckOut;

use App\Reservation\Application\UseCase\CheckOut\CheckOutCommand;
use App\Reservation\Application\UseCase\CheckOut\CheckOutCommandHandler;
use App\Reservation\Domain\Exception\ReservationNotFoundException;
use App\Reservation\Domain\Model\Reservation;
use App\Reservation\Domain\Model\ReservationStatus;
use App\Reservation\Domain\ValueObject\CancellationTerms;
use App\Reservation\Domain\ValueObject\DatePeriod;
use App\Reservation\Domain\ValueObject\GuestCount;
use App\Reservation\Domain\ValueObject\PriceBreakdown;
use App\Shared\Domain\Event\ReservationCheckedOut;
use App\Tests\Fake\FakeEventDispatcher;
use App\Tests\Reservation\Infrastructure\Persistence\InMemory\InMemoryReservationRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('integration')]
final class CheckOutCommandHandlerTest extends KernelTestCase
{
    private FakeEventDispatcher $eventDispatcher;
    private InMemoryReservationRepository $repository;
    private CheckOutCommandHandler $handler;

    protected function setUp(): void
    {
        $this->eventDispatcher = new FakeEventDispatcher();
        $this->repository = new InMemoryReservationRepository();
        $this->handler = new CheckOutCommandHandler($this->repository, $this->eventDispatcher);
    }

    #[Test]
    public function itTransitionsReservationToCheckedOutAndDispatchesEvent(): void
    {
        $checkIn = new \DateTimeImmutable('2025-06-10');
        $checkOut = new \DateTimeImmutable('2025-06-15');
        $reservation = $this->makeCheckedInReservation('res-uuid', 'room-uuid', 'booker-uuid', $checkIn, $checkOut);
        $this->repository->add($reservation);

        ($this->handler)(new CheckOutCommand(
            reservationId: 'res-uuid',
            actualDepartureDate: new \DateTimeImmutable('2025-06-13'),
        ));

        $saved = $this->repository->get('res-uuid');
        self::assertNotNull($saved);
        self::assertSame(ReservationStatus::CheckedOut, $saved->status);

        $dispatched = $this->eventDispatcher->getDispatched();
        self::assertCount(1, $dispatched);

        $event = $dispatched[0];
        self::assertInstanceOf(ReservationCheckedOut::class, $event);
        self::assertSame('res-uuid', $event->reservationId);
        self::assertSame('room-uuid', $event->roomId);
        self::assertSame('booker-uuid', $event->bookerId);
        self::assertEquals($checkIn, $event->checkIn);
        self::assertEquals($checkOut, $event->checkOut);
        self::assertEquals(new \DateTimeImmutable('2025-06-13'), $event->actualDepartureDate);
    }

    #[Test]
    public function itThrowsWhenReservationNotFound(): void
    {
        $this->expectException(ReservationNotFoundException::class);
        ($this->handler)(new CheckOutCommand(
            reservationId: 'non-existent',
            actualDepartureDate: new \DateTimeImmutable('2025-06-13'),
        ));
    }

    private function makeCheckedInReservation(
        string $id,
        string $roomId,
        string $bookerId,
        \DateTimeImmutable $checkIn,
        \DateTimeImmutable $checkOut,
    ): Reservation {
        $reservation = new Reservation(
            id: $id,
            roomId: $roomId,
            bookerId: $bookerId,
            period: new DatePeriod($checkIn, $checkOut),
            totalPrice: 50000,
            cancellationTerms: CancellationTerms::alwaysRefundable(),
            priceBreakdown: new PriceBreakdown([]),
            guestCount: new GuestCount(1),
            createdAt: new \DateTimeImmutable('2025-06-01'),
        );
        $reservation->confirm();
        $reservation->checkIn([], $checkIn);

        return $reservation;
    }
}
