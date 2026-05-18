<?php

declare(strict_types=1);

namespace App\Reservation\Infrastructure\Service;

use App\Reservation\Domain\Port\ReservationIdGeneratorInterface;
use Symfony\Component\Uid\Uuid;

final class UuidReservationIdGenerator implements ReservationIdGeneratorInterface
{
    public function generate(): string
    {
        return Uuid::v4()->toString();
    }
}
