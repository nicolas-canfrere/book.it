<?php

declare(strict_types=1);

namespace App\Notification\Domain\ReadModel;

final readonly class BookerContact
{
    public function __construct(
        public string $firstName,
        public string $lastName,
        public string $email,
    ) {
    }
}
