<?php

declare(strict_types=1);

namespace App\Booker\Application\Contract;

final readonly class BookerView
{
    public function __construct(
        public string $id,
        public string $firstName,
        public string $lastName,
        public string $email,
    ) {
    }
}
