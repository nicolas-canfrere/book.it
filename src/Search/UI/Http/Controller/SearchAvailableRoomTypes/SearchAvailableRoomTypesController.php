<?php

declare(strict_types=1);

namespace App\Search\UI\Http\Controller\SearchAvailableRoomTypes;

use App\Search\Application\UseCase\SearchAvailableRoomTypes\SearchAvailableRoomTypesQuery;
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
            content: new OA\JsonContent(type: 'array', items: new OA\Items(type: 'object')),
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
        /** @var list<array<string, mixed>> $results */
        $results = $this->queryBus->ask(new SearchAvailableRoomTypesQuery( // @phpstan-ignore argument.type
            city: (string) $request->city,
            checkIn: new \DateTimeImmutable((string) $request->checkIn),
            checkOut: new \DateTimeImmutable((string) $request->checkOut),
            guests: (int) $request->guests,
        ));

        return new JsonResponse($results);
    }
}
