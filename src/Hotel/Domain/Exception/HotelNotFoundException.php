<?php

declare(strict_types=1);

namespace App\Hotel\Domain\Exception;

final class HotelNotFoundException extends \DomainException
{
    public static function withId(string $id): self
    {
        return new self(sprintf('Hotel "%s" not found.', $id));
    }
}
