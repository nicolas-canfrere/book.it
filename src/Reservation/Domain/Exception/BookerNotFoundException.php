<?php

declare(strict_types=1);

namespace App\Reservation\Domain\Exception;

use App\Shared\Domain\ValueObject\BookerId;

final class BookerNotFoundException extends \DomainException
{
    public function __construct(BookerId $bookerId)
    {
        parent::__construct(sprintf('Booker "%s" not found.', $bookerId->value));
    }
}
