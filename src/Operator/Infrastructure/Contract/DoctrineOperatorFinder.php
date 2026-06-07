<?php

declare(strict_types=1);

namespace App\Operator\Infrastructure\Contract;

use App\Operator\Application\Contract\OperatorFinderInterface;
use App\Operator\Application\Contract\OperatorView;
use Doctrine\DBAL\Connection;

final readonly class DoctrineOperatorFinder implements OperatorFinderInterface
{
    public function __construct(
        private Connection $operatorConnection,
    ) {
    }

    public function find(string $operatorId): ?OperatorView
    {
        $row = $this->operatorConnection->fetchAssociative(
            'SELECT id, email FROM operator WHERE id = ?',
            [$operatorId],
        );

        if (!\is_array($row)) {
            return null;
        }

        /** @var array{id: string, email: string} $row */
        return new OperatorView(
            id: $row['id'],
            email: $row['email'],
        );
    }
}
