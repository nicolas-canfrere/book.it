<?php

declare(strict_types=1);

namespace App\Availability\UI\Http\Controller\BlockPeriod;

use App\Availability\Application\Service\BlockPeriodCommandFactory;
use App\Availability\Application\UseCase\GetBlockedPeriod\GetBlockedPeriodQuery;
use App\Availability\UI\Http\Controller\BlockedPeriodSerializer;
use App\Shared\Application\Bus\SyncCommandBusInterface;
use App\Shared\Application\Bus\SyncQueryBusInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final readonly class BlockPeriodController
{
    public function __construct(
        private BlockPeriodCommandFactory $commandFactory,
        private SyncCommandBusInterface $commandBus,
        private SyncQueryBusInterface $queryBus,
        private BlockedPeriodSerializer $serializer,
    ) {
    }

    #[Route('/rooms/{roomId}/blocked-periods', name: 'availability_block_period', requirements: ['roomId' => Requirement::UUID_V4], methods: ['POST'])]
    #[OA\Post(
        summary: 'Block a period for a room',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: new Model(type: BlockPeriodRequest::class)),
        ),
        tags: ['Availability'],
        parameters: [
            new OA\Parameter(name: 'roomId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(
                response: Response::HTTP_CREATED,
                description: 'Period blocked',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                        new OA\Property(property: 'roomId', type: 'string', format: 'uuid'),
                        new OA\Property(property: 'checkIn', type: 'string', format: 'date', example: '2025-06-10'),
                        new OA\Property(property: 'checkOut', type: 'string', format: 'date', example: '2025-06-13'),
                        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time'),
                    ],
                ),
            ),
            new OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Room not found', content: new OA\MediaType(mediaType: 'application/problem+json', schema: new OA\Schema(ref: '#/components/schemas/ProblemDetail'))),
            new OA\Response(response: Response::HTTP_CONFLICT, description: 'Period overlaps existing block', content: new OA\MediaType(mediaType: 'application/problem+json', schema: new OA\Schema(ref: '#/components/schemas/ProblemDetail'))),
            new OA\Response(response: Response::HTTP_UNPROCESSABLE_ENTITY, description: 'Validation error', content: new OA\MediaType(mediaType: 'application/problem+json', schema: new OA\Schema(ref: '#/components/schemas/ValidationProblemDetail'))),
            new OA\Response(response: Response::HTTP_UNSUPPORTED_MEDIA_TYPE, description: 'Unsupported media type', content: new OA\MediaType(mediaType: 'application/problem+json', schema: new OA\Schema(ref: '#/components/schemas/ProblemDetail'))),
        ],
    )]
    public function __invoke(
        string $roomId,
        #[MapRequestPayload(acceptFormat: 'json')] BlockPeriodRequest $request,
    ): Response {
        $command = $this->commandFactory->create($roomId, (string) $request->checkIn, (string) $request->checkOut);
        $this->commandBus->execute($command);

        $period = $this->queryBus->ask(new GetBlockedPeriodQuery($command->id));
        if (null === $period) {
            throw new NotFoundHttpException();
        }

        return new JsonResponse($this->serializer->serialize($period), Response::HTTP_CREATED);
    }
}
