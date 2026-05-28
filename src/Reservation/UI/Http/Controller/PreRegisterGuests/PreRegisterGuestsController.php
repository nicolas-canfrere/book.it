<?php

declare(strict_types=1);

namespace App\Reservation\UI\Http\Controller\PreRegisterGuests;

use App\Reservation\Application\UseCase\PreRegisterGuests\PreRegisterGuestsCommand;
use App\Shared\Application\Bus\SyncCommandBusInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final readonly class PreRegisterGuestsController
{
    public function __construct(
        private SyncCommandBusInterface $commandBus,
    ) {
    }

    #[Route(
        path: '/reservations/{id}/guests',
        name: 'reservation_pre_register_guests',
        requirements: ['id' => Requirement::UUID_V4],
        methods: ['PUT'],
    )]
    #[OA\Put(
        path: '/reservations/{id}/guests',
        summary: 'Pre-register guests on a reservation',
        tags: ['Reservation'],
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/PreRegisterGuestsRequest'))]
    #[OA\Response(response: 204, description: 'Guests pre-registered')]
    #[OA\Response(response: 404, description: 'Reservation not found')]
    #[OA\Response(response: 409, description: 'Pre-registration not allowed for current reservation status or date')]
    #[OA\Response(response: 422, description: 'Validation error')]
    public function __invoke(
        string $id,
        #[MapRequestPayload(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)]
        PreRegisterGuestsRequest $request,
    ): Response {
        $this->commandBus->execute(new PreRegisterGuestsCommand(
            reservationId: $id,
            guests: $request->guests,
            today: new \DateTimeImmutable('today'),
        ));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
