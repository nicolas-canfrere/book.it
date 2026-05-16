<?php

declare(strict_types=1);

namespace App\Booker\UI\Http\Controller\RegisterBooker;

use App\Booker\Application\Service\RegisterBookerCommandFactory;
use App\Booker\Application\UseCase\GetBooker\GetBookerQuery;
use App\Booker\UI\Http\Controller\BookerSerializer;
use App\Shared\Application\Bus\SyncCommandBusInterface;
use App\Shared\Application\Bus\SyncQueryBusInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

final readonly class RegisterBookerController
{
    public function __construct(
        private RegisterBookerCommandFactory $commandFactory,
        private SyncCommandBusInterface $commandBus,
        private SyncQueryBusInterface $queryBus,
        private BookerSerializer $bookerSerializer,
    ) {
    }

    #[Route('/api/bookers', name: 'booker_register_booker', methods: ['POST'])]
    #[OA\Post(
        summary: 'Register a new booker',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: new Model(type: RegisterBookerRequest::class)),
        ),
        tags: ['Bookers'],
        responses: [
            new OA\Response(
                response: Response::HTTP_CREATED,
                description: 'Booker registered',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                        new OA\Property(property: 'firstName', type: 'string', example: 'Jean'),
                        new OA\Property(property: 'lastName', type: 'string', example: 'Dupont'),
                        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'jean.dupont@example.com'),
                        new OA\Property(property: 'phone', type: 'string', example: '+33612345678'),
                        new OA\Property(property: 'dateOfBirth', type: 'string', format: 'date', example: '1990-05-15'),
                        new OA\Property(property: 'registeredAt', description: 'Unix timestamp', type: 'integer'),
                    ],
                ),
            ),
            new OA\Response(
                response: Response::HTTP_CONFLICT,
                description: 'Email already taken',
                content: new OA\MediaType(
                    mediaType: 'application/problem+json',
                    schema: new OA\Schema(ref: '#/components/schemas/ProblemDetail'),
                ),
            ),
            new OA\Response(
                response: Response::HTTP_UNPROCESSABLE_ENTITY,
                description: 'Validation error or underage applicant',
                content: new OA\MediaType(
                    mediaType: 'application/problem+json',
                    schema: new OA\Schema(ref: '#/components/schemas/ValidationProblemDetail'),
                ),
            ),
            new OA\Response(
                response: Response::HTTP_NOT_FOUND,
                description: 'Not found',
                content: new OA\MediaType(
                    mediaType: 'application/problem+json',
                    schema: new OA\Schema(ref: '#/components/schemas/ProblemDetail'),
                ),
            ),
        ],
    )]
    public function __invoke(
        #[MapRequestPayload(acceptFormat: 'json')]
        RegisterBookerRequest $request,
    ): Response {
        $command = $this->commandFactory->create(
            $request->firstName,
            $request->lastName,
            $request->email,
            $request->phone,
            $request->dateOfBirth,
        );
        $this->commandBus->execute($command);

        $booker = $this->queryBus->ask(new GetBookerQuery($command->id));
        if (null === $booker) {
            throw new NotFoundHttpException();
        }

        return new JsonResponse(
            $this->bookerSerializer->serialize($booker),
            Response::HTTP_CREATED,
        );
    }
}
