<?php

declare(strict_types=1);

namespace App\Reservation\Infrastructure\Service;

use App\Booker\Application\Contract\BookerFinder;
use App\Reservation\Domain\Port\BookerExistsInterface;

final readonly class BookerExistenceChecker implements BookerExistsInterface
{
    public function __construct(private BookerFinder $bookerFinder)
    {
    }

    public function exists(string $bookerId): bool
    {
        return null !== $this->bookerFinder->find($bookerId);
    }
}
