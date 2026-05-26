<?php

declare(strict_types=1);

namespace App\Room\UI\Http\Controller\DeleteRoomType;

use App\Room\Application\UseCase\DeleteRoomType\DeleteRoomTypeCommand;
use App\Shared\Application\Bus\SyncCommandBusInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final readonly class DeleteRoomTypeController
{
    public function __construct(private SyncCommandBusInterface $commandBus)
    {
    }

    #[Route('/hotels/{hotelId}/room-types/{roomTypeId}', name: 'room_delete_room_type', requirements: ['hotelId' => Requirement::UUID_V4, 'roomTypeId' => Requirement::UUID_V4], methods: ['DELETE'])]
    #[OA\Delete(
        summary: 'Delete a room type',
        tags: ['Room Types'],
        parameters: [
            new OA\Parameter(name: 'hotelId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'roomTypeId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: Response::HTTP_NO_CONTENT, description: 'Room type deleted'),
            new OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Room type not found', content: new OA\MediaType(mediaType: 'application/problem+json', schema: new OA\Schema(ref: '#/components/schemas/ProblemDetail'))),
            new OA\Response(response: Response::HTTP_CONFLICT, description: 'Room type has rooms assigned', content: new OA\MediaType(mediaType: 'application/problem+json', schema: new OA\Schema(ref: '#/components/schemas/ProblemDetail'))),
        ],
    )]
    public function __invoke(string $hotelId, string $roomTypeId): Response
    {
        $this->commandBus->execute(new DeleteRoomTypeCommand($roomTypeId));

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
