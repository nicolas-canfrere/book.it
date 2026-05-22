<?php

declare(strict_types=1);

namespace App\Hotel\Domain\Exception;

final class HotelNotFoundException extends \DomainException
{
    public function __construct(string $id)
    {
        parent::__construct(sprintf('Hotel "%s" not found.', $id));
    }
}
