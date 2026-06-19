<?php

declare(strict_types=1);

namespace App\Tests\Reservation\Infrastructure\Persistence\InMemory;

use App\Reservation\Domain\Model\Reservation;
use App\Reservation\Domain\Model\ReservationPage;
use App\Reservation\Domain\Model\ReservationPeriodFilter;
use App\Reservation\Domain\Model\ReservationStatus;
use App\Reservation\Domain\Port\ReservationRepositoryInterface;
use App\Shared\Domain\ValueObject\BookerId;
use App\Shared\Domain\ValueObject\ReservationId;

final class InMemoryReservationRepository implements ReservationRepositoryInterface
{
    /** @var array<string, Reservation> */
    private array $store = [];

    public function add(Reservation $reservation): void
    {
        $this->store[$reservation->id->value] = $reservation;
    }

    public function save(Reservation $reservation): void
    {
        $this->store[$reservation->id->value] = $reservation;
    }

    public function get(ReservationId $id): ?Reservation
    {
        return $this->store[$id->value] ?? null;
    }

    public function listByBooker(
        BookerId $bookerId,
        int $page,
        int $limit,
        ?ReservationStatus $status = null,
        ?ReservationPeriodFilter $period = null,
    ): ReservationPage {
        $today = new \DateTimeImmutable('today');

        $all = array_values(array_filter(
            $this->store,
            function (Reservation $r) use ($bookerId, $status, $period, $today): bool {
                if ($r->bookerId->value !== $bookerId->value) {
                    return false;
                }

                if (null !== $status && $r->status !== $status) {
                    return false;
                }

                if (null !== $period && !$this->matchesPeriod($r, $period, $today)) {
                    return false;
                }

                return true;
            },
        ));

        usort($all, fn(Reservation $a, Reservation $b) => $b->createdAt <=> $a->createdAt);

        $total = count($all);
        $items = array_slice($all, ($page - 1) * $limit, $limit);

        return new ReservationPage($items, $total);
    }

    private function matchesPeriod(Reservation $r, ReservationPeriodFilter $period, \DateTimeImmutable $today): bool
    {
        return match ($period) {
            ReservationPeriodFilter::Upcoming => $r->period->checkIn > $today,
            ReservationPeriodFilter::Current => $r->period->checkIn <= $today && $r->period->checkOut > $today,
            ReservationPeriodFilter::Past => $r->period->checkOut <= $today,
        };
    }
}
