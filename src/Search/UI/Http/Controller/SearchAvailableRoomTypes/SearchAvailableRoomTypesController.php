<?php

declare(strict_types=1);

namespace App\Search\UI\Http\Controller\SearchAvailableRoomTypes;

use App\Search\Application\UseCase\SearchAvailableRoomTypes\SearchAvailableRoomTypesQuery;
use App\Search\Domain\AvailableRoomType;
use App\Shared\Application\Bus\SyncQueryBusInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

#[Route(
    path: '/search',
    name: 'search_available_room_types',
    methods: ['GET'],
)]
#[OA\Get(
    summary: 'Search available room types',
    security: [],
    tags: ['Search'],
    responses: [
        new OA\Response(
            response: Response::HTTP_OK,
            description: 'List of available hotel room types matching the criteria',
            content: new OA\JsonContent(
                type: 'array',
                items: new OA\Items(
                    properties: [
                        new OA\Property(property: 'hotelId', type: 'string', format: 'uuid'),
                        new OA\Property(property: 'hotelName', type: 'string', example: 'Grand Hôtel du Louvre'),
                        new OA\Property(property: 'city', type: 'string', example: 'Paris'),
                        new OA\Property(property: 'country', type: 'string', example: 'France'),
                        new OA\Property(property: 'geoPlaceId', type: 'string', example: '2988507', nullable: true),
                        new OA\Property(property: 'starRating', type: 'integer', example: 4, nullable: true, maximum: 5, minimum: 1),
                        new OA\Property(property: 'hotelAmenities', type: 'array', items: new OA\Items(type: 'string'), example: ['pool', 'spa']),
                        new OA\Property(property: 'roomTypeId', type: 'string', format: 'uuid'),
                        new OA\Property(property: 'roomTypeName', type: 'string', example: 'Deluxe Double'),
                        new OA\Property(property: 'guestCapacity', type: 'integer', example: 2, minimum: 1),
                        new OA\Property(property: 'bedComposition', type: 'object', example: ['double' => 1]),
                        new OA\Property(property: 'roomAmenities', type: 'array', items: new OA\Items(type: 'string'), example: ['air_conditioning', 'minibar']),
                        new OA\Property(property: 'basePriceCents', description: 'Base price in euro cents', type: 'integer', example: 18000, nullable: true),
                    ],
                    type: 'object',
                ),
            ),
        ),
        new OA\Response(
            response: Response::HTTP_UNPROCESSABLE_ENTITY,
            description: 'Validation error',
            content: new OA\MediaType(
                mediaType: 'application/problem+json',
                schema: new OA\Schema(ref: '#/components/schemas/ValidationProblemDetail'),
            ),
        ),
    ],
)]
final readonly class SearchAvailableRoomTypesController
{
    public function __construct(private SyncQueryBusInterface $queryBus)
    {
    }

    public function __invoke(
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)]
        SearchAvailableRoomTypesRequest $request,
    ): JsonResponse {
        /** @var list<AvailableRoomType> $results */
        $results = $this->queryBus->ask(new SearchAvailableRoomTypesQuery(
            geoPlaceId: $request->geoPlaceId,
            checkIn: new \DateTimeImmutable($request->checkIn),
            checkOut: new \DateTimeImmutable($request->checkOut),
            guests: $request->guests,
        ));

        return new JsonResponse($results);
    }
}
