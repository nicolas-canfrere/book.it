<?php

declare(strict_types=1);

namespace App\Reservation\UI\Http\Controller\GetReservation;

use App\Reservation\Application\UseCase\GetReservation\GetReservationQuery;
use App\Reservation\Domain\Exception\ReservationNotFoundException;
use App\Reservation\UI\Http\Controller\ReservationSerializer;
use App\Shared\Application\Bus\SyncQueryBusInterface;
use App\Shared\Domain\ValueObject\ReservationId;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final readonly class GetReservationController
{
    public function __construct(
        private SyncQueryBusInterface $queryBus,
        private ReservationSerializer $serializer,
    ) {
    }

    #[Route(
        path: '/reservations/{id}',
        name: 'reservation_get',
        requirements: ['id' => Requirement::UUID_V4],
        methods: ['GET'],
    )]
    #[OA\Get(
        summary: 'Get a reservation by ID',
        tags: ['Reservation'],
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Response(
        response: Response::HTTP_OK,
        description: 'Reservation found',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                new OA\Property(property: 'roomId', type: 'string', format: 'uuid'),
                new OA\Property(property: 'bookerId', type: 'string', format: 'uuid'),
                new OA\Property(property: 'checkIn', type: 'string', format: 'date', example: '2026-06-01'),
                new OA\Property(property: 'checkOut', type: 'string', format: 'date', example: '2026-06-05'),
                new OA\Property(property: 'totalPrice', type: 'integer', example: 42000),
                new OA\Property(property: 'guestCount', type: 'integer', example: 2),
                new OA\Property(property: 'status', type: 'string', example: 'pending'),
                new OA\Property(
                    property: 'cancellationTerms',
                    properties: [new OA\Property(property: 'daysThreshold', type: 'integer', nullable: true, example: 7)],
                    type: 'object',
                ),
                new OA\Property(
                    property: 'priceBreakdown',
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'date', type: 'string', format: 'date', example: '2026-06-01'),
                            new OA\Property(property: 'rateAmountCents', type: 'integer', example: 10000),
                            new OA\Property(property: 'discountPercent', type: 'integer', nullable: true, example: null),
                            new OA\Property(property: 'effectiveAmountCents', type: 'integer', example: 10000),
                        ],
                        type: 'object',
                    ),
                ),
                new OA\Property(property: 'createdAt', type: 'string', format: 'date-time'),
            ],
        ),
    )]
    #[OA\Response(
        response: Response::HTTP_NOT_FOUND,
        description: 'Reservation not found',
        content: new OA\MediaType(mediaType: 'application/problem+json', schema: new OA\Schema(ref: '#/components/schemas/ProblemDetail')),
    )]
    public function __invoke(string $id): Response
    {
        $reservation = $this->queryBus->ask(new GetReservationQuery(new ReservationId($id)));

        if (null === $reservation) {
            throw new ReservationNotFoundException($id);
        }

        return new JsonResponse($this->serializer->serialize($reservation));
    }
}
