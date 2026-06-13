<?php

declare(strict_types=1);

namespace App\Operator\Infrastructure\Persistence\Doctrine;

use App\Operator\Domain\Model\Operator;
use App\Operator\Domain\Port\OperatorRepositoryInterface;
use Doctrine\DBAL\Connection;

final readonly class OperatorRepository implements OperatorRepositoryInterface
{
    public function __construct(
        private Connection $operatorConnection,
    ) {
    }

    public function add(Operator $operator): void
    {
        $this->operatorConnection->insert('operator', [
            'id' => $operator->id->value,
            'first_name' => $operator->firstName,
            'last_name' => $operator->lastName,
            'email' => $operator->email,
            'phone' => $operator->phone,
            'registered_at' => $operator->registeredAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function existsByEmail(string $email): bool
    {
        /** @var int|string $count */
        $count = $this->operatorConnection->fetchOne(
            'SELECT COUNT(*) FROM operator WHERE LOWER(email) = LOWER(:email)',
            ['email' => $email],
        );

        return (int) $count > 0;
    }
}
