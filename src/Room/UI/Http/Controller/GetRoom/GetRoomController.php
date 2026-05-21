<?php

declare(strict_types=1);

namespace App\Room\UI\Http\Controller\GetRoom;

use App\Room\Application\UseCase\GetRoom\GetRoomQuery;
use App\Room\UI\Http\Controller\RoomSerializer;
use App\Shared\Application\Bus\SyncQueryBusInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final readonly class GetRoomController
{
    public function __construct(
        private SyncQueryBusInterface $queryBus,
        private RoomSerializer $roomSerializer,
    ) {
    }

    #[Route('/rooms/{id}', name: 'room_get_room', requirements: ['id' => Requirement::UUID_V4], methods: ['GET'])]
    #[OA\Get(
        summary: 'Get a room by ID',
        tags: ['Rooms'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(
                response: Response::HTTP_OK,
                description: 'Room found',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                        new OA\Property(property: 'hotelId', type: 'string', format: 'uuid'),
                        new OA\Property(property: 'number', type: 'string', example: '101'),
                        new OA\Property(property: 'createdAt', description: 'Unix timestamp', type: 'integer'),
                    ],
                ),
            ),
            new OA\Response(
                response: Response::HTTP_NOT_FOUND,
                description: 'Room not found',
                content: new OA\MediaType(
                    mediaType: 'application/problem+json',
                    schema: new OA\Schema(ref: '#/components/schemas/ProblemDetail'),
                ),
            ),
        ],
    )]
    public function __invoke(string $id): Response
    {
        $room = $this->queryBus->ask(new GetRoomQuery($id));

        if (null === $room) {
            throw new NotFoundHttpException();
        }

        return new JsonResponse($this->roomSerializer->serialize($room));
    }
}
