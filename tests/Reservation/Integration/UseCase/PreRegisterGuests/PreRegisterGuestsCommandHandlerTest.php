<?php

declare(strict_types=1);

namespace App\Tests\Reservation\Integration\UseCase\PreRegisterGuests;

use App\Reservation\Application\UseCase\PreRegisterGuests\PreRegisterGuestsCommand;
use App\Reservation\Application\UseCase\PreRegisterGuests\PreRegisterGuestsCommandHandler;
use App\Reservation\Domain\Exception\GuestPreRegistrationNotAllowedException;
use App\Reservation\Domain\Exception\ReservationNotFoundException;
use App\Reservation\Domain\Model\Reservation;
use App\Reservation\Domain\Model\ReservationStatus;
use App\Reservation\Domain\ValueObject\CancellationTerms;
use App\Reservation\Domain\ValueObject\DatePeriod;
use App\Reservation\Domain\ValueObject\GuestCount;
use App\Reservation\Domain\ValueObject\PriceBreakdown;
use App\Tests\Reservation\Infrastructure\Persistence\InMemory\InMemoryReservationRepository;
use App\Tests\Reservation\Infrastructure\Service\SequentialGuestIdGenerator;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('integration')]
final class PreRegisterGuestsCommandHandlerTest extends KernelTestCase
{
    private PreRegisterGuestsCommandHandler $handler;
    private InMemoryReservationRepository $repository;

    protected function setUp(): void
    {
        $this->repository = new InMemoryReservationRepository();
        $this->handler = new PreRegisterGuestsCommandHandler(
            $this->repository,
            new SequentialGuestIdGenerator(),
        );
    }

    public function test_pre_registers_guests_on_confirmed_reservation(): void
    {
        $reservation = $this->makeConfirmedReservation();
        $this->repository->add($reservation);

        ($this->handler)(new PreRegisterGuestsCommand(
            reservationId: 'res-1',
            guests: [
                ['firstName' => 'Alice', 'lastName' => 'Smith', 'dateOfBirth' => '1990-01-15'],
                ['firstName' => 'Bob', 'lastName' => 'Jones', 'dateOfBirth' => '1992-03-20'],
            ],
            today: new \DateTimeImmutable('2026-06-15'),
        ));

        $saved = $this->repository->get('res-1');
        self::assertNotNull($saved);
        self::assertCount(2, $saved->guests);
        self::assertSame('Alice', $saved->guests[0]->firstName);
        self::assertSame('Smith', $saved->guests[0]->lastName);
        self::assertSame('1990-01-15', $saved->guests[0]->dateOfBirth->format('Y-m-d'));
        self::assertSame('Bob', $saved->guests[1]->firstName);
    }

    public function test_throws_when_reservation_not_found(): void
    {
        $this->expectException(ReservationNotFoundException::class);
        ($this->handler)(new PreRegisterGuestsCommand(
            reservationId: 'does-not-exist',
            guests: [],
            today: new \DateTimeImmutable('2026-06-15'),
        ));
    }

    public function test_throws_when_pre_registration_not_allowed(): void
    {
        $reservation = $this->makeConfirmedReservation();
        $reservation->status = ReservationStatus::CheckedIn;
        $this->repository->add($reservation);

        $this->expectException(GuestPreRegistrationNotAllowedException::class);
        ($this->handler)(new PreRegisterGuestsCommand(
            reservationId: 'res-1',
            guests: [],
            today: new \DateTimeImmutable('2026-06-15'),
        ));
    }

    private function makeConfirmedReservation(string $id = 'res-1'): Reservation
    {
        $reservation = new Reservation(
            id: $id,
            roomId: 'room-1',
            bookerId: 'booker-1',
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
