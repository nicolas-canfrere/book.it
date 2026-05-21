<?php

declare(strict_types=1);

namespace App\Pricing\UI\Http\Controller\SetCancellationPolicy;

use App\Pricing\Application\UseCase\SetCancellationPolicy\SetCancellationPolicyCommand;
use App\Shared\Application\Bus\SyncCommandBusInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final readonly class SetCancellationPolicyController
{
    public function __construct(private SyncCommandBusInterface $commandBus)
    {
    }

    #[Route(
        path: '/rooms/{roomId}/cancellation-policy',
        name: 'pricing_set_cancellation_policy',
        requirements: ['roomId' => Requirement::UUID_V4],
        methods: ['PUT'],
    )]
    #[OA\Put(
        summary: 'Set or update the cancellation policy for a room',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: new Model(type: SetCancellationPolicyRequest::class)),
        ),
        tags: ['Pricing'],
        parameters: [
            new OA\Parameter(name: 'roomId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: Response::HTTP_NO_CONTENT, description: 'Cancellation policy set'),
            new OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Room not found', content: new OA\MediaType(mediaType: 'application/problem+json', schema: new OA\Schema(ref: '#/components/schemas/ProblemDetail'))),
            new OA\Response(response: Response::HTTP_UNPROCESSABLE_ENTITY, description: 'Validation error', content: new OA\MediaType(mediaType: 'application/problem+json', schema: new OA\Schema(ref: '#/components/schemas/ValidationProblemDetail'))),
        ],
    )]
    public function __invoke(
        string $roomId,
        #[MapRequestPayload(acceptFormat: 'json', validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)]
        SetCancellationPolicyRequest $request,
    ): Response {
        $this->commandBus->execute(new SetCancellationPolicyCommand($roomId, $request->daysThreshold));

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
