<?php

declare(strict_types=1);

namespace App\Pricing\Infrastructure\Service;

use App\Pricing\Application\Service\IdGeneratorInterface;
use Symfony\Component\Uid\Uuid;

final class UuidIdGenerator implements IdGeneratorInterface
{
    public function generate(): string
    {
        return Uuid::v4()->toString();
    }
}
