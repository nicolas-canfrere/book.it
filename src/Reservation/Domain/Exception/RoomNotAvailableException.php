<?php

declare(strict_types=1);

namespace App\Reservation\Domain\Exception;

use App\Shared\Domain\ValueObject\RoomId;

final class RoomNotAvailableException extends \DomainException
{
    public function __construct(RoomId $roomId)
    {
        parent::__construct(sprintf('Room "%s" is not available for the requested period.', $roomId->value));
    }
}
