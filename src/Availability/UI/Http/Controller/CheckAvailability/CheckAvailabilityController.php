<?php

declare(strict_types=1);

namespace App\Availability\UI\Http\Controller\CheckAvailability;

use App\Availability\Application\UseCase\CheckAvailability\CheckAvailabilityQuery;
use App\Shared\Application\Bus\SyncQueryBusInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final readonly class CheckAvailabilityController
{
    public function __construct(private SyncQueryBusInterface $queryBus)
    {
    }

    #[Route('/rooms/{roomId}/availability', name: 'availability_check', requirements: ['roomId' => Requirement::UUID_V4], methods: ['GET'])]
    #[OA\Get(
        summary: 'Check whether a room is available for a given period',
        tags: ['Availability'],
        parameters: [
            new OA\Parameter(name: 'roomId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(
                response: Response::HTTP_OK,
                description: 'Availability result',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'available', type: 'boolean')],
                ),
            ),
            new OA\Response(response: Response::HTTP_UNPROCESSABLE_ENTITY, description: 'Validation error', content: new OA\MediaType(mediaType: 'application/problem+json', schema: new OA\Schema(ref: '#/components/schemas/ValidationProblemDetail'))),
        ],
    )]
    public function __invoke(
        string $roomId,
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)] CheckAvailabilityRequest $request,
    ): Response {
        $available = $this->queryBus->ask(new CheckAvailabilityQuery(
            roomId: $roomId,
            checkIn: new \DateTimeImmutable((string) $request->checkIn),
            checkOut: new \DateTimeImmutable((string) $request->checkOut),
        ));

        return new JsonResponse(['available' => $available]);
    }
}
