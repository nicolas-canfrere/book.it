<?php

declare(strict_types=1);

namespace App\Hotel\UI\Http\Controller\ListHotels;

use App\Hotel\Application\UseCase\ListHotels\ListHotelsQuery;
use App\Hotel\Domain\Model\Hotel;
use App\Shared\Application\Bus\SyncQueryBusInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

final readonly class ListHotelsController
{
    public function __construct(
        private SyncQueryBusInterface $queryBus,
    ) {
    }

    #[Route('/api/hotels', name: 'hotel_list_hotels', methods: ['GET'])]
    #[OA\Get(
        summary: 'List hotels (paginated)',
        tags: ['Hotels'],
        responses: [
            new OA\Response(
                response: Response::HTTP_OK,
                description: 'Paginated hotel catalogue',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                                    new OA\Property(property: 'name', type: 'string'),
                                    new OA\Property(property: 'streetAddress', type: 'string'),
                                    new OA\Property(property: 'postalCode', type: 'string'),
                                    new OA\Property(property: 'city', type: 'string'),
                                    new OA\Property(property: 'country', type: 'string'),
                                    new OA\Property(property: 'createdAt', type: 'integer'),
                                ],
                                type: 'object',
                            ),
                        ),
                        new OA\Property(
                            property: 'meta',
                            properties: [
                                new OA\Property(property: 'page', type: 'integer', example: 1),
                                new OA\Property(property: 'limit', type: 'integer', example: 20),
                                new OA\Property(property: 'total', type: 'integer', example: 143),
                                new OA\Property(property: 'totalPages', type: 'integer', example: 8),
                            ],
                            type: 'object',
                        ),
                    ],
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
    public function __invoke(
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)] ListHotelsRequest $request = new ListHotelsRequest(),
    ): Response {
        $hotelPage = $this->queryBus->ask(new ListHotelsQuery(
            $request->page,
            $request->limit,
            $request->city,
            $request->country,
        ));

        return new JsonResponse([
            'data' => array_map(
                fn(Hotel $hotel) => [
                    'id' => $hotel->id,
                    'name' => $hotel->name,
                    'streetAddress' => $hotel->address->streetAddress,
                    'postalCode' => $hotel->address->postalCode,
                    'city' => $hotel->address->city,
                    'country' => $hotel->address->country,
                    'createdAt' => $hotel->createdAt->getTimestamp(),
                ],
                $hotelPage->hotels,
            ),
            'meta' => [
                'page' => $request->page,
                'limit' => $request->limit,
                'total' => $hotelPage->total,
                'totalPages' => (int) ceil($hotelPage->total / $request->limit),
            ],
        ]);
    }
}
