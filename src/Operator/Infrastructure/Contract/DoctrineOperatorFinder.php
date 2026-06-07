<?php

declare(strict_types=1);

namespace App\Operator\Infrastructure\Contract;

use App\Operator\Application\Contract\OperatorFinderInterface;
use App\Operator\Application\Contract\OperatorView;
use Doctrine\DBAL\Connection;

final readonly class DoctrineOperatorFinder implements OperatorFinderInterface
{
    public function __construct(
        private Connection $bookit,
    ) {
    }

    public function find(string $operatorId): ?OperatorView
    {
        $row = $this->bookit->fetchAssociative(
            'SELECT id, email FROM operator WHERE id = ?',
            [$operatorId],
        );

        if (!\is_array($row)) {
            return null;
        }

        return new OperatorView(
            id: (string) $row['id'],
            email: (string) $row['email'],
        );
    }
}
