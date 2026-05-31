<?php

declare(strict_types=1);

namespace App\Room\UI\Http\Controller\GetRoomType;

use App\Room\Application\UseCase\GetRoomType\GetRoomTypeQuery;
use App\Room\UI\Http\Controller\RoomTypeSerializer;
use App\Shared\Application\Bus\SyncQueryBusInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final readonly class GetRoomTypeController
{
    public function __construct(
        private SyncQueryBusInterface $queryBus,
        private RoomTypeSerializer $roomTypeSerializer,
    ) {
    }

    #[Route('/hotels/{hotelId}/room-types/{roomTypeId}', name: 'room_get_room_type', requirements: ['hotelId' => Requirement::UUID_V4, 'roomTypeId' => Requirement::UUID_V4], methods: ['GET'])]
    #[OA\Get(
        summary: 'Get a room type by id',
        tags: ['Room Types'],
        parameters: [
            new OA\Parameter(name: 'hotelId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'roomTypeId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(
                response: Response::HTTP_OK,
                description: 'Room type found',
                content: new OA\JsonContent(
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
                        new OA\Property(property: 'createdAt', description: 'Unix timestamp', type: 'integer'),
                    ],
                ),
            ),
            new OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Room type not found', content: new OA\MediaType(mediaType: 'application/problem+json', schema: new OA\Schema(ref: '#/components/schemas/ProblemDetail'))),
        ],
    )]
    public function __invoke(string $hotelId, string $roomTypeId): Response
    {
        $roomType = $this->queryBus->ask(new GetRoomTypeQuery($roomTypeId));
        if (null === $roomType) {
            throw new NotFoundHttpException();
        }

        return new JsonResponse($this->roomTypeSerializer->serialize($roomType));
    }
}
