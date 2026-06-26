<?php

declare(strict_types=1);

namespace App\Room\UI\Http\Controller\GetRoomTypeAmenities;

use App\Room\Application\UseCase\GetRoomTypeAmenities\GetRoomTypeAmenitiesQuery;
use App\Shared\Application\Bus\SyncQueryBusInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final readonly class GetRoomTypeAmenitiesController
{
    public function __construct(
        private SyncQueryBusInterface $queryBus,
    ) {
    }

    #[Route(path: '/room-type-amenities', name: 'room_get_amenities_catalogue', methods: ['GET'])]
    #[OA\Get(
        summary: 'List all possible room type amenities',
        tags: ['Room Types'],
        responses: [
            new OA\Response(
                response: Response::HTTP_OK,
                description: 'Amenities catalogue',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'amenities',
                            type: 'array',
                            items: new OA\Items(type: 'string'),
                            example: ['wifi', 'tv', 'balcony'],
                        ),
                    ],
                ),
            ),
            new OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Missing or invalid authentication token'),
        ],
    )]
    public function __invoke(): Response
    {
        /** @var string[] $amenities */
        $amenities = $this->queryBus->ask(new GetRoomTypeAmenitiesQuery());

        return new JsonResponse(['amenities' => $amenities]);
    }
}
