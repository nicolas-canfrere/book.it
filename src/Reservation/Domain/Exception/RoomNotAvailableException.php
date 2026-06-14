<?php

declare(strict_types=1);

namespace App\Reservation\Domain\Exception;

use App\Shared\Domain\ValueObject\RoomTypeId;

final class RoomNotAvailableException extends \DomainException
{
    public function __construct(RoomTypeId $roomTypeId)
    {
        parent::__construct(sprintf('No room available for type "%s" on the requested period.', $roomTypeId->value));
    }
}
