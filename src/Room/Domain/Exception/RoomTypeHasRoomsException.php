<?php

declare(strict_types=1);

namespace App\Room\Domain\Exception;

final class RoomTypeHasRoomsException extends \DomainException
{
    public function __construct(string $id)
    {
        parent::__construct(sprintf('Room type "%s" cannot be deleted: rooms are assigned to it.', $id));
    }
}
