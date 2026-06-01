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
                        new OA\Property(property: 'starRating', type: 'integer', nullable: true, minimum: 1, maximum: 5, example: 4),
                        new OA\Property(property: 'hotelAmenities', type: 'array', items: new OA\Items(type: 'string'), example: ['pool', 'spa']),
                        new OA\Property(property: 'roomTypeId', type: 'string', format: 'uuid'),
                        new OA\Property(property: 'roomTypeName', type: 'string', example: 'Deluxe Double'),
                        new OA\Property(property: 'guestCapacity', type: 'integer', minimum: 1, example: 2),
                        new OA\Property(property: 'bedComposition', type: 'object', example: ['double' => 1]),
                        new OA\Property(property: 'roomAmenities', type: 'array', items: new OA\Items(type: 'string'), example: ['air_conditioning', 'minibar']),
                        new OA\Property(property: 'basePriceCents', type: 'integer', nullable: true, description: 'Base price in euro cents', example: 18000),
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
            city: (string) $request->city,
            checkIn: new \DateTimeImmutable((string) $request->checkIn),
            checkOut: new \DateTimeImmutable((string) $request->checkOut),
            guests: (int) $request->guests,
        ));

        return new JsonResponse($results);
    }
}
