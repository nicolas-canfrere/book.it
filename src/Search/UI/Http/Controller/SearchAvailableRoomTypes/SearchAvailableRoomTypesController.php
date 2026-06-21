<?php

declare(strict_types=1);

namespace App\Search\UI\Http\Controller\SearchAvailableRoomTypes;

use App\Search\Application\UseCase\SearchAvailableRoomTypes\SearchAvailableRoomTypesQuery;
use App\Search\Domain\AvailableRoomType;
use App\Shared\Application\Bus\SyncQueryBusInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route(
    path: '/search',
    name: 'search_available_room_types',
    methods: ['GET'],
)]
#[OA\Get(
    summary: 'Search available room types',
    tags: ['Search'],
    parameters: [
        new OA\Parameter(
            name: 'geoPlaceId',
            description: 'GeoNames id of the place selected via Geo Place Search autocomplete — sole filtering criterion',
            in: 'query',
            required: true,
            schema: new OA\Schema(type: 'string', example: '2988507', maxLength: 255),
        ),
        new OA\Parameter(
            name: 'city',
            description: 'Free-text city name typed by the visitor — informational only, not used for filtering',
            in: 'query',
            required: true,
            schema: new OA\Schema(type: 'string', example: 'Paris', maxLength: 255),
        ),
        new OA\Parameter(
            name: 'checkIn',
            in: 'query',
            required: true,
            schema: new OA\Schema(type: 'string', format: 'date', example: '2026-07-01'),
        ),
        new OA\Parameter(
            name: 'checkOut',
            in: 'query',
            required: true,
            schema: new OA\Schema(type: 'string', format: 'date', example: '2026-07-05'),
        ),
        new OA\Parameter(
            name: 'guests',
            in: 'query',
            required: true,
            schema: new OA\Schema(type: 'integer', example: 2, maximum: 20, minimum: 1),
        ),
    ],
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
    public function __construct(
        private SyncQueryBusInterface $queryBus,
        private ValidatorInterface $validator,
    ) {
    }

    public function __invoke(Request $httpRequest): JsonResponse
    {
        $request = new SearchAvailableRoomTypesRequest(
            geoPlaceId: $httpRequest->query->getString('geoPlaceId'),
            city: $httpRequest->query->getString('city'),
            checkIn: $httpRequest->query->getString('checkIn'),
            checkOut: $httpRequest->query->getString('checkOut'),
            guests: $httpRequest->query->getInt('guests'),
        );

        $violations = $this->validator->validate($request);
        if (\count($violations) > 0) {
            throw HttpException::fromStatusCode(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                'Validation failed.',
                new ValidationFailedException($request, $violations),
            );
        }

        /** @var list<AvailableRoomType> $results */
        $results = $this->queryBus->ask(new SearchAvailableRoomTypesQuery(
            geoPlaceId: (string) $request->geoPlaceId,
            checkIn: new \DateTimeImmutable((string) $request->checkIn),
            checkOut: new \DateTimeImmutable((string) $request->checkOut),
            guests: (int) $request->guests,
        ));

        return new JsonResponse($results);
    }
}
