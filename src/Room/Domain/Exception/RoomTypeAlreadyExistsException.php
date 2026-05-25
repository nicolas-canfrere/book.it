<?php

declare(strict_types=1);

namespace App\Room\Domain\Exception;

final class RoomTypeAlreadyExistsException extends \DomainException
{
    public function __construct(string $name, string $hotelId)
    {
        parent::__construct(sprintf('Room type "%s" already exists in hotel "%s".', $name, $hotelId));
    }
}
