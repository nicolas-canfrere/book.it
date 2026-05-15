<?php

declare(strict_types=1);

namespace App\Room\Infrastructure\Service;

use App\Room\Application\Service\RoomIdGeneratorInterface;
use Symfony\Component\Uid\Uuid;

final class RoomIdGenerator implements RoomIdGeneratorInterface
{
    public function generate(): string
    {
        return Uuid::v4()->toString();
    }
}
