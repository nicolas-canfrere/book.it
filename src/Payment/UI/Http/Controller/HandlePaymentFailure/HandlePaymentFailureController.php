<?php

declare(strict_types=1);

namespace App\Payment\UI\Http\Controller\HandlePaymentFailure;

use App\Payment\Application\UseCase\HandlePaymentFailure\HandlePaymentFailureCommand;
use App\Shared\Application\Bus\SyncCommandBusInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

final readonly class HandlePaymentFailureController
{
    public function __construct(private SyncCommandBusInterface $commandBus)
    {
    }

    #[Route('/payment/webhooks/failed', name: 'payment_webhook_failed', methods: ['POST'])]
    #[OA\Post(
        summary: 'Payment failure webhook',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['reservation_id'],
                properties: [new OA\Property(property: 'reservation_id', type: 'string', format: 'uuid')],
            ),
        ),
        tags: ['Payment'],
        responses: [
            new OA\Response(response: 204, description: 'Acknowledged'),
            new OA\Response(response: 422, description: 'Validation error'),
        ],
    )]
    public function __invoke(
        #[MapRequestPayload(acceptFormat: 'json', validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)]
        HandlePaymentFailureRequest $request,
    ): Response {
        $this->commandBus->execute(new HandlePaymentFailureCommand($request->reservationId));

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
