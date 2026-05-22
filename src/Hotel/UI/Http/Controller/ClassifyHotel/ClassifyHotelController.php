<?php

declare(strict_types=1);

namespace App\Hotel\UI\Http\Controller\ClassifyHotel;

use App\Hotel\Application\UseCase\ClassifyHotel\ClassifyHotelCommand;
use App\Shared\Application\Bus\SyncCommandBusInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final readonly class ClassifyHotelController
{
    public function __construct(
        private SyncCommandBusInterface $commandBus,
    ) {
    }

    #[Route(
        path: '/hotels/{id}/star-rating',
        name: 'hotel_classify',
        requirements: ['id' => Requirement::UUID_V4],
        methods: ['PATCH'],
    )]
    #[OA\Patch(
        path: '/hotels/{id}/star-rating',
        summary: 'Set or update the Star Rating of a Hotel',
        tags: ['Hotels'],
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(ref: new Model(type: ClassifyHotelRequest::class)),
    )]
    #[OA\Response(response: 204, description: 'Star Rating updated')]
    #[OA\Response(response: 404, description: 'Hotel not found', content: new OA\MediaType(mediaType: 'application/problem+json', schema: new OA\Schema(ref: '#/components/schemas/ProblemDetail')))]
    #[OA\Response(response: 422, description: 'Validation error', content: new OA\MediaType(mediaType: 'application/problem+json', schema: new OA\Schema(ref: '#/components/schemas/ValidationProblemDetail')))]
    public function __invoke(
        string $id,
        #[MapRequestPayload(acceptFormat: 'json', validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)]
        ClassifyHotelRequest $request,
    ): Response {
        $this->commandBus->execute(new ClassifyHotelCommand(
            hotelId: $id,
            stars: $request->stars,
            superior: $request->superior,
        ));

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
