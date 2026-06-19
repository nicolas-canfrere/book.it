<?php

declare(strict_types=1);

namespace App\Reservation\Infrastructure\Service;

use App\Booker\Application\Contract\BookerFinderInterface;
use App\Reservation\Domain\Port\BookerExistsInterface;
use App\Shared\Domain\ValueObject\BookerId;

final readonly class BookerExistenceChecker implements BookerExistsInterface
{
    public function __construct(private BookerFinderInterface $bookerFinder)
    {
    }

    public function exists(BookerId $bookerId): bool
    {
        return null !== $this->bookerFinder->find($bookerId);
    }
}
