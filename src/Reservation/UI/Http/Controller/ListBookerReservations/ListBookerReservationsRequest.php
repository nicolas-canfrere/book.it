<?php

declare(strict_types=1);

namespace App\Reservation\UI\Http\Controller\ListBookerReservations;

use App\Reservation\Domain\Model\ReservationPeriodFilter;
use App\Reservation\Domain\Model\ReservationStatus;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class ListBookerReservationsRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Uuid(versions: [Assert\Uuid::V4_RANDOM])]
        #[OA\Parameter(name: 'bookerId', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
        public string $bookerId,
        #[Assert\GreaterThanOrEqual(1)]
        #[OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1, minimum: 1))]
        public int $page = 1,
        #[Assert\GreaterThanOrEqual(1)]
        #[Assert\LessThanOrEqual(100)]
        #[OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', default: 20, maximum: 100, minimum: 1))]
        public int $limit = 20,
        #[Assert\Choice(callback: [ReservationStatus::class, 'values'])]
        #[OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string', nullable: true, enum: ['pending', 'confirmed', 'cancelled', 'expired', 'checked_in', 'checked_out']))]
        public ?string $status = null,
        #[Assert\Choice(callback: [ReservationPeriodFilter::class, 'values'])]
        #[OA\Parameter(name: 'period', in: 'query', schema: new OA\Schema(type: 'string', nullable: true, enum: ['past', 'current', 'upcoming']))]
        public ?string $period = null,
    ) {
    }
}
