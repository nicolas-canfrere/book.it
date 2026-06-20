<?php

declare(strict_types=1);

namespace App\Geo\UI\Http\Controller\SearchGeoPlaces;

use App\Geo\Application\UseCase\SearchGeoPlaces\SearchGeoPlacesQuery;
use App\Geo\Domain\GeoPlace;
use App\Geo\UI\Http\Controller\GeoPlaceSerializer;
use App\Shared\Application\Bus\SyncQueryBusInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

#[Route(
    path: '/geo/places',
    name: 'search_geo_places',
    methods: ['GET'],
)]
#[OA\Get(
    summary: 'Search Geo Places by fuzzy name match',
    tags: ['Geo'],
    responses: [
        new OA\Response(
            response: Response::HTTP_OK,
            description: 'Geo Places matching the query, ranked by similarity',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(
                        property: 'data',
                        type: 'array',
                        items: new OA\Items(
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 2988507),
                                new OA\Property(property: 'name', type: 'string', example: 'Paris'),
                                new OA\Property(property: 'countryCode', type: 'string', example: 'FR'),
                                new OA\Property(property: 'admin1Code', type: 'string', nullable: true, example: '11'),
                            ],
                            type: 'object',
                        ),
                    ),
                ],
                type: 'object',
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
final readonly class SearchGeoPlacesController
{
    public function __construct(
        private SyncQueryBusInterface $queryBus,
        private GeoPlaceSerializer $serializer,
    ) {
    }

    public function __invoke(
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)]
        SearchGeoPlacesRequest $request,
    ): JsonResponse {
        /** @var list<GeoPlace> $results */
        $results = $this->queryBus->ask(new SearchGeoPlacesQuery(query: (string) $request->query));

        return new JsonResponse(['data' => array_map($this->serializer->serialize(...), $results)]);
    }
}
