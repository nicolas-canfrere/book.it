<?php

declare(strict_types=1);

namespace App\Reservation\Domain\Model;

final readonly class Guest
{
    public function __construct(
        public string $id,
        public string $firstName,
        public string $lastName,
        public \DateTimeImmutable $dateOfBirth,
    ) {
    }
}
