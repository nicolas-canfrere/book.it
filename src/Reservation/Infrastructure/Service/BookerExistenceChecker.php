<?php

declare(strict_types=1);

namespace App\Reservation\Infrastructure\Service;

use App\Booker\Application\UseCase\GetBooker\GetBookerQuery;
use App\Reservation\Domain\Port\BookerExistsInterface;
use App\Shared\Application\Bus\SyncQueryBusInterface;

final readonly class BookerExistenceChecker implements BookerExistsInterface
{
    public function __construct(private SyncQueryBusInterface $queryBus)
    {
    }

    public function exists(string $bookerId): bool
    {
        return null !== $this->queryBus->ask(new GetBookerQuery($bookerId));
    }
}
