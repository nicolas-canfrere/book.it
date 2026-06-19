<?php

declare(strict_types=1);

namespace App\Tests\Reservation\Application\UseCase\ExpireReservation;

use App\Reservation\Application\UseCase\ExpireReservation\ExpireReservationCommand;
use App\Reservation\Application\UseCase\ExpireReservation\ExpireReservationCommandHandler;
use App\Reservation\Domain\Model\Reservation;
use App\Reservation\Domain\Model\ReservationStatus;
use App\Reservation\Domain\Port\ReservationRepositoryInterface;
use App\Reservation\Domain\ValueObject\CancellationTerms;
use App\Reservation\Domain\ValueObject\DatePeriod;
use App\Reservation\Domain\ValueObject\GuestCount;
use App\Reservation\Domain\ValueObject\PriceBreakdown;
use App\Shared\Domain\Event\ReservationExpired;
use App\Shared\Domain\ValueObject\BookerId;
use App\Shared\Domain\ValueObject\ReservationId;
use App\Shared\Domain\ValueObject\RoomId;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

#[Group('unit')]
final class ExpireReservationCommandHandlerTest extends TestCase
{
    private MockObject&ReservationRepositoryInterface $repository;
    private MockObject&EventDispatcherInterface $eventDispatcher;
    private ExpireReservationCommandHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(ReservationRepositoryInterface::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->handler = new ExpireReservationCommandHandler($this->repository, $this->eventDispatcher);
    }

    #[Test]
    public function itExpiresPendingReservationAndDispatchesEvent(): void
    {
        $reservation = $this->makePendingReservation();
        $this->repository->method('get')->willReturn($reservation);
        $this->repository->expects(self::once())->method('save')->with($reservation);
        $this->eventDispatcher->expects(self::once())->method('dispatch')
            ->with(self::isInstanceOf(ReservationExpired::class));

        ($this->handler)(new ExpireReservationCommand('res-uuid'));

        self::assertSame(ReservationStatus::Expired, $reservation->status);
    }

    #[Test]
    public function itIsNoopWhenReservationNotFound(): void
    {
        $this->repository->method('get')->willReturn(null);
        $this->repository->expects(self::never())->method('save');
        $this->eventDispatcher->expects(self::never())->method('dispatch');

        ($this->handler)(new ExpireReservationCommand('res-uuid'));
    }

    #[Test]
    public function itIsNoopWhenReservationAlreadyConfirmed(): void
    {
        $reservation = $this->makePendingReservation();
        $reservation->status = ReservationStatus::Confirmed;
        $this->repository->method('get')->willReturn($reservation);
        $this->repository->expects(self::never())->method('save');
        $this->eventDispatcher->expects(self::never())->method('dispatch');

        ($this->handler)(new ExpireReservationCommand('res-uuid'));
    }

    #[Test]
    public function itIsNoopWhenReservationAlreadyExpired(): void
    {
        $reservation = $this->makePendingReservation();
        $reservation->status = ReservationStatus::Expired;
        $this->repository->method('get')->willReturn($reservation);
        $this->repository->expects(self::never())->method('save');
        $this->eventDispatcher->expects(self::never())->method('dispatch');

        ($this->handler)(new ExpireReservationCommand('res-uuid'));
    }

    private function makePendingReservation(): Reservation
    {
        return new Reservation(
            id: new ReservationId('res-uuid'),
            roomId: new RoomId('room-uuid'),
            bookerId: new BookerId('booker-uuid'),
            period: new DatePeriod(
                new \DateTimeImmutable('2030-06-01'),
                new \DateTimeImmutable('2030-06-05'),
            ),
            totalPrice: 40000,
            cancellationTerms: CancellationTerms::alwaysRefundable(),
            priceBreakdown: new PriceBreakdown([]),
            guestCount: new GuestCount(1),
            createdAt: new \DateTimeImmutable(),
        );
    }
}
