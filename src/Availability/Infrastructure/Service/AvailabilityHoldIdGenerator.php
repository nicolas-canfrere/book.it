<?php

declare(strict_types=1);

namespace App\Availability\Infrastructure\Service;

use App\Availability\Domain\Port\AvailabilityHoldIdGeneratorInterface;
use Symfony\Component\Uid\Uuid;

final class AvailabilityHoldIdGenerator implements AvailabilityHoldIdGeneratorInterface
{
    public function generate(): string
    {
        return Uuid::v4()->toRfc4122();
    }
}
