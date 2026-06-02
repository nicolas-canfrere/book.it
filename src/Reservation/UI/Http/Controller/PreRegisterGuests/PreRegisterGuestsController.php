<?php

declare(strict_types=1);

namespace App\Reservation\UI\Http\Controller\PreRegisterGuests;

use App\Reservation\Application\UseCase\PreRegisterGuests\PreRegisterGuestsCommand;
use App\Shared\Application\Bus\SyncCommandBusInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
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
        summary: 'Pre-register guests on a reservation',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: new Model(type: PreRegisterGuestsRequest::class)),
        ),
        tags: ['Reservation'],
        responses: [
            new OA\Response(response: Response::HTTP_NO_CONTENT, description: 'Guests pre-registered'),
            new OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Reservation not found', content: new OA\MediaType(mediaType: 'application/problem+json', schema: new OA\Schema(ref: '#/components/schemas/ProblemDetail'))),
            new OA\Response(response: Response::HTTP_CONFLICT, description: 'Pre-registration not allowed for current reservation status or date', content: new OA\MediaType(mediaType: 'application/problem+json', schema: new OA\Schema(ref: '#/components/schemas/ProblemDetail'))),
            new OA\Response(response: Response::HTTP_UNPROCESSABLE_ENTITY, description: 'Validation error', content: new OA\MediaType(mediaType: 'application/problem+json', schema: new OA\Schema(ref: '#/components/schemas/ValidationProblemDetail'))),
            new OA\Response(response: Response::HTTP_UNSUPPORTED_MEDIA_TYPE, description: 'Unsupported media type', content: new OA\MediaType(mediaType: 'application/problem+json', schema: new OA\Schema(ref: '#/components/schemas/ProblemDetail'))),
        ],
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    public function __invoke(
        string $id,
        #[MapRequestPayload(acceptFormat: 'json', validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)]
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
