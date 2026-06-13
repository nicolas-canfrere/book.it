<?php

declare(strict_types=1);

namespace App\Availability\Infrastructure\Service;

use App\Availability\Domain\Port\AvailabilityHoldIdGeneratorInterface;
use App\Shared\Domain\ValueObject\AvailabilityHoldId;
use Symfony\Component\Uid\Uuid;

final class AvailabilityHoldIdGenerator implements AvailabilityHoldIdGeneratorInterface
{
    public function generate(): AvailabilityHoldId
    {
        return new AvailabilityHoldId(Uuid::v4()->toRfc4122());
    }
}
