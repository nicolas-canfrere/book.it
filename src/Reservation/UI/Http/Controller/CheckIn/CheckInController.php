<?php

declare(strict_types=1);

namespace App\Reservation\UI\Http\Controller\CheckIn;

use App\Reservation\Application\UseCase\CheckIn\CheckInCommand;
use App\Shared\Application\Bus\SyncCommandBusInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final readonly class CheckInController
{
    public function __construct(
        private SyncCommandBusInterface $commandBus,
    ) {
    }

    #[Route(
        path: '/reservations/{id}/check-in',
        name: 'reservation_check_in',
        requirements: ['id' => Requirement::UUID_V4],
        methods: ['POST'],
    )]
    #[OA\Post(
        summary: 'Check in a reservation',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: new Model(type: CheckInRequest::class)),
        ),
        tags: ['Reservation'],
        responses: [
            new OA\Response(response: Response::HTTP_NO_CONTENT, description: 'Checked in'),
            new OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Reservation not found', content: new OA\MediaType(mediaType: 'application/problem+json', schema: new OA\Schema(ref: '#/components/schemas/ProblemDetail'))),
            new OA\Response(response: Response::HTTP_CONFLICT, description: 'Check-in not allowed', content: new OA\MediaType(mediaType: 'application/problem+json', schema: new OA\Schema(ref: '#/components/schemas/ProblemDetail'))),
            new OA\Response(response: Response::HTTP_UNPROCESSABLE_ENTITY, description: 'Validation error', content: new OA\MediaType(mediaType: 'application/problem+json', schema: new OA\Schema(ref: '#/components/schemas/ValidationProblemDetail'))),
        ],
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    public function __invoke(
        string $id,
        #[MapRequestPayload(acceptFormat: 'json', validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)]
        CheckInRequest $request,
    ): Response {
        $this->commandBus->execute(new CheckInCommand(
            reservationId: $id,
            guests: $request->guests,
            today: new \DateTimeImmutable('today'),
        ));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
