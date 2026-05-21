<?php

declare(strict_types=1);

namespace App\Hotel\UI\Http\Controller\GetHotel;

use App\Hotel\Application\UseCase\GetHotel\GetHotelQuery;
use App\Hotel\UI\Http\Controller\HotelSerializer;
use App\Shared\Application\Bus\SyncQueryBusInterface;
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
                        new OA\Property(property: 'createdAt', description: 'Unix timestamp', type: 'integer'),
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
        $hotel = $this->queryBus->ask(new GetHotelQuery($id));

        if (null === $hotel) {
            throw new NotFoundHttpException();
        }

        return new JsonResponse($this->hotelSerializer->serialize($hotel));
    }
}
