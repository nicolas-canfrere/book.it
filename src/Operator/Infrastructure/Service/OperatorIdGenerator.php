<?php

declare(strict_types=1);

namespace App\Operator\Infrastructure\Service;

use App\Operator\Domain\Port\OperatorIdGeneratorInterface;
use App\Shared\Domain\ValueObject\OperatorId;
use Symfony\Component\Uid\Uuid;

final class OperatorIdGenerator implements OperatorIdGeneratorInterface
{
    public function generate(): OperatorId
    {
        return new OperatorId(Uuid::v4()->toString());
    }
}
