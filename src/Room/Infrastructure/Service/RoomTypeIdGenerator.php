<?php

declare(strict_types=1);

namespace App\Room\Infrastructure\Service;

use App\Room\Domain\Port\RoomTypeIdGeneratorInterface;
use App\Shared\Domain\ValueObject\RoomTypeId;
use Symfony\Component\Uid\Uuid;

final class RoomTypeIdGenerator implements RoomTypeIdGeneratorInterface
{
    public function generate(): RoomTypeId
    {
        return new RoomTypeId(Uuid::v4()->toString());
    }
}
