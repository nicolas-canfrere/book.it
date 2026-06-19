<?php

declare(strict_types=1);

namespace App\Reservation\Domain\Model;

use App\Shared\Domain\ValueObject\GuestId;

final readonly class Guest
{
    public function __construct(
        public GuestId $id,
        public string $firstName,
        public string $lastName,
        public \DateTimeImmutable $dateOfBirth,
    ) {
    }
}
