<?php

declare(strict_types=1);

namespace App\Booker\Infrastructure\Contract;

use App\Booker\Application\Contract\BookerFinderInterface;
use App\Booker\Application\Contract\BookerView;
use App\Booker\Domain\Port\BookerRepositoryInterface;

final readonly class DoctrineBookerFinder implements BookerFinderInterface
{
    public function __construct(private BookerRepositoryInterface $bookerRepository)
    {
    }

    public function find(string $bookerId): ?BookerView
    {
        $booker = $this->bookerRepository->get($bookerId);

        if (null === $booker) {
            return null;
        }

        return new BookerView(
            id: $booker->id,
            firstName: $booker->firstName,
            lastName: $booker->lastName,
            email: $booker->email,
        );
    }
}
