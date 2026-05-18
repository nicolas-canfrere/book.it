<?php

declare(strict_types=1);

namespace App\Reservation\Domain\Exception;

final class RoomNotAvailableException extends \RuntimeException
{
    public function __construct(string $roomId)
    {
        parent::__construct(sprintf('Room "%s" is not available for the requested period.', $roomId));
    }
}
