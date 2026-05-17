<?php

declare(strict_types=1);

namespace App\Pricing\UI\Http\Controller\DeletePromotion;

use App\Pricing\Application\UseCase\DeletePromotion\DeletePromotionCommand;
use App\Shared\Application\Bus\SyncCommandBusInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final readonly class DeletePromotionController
{
    public function __construct(
        private SyncCommandBusInterface $commandBus,
    ) {
    }

    #[Route('/api/rooms/{roomId}/promotions/{promotionId}', name: 'pricing_delete_promotion', requirements: ['roomId' => Requirement::UUID_V4, 'promotionId' => Requirement::UUID_V4], methods: ['DELETE'])]
    #[OA\Delete(
        summary: 'Delete a promotion',
        tags: ['Pricing'],
        parameters: [
            new OA\Parameter(name: 'roomId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'promotionId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: Response::HTTP_NO_CONTENT, description: 'Promotion deleted'),
            new OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Promotion not found', content: new OA\MediaType(mediaType: 'application/problem+json', schema: new OA\Schema(ref: '#/components/schemas/ProblemDetail'))),
        ],
    )]
    public function __invoke(string $promotionId): Response
    {
        $this->commandBus->execute(new DeletePromotionCommand($promotionId));

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
