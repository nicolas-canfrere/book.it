<?php

declare(strict_types=1);

namespace App\Booker\Application\UseCase\RegisterBooker;

use App\Shared\Application\Bus\SyncCommandInterface;

final readonly class RegisterBookerCommand implements SyncCommandInterface
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
