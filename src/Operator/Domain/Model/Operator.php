<?php

declare(strict_types=1);

namespace App\Operator\Domain\Model;

final readonly class Operator
{
    public function __construct(
        public string $id,
        public string $firstName,
        public string $lastName,
        public string $email,
        public string $phone,
        public \DateTimeImmutable $registeredAt,
    ) {
    }
}
