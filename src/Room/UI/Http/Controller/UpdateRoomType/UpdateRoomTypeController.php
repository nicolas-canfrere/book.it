<?php

declare(strict_types=1);

namespace App\Room\UI\Http\Controller\UpdateRoomType;

use App\Room\Application\UseCase\GetRoomType\GetRoomTypeQuery;
use App\Room\Application\UseCase\UpdateRoomType\UpdateRoomTypeCommand;
use App\Room\UI\Http\Controller\RoomTypeSerializer;
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

final readonly class UpdateRoomTypeController
{
    public function __construct(
        private SyncCommandBusInterface $commandBus,
        private SyncQueryBusInterface $queryBus,
        private RoomTypeSerializer $roomTypeSerializer,
    ) {
    }

    #[Route('/hotels/{hotelId}/room-types/{roomTypeId}', name: 'room_update_room_type', requirements: ['hotelId' => Requirement::UUID_V4, 'roomTypeId' => Requirement::UUID_V4], methods: ['PUT'])]
    #[OA\Put(
        summary: 'Update a room type',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: new Model(type: UpdateRoomTypeRequest::class)),
        ),
        tags: ['Room Types'],
        parameters: [
            new OA\Parameter(name: 'hotelId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'roomTypeId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: Response::HTTP_OK, description: 'Room type updated'),
            new OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Room type not found', content: new OA\MediaType(mediaType: 'application/problem+json', schema: new OA\Schema(ref: '#/components/schemas/ProblemDetail'))),
            new OA\Response(response: Response::HTTP_CONFLICT, description: 'Room type name already exists', content: new OA\MediaType(mediaType: 'application/problem+json', schema: new OA\Schema(ref: '#/components/schemas/ProblemDetail'))),
            new OA\Response(response: Response::HTTP_UNPROCESSABLE_ENTITY, description: 'Validation error', content: new OA\MediaType(mediaType: 'application/problem+json', schema: new OA\Schema(ref: '#/components/schemas/ValidationProblemDetail'))),
        ],
    )]
    public function __invoke(
        string $hotelId,
        string $roomTypeId,
        #[MapRequestPayload(acceptFormat: 'json')] UpdateRoomTypeRequest $request,
    ): Response {
        $this->commandBus->execute(new UpdateRoomTypeCommand(
            id: $roomTypeId,
            name: $request->name ?? throw new \LogicException('name is required'),
            livingSpaceCount: $request->livingSpaceCount ?? throw new \LogicException('livingSpaceCount is required'),
            surfaceM2: $request->surfaceM2,
            guestCapacity: $request->guestCapacity ?? throw new \LogicException('guestCapacity is required'),
            isAccessible: $request->isAccessible ?? throw new \LogicException('isAccessible is required'),
            bedEntries: $request->bedComposition ?? throw new \LogicException('bedComposition is required'),
        ));

        $roomType = $this->queryBus->ask(new GetRoomTypeQuery($roomTypeId));
        if (null === $roomType) {
            throw new NotFoundHttpException();
        }

        return new JsonResponse($this->roomTypeSerializer->serialize($roomType));
    }
}
