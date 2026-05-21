<?php

declare(strict_types=1);

namespace App\Pricing\UI\Http\Controller\CreatePromotion;

use App\Pricing\Application\Service\CreatePromotionCommandFactory;
use App\Pricing\Domain\Model\Promotion;
use App\Pricing\UI\Http\Controller\PromotionSerializer;
use App\Shared\Application\Bus\SyncCommandBusInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final readonly class CreatePromotionController
{
    public function __construct(
        private CreatePromotionCommandFactory $commandFactory,
        private SyncCommandBusInterface $commandBus,
        private PromotionSerializer $serializer,
    ) {
    }

    #[Route('/rooms/{roomId}/promotions', name: 'pricing_create_promotion', requirements: ['roomId' => Requirement::UUID_V4], methods: ['POST'])]
    #[OA\Post(
        summary: 'Create a promotion for a room',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: new Model(type: CreatePromotionRequest::class)),
        ),
        tags: ['Pricing'],
        parameters: [
            new OA\Parameter(name: 'roomId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(
                response: Response::HTTP_CREATED,
                description: 'Promotion created',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                        new OA\Property(property: 'roomId', type: 'string', format: 'uuid'),
                        new OA\Property(property: 'checkIn', type: 'string', format: 'date', example: '2025-07-01'),
                        new OA\Property(property: 'checkOut', type: 'string', format: 'date', example: '2025-08-31'),
                        new OA\Property(property: 'discountPercent', type: 'integer', example: 20),
                        new OA\Property(property: 'createdAt', description: 'Unix timestamp', type: 'integer'),
                    ],
                ),
            ),
            new OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Room not found', content: new OA\MediaType(mediaType: 'application/problem+json', schema: new OA\Schema(ref: '#/components/schemas/ProblemDetail'))),
            new OA\Response(response: Response::HTTP_CONFLICT, description: 'Promotion overlaps existing promotion', content: new OA\MediaType(mediaType: 'application/problem+json', schema: new OA\Schema(ref: '#/components/schemas/ProblemDetail'))),
            new OA\Response(response: Response::HTTP_UNPROCESSABLE_ENTITY, description: 'Validation error', content: new OA\MediaType(mediaType: 'application/problem+json', schema: new OA\Schema(ref: '#/components/schemas/ValidationProblemDetail'))),
        ],
    )]
    public function __invoke(
        string $roomId,
        #[MapRequestPayload(acceptFormat: 'json', validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)] CreatePromotionRequest $request,
    ): Response {
        $command = $this->commandFactory->create($roomId, (string) $request->checkIn, (string) $request->checkOut, (int) $request->discountPercent);
        $this->commandBus->execute($command);

        $promotion = new Promotion(
            id: $command->id,
            roomId: $command->roomId,
            checkIn: $command->checkIn,
            checkOut: $command->checkOut,
            discountPercent: $command->discountPercent,
            createdAt: $command->createdAt,
            updatedAt: $command->createdAt,
        );

        return new JsonResponse($this->serializer->serialize($promotion), Response::HTTP_CREATED);
    }
}
