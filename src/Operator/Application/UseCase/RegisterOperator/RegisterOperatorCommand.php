<?php

declare(strict_types=1);

namespace App\Operator\Application\UseCase\RegisterOperator;

use App\Shared\Application\Bus\SyncCommandInterface;
use App\Shared\Domain\ValueObject\OperatorId;

final readonly class RegisterOperatorCommand implements SyncCommandInterface
{
    public function __construct(
        public OperatorId $id,
        public string $firstName,
        public string $lastName,
        public string $email,
        public string $phone,
        public string $password,
        public \DateTimeImmutable $registeredAt,
    ) {
    }
}
