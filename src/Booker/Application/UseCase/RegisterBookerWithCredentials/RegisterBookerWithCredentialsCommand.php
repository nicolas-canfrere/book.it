<?php

declare(strict_types=1);

namespace App\Booker\Application\UseCase\RegisterBookerWithCredentials;

use App\Shared\Application\Bus\SyncCommandInterface;
use App\Shared\Domain\ValueObject\BookerId;

final readonly class RegisterBookerWithCredentialsCommand implements SyncCommandInterface
{
    public function __construct(
        public BookerId $id,
        public string $firstName,
        public string $lastName,
        public string $email,
        public string $phone,
        public \DateTimeImmutable $dateOfBirth,
        public string $password,
        public \DateTimeImmutable $registeredAt,
    ) {
    }
}
