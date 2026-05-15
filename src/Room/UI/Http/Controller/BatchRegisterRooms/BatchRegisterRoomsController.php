<?php

declare(strict_types=1);

namespace App\Room\UI\Http\Controller\BatchRegisterRooms;

use App\Room\Application\Exception\InvalidCsvFormatException;
use App\Room\Application\Service\BatchRegisterRoomsCommandFactory;
use App\Room\Application\Service\CsvRoomNumbersParser;
use App\Room\Domain\Model\Room;
use App\Room\UI\Http\Controller\RoomSerializer;
use App\Shared\Application\Bus\SyncCommandBusInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final readonly class BatchRegisterRoomsController
{
    public function __construct(
        private BatchRegisterRoomsCommandFactory $commandFactory,
        private SyncCommandBusInterface $commandBus,
        private RoomSerializer $roomSerializer,
        private CsvRoomNumbersParser $csvParser,
    ) {
    }

    #[Route('/api/hotels/{hotelId}/rooms/batch', name: 'room_batch_register_rooms', requirements: ['hotelId' => Requirement::UUID_V4], methods: ['POST'])]
    #[OA\Post(
        summary: 'Import multiple rooms in a hotel from a CSV file',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['csv'],
                    properties: [
                        new OA\Property(
                            property: 'csv',
                            description: 'CSV file with a "number" header column and one room number per row',
                            type: 'string',
                            format: 'binary',
                        ),
                    ],
                    type: 'object',
                ),
            ),
        ),
        tags: ['Rooms'],
        parameters: [
            new OA\Parameter(name: 'hotelId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(
                response: Response::HTTP_CREATED,
                description: 'All rooms registered',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                            new OA\Property(property: 'hotelId', type: 'string', format: 'uuid'),
                            new OA\Property(property: 'number', type: 'string', example: '101'),
                            new OA\Property(property: 'createdAt', description: 'Unix timestamp', type: 'integer'),
                        ],
                    ),
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
                response: Response::HTTP_UNPROCESSABLE_ENTITY,
                description: 'Validation error (invalid CSV format or room constraint violations)',
                content: new OA\MediaType(
                    mediaType: 'application/problem+json',
                    schema: new OA\Schema(ref: '#/components/schemas/ValidationProblemDetail'),
                ),
            ),
        ],
    )]
    public function __invoke(string $hotelId, Request $request): Response
    {
        $file = $request->files->get('csv');
        if (!$file instanceof UploadedFile) {
            throw new InvalidCsvFormatException('A CSV file is required.');
        }

        $numbers = $this->csvParser->parse($file);

        $command = $this->commandFactory->create($hotelId, $numbers);

        $this->commandBus->execute($command);

        $rooms = array_map(
            fn(array $entry) => $this->roomSerializer->serialize(
                new Room($entry['id'], $command->hotelId, $entry['number'], $command->createdAt)
            ),
            $command->entries,
        );

        return new JsonResponse($rooms, Response::HTTP_CREATED);
    }
}
