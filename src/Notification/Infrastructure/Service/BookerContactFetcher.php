<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\Service;

use App\Booker\Application\UseCase\GetBooker\GetBookerQuery;
use App\Booker\Domain\Model\Booker;
use App\Notification\Domain\Port\BookerContactFetcherInterface;
use App\Notification\Domain\ReadModel\BookerContact;
use App\Shared\Application\Bus\SyncQueryBusInterface;

final readonly class BookerContactFetcher implements BookerContactFetcherInterface
{
    public function __construct(private SyncQueryBusInterface $queryBus)
    {
    }

    public function fetch(string $bookerId): ?BookerContact
    {
        /** @var Booker|null $booker */
        $booker = $this->queryBus->ask(new GetBookerQuery($bookerId));

        if (null === $booker) {
            return null;
        }

        return new BookerContact(
            firstName: $booker->firstName,
            lastName: $booker->lastName,
            email: $booker->email,
        );
    }
}
