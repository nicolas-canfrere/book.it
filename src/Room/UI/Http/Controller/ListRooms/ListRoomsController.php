<?php

declare(strict_types=1);

namespace App\Room\UI\Http\Controller\ListRooms;

use App\Room\Application\UseCase\ListRooms\ListRoomsQuery;
use App\Room\Domain\Port\RoomBaseRateFinderInterface;
use App\Shared\Application\Bus\SyncQueryBusInterface;
use App\Shared\Domain\ValueObject\HotelId;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final readonly class ListRoomsController
{
    public function __construct(
        private SyncQueryBusInterface $queryBus,
        private RoomBaseRateFinderInterface $baseRateFinder,
        private RoomCatalogueSerializer $serializer,
    ) {
    }

    #[Route('/hotels/{hotelId}/rooms', name: 'room_list_rooms', requirements: ['hotelId' => Requirement::UUID_V4], methods: ['GET'])]
    #[OA\Get(
        summary: 'List rooms of a hotel (paginated)',
        tags: ['Rooms'],
        parameters: [
            new OA\Parameter(name: 'hotelId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(
                response: Response::HTTP_OK,
                description: 'Paginated room catalogue',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                                    new OA\Property(property: 'hotelId', type: 'string', format: 'uuid'),
                                    new OA\Property(property: 'number', type: 'string'),
                                    new OA\Property(property: 'createdAt', type: 'integer'),
                                    new OA\Property(property: 'baseRateAmountCents', type: 'integer', nullable: true, example: 12000),
                                ],
                                type: 'object',
                            ),
                        ),
                        new OA\Property(
                            property: 'meta',
                            properties: [
                                new OA\Property(property: 'page', type: 'integer', example: 1),
                                new OA\Property(property: 'limit', type: 'integer', example: 20),
                                new OA\Property(property: 'total', type: 'integer', example: 10),
                                new OA\Property(property: 'totalPages', type: 'integer', example: 1),
                            ],
                            type: 'object',
                        ),
                    ],
                ),
            ),
            new OA\Response(
                response: Response::HTTP_UNPROCESSABLE_ENTITY,
                description: 'Validation error',
                content: new OA\MediaType(
                    mediaType: 'application/problem+json',
                    schema: new OA\Schema(ref: '#/components/schemas/ValidationProblemDetail'),
                ),
            ),
        ],
    )]
    public function __invoke(
        string $hotelId,
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)] ListRoomsRequest $request = new ListRoomsRequest(),
    ): Response {
        $roomPage = $this->queryBus->ask(new ListRoomsQuery(
            new HotelId($hotelId),
            $request->page,
            $request->limit,
        ));

        $baseRateAmountCentsByRoomId = [];
        foreach ($roomPage->rooms as $room) {
            $amountCents = $this->baseRateFinder->find($room->id);
            if (null !== $amountCents) {
                $baseRateAmountCentsByRoomId[$room->id->value] = $amountCents;
            }
        }

        return new JsonResponse(
            $this->serializer->serialize($roomPage, $baseRateAmountCentsByRoomId, $request->page, $request->limit),
        );
    }
}
