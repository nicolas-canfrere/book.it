<?php

declare(strict_types=1);

namespace App\Booker\Infrastructure\Service;

use App\Booker\Application\UseCase\GetBooker\GetBookerQuery;
use App\Shared\Application\Bus\SyncQueryBusInterface;
use App\Shared\Domain\Port\BookerProviderInterface;

final readonly class BookerProvider implements BookerProviderInterface
{
    public function __construct(private SyncQueryBusInterface $queryBus)
    {
    }

    public function exists(string $bookerId): bool
    {
        return null !== $this->queryBus->ask(new GetBookerQuery($bookerId));
    }
}
