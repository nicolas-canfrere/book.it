<?php

declare(strict_types=1);

namespace App\Pricing\UI\Http\Controller\CreateRatePeriod;

use App\Pricing\Application\Service\CreateRatePeriodCommandFactory;
use App\Pricing\Domain\Model\RatePeriod;
use App\Pricing\UI\Http\Controller\RatePeriodSerializer;
use App\Shared\Application\Bus\SyncCommandBusInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final readonly class CreateRatePeriodController
{
    public function __construct(
        private CreateRatePeriodCommandFactory $commandFactory,
        private SyncCommandBusInterface $commandBus,
        private RatePeriodSerializer $serializer,
    ) {
    }

    #[Route('/rooms/{roomId}/rate-periods', name: 'pricing_create_rate_period', requirements: ['roomId' => Requirement::UUID_V4], methods: ['POST'])]
    #[OA\Post(
        summary: 'Create a rate period for a room',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: new Model(type: CreateRatePeriodRequest::class)),
        ),
        tags: ['Pricing'],
        parameters: [
            new OA\Parameter(name: 'roomId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(
                response: Response::HTTP_CREATED,
                description: 'Rate period created',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                        new OA\Property(property: 'roomId', type: 'string', format: 'uuid'),
                        new OA\Property(property: 'checkIn', type: 'string', format: 'date', example: '2025-07-01'),
                        new OA\Property(property: 'checkOut', type: 'string', format: 'date', example: '2025-08-31'),
                        new OA\Property(property: 'amountCents', type: 'integer', example: 15000),
                        new OA\Property(property: 'createdAt', description: 'Unix timestamp', type: 'integer'),
                    ],
                ),
            ),
            new OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Room not found', content: new OA\MediaType(mediaType: 'application/problem+json', schema: new OA\Schema(ref: '#/components/schemas/ProblemDetail'))),
            new OA\Response(response: Response::HTTP_CONFLICT, description: 'Period overlaps existing rate period', content: new OA\MediaType(mediaType: 'application/problem+json', schema: new OA\Schema(ref: '#/components/schemas/ProblemDetail'))),
            new OA\Response(response: Response::HTTP_UNPROCESSABLE_ENTITY, description: 'Validation error', content: new OA\MediaType(mediaType: 'application/problem+json', schema: new OA\Schema(ref: '#/components/schemas/ValidationProblemDetail'))),
            new OA\Response(response: Response::HTTP_UNSUPPORTED_MEDIA_TYPE, description: 'Unsupported media type', content: new OA\MediaType(mediaType: 'application/problem+json', schema: new OA\Schema(ref: '#/components/schemas/ProblemDetail'))),
        ],
    )]
    public function __invoke(
        string $roomId,
        #[MapRequestPayload(acceptFormat: 'json', validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)] CreateRatePeriodRequest $request,
    ): Response {
        $command = $this->commandFactory->create($roomId, (string) $request->checkIn, (string) $request->checkOut, (float) $request->amount);
        $this->commandBus->execute($command);

        $ratePeriod = new RatePeriod(
            id: $command->id,
            roomId: $command->roomId,
            checkIn: $command->checkIn,
            checkOut: $command->checkOut,
            amountCents: $command->amountCents,
            createdAt: $command->createdAt,
            updatedAt: $command->updatedAt,
        );

        return new JsonResponse($this->serializer->serialize($ratePeriod), Response::HTTP_CREATED);
    }
}
