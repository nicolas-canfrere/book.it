<?php

declare(strict_types=1);

namespace App\Pricing\UI\Http\Controller\GetRatePeriods;

use App\Pricing\Application\UseCase\GetRatePeriods\GetRatePeriodsQuery;
use App\Pricing\UI\Http\Controller\RatePeriodSerializer;
use App\Shared\Application\Bus\SyncQueryBusInterface;
use App\Shared\Domain\ValueObject\RoomId;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final readonly class GetRatePeriodsController
{
    public function __construct(
        private SyncQueryBusInterface $queryBus,
        private RatePeriodSerializer $serializer,
    ) {
    }

    #[Route('/rooms/{roomId}/rate-periods', name: 'pricing_get_rate_periods', requirements: ['roomId' => Requirement::UUID_V4], methods: ['GET'])]
    #[OA\Get(
        summary: 'Get rate periods for a room',
        tags: ['Pricing'],
        parameters: [
            new OA\Parameter(name: 'roomId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(
                response: Response::HTTP_OK,
                description: 'List of rate periods',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'ratePeriods',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                                    new OA\Property(property: 'roomId', type: 'string', format: 'uuid'),
                                    new OA\Property(property: 'checkIn', type: 'string', format: 'date', example: '2025-07-01'),
                                    new OA\Property(property: 'checkOut', type: 'string', format: 'date', example: '2025-08-31'),
                                    new OA\Property(property: 'amountCents', type: 'integer', example: 15000),
                                    new OA\Property(property: 'createdAt', type: 'string', format: 'date-time'),
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
        $ratePeriods = $this->queryBus->ask(new GetRatePeriodsQuery(new RoomId($roomId)));

        return new JsonResponse([
            'ratePeriods' => array_map($this->serializer->serialize(...), $ratePeriods),
        ]);
    }
}
