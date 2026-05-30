<?php

declare(strict_types=1);

namespace App\Reservation\UI\Http\Controller\CheckOut;

use App\Reservation\Application\UseCase\CheckOut\CheckOutCommand;
use App\Shared\Application\Bus\SyncCommandBusInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final readonly class CheckOutController
{
    public function __construct(
        private SyncCommandBusInterface $commandBus,
    ) {
    }

    #[Route(
        path: '/reservations/{reservationId}/check-out',
        name: 'reservation_check_out',
        requirements: ['reservationId' => Requirement::UUID_V4],
        methods: ['POST'],
    )]
    #[OA\Post(
        summary: 'Check out a reservation',
        tags: ['Reservation'],
    )]
    #[OA\Parameter(name: 'reservationId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/CheckOutRequest'))]
    #[OA\Response(response: 204, description: 'Checked out')]
    #[OA\Response(response: 404, description: 'Reservation not found')]
    #[OA\Response(response: 422, description: 'Unprocessable Entity (check-out not allowed or validation error)')]
    public function __invoke(
        string $reservationId,
        #[MapRequestPayload(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)]
        CheckOutRequest $request,
    ): Response {
        $this->commandBus->execute(new CheckOutCommand(
            reservationId: $reservationId,
            actualDepartureDate: \DateTimeImmutable::createFromFormat('Y-m-d', $request->actualDepartureDate)
                ?: throw new \InvalidArgumentException(sprintf('Invalid date format: "%s"', $request->actualDepartureDate)),
        ));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
