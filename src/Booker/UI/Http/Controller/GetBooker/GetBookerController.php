<?php

declare(strict_types=1);

namespace App\Booker\UI\Http\Controller\GetBooker;

use App\Booker\Application\UseCase\GetBooker\GetBookerQuery;
use App\Booker\UI\Http\Controller\BookerSerializer;
use App\Shared\Application\Bus\SyncQueryBusInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final readonly class GetBookerController
{
    public function __construct(
        private SyncQueryBusInterface $queryBus,
        private BookerSerializer $bookerSerializer,
    ) {
    }

    #[Route('/bookers/{id}', name: 'booker_get_booker', requirements: ['id' => Requirement::UUID_V4], methods: ['GET'])]
    #[OA\Get(
        summary: 'Get a booker by ID',
        tags: ['Bookers'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(
                response: Response::HTTP_OK,
                description: 'Booker found',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                        new OA\Property(property: 'firstName', type: 'string', example: 'Jane'),
                        new OA\Property(property: 'lastName', type: 'string', example: 'Doe'),
                        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'jane.doe@example.com'),
                        new OA\Property(property: 'phone', type: 'string', example: '+33612345678'),
                        new OA\Property(property: 'dateOfBirth', type: 'string', format: 'date', example: '1990-01-15'),
                        new OA\Property(property: 'registeredAt', description: 'Unix timestamp', type: 'integer'),
                    ],
                ),
            ),
            new OA\Response(
                response: Response::HTTP_NOT_FOUND,
                description: 'Booker not found',
                content: new OA\MediaType(
                    mediaType: 'application/problem+json',
                    schema: new OA\Schema(ref: '#/components/schemas/ProblemDetail'),
                ),
            ),
        ],
    )]
    public function __invoke(string $id): Response
    {
        $query = new GetBookerQuery($id);
        $booker = $this->queryBus->ask($query);
        if (null === $booker) {
            throw new NotFoundHttpException('Booker not found.');
        }

        return new JsonResponse(
            $this->bookerSerializer->serialize($booker),
        );
    }
}
