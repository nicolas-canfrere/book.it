<?php

declare(strict_types=1);

namespace App\Reservation\Application\UseCase\GetReservation;

use App\Reservation\Domain\Model\Reservation;
use App\Reservation\Domain\Port\ReservationRepositoryInterface;
use App\Shared\Application\Bus\SyncQueryHandlerInterface;

final readonly class GetReservationQueryHandler implements SyncQueryHandlerInterface
{
    public function __construct(private ReservationRepositoryInterface $repository)
    {
    }

    public function __invoke(GetReservationQuery $query): ?Reservation
    {
        return $this->repository->get($query->id);
    }
}
