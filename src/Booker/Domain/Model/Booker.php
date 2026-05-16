<?php

declare(strict_types=1);

namespace App\Booker\Domain\Model;

final readonly class Booker
{
    public function __construct(
        public string $id,
        public string $firstName,
        public string $lastName,
        public string $email,
        public string $phone,
        public \DateTimeImmutable $dateOfBirth,
        public \DateTimeImmutable $registeredAt,
    ) {
    }
}
