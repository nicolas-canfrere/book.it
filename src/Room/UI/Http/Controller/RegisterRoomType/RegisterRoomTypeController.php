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
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Requirement\Requirement;

final readonly class RegisterRoomTypeController
{
    public function __construct(
        private RegisterRoomTypeCommandFactory $commandFactory,
        private SyncCommandBusInterface $commandBus,
        private SyncQueryBusInterface $queryBus,
        private RoomTypeSerializer $roomTypeSerializer,
        private UrlGeneratorInterface $urlGenerator,
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
            new OA\Response(
                response: Response::HTTP_CREATED,
                description: 'Room type registered',
                headers: [new OA\Header(header: 'Location', description: 'URL of the created room type', schema: new OA\Schema(type: 'string', format: 'uri'))],
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
                        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time'),
                    ],
                ),
            ),
            new OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Hotel not found', content: new OA\MediaType(mediaType: 'application/problem+json', schema: new OA\Schema(ref: '#/components/schemas/ProblemDetail'))),
            new OA\Response(response: Response::HTTP_CONFLICT, description: 'Room type name already exists', content: new OA\MediaType(mediaType: 'application/problem+json', schema: new OA\Schema(ref: '#/components/schemas/ProblemDetail'))),
            new OA\Response(response: Response::HTTP_UNPROCESSABLE_ENTITY, description: 'Validation error', content: new OA\MediaType(mediaType: 'application/problem+json', schema: new OA\Schema(ref: '#/components/schemas/ValidationProblemDetail'))),
            new OA\Response(response: Response::HTTP_UNSUPPORTED_MEDIA_TYPE, description: 'Unsupported media type', content: new OA\MediaType(mediaType: 'application/problem+json', schema: new OA\Schema(ref: '#/components/schemas/ProblemDetail'))),
        ],
    )]
    public function __invoke(
        string $hotelId,
        #[MapRequestPayload(acceptFormat: 'json')] RegisterRoomTypeRequest $request,
    ): Response {
        $command = $this->commandFactory->create(
            hotelId: $hotelId,
            name: $request->name,
            livingSpaceCount: $request->livingSpaceCount,
            surfaceM2: $request->surfaceM2,
            guestCapacity: $request->guestCapacity,
            isAccessible: $request->isAccessible,
            bedEntries: $request->bedComposition,
        );
        $this->commandBus->execute($command);

        $roomType = $this->queryBus->ask(new GetRoomTypeQuery($command->id));
        if (null === $roomType) {
            throw new NotFoundHttpException();
        }

        return new JsonResponse($this->roomTypeSerializer->serialize($roomType), Response::HTTP_CREATED, ['Location' => $this->urlGenerator->generate('room_get_room_type', ['hotelId' => $hotelId, 'roomTypeId' => $command->id])]);
    }
}
