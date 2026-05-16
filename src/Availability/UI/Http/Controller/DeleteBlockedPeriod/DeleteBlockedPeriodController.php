<?php

declare(strict_types=1);

namespace App\Availability\UI\Http\Controller\DeleteBlockedPeriod;

use App\Availability\Application\UseCase\DeleteBlockedPeriod\DeleteBlockedPeriodCommand;
use App\Shared\Application\Bus\SyncCommandBusInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final readonly class DeleteBlockedPeriodController
{
    public function __construct(private SyncCommandBusInterface $commandBus)
    {
    }

    #[Route('/api/blocked-periods/{id}', name: 'availability_delete_blocked_period', requirements: ['id' => Requirement::UUID_V4], methods: ['DELETE'])]
    #[OA\Delete(
        summary: 'Delete a blocked period',
        tags: ['Availability'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: Response::HTTP_NO_CONTENT, description: 'Blocked period deleted'),
            new OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Blocked period not found', content: new OA\MediaType(mediaType: 'application/problem+json', schema: new OA\Schema(ref: '#/components/schemas/ProblemDetail'))),
        ],
    )]
    public function __invoke(string $id): Response
    {
        $this->commandBus->execute(new DeleteBlockedPeriodCommand($id));

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
