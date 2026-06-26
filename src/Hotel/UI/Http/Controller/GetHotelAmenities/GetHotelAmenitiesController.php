<?php

declare(strict_types=1);

namespace App\Hotel\UI\Http\Controller\GetHotelAmenities;

use App\Hotel\Application\UseCase\GetHotelAmenities\GetHotelAmenitiesQuery;
use App\Shared\Application\Bus\SyncQueryBusInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final readonly class GetHotelAmenitiesController
{
    public function __construct(
        private SyncQueryBusInterface $queryBus,
    ) {
    }

    #[Route(path: '/hotel-amenities', name: 'hotel_get_amenities_catalogue', methods: ['GET'])]
    #[OA\Get(
        summary: 'List all possible hotel amenities',
        tags: ['Hotels'],
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
                            example: ['pool', 'spa', 'gym'],
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
        $amenities = $this->queryBus->ask(new GetHotelAmenitiesQuery());

        return new JsonResponse(['amenities' => $amenities]);
    }
}
