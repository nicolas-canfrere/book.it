<?php

declare(strict_types=1);

namespace App\Pricing\UI\Http\Controller\UpdatePromotion;

use App\Pricing\Application\Service\UpdatePromotionCommandFactory;
use App\Pricing\Domain\Port\PromotionRepositoryInterface;
use App\Pricing\UI\Http\Controller\PromotionSerializer;
use App\Shared\Application\Bus\SyncCommandBusInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final readonly class UpdatePromotionController
{
    public function __construct(
        private UpdatePromotionCommandFactory $commandFactory,
        private SyncCommandBusInterface $commandBus,
        private PromotionRepositoryInterface $repository,
        private PromotionSerializer $serializer,
    ) {
    }

    #[Route('/api/rooms/{roomId}/promotions/{promotionId}', name: 'pricing_update_promotion', requirements: ['roomId' => Requirement::UUID_V4, 'promotionId' => Requirement::UUID_V4], methods: ['PUT'])]
    #[OA\Put(
        summary: 'Update a promotion for a room',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: new Model(type: UpdatePromotionRequest::class)),
        ),
        tags: ['Pricing'],
        parameters: [
            new OA\Parameter(name: 'roomId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'promotionId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(
                response: Response::HTTP_OK,
                description: 'Promotion updated',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                        new OA\Property(property: 'roomId', type: 'string', format: 'uuid'),
                        new OA\Property(property: 'checkIn', type: 'string', format: 'date', example: '2025-07-01'),
                        new OA\Property(property: 'checkOut', type: 'string', format: 'date', example: '2025-09-01'),
                        new OA\Property(property: 'discountPercent', type: 'integer', example: 25),
                        new OA\Property(property: 'createdAt', description: 'Unix timestamp', type: 'integer'),
                    ],
                ),
            ),
            new OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Promotion not found', content: new OA\MediaType(mediaType: 'application/problem+json', schema: new OA\Schema(ref: '#/components/schemas/ProblemDetail'))),
            new OA\Response(response: Response::HTTP_CONFLICT, description: 'Promotion overlaps existing promotion', content: new OA\MediaType(mediaType: 'application/problem+json', schema: new OA\Schema(ref: '#/components/schemas/ProblemDetail'))),
            new OA\Response(response: Response::HTTP_UNPROCESSABLE_ENTITY, description: 'Validation error', content: new OA\MediaType(mediaType: 'application/problem+json', schema: new OA\Schema(ref: '#/components/schemas/ValidationProblemDetail'))),
        ],
    )]
    public function __invoke(
        string $roomId,
        string $promotionId,
        #[MapRequestPayload(acceptFormat: 'json', validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)] UpdatePromotionRequest $request,
    ): Response {
        $command = $this->commandFactory->create($promotionId, $roomId, (string) $request->checkIn, (string) $request->checkOut, (int) $request->discountPercent);
        $this->commandBus->execute($command);

        $promotion = $this->repository->findById($command->promotionId);
        if (null === $promotion) {
            throw new NotFoundHttpException();
        }

        return new JsonResponse($this->serializer->serialize($promotion));
    }
}
