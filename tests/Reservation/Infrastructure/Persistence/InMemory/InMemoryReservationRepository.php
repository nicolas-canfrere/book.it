<?php

declare(strict_types=1);

namespace App\Tests\Reservation\Infrastructure\Persistence\InMemory;

use App\Reservation\Domain\Model\Reservation;
use App\Reservation\Domain\Model\ReservationPage;
use App\Reservation\Domain\Port\ReservationRepositoryInterface;
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

    public function listByBooker(string $bookerId, int $page, int $limit): ReservationPage
    {
        $all = array_values(array_filter(
            $this->store,
            fn(Reservation $r) => $r->bookerId === $bookerId,
        ));

        usort($all, fn(Reservation $a, Reservation $b) => $b->createdAt <=> $a->createdAt);

        $total = count($all);
        $items = array_slice($all, ($page - 1) * $limit, $limit);

        return new ReservationPage($items, $total);
    }
}
