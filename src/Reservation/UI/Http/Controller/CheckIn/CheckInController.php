<?php

declare(strict_types=1);

namespace App\Reservation\UI\Http\Controller\CheckIn;

use App\Reservation\Application\UseCase\CheckIn\CheckInCommand;
use App\Shared\Application\Bus\SyncCommandBusInterface;
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
        path: '/reservations/{id}/check-in',
        summary: 'Check in a reservation',
        tags: ['Reservation'],
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/CheckInRequest'))]
    #[OA\Response(response: 204, description: 'Checked in')]
    #[OA\Response(response: 404, description: 'Reservation not found')]
    #[OA\Response(response: 409, description: 'Check-in not allowed')]
    #[OA\Response(response: 422, description: 'Validation error')]
    public function __invoke(
        string $id,
        #[MapRequestPayload(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)]
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
