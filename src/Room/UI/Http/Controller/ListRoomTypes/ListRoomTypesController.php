<?php

declare(strict_types=1);

namespace App\Room\UI\Http\Controller\ListRoomTypes;

use App\Room\Application\UseCase\ListRoomTypes\ListRoomTypesQuery;
use App\Shared\Application\Bus\SyncQueryBusInterface;
use App\Shared\Domain\ValueObject\HotelId;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final readonly class ListRoomTypesController
{
    public function __construct(
        private SyncQueryBusInterface $queryBus,
        private RoomTypeCatalogueSerializer $serializer,
    ) {
    }

    #[Route('/hotels/{hotelId}/room-types', name: 'room_list_room_types', requirements: ['hotelId' => Requirement::UUID_V4], methods: ['GET'])]
    #[OA\Get(
        summary: 'List room types of a hotel (paginated)',
        tags: ['Room Types'],
        parameters: [
            new OA\Parameter(name: 'hotelId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(
                response: Response::HTTP_OK,
                description: 'Paginated room type catalogue',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                                    new OA\Property(property: 'hotelId', type: 'string', format: 'uuid'),
                                    new OA\Property(property: 'name', type: 'string', example: 'Standard'),
                                    new OA\Property(property: 'livingSpaceCount', type: 'integer', example: 1),
                                    new OA\Property(property: 'surfaceM2', type: 'integer', nullable: true, example: 25),
                                    new OA\Property(property: 'guestCapacity', type: 'integer', example: 2),
                                    new OA\Property(property: 'isAccessible', type: 'boolean'),
                                    new OA\Property(
                                        property: 'bedComposition',
                                        type: 'array',
                                        items: new OA\Items(
                                            properties: [
                                                new OA\Property(property: 'type', type: 'string', example: 'double'),
                                                new OA\Property(property: 'count', type: 'integer', example: 1),
                                            ],
                                            type: 'object',
                                        ),
                                    ),
                                    new OA\Property(property: 'amenities', type: 'array', items: new OA\Items(type: 'string'), example: ['wifi', 'tv']),
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
                                new OA\Property(property: 'total', type: 'integer', example: 10),
                                new OA\Property(property: 'totalPages', type: 'integer', example: 1),
                            ],
                            type: 'object',
                        ),
                    ],
                ),
            ),
            new OA\Response(response: Response::HTTP_UNPROCESSABLE_ENTITY, description: 'Validation error', content: new OA\MediaType(mediaType: 'application/problem+json', schema: new OA\Schema(ref: '#/components/schemas/ValidationProblemDetail'))),
        ],
    )]
    public function __invoke(
        string $hotelId,
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)] ListRoomTypesRequest $request = new ListRoomTypesRequest(),
    ): Response {
        $page = $this->queryBus->ask(new ListRoomTypesQuery(new HotelId($hotelId), $request->page, $request->limit));

        return new JsonResponse($this->serializer->serialize($page, $request->page, $request->limit));
    }
}
