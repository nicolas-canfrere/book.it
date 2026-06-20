<?php

declare(strict_types=1);

namespace App\Hotel\UI\Http\Controller\RegisterHotel;

use App\Hotel\Application\Service\RegisterHotelCommandFactory;
use App\Hotel\Application\UseCase\GetHotel\GetHotelQuery;
use App\Hotel\UI\Http\Controller\HotelSerializer;
use App\Shared\Application\Bus\SyncCommandBusInterface;
use App\Shared\Application\Bus\SyncQueryBusInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class RegisterHotelController
{
    public function __construct(
        private RegisterHotelCommandFactory $commandFactory,
        private SyncCommandBusInterface $commandBus,
        private SyncQueryBusInterface $queryBus,
        private HotelSerializer $hotelSerializer,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    #[Route('/hotels', name: 'hotel_register_hotel', methods: ['POST'])]
    #[OA\Post(
        summary: 'Register a new hotel',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: new Model(type: RegisterHotelRequest::class)),
        ),
        tags: ['Hotels'],
        responses: [
            new OA\Response(
                response: Response::HTTP_CREATED,
                description: 'Hotel registered',
                headers: [new OA\Header(header: 'Location', description: 'URL of the created hotel', schema: new OA\Schema(type: 'string', format: 'uri'))],
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
                    ],
                ),
            ),
            new OA\Response(
                response: Response::HTTP_NOT_FOUND,
                description: 'Not found',
                content: new OA\MediaType(
                    mediaType: 'application/problem+json',
                    schema: new OA\Schema(ref: '#/components/schemas/ProblemDetail'),
                ),
            ),
            new OA\Response(
                response: Response::HTTP_CONFLICT,
                description: 'Hotel already exists',
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
        #[MapRequestPayload(acceptFormat: 'json')]
        RegisterHotelRequest $request,
    ): Response {
        $command = $this->commandFactory->create(
            $request->name,
            $request->streetAddress,
            $request->postalCode,
            $request->city,
            $request->country,
            $request->stars,
            $request->superior,
            $request->geoPlaceId,
        );
        $this->commandBus->execute($command);

        $hotel = $this->queryBus->ask(new GetHotelQuery($command->id));
        if (null === $hotel) {
            throw new NotFoundHttpException();
        }

        return new JsonResponse(
            $this->hotelSerializer->serialize($hotel),
            Response::HTTP_CREATED,
            ['Location' => $this->urlGenerator->generate('hotel_get_hotel', ['id' => $command->id->value])],
        );
    }
}
