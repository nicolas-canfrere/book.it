<?php

declare(strict_types=1);

namespace App\Reservation\Domain\Exception;

final class BookerNotFoundException extends \DomainException
{
    public function __construct(string $bookerId)
    {
        parent::__construct(sprintf('Booker "%s" not found.', $bookerId));
    }
}
