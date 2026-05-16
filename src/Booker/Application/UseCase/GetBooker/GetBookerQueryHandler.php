<?php

declare(strict_types=1);

namespace App\Booker\Application\UseCase\GetBooker;

use App\Booker\Domain\Model\Booker;
use App\Booker\Domain\Port\BookerRepositoryInterface;
use App\Shared\Application\Bus\SyncQueryHandlerInterface;

final readonly class GetBookerQueryHandler implements SyncQueryHandlerInterface
{
    public function __construct(
        private BookerRepositoryInterface $bookerRepository,
    ) {
    }

    public function __invoke(GetBookerQuery $query): ?Booker
    {
        return $this->bookerRepository->get($query->bookerId);
    }
}
