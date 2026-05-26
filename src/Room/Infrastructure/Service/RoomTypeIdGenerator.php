<?php

declare(strict_types=1);

namespace App\Room\Infrastructure\Service;

use App\Room\Domain\Port\RoomTypeIdGeneratorInterface;
use Symfony\Component\Uid\Uuid;

final class RoomTypeIdGenerator implements RoomTypeIdGeneratorInterface
{
    public function generate(): string
    {
        return Uuid::v4()->toString();
    }
}
