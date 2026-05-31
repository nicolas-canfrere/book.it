<?php

declare(strict_types=1);

namespace App\Hotel\UI\Http\Controller\DeclareHotelAmenities;

use App\Hotel\Application\UseCase\DeclareHotelAmenities\DeclareHotelAmenitiesCommand;
use App\Shared\Application\Bus\SyncCommandBusInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final readonly class DeclareHotelAmenitiesController
{
    public function __construct(
        private SyncCommandBusInterface $commandBus,
    ) {
    }

    #[Route(
        path: '/hotels/{id}/amenities',
        name: 'hotel_declare_amenities',
        requirements: ['id' => Requirement::UUID_V4],
        methods: ['PATCH'],
    )]
    #[OA\Patch(
        summary: 'Declare or replace the Hotel Amenity list',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: new Model(type: DeclareHotelAmenitiesRequest::class)),
        ),
        tags: ['Hotels'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: Response::HTTP_NO_CONTENT, description: 'Amenities declared'),
            new OA\Response(
                response: Response::HTTP_NOT_FOUND,
                description: 'Hotel not found',
                content: new OA\MediaType(
                    mediaType: 'application/problem+json',
                    schema: new OA\Schema(ref: '#/components/schemas/ProblemDetail'),
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
        string $id,
        #[MapRequestPayload(acceptFormat: 'json', validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)]
        DeclareHotelAmenitiesRequest $request,
    ): Response {
        $this->commandBus->execute(new DeclareHotelAmenitiesCommand(
            hotelId: $id,
            amenities: $request->amenities,
        ));

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
