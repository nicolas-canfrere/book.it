<?php

declare(strict_types=1);

namespace App\Operator\Application\UseCase\RegisterOperator;

use App\Shared\Application\Bus\SyncCommandInterface;

final readonly class RegisterOperatorCommand implements SyncCommandInterface
{
    public function __construct(
        public string $id,
        public string $firstName,
        public string $lastName,
        public string $email,
        public string $phone,
        public string $password,
        public \DateTimeImmutable $registeredAt,
    ) {
    }
}
