<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\Service;

use App\Booker\Application\Contract\BookerFinderInterface;
use App\Notification\Domain\Port\BookerContactFetcherInterface;
use App\Notification\Domain\ReadModel\BookerContact;
use App\Shared\Domain\ValueObject\BookerId;

final readonly class BookerContactFetcher implements BookerContactFetcherInterface
{
    public function __construct(private BookerFinderInterface $bookers)
    {
    }

    public function fetch(string $bookerId): ?BookerContact
    {
        $view = $this->bookers->find(new BookerId($bookerId));

        if (null === $view) {
            return null;
        }

        return new BookerContact(
            firstName: $view->firstName,
            lastName: $view->lastName,
            email: $view->email,
        );
    }
}
