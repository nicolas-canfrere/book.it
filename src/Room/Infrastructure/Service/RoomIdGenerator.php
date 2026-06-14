<?php

declare(strict_types=1);

namespace App\Room\Infrastructure\Service;

use App\Room\Domain\Port\RoomIdGeneratorInterface;
use App\Shared\Domain\ValueObject\RoomId;
use Symfony\Component\Uid\Uuid;

final class RoomIdGenerator implements RoomIdGeneratorInterface
{
    public function generate(): RoomId
    {
        return new RoomId(Uuid::v4()->toString());
    }
}
