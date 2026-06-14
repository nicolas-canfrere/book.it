<?php

declare(strict_types=1);

namespace App\Pricing\UI\Http\Controller\GetBaseRate;

use App\Pricing\Application\UseCase\GetBaseRate\GetBaseRateQuery;
use App\Pricing\UI\Http\Controller\BaseRateSerializer;
use App\Shared\Application\Bus\SyncQueryBusInterface;
use App\Shared\Domain\ValueObject\RoomId;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final readonly class GetBaseRateController
{
    public function __construct(
        private SyncQueryBusInterface $queryBus,
        private BaseRateSerializer $serializer,
    ) {
    }

    #[Route('/rooms/{roomId}/base-rate', name: 'pricing_get_base_rate', requirements: ['roomId' => Requirement::UUID_V4], methods: ['GET'])]
    #[OA\Get(
        summary: 'Get the base rate for a room',
        tags: ['Pricing'],
        parameters: [
            new OA\Parameter(name: 'roomId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(
                response: Response::HTTP_OK,
                description: 'Base rate retrieved',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'roomId', type: 'string', format: 'uuid'),
                        new OA\Property(property: 'amountCents', type: 'integer', example: 12000),
                        new OA\Property(property: 'updatedAt', type: 'string', format: 'date-time'),
                    ],
                ),
            ),
            new OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Room not found or base rate not configured', content: new OA\MediaType(mediaType: 'application/problem+json', schema: new OA\Schema(ref: '#/components/schemas/ProblemDetail'))),
        ],
    )]
    public function __invoke(string $roomId): Response
    {
        $baseRate = $this->queryBus->ask(new GetBaseRateQuery(new RoomId($roomId)));

        return new JsonResponse($this->serializer->serialize($baseRate));
    }
}
