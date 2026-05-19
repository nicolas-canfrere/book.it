<?php

declare(strict_types=1);

namespace App\Pricing\UI\Http\Controller\DeleteCancellationPolicy;

use App\Pricing\Application\UseCase\DeleteCancellationPolicy\DeleteCancellationPolicyCommand;
use App\Shared\Application\Bus\SyncCommandBusInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final readonly class DeleteCancellationPolicyController
{
    public function __construct(private SyncCommandBusInterface $commandBus)
    {
    }

    #[Route(
        path: '/api/rooms/{roomId}/cancellation-policy',
        name: 'pricing_delete_cancellation_policy',
        requirements: ['roomId' => Requirement::UUID_V4],
        methods: ['DELETE'],
    )]
    #[OA\Delete(
        summary: 'Delete the cancellation policy for a room',
        tags: ['Pricing'],
        parameters: [
            new OA\Parameter(name: 'roomId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: Response::HTTP_NO_CONTENT, description: 'Cancellation policy deleted'),
            new OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Cancellation policy not found', content: new OA\MediaType(mediaType: 'application/problem+json', schema: new OA\Schema(ref: '#/components/schemas/ProblemDetail'))),
        ],
    )]
    public function __invoke(string $roomId): Response
    {
        $this->commandBus->execute(new DeleteCancellationPolicyCommand($roomId));

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
