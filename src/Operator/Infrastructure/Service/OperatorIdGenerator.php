<?php

declare(strict_types=1);

namespace App\Operator\Infrastructure\Service;

use App\Operator\Domain\Port\OperatorIdGeneratorInterface;
use Symfony\Component\Uid\Uuid;

final class OperatorIdGenerator implements OperatorIdGeneratorInterface
{
    public function generate(): string
    {
        return Uuid::v4()->toString();
    }
}
