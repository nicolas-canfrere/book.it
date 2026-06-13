<?php

declare(strict_types=1);

namespace App\Hotel\UI\Http\Controller\ClassifyHotel;

use App\Hotel\Application\UseCase\ClassifyHotel\ClassifyHotelCommand;
use App\Hotel\Domain\ValueObject\StarRating;
use App\Shared\Application\Bus\SyncCommandBusInterface;
use App\Shared\Domain\ValueObject\HotelId;
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
        summary: 'Set or update the Star Rating of a Hotel',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: new Model(type: ClassifyHotelRequest::class)),
        ),
        tags: ['Hotels'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: Response::HTTP_NO_CONTENT, description: 'Star Rating updated'),
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
            new OA\Response(response: Response::HTTP_UNSUPPORTED_MEDIA_TYPE, description: 'Unsupported media type', content: new OA\MediaType(mediaType: 'application/problem+json', schema: new OA\Schema(ref: '#/components/schemas/ProblemDetail'))),
        ],
    )]
    public function __invoke(
        string $id,
        #[MapRequestPayload(acceptFormat: 'json', validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)]
        ClassifyHotelRequest $request,
    ): Response {
        $this->commandBus->execute(new ClassifyHotelCommand(
            hotelId: new HotelId($id),
            starRating: null !== $request->stars ? new StarRating($request->stars, $request->superior) : null,
        ));

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
