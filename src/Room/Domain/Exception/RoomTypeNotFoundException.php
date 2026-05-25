<?php

declare(strict_types=1);

namespace App\Room\Domain\Exception;

final class RoomTypeNotFoundException extends \DomainException
{
    public function __construct(string $id)
    {
        parent::__construct(sprintf('Room type "%s" not found.', $id));
    }
}
