<?php

declare(strict_types=1);

namespace App\Reservation\UI\Http\Controller\ListBookerReservations;

use App\Reservation\Application\UseCase\ListBookerReservations\ListBookerReservationsQuery;
use App\Reservation\UI\Http\Controller\ReservationSerializer;
use App\Shared\Application\Bus\SyncQueryBusInterface;
use App\Shared\Domain\ValueObject\BookerId;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

final readonly class ListBookerReservationsController
{
    public function __construct(
        private SyncQueryBusInterface $queryBus,
        private ReservationSerializer $serializer,
    ) {
    }

    #[Route('/reservations', name: 'reservation_list_by_booker', methods: ['GET'])]
    #[OA\Get(
        summary: 'List reservations for a booker (paginated)',
        tags: ['Reservation'],
        responses: [
            new OA\Response(
                response: Response::HTTP_OK,
                description: 'Paginated reservation list',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                                    new OA\Property(property: 'roomId', type: 'string', format: 'uuid'),
                                    new OA\Property(property: 'bookerId', type: 'string', format: 'uuid'),
                                    new OA\Property(property: 'checkIn', type: 'string', format: 'date'),
                                    new OA\Property(property: 'checkOut', type: 'string', format: 'date'),
                                    new OA\Property(property: 'totalPrice', type: 'integer'),
                                    new OA\Property(property: 'guestCount', type: 'integer'),
                                    new OA\Property(property: 'status', type: 'string'),
                                    new OA\Property(property: 'createdAt', type: 'string', format: 'date-time'),
                                ],
                                type: 'object',
                            ),
                        ),
                        new OA\Property(
                            property: 'meta',
                            properties: [
                                new OA\Property(property: 'page', type: 'integer', example: 1),
                                new OA\Property(property: 'limit', type: 'integer', example: 20),
                                new OA\Property(property: 'total', type: 'integer', example: 42),
                                new OA\Property(property: 'totalPages', type: 'integer', example: 3),
                            ],
                            type: 'object',
                        ),
                    ],
                ),
            ),
            new OA\Response(
                response: Response::HTTP_UNPROCESSABLE_ENTITY,
                description: 'Validation error',
                content: new OA\MediaType(mediaType: 'application/problem+json', schema: new OA\Schema(ref: '#/components/schemas/ValidationProblemDetail')),
            ),
        ],
    )]
    public function __invoke(
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)]
        ListBookerReservationsRequest $request = new ListBookerReservationsRequest(),
    ): Response {
        $page = $this->queryBus->ask(new ListBookerReservationsQuery(
            new BookerId((string) $request->bookerId),
            $request->page,
            $request->limit,
        ));

        return new JsonResponse([
            'data' => array_map($this->serializer->serialize(...), $page->reservations),
            'meta' => [
                'page' => $request->page,
                'limit' => $request->limit,
                'total' => $page->total,
                'totalPages' => (int) ceil($page->total / $request->limit),
            ],
        ]);
    }
}
