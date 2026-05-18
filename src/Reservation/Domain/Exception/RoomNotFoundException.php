<?php

declare(strict_types=1);

namespace App\Reservation\Domain\Exception;

final class RoomNotFoundException extends \RuntimeException
{
    public function __construct(string $roomId)
    {
        parent::__construct(sprintf('Room "%s" not found.', $roomId));
    }
}
