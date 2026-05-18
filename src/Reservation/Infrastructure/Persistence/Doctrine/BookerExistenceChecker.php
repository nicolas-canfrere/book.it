<?php

declare(strict_types=1);

namespace App\Reservation\Infrastructure\Persistence\Doctrine;

use App\Booker\Domain\Port\BookerRepositoryInterface;
use App\Reservation\Domain\Port\BookerExistsInterface;

final readonly class BookerExistenceChecker implements BookerExistsInterface
{
    public function __construct(private BookerRepositoryInterface $bookerRepository)
    {
    }

    public function exists(string $bookerId): bool
    {
        return null !== $this->bookerRepository->get($bookerId);
    }
}
