<?php

declare(strict_types=1);

namespace App\Pricing\UI\Http\Controller\UpdateRatePeriod;

use App\Pricing\Application\Service\UpdateRatePeriodCommandFactory;
use App\Pricing\Domain\Port\RatePeriodRepositoryInterface;
use App\Pricing\UI\Http\Controller\RatePeriodSerializer;
use App\Shared\Application\Bus\SyncCommandBusInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final readonly class UpdateRatePeriodController
{
    public function __construct(
        private UpdateRatePeriodCommandFactory $commandFactory,
        private SyncCommandBusInterface $commandBus,
        private RatePeriodRepositoryInterface $repository,
        private RatePeriodSerializer $serializer,
    ) {
    }

    #[Route('/rooms/{roomId}/rate-periods/{ratePeriodId}', name: 'pricing_update_rate_period', requirements: ['roomId' => Requirement::UUID_V4, 'ratePeriodId' => Requirement::UUID_V4], methods: ['PUT'])]
    #[OA\Put(
        summary: 'Update a rate period for a room',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: new Model(type: UpdateRatePeriodRequest::class)),
        ),
        tags: ['Pricing'],
        parameters: [
            new OA\Parameter(name: 'roomId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'ratePeriodId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(
                response: Response::HTTP_OK,
                description: 'Rate period updated',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                        new OA\Property(property: 'roomId', type: 'string', format: 'uuid'),
                        new OA\Property(property: 'checkIn', type: 'string', format: 'date', example: '2025-07-01'),
                        new OA\Property(property: 'checkOut', type: 'string', format: 'date', example: '2025-09-01'),
                        new OA\Property(property: 'amountCents', type: 'integer', example: 16000),
                        new OA\Property(property: 'createdAt', description: 'Unix timestamp', type: 'integer'),
                    ],
                ),
            ),
            new OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Rate period not found', content: new OA\MediaType(mediaType: 'application/problem+json', schema: new OA\Schema(ref: '#/components/schemas/ProblemDetail'))),
            new OA\Response(response: Response::HTTP_CONFLICT, description: 'Period overlaps existing rate period', content: new OA\MediaType(mediaType: 'application/problem+json', schema: new OA\Schema(ref: '#/components/schemas/ProblemDetail'))),
            new OA\Response(response: Response::HTTP_UNPROCESSABLE_ENTITY, description: 'Validation error', content: new OA\MediaType(mediaType: 'application/problem+json', schema: new OA\Schema(ref: '#/components/schemas/ValidationProblemDetail'))),
            new OA\Response(response: Response::HTTP_UNSUPPORTED_MEDIA_TYPE, description: 'Unsupported media type', content: new OA\MediaType(mediaType: 'application/problem+json', schema: new OA\Schema(ref: '#/components/schemas/ProblemDetail'))),
        ],
    )]
    public function __invoke(
        string $roomId,
        string $ratePeriodId,
        #[MapRequestPayload(acceptFormat: 'json', validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)] UpdateRatePeriodRequest $request,
    ): Response {
        $command = $this->commandFactory->create($ratePeriodId, $roomId, (string) $request->checkIn, (string) $request->checkOut, (float) $request->amount);
        $this->commandBus->execute($command);

        $ratePeriod = $this->repository->findById($command->ratePeriodId);
        if (null === $ratePeriod) {
            throw new NotFoundHttpException();
        }

        return new JsonResponse($this->serializer->serialize($ratePeriod));
    }
}
