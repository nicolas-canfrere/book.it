<?php

declare(strict_types=1);

namespace App\Pricing\UI\Http\Controller\SetBaseRate;

use App\Pricing\Application\Service\SetBaseRateCommandFactory;
use App\Pricing\Application\UseCase\GetBaseRate\GetBaseRateQuery;
use App\Pricing\UI\Http\Controller\BaseRateSerializer;
use App\Shared\Application\Bus\SyncCommandBusInterface;
use App\Shared\Application\Bus\SyncQueryBusInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final readonly class SetBaseRateController
{
    public function __construct(
        private SetBaseRateCommandFactory $commandFactory,
        private SyncCommandBusInterface $commandBus,
        private SyncQueryBusInterface $queryBus,
        private BaseRateSerializer $serializer,
    ) {
    }

    #[Route('/rooms/{roomId}/base-rate', name: 'pricing_set_base_rate', requirements: ['roomId' => Requirement::UUID_V4], methods: ['PUT'])]
    #[OA\Put(
        summary: 'Set the base rate for a room',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: new Model(type: SetBaseRateRequest::class)),
        ),
        tags: ['Pricing'],
        parameters: [
            new OA\Parameter(name: 'roomId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(
                response: Response::HTTP_OK,
                description: 'Base rate set',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'roomId', type: 'string', format: 'uuid'),
                        new OA\Property(property: 'amountCents', type: 'integer', example: 12000),
                        new OA\Property(property: 'updatedAt', description: 'Unix timestamp', type: 'integer'),
                    ],
                ),
            ),
            new OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Room not found', content: new OA\MediaType(mediaType: 'application/problem+json', schema: new OA\Schema(ref: '#/components/schemas/ProblemDetail'))),
            new OA\Response(response: Response::HTTP_UNPROCESSABLE_ENTITY, description: 'Validation error', content: new OA\MediaType(mediaType: 'application/problem+json', schema: new OA\Schema(ref: '#/components/schemas/ValidationProblemDetail'))),
        ],
    )]
    public function __invoke(
        string $roomId,
        #[MapRequestPayload(acceptFormat: 'json', validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)] SetBaseRateRequest $request,
    ): Response {
        $command = $this->commandFactory->create($roomId, (float) $request->amount);
        $this->commandBus->execute($command);

        $baseRate = $this->queryBus->ask(new GetBaseRateQuery($roomId));

        return new JsonResponse($this->serializer->serialize($baseRate));
    }
}
