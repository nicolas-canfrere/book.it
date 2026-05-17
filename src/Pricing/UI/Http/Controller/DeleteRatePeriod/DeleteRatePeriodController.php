<?php

declare(strict_types=1);

namespace App\Pricing\UI\Http\Controller\DeleteRatePeriod;

use App\Pricing\Application\UseCase\DeleteRatePeriod\DeleteRatePeriodCommand;
use App\Shared\Application\Bus\SyncCommandBusInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final readonly class DeleteRatePeriodController
{
    public function __construct(
        private SyncCommandBusInterface $commandBus,
    ) {
    }

    #[Route('/api/rooms/{roomId}/rate-periods/{ratePeriodId}', name: 'pricing_delete_rate_period', requirements: ['roomId' => Requirement::UUID_V4, 'ratePeriodId' => Requirement::UUID_V4], methods: ['DELETE'])]
    #[OA\Delete(
        summary: 'Delete a rate period',
        tags: ['Pricing'],
        parameters: [
            new OA\Parameter(name: 'roomId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'ratePeriodId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: Response::HTTP_NO_CONTENT, description: 'Rate period deleted'),
            new OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Rate period not found', content: new OA\MediaType(mediaType: 'application/problem+json', schema: new OA\Schema(ref: '#/components/schemas/ProblemDetail'))),
        ],
    )]
    public function __invoke(string $ratePeriodId): Response
    {
        $this->commandBus->execute(new DeleteRatePeriodCommand($ratePeriodId));

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
