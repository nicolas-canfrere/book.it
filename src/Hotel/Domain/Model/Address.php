<?php

declare(strict_types=1);

namespace App\Hotel\Domain\Model;

final readonly class Address
{
    public function __construct(
        public string $streetAddress,
        public string $postalCode,
        public string $city,
        public string $country,
    ) {
    }
}
