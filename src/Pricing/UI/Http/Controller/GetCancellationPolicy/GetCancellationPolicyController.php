<?php

declare(strict_types=1);

namespace App\Pricing\UI\Http\Controller\GetCancellationPolicy;

use App\Pricing\Application\UseCase\GetCancellationPolicy\GetCancellationPolicyQuery;
use App\Pricing\Domain\Model\CancellationPolicy;
use App\Pricing\UI\Http\Controller\CancellationPolicySerializer;
use App\Shared\Application\Bus\SyncQueryBusInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final readonly class GetCancellationPolicyController
{
    public function __construct(
        private SyncQueryBusInterface $queryBus,
        private CancellationPolicySerializer $serializer,
    ) {
    }

    #[Route(
        path: '/rooms/{roomId}/cancellation-policy',
        name: 'pricing_get_cancellation_policy',
        requirements: ['roomId' => Requirement::UUID_V4],
        methods: ['GET'],
    )]
    #[OA\Get(
        summary: 'Get the cancellation policy for a room',
        tags: ['Pricing'],
        parameters: [
            new OA\Parameter(name: 'roomId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(
                response: Response::HTTP_OK,
                description: 'Cancellation policy',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'room_id', type: 'string', format: 'uuid'),
                        new OA\Property(property: 'days_threshold', type: 'integer', example: 14),
                        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
                    ],
                ),
            ),
            new OA\Response(response: Response::HTTP_NOT_FOUND, description: 'No cancellation policy set for this room', content: new OA\MediaType(mediaType: 'application/problem+json', schema: new OA\Schema(ref: '#/components/schemas/ProblemDetail'))),
        ],
    )]
    public function __invoke(string $roomId): JsonResponse
    {
        /** @var CancellationPolicy $policy */
        $policy = $this->queryBus->ask(new GetCancellationPolicyQuery($roomId));

        return new JsonResponse($this->serializer->serialize($policy));
    }
}
