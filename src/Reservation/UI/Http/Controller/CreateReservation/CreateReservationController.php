<?php

declare(strict_types=1);

namespace App\Reservation\UI\Http\Controller\CreateReservation;

use App\Reservation\Application\Service\CreateReservationCommandFactory;
use App\Reservation\Application\UseCase\GetReservation\GetReservationQuery;
use App\Reservation\UI\Http\Controller\ReservationSerializer;
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

final readonly class CreateReservationController
{
    public function __construct(
        private CreateReservationCommandFactory $commandFactory,
        private SyncCommandBusInterface $commandBus,
        private SyncQueryBusInterface $queryBus,
        private ReservationSerializer $serializer,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    #[Route('/reservations', name: 'reservation_create', methods: ['POST'])]
    #[OA\Post(
        summary: 'Create a reservation',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: new Model(type: CreateReservationRequest::class)),
        ),
        tags: ['Reservation'],
        responses: [
            new OA\Response(
                response: Response::HTTP_CREATED,
                description: 'Reservation created',
                headers: [new OA\Header(header: 'Location', description: 'URL of the created reservation', schema: new OA\Schema(type: 'string', format: 'uri'))],
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                        new OA\Property(property: 'roomId', type: 'string', format: 'uuid'),
                        new OA\Property(property: 'bookerId', type: 'string', format: 'uuid'),
                        new OA\Property(property: 'checkIn', type: 'string', format: 'date', example: '2026-06-01'),
                        new OA\Property(property: 'checkOut', type: 'string', format: 'date', example: '2026-06-05'),
                        new OA\Property(property: 'totalPrice', type: 'integer', example: 42000),
                        new OA\Property(property: 'guestCount', type: 'integer', example: 2),
                        new OA\Property(property: 'status', type: 'string', example: 'pending'),
                        new OA\Property(
                            property: 'cancellationTerms',
                            properties: [
                                new OA\Property(property: 'daysThreshold', type: 'integer', nullable: true, example: 7),
                            ],
                            type: 'object',
                        ),
                        new OA\Property(
                            property: 'priceBreakdown',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'date', type: 'string', format: 'date', example: '2026-06-01'),
                                    new OA\Property(property: 'rateAmountCents', type: 'integer', example: 10000),
                                    new OA\Property(property: 'discountPercent', type: 'integer', nullable: true, example: 10),
                                    new OA\Property(property: 'effectiveAmountCents', type: 'integer', example: 9000),
                                ],
                                type: 'object',
                            ),
                        ),
                        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time'),
                    ],
                ),
            ),
            new OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Booker not found', content: new OA\MediaType(mediaType: 'application/problem+json', schema: new OA\Schema(ref: '#/components/schemas/ProblemDetail'))),
            new OA\Response(response: Response::HTTP_CONFLICT, description: 'Room not available', content: new OA\MediaType(mediaType: 'application/problem+json', schema: new OA\Schema(ref: '#/components/schemas/ProblemDetail'))),
            new OA\Response(response: Response::HTTP_UNPROCESSABLE_ENTITY, description: 'No pricing configured or validation error', content: new OA\MediaType(mediaType: 'application/problem+json', schema: new OA\Schema(ref: '#/components/schemas/ValidationProblemDetail'))),
            new OA\Response(response: Response::HTTP_UNSUPPORTED_MEDIA_TYPE, description: 'Unsupported media type', content: new OA\MediaType(mediaType: 'application/problem+json', schema: new OA\Schema(ref: '#/components/schemas/ProblemDetail'))),
        ],
    )]
    public function __invoke(
        #[MapRequestPayload(acceptFormat: 'json', validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)] CreateReservationRequest $request,
    ): Response {
        $command = $this->commandFactory->create(
            (string) $request->roomTypeId,
            (string) $request->bookerId,
            (string) $request->checkIn,
            (string) $request->checkOut,
            (int) $request->guestCount,
        );
        $this->commandBus->execute($command);

        $reservation = $this->queryBus->ask(new GetReservationQuery($command->id));
        if (null === $reservation) {
            throw new NotFoundHttpException();
        }

        return new JsonResponse(
            $this->serializer->serialize($reservation),
            Response::HTTP_CREATED,
            ['Location' => $this->urlGenerator->generate('reservation_get', ['id' => $command->id])],
        );
    }
}
