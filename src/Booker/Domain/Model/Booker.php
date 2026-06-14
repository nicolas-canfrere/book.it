<?php

declare(strict_types=1);

namespace App\Booker\Domain\Model;

use App\Shared\Domain\ValueObject\BookerId;

final readonly class Booker
{
    public function __construct(
        public BookerId $id,
        public string $firstName,
        public string $lastName,
        public string $email,
        public string $phone,
        public \DateTimeImmutable $dateOfBirth,
        public \DateTimeImmutable $registeredAt,
    ) {
    }
}
