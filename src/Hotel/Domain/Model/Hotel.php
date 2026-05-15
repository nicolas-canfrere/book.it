<?php

declare(strict_types=1);

namespace App\Hotel\Domain\Model;

final readonly class Hotel
{
    public function __construct(
        public string $id,
        public string $name,
        public Address $address,
        public \DateTimeImmutable $createdAt,
    ) {
    }
}
