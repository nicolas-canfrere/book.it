<?php

declare(strict_types=1);

namespace App\Availability\UI\Http\Controller\GetAvailabilityCalendar;

use App\Availability\Application\UseCase\GetAvailabilityCalendar\GetAvailabilityCalendarQuery;
use App\Availability\UI\Http\Controller\BlockedPeriodSerializer;
use App\Shared\Application\Bus\SyncQueryBusInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final readonly class GetAvailabilityCalendarController
{
    public function __construct(
        private SyncQueryBusInterface $queryBus,
        private BlockedPeriodSerializer $serializer,
    ) {
    }

    #[Route('/api/rooms/{roomId}/blocked-periods', name: 'availability_get_calendar', requirements: ['roomId' => Requirement::UUID_V4], methods: ['GET'])]
    #[OA\Get(
        summary: 'Get all blocked periods for a room',
        tags: ['Availability'],
        parameters: [
            new OA\Parameter(name: 'roomId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(
                response: Response::HTTP_OK,
                description: 'Availability calendar',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'blockedPeriods',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                                    new OA\Property(property: 'roomId', type: 'string', format: 'uuid'),
                                    new OA\Property(property: 'checkIn', type: 'string', format: 'date', example: '2025-06-10'),
                                    new OA\Property(property: 'checkOut', type: 'string', format: 'date', example: '2025-06-13'),
                                    new OA\Property(property: 'createdAt', type: 'integer'),
                                ],
                            ),
                        ),
                    ],
                ),
            ),
        ],
    )]
    public function __invoke(string $roomId): Response
    {
        $periods = $this->queryBus->ask(new GetAvailabilityCalendarQuery($roomId));

        return new JsonResponse([
            'blockedPeriods' => array_map($this->serializer->serialize(...), $periods),
        ]);
    }
}
