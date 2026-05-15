<?php

declare(strict_types=1);

namespace App\Room\Domain\Exception;

final class RoomAlreadyExistsException extends \DomainException
{
    public function __construct(string $number, string $hotelId)
    {
        parent::__construct(\sprintf('A room with number "%s" already exists in hotel %s.', $number, $hotelId));
    }
}
