<?php

declare(strict_types=1);

namespace App\Reservation\Domain\Port;

use App\Reservation\Domain\Model\Reservation;
use App\Reservation\Domain\Model\ReservationPage;

interface ReservationRepositoryInterface
{
    public function add(Reservation $reservation): void;

    public function save(Reservation $reservation): void;

    public function get(string $id): ?Reservation;

    public function listByBooker(string $bookerId, int $page, int $limit): ReservationPage;
}
