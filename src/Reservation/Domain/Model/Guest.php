<?php

declare(strict_types=1);

namespace App\Reservation\Domain\Model;

final class Guest
{
    public function __construct(
        public readonly string $id,
        public readonly string $firstName,
        public readonly string $lastName,
        public readonly \DateTimeImmutable $dateOfBirth,
    ) {
    }
}
