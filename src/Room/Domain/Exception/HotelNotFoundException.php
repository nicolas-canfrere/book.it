<?php

declare(strict_types=1);

namespace App\Room\Domain\Exception;

final class HotelNotFoundException extends \DomainException
{
    public function __construct(string $hotelId)
    {
        parent::__construct(\sprintf('Hotel with id "%s" does not exist.', $hotelId));
    }
}
