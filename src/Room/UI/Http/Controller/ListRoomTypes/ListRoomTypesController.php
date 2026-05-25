<?php

declare(strict_types=1);

namespace App\Room\UI\Http\Controller\ListRoomTypes;

use App\Room\Application\UseCase\ListRoomTypes\ListRoomTypesQuery;
use App\Shared\Application\Bus\SyncQueryBusInterface;
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
            new OA\Response(response: Response::HTTP_OK, description: 'Paginated room type catalogue'),
            new OA\Response(response: Response::HTTP_UNPROCESSABLE_ENTITY, description: 'Validation error', content: new OA\MediaType(mediaType: 'application/problem+json', schema: new OA\Schema(ref: '#/components/schemas/ValidationProblemDetail'))),
        ],
    )]
    public function __invoke(
        string $hotelId,
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)] ListRoomTypesRequest $request = new ListRoomTypesRequest(),
    ): Response {
        $page = $this->queryBus->ask(new ListRoomTypesQuery($hotelId, $request->page, $request->limit));

        return new JsonResponse($this->serializer->serialize($page, $request->page, $request->limit));
    }
}
