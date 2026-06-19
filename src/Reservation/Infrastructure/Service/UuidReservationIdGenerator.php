<?php

declare(strict_types=1);

namespace App\Reservation\Infrastructure\Service;

use App\Reservation\Domain\Port\ReservationIdGeneratorInterface;
use App\Shared\Domain\ValueObject\ReservationId;
use Symfony\Component\Uid\Uuid;

final class UuidReservationIdGenerator implements ReservationIdGeneratorInterface
{
    public function generate(): ReservationId
    {
        return new ReservationId(Uuid::v4()->toString());
    }
}
