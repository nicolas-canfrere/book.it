<?php

declare(strict_types=1);

namespace App\Reservation\UI\Http\Controller\CancelReservation;

use App\Reservation\Application\UseCase\CancelReservation\CancelReservationCommand;
use App\Shared\Application\Bus\SyncCommandBusInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final readonly class CancelReservationController
{
    public function __construct(
        private SyncCommandBusInterface $commandBus,
    ) {
    }

    #[Route(
        path: '/reservations/{id}/cancel',
        name: 'reservation_cancel',
        requirements: ['id' => Requirement::UUID_V4],
        methods: ['POST'],
    )]
    #[OA\Post(
        summary: 'Cancel a reservation (by Booker)',
        tags: ['Reservation'],
        responses: [
            new OA\Response(response: Response::HTTP_NO_CONTENT, description: 'Reservation cancelled'),
            new OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Reservation not found', content: new OA\MediaType(mediaType: 'application/problem+json', schema: new OA\Schema(ref: '#/components/schemas/ProblemDetail'))),
            new OA\Response(response: Response::HTTP_CONFLICT, description: 'Cancellation not allowed (wrong status or check-in date reached)', content: new OA\MediaType(mediaType: 'application/problem+json', schema: new OA\Schema(ref: '#/components/schemas/ProblemDetail'))),
        ],
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    public function __invoke(string $id): Response
    {
        $this->commandBus->execute(new CancelReservationCommand(
            reservationId: $id,
            today: new \DateTimeImmutable('today'),
        ));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
