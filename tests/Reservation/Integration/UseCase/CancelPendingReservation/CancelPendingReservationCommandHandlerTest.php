<?php

declare(strict_types=1);

namespace App\Tests\Reservation\Integration\UseCase\CancelPendingReservation;

use App\Reservation\Application\UseCase\CancelPendingReservation\CancelPendingReservationCommand;
use App\Reservation\Application\UseCase\CancelPendingReservation\CancelPendingReservationCommandHandler;
use App\Reservation\Domain\Model\Reservation;
use App\Reservation\Domain\Model\ReservationPage;
use App\Reservation\Domain\Model\ReservationStatus;
use App\Reservation\Domain\Port\ReservationRepositoryInterface;
use App\Reservation\Domain\ValueObject\CancellationTerms;
use App\Reservation\Domain\ValueObject\DatePeriod;
use App\Reservation\Domain\ValueObject\GuestCount;
use App\Reservation\Domain\ValueObject\PriceBreakdown;
use App\Shared\Domain\Event\ReservationPaymentCancelled;
use App\Shared\Domain\ValueObject\ReservationId;
use App\Shared\Domain\ValueObject\RoomId;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

#[Group('integration')]
final class CancelPendingReservationCommandHandlerTest extends KernelTestCase
{
    #[Test]
    public function itCancelsPendingReservationAndDispatchesEvent(): void
    {
        $reservation = new Reservation(
            id: new ReservationId('res-001'),
            roomId: new RoomId('room-001'),
            bookerId: 'booker-001',
            period: new DatePeriod(
                new \DateTimeImmutable('2026-07-01'),
                new \DateTimeImmutable('2026-07-05'),
            ),
            totalPrice: 40000,
            cancellationTerms: CancellationTerms::alwaysRefundable(),
            priceBreakdown: new PriceBreakdown([]),
            guestCount: new GuestCount(1),
            createdAt: new \DateTimeImmutable(),
        );

        $repository = new InMemoryReservationRepository($reservation);
        $dispatcher = new EventDispatcher();
        $dispatchedEvents = [];
        $dispatcher->addListener(ReservationPaymentCancelled::class, function (ReservationPaymentCancelled $e) use (&$dispatchedEvents): void {
            $dispatchedEvents[] = $e;
        });

        $handler = new CancelPendingReservationCommandHandler($repository, $dispatcher);
        ($handler)(new CancelPendingReservationCommand('res-001'));

        self::assertSame(ReservationStatus::Cancelled, $reservation->status);
        self::assertCount(1, $dispatchedEvents);
        self::assertSame('res-001', $dispatchedEvents[0]->reservationId);
    }

    #[Test]
    public function itIsIdempotentIfReservationNotPending(): void
    {
        $repository = new InMemoryReservationRepository(null);
        $dispatcher = new EventDispatcher();

        $handler = new CancelPendingReservationCommandHandler($repository, $dispatcher);
        ($handler)(new CancelPendingReservationCommand('unknown'));

        $this->addToAssertionCount(1);
    }
}

final class InMemoryReservationRepository implements ReservationRepositoryInterface
{
    public function __construct(private ?Reservation $reservation)
    {
    }

    public function get(ReservationId $id): ?Reservation
    {
        return $this->reservation?->id->value === $id->value ? $this->reservation : null;
    }

    public function add(Reservation $reservation): void
    {
    }

    public function save(Reservation $reservation): void
    {
    }

    public function listByBooker(string $bookerId, int $page, int $limit): ReservationPage
    {
        return new ReservationPage([], 0);
    }
}
