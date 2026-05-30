<?php

declare(strict_types=1);

namespace App\Reservation\Application\UseCase\ListBookerReservations;

use App\Reservation\Domain\Model\ReservationPage;
use App\Reservation\Domain\Port\ReservationRepositoryInterface;
use App\Shared\Application\Bus\SyncQueryHandlerInterface;

final readonly class ListBookerReservationsQueryHandler implements SyncQueryHandlerInterface
{
    public function __construct(private ReservationRepositoryInterface $repository)
    {
    }

    public function __invoke(ListBookerReservationsQuery $query): ReservationPage
    {
        return $this->repository->listByBooker($query->bookerId, $query->page, $query->limit);
    }
}
