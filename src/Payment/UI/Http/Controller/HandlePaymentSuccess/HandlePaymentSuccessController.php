<?php

declare(strict_types=1);

namespace App\Payment\UI\Http\Controller\HandlePaymentSuccess;

use App\Payment\Application\UseCase\HandlePaymentSuccess\HandlePaymentSuccessCommand;
use App\Shared\Application\Bus\SyncCommandBusInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

final readonly class HandlePaymentSuccessController
{
    public function __construct(private SyncCommandBusInterface $commandBus)
    {
    }

    #[Route('/payment/webhooks/success', name: 'payment_webhook_success', methods: ['POST'])]
    #[OA\Post(
        summary: 'Payment success webhook',
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
            new OA\Response(response: Response::HTTP_UNSUPPORTED_MEDIA_TYPE, description: 'Unsupported media type', content: new OA\MediaType(mediaType: 'application/problem+json', schema: new OA\Schema(ref: '#/components/schemas/ProblemDetail'))),
        ],
    )]
    public function __invoke(
        #[MapRequestPayload(acceptFormat: 'json', validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)]
        HandlePaymentSuccessRequest $request,
    ): Response {
        $this->commandBus->execute(new HandlePaymentSuccessCommand($request->reservationId));

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
