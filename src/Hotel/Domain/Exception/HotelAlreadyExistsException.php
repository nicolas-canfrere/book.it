<?php

declare(strict_types=1);

namespace App\Hotel\Domain\Exception;

final class HotelAlreadyExistsException extends \DomainException
{
    public function __construct(string $name, string $city)
    {
        parent::__construct(\sprintf('A hotel named "%s" already exists in %s.', $name, $city));
    }
}
