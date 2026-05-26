<?php

declare(strict_types=1);

namespace App\Room\UI\Http\Controller\RegisterRoomType;

use App\Room\Application\Service\RegisterRoomTypeCommandFactory;
use App\Room\Application\UseCase\GetRoomType\GetRoomTypeQuery;
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

final readonly class RegisterRoomTypeController
{
    public function __construct(
        private RegisterRoomTypeCommandFactory $commandFactory,
        private SyncCommandBusInterface $commandBus,
        private SyncQueryBusInterface $queryBus,
        private RoomTypeSerializer $roomTypeSerializer,
    ) {
    }

    #[Route('/hotels/{hotelId}/room-types', name: 'room_register_room_type', requirements: ['hotelId' => Requirement::UUID_V4], methods: ['POST'])]
    #[OA\Post(
        summary: 'Register a new room type in a hotel',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: new Model(type: RegisterRoomTypeRequest::class)),
        ),
        tags: ['Room Types'],
        parameters: [
            new OA\Parameter(name: 'hotelId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: Response::HTTP_CREATED, description: 'Room type registered'),
            new OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Hotel not found', content: new OA\MediaType(mediaType: 'application/problem+json', schema: new OA\Schema(ref: '#/components/schemas/ProblemDetail'))),
            new OA\Response(response: Response::HTTP_CONFLICT, description: 'Room type name already exists', content: new OA\MediaType(mediaType: 'application/problem+json', schema: new OA\Schema(ref: '#/components/schemas/ProblemDetail'))),
            new OA\Response(response: Response::HTTP_UNPROCESSABLE_ENTITY, description: 'Validation error', content: new OA\MediaType(mediaType: 'application/problem+json', schema: new OA\Schema(ref: '#/components/schemas/ValidationProblemDetail'))),
        ],
    )]
    public function __invoke(
        string $hotelId,
        #[MapRequestPayload(acceptFormat: 'json')] RegisterRoomTypeRequest $request,
    ): Response {
        $command = $this->commandFactory->create(
            hotelId: $hotelId,
            name: $request->name ?? throw new \LogicException('name is required'),
            livingSpaceCount: $request->livingSpaceCount ?? throw new \LogicException('livingSpaceCount is required'),
            surfaceM2: $request->surfaceM2,
            guestCapacity: $request->guestCapacity ?? throw new \LogicException('guestCapacity is required'),
            isAccessible: $request->isAccessible ?? throw new \LogicException('isAccessible is required'),
            bedEntries: $request->bedComposition ?? throw new \LogicException('bedComposition is required'),
        );
        $this->commandBus->execute($command);

        $roomType = $this->queryBus->ask(new GetRoomTypeQuery($command->id));
        if (null === $roomType) {
            throw new NotFoundHttpException();
        }

        return new JsonResponse($this->roomTypeSerializer->serialize($roomType), Response::HTTP_CREATED);
    }
}
