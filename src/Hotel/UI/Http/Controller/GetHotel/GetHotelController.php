<?php

declare(strict_types=1);

namespace App\Hotel\UI\Http\Controller\GetHotel;

use App\Hotel\Application\UseCase\GetHotel\GetHotelQuery;
use App\Hotel\UI\Http\Controller\HotelSerializer;
use App\Shared\Application\Bus\SyncQueryBusInterface;
use App\Shared\Domain\ValueObject\HotelId;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final readonly class GetHotelController
{
    public function __construct(
        private SyncQueryBusInterface $queryBus,
        private HotelSerializer $hotelSerializer,
    ) {
    }

    #[Route('/hotels/{id}', name: 'hotel_get_hotel', requirements: ['id' => Requirement::UUID_V4], methods: ['GET'])]
    #[OA\Get(
        summary: 'Get a hotel by ID',
        security: [],
        tags: ['Hotels'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(
                response: Response::HTTP_OK,
                description: 'Hotel found',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                        new OA\Property(property: 'name', type: 'string', example: 'Hotel Ibis Paris'),
                        new OA\Property(property: 'streetAddress', type: 'string', example: '15 rue de Rivoli'),
                        new OA\Property(property: 'postalCode', type: 'string', example: '75001'),
                        new OA\Property(property: 'city', type: 'string', example: 'Paris'),
                        new OA\Property(property: 'country', type: 'string', example: 'FR'),
                        new OA\Property(property: 'geoPlaceId', type: 'string', nullable: true, example: '2988507'),
                        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time'),
                        new OA\Property(
                            property: 'starRating',
                            properties: [
                                new OA\Property(property: 'stars', type: 'integer', minimum: 1, maximum: 5),
                                new OA\Property(property: 'superior', type: 'boolean'),
                            ],
                            type: 'object',
                            nullable: true,
                        ),
                        new OA\Property(
                            property: 'amenities',
                            type: 'array',
                            items: new OA\Items(type: 'string', example: 'wifi'),
                        ),
                    ],
                ),
            ),
            new OA\Response(
                response: Response::HTTP_NOT_FOUND,
                description: 'Hotel not found',
                content: new OA\MediaType(
                    mediaType: 'application/problem+json',
                    schema: new OA\Schema(ref: '#/components/schemas/ProblemDetail'),
                ),
            ),
        ],
    )]
    public function __invoke(string $id): Response
    {
        $hotel = $this->queryBus->ask(new GetHotelQuery(new HotelId($id)));

        if (null === $hotel) {
            throw new NotFoundHttpException();
        }

        return new JsonResponse($this->hotelSerializer->serialize($hotel));
    }
}
