<?php

declare(strict_types=1);

namespace App\Tests\Reservation\Integration\UseCase\CheckIn;

use App\Reservation\Application\UseCase\CheckIn\CheckInCommand;
use App\Reservation\Application\UseCase\CheckIn\CheckInCommandHandler;
use App\Reservation\Domain\Exception\CheckInNotAllowedException;
use App\Reservation\Domain\Exception\ReservationNotFoundException;
use App\Reservation\Domain\Model\Reservation;
use App\Reservation\Domain\Model\ReservationStatus;
use App\Reservation\Domain\ValueObject\CancellationTerms;
use App\Reservation\Domain\ValueObject\DatePeriod;
use App\Reservation\Domain\ValueObject\GuestCount;
use App\Reservation\Domain\ValueObject\PriceBreakdown;
use App\Shared\Domain\ValueObject\BookerId;
use App\Shared\Domain\ValueObject\ReservationId;
use App\Shared\Domain\ValueObject\RoomId;
use App\Tests\Reservation\Infrastructure\Persistence\InMemory\InMemoryReservationRepository;
use App\Tests\Reservation\Infrastructure\Service\SequentialGuestIdGenerator;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('integration')]
final class CheckInCommandHandlerTest extends KernelTestCase
{
    private CheckInCommandHandler $handler;
    private InMemoryReservationRepository $repository;

    protected function setUp(): void
    {
        $this->repository = new InMemoryReservationRepository();
        $this->handler = new CheckInCommandHandler(
            $this->repository,
            new SequentialGuestIdGenerator(),
        );
    }

    #[Test]
    public function itChecksInAndTransitionsStatus(): void
    {
        $reservation = $this->makeConfirmedReservation();
        $this->repository->add($reservation);

        ($this->handler)(new CheckInCommand(
            reservationId: 'res-1',
            guests: [
                ['firstName' => 'Alice', 'lastName' => 'Smith', 'dateOfBirth' => '1990-01-15'],
            ],
            today: new \DateTimeImmutable('2026-07-01'),
        ));

        $saved = $this->repository->get(new ReservationId('res-1'));
        self::assertNotNull($saved);
        self::assertSame(ReservationStatus::CheckedIn, $saved->status);
        self::assertCount(1, $saved->guests);
        self::assertSame('Alice', $saved->guests[0]->firstName);
        self::assertSame('1990-01-15', $saved->guests[0]->dateOfBirth->format('Y-m-d'));
    }

    #[Test]
    public function itThrowsWhenReservationNotFound(): void
    {
        $this->expectException(ReservationNotFoundException::class);
        ($this->handler)(new CheckInCommand(
            reservationId: 'does-not-exist',
            guests: [],
            today: new \DateTimeImmutable('2026-07-01'),
        ));
    }

    #[Test]
    public function itThrowsWhenCheckInNotAllowed(): void
    {
        $reservation = $this->makeConfirmedReservation();
        $this->repository->add($reservation);

        $this->expectException(CheckInNotAllowedException::class);
        ($this->handler)(new CheckInCommand(
            reservationId: 'res-1',
            guests: [],
            today: new \DateTimeImmutable('2026-06-30'), // day before check-in
        ));
    }

    private function makeConfirmedReservation(string $id = 'res-1'): Reservation
    {
        $reservation = new Reservation(
            id: new ReservationId($id),
            roomId: new RoomId('room-1'),
            bookerId: new BookerId('booker-1'),
            period: new DatePeriod(
                new \DateTimeImmutable('2026-07-01'),
                new \DateTimeImmutable('2026-07-03'),
            ),
            totalPrice: 10000,
            cancellationTerms: CancellationTerms::alwaysRefundable(),
            priceBreakdown: PriceBreakdown::fromArray([
                ['date' => '2026-07-01', 'rateAmountCents' => 5000, 'discountPercent' => null, 'effectiveAmountCents' => 5000],
                ['date' => '2026-07-02', 'rateAmountCents' => 5000, 'discountPercent' => null, 'effectiveAmountCents' => 5000],
            ]),
            guestCount: new GuestCount(2),
            createdAt: new \DateTimeImmutable('2026-06-01'),
        );
        $reservation->status = ReservationStatus::Confirmed;

        return $reservation;
    }
}
