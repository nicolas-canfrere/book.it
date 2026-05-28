<?php

declare(strict_types=1);

namespace App\Reservation\Infrastructure\Service;

use App\Reservation\Domain\Port\GuestIdGeneratorInterface;
use Symfony\Component\Uid\Uuid;

final class UuidGuestIdGenerator implements GuestIdGeneratorInterface
{
    public function generate(): string
    {
        return Uuid::v4()->toRfc4122();
    }
}
