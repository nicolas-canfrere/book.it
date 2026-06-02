<?php

declare(strict_types=1);

namespace App\Pricing\UI\Http\Controller\GetPromotions;

use App\Pricing\Application\UseCase\GetPromotions\GetPromotionsQuery;
use App\Pricing\UI\Http\Controller\PromotionSerializer;
use App\Shared\Application\Bus\SyncQueryBusInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final readonly class GetPromotionsController
{
    public function __construct(
        private SyncQueryBusInterface $queryBus,
        private PromotionSerializer $serializer,
    ) {
    }

    #[Route('/rooms/{roomId}/promotions', name: 'pricing_get_promotions', requirements: ['roomId' => Requirement::UUID_V4], methods: ['GET'])]
    #[OA\Get(
        summary: 'Get promotions for a room',
        tags: ['Pricing'],
        parameters: [
            new OA\Parameter(name: 'roomId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(
                response: Response::HTTP_OK,
                description: 'List of promotions',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'promotions',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                                    new OA\Property(property: 'roomId', type: 'string', format: 'uuid'),
                                    new OA\Property(property: 'checkIn', type: 'string', format: 'date', example: '2025-07-01'),
                                    new OA\Property(property: 'checkOut', type: 'string', format: 'date', example: '2025-08-31'),
                                    new OA\Property(property: 'discountPercent', type: 'integer', example: 20),
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
        $promotions = $this->queryBus->ask(new GetPromotionsQuery($roomId));

        return new JsonResponse([
            'promotions' => array_map($this->serializer->serialize(...), $promotions),
        ]);
    }
}
