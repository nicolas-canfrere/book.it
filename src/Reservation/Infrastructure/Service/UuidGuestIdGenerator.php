<?php

declare(strict_types=1);

namespace App\Reservation\Infrastructure\Service;

use App\Reservation\Domain\Port\GuestIdGeneratorInterface;
use App\Shared\Domain\ValueObject\GuestId;
use Symfony\Component\Uid\Uuid;

final class UuidGuestIdGenerator implements GuestIdGeneratorInterface
{
    public function generate(): GuestId
    {
        return new GuestId(Uuid::v4()->toRfc4122());
    }
}
