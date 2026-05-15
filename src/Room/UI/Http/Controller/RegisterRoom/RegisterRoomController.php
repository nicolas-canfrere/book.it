<?php

declare(strict_types=1);

namespace App\Room\UI\Http\Controller\RegisterRoom;

use App\Room\Application\Service\RegisterRoomCommandFactory;
use App\Room\Application\UseCase\GetRoom\GetRoomQuery;
use App\Room\UI\Http\Controller\RoomSerializer;
use App\Shared\Application\Bus\SyncCommandBusInterface;
use App\Shared\Application\Bus\SyncQueryBusInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final readonly class RegisterRoomController
{
    public function __construct(
        private RegisterRoomCommandFactory $commandFactory,
        private SyncCommandBusInterface $commandBus,
        private SyncQueryBusInterface $queryBus,
        private RoomSerializer $roomSerializer,
    ) {
    }

    #[Route('/api/hotels/{hotelId}/rooms', name: 'room_register_room', requirements: ['hotelId' => Requirement::UUID_V4], methods: ['POST'])]
    #[OA\Post(
        summary: 'Register a new room in a hotel',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: new Model(type: RegisterRoomRequest::class)),
        ),
        tags: ['Rooms'],
        parameters: [
            new OA\Parameter(name: 'hotelId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(
                response: Response::HTTP_CREATED,
                description: 'Room registered',
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
                description: 'Hotel not found',
                content: new OA\MediaType(
                    mediaType: 'application/problem+json',
                    schema: new OA\Schema(ref: '#/components/schemas/ProblemDetail'),
                ),
            ),
            new OA\Response(
                response: Response::HTTP_CONFLICT,
                description: 'Room already exists',
                content: new OA\MediaType(
                    mediaType: 'application/problem+json',
                    schema: new OA\Schema(ref: '#/components/schemas/ProblemDetail'),
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
        #[MapRequestPayload(acceptFormat: 'json')] RegisterRoomRequest $request,
    ): Response {
        $command = $this->commandFactory->create($hotelId, $request->number);
        $this->commandBus->execute($command);

        $room = $this->queryBus->ask(new GetRoomQuery($command->id));
        if (null === $room) {
            throw new NotFoundHttpException();
        }

        return new JsonResponse($this->roomSerializer->serialize($room), Response::HTTP_CREATED);
    }
}
