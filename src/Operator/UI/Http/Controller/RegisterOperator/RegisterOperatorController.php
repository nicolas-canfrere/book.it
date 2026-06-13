<?php

declare(strict_types=1);

namespace App\Operator\UI\Http\Controller\RegisterOperator;

use App\Operator\Application\Service\RegisterOperatorCommandFactory;
use App\Shared\Application\Bus\SyncCommandBusInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

final readonly class RegisterOperatorController
{
    public function __construct(
        private RegisterOperatorCommandFactory $commandFactory,
        private SyncCommandBusInterface $commandBus,
    ) {
    }

    #[Route('/operators', name: 'operator_register_operator', methods: ['POST'])]
    #[OA\Post(
        summary: 'Register a new operator',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: new Model(type: RegisterOperatorRequest::class)),
        ),
        tags: ['Operators'],
        responses: [
            new OA\Response(
                response: Response::HTTP_CREATED,
                description: 'Operator registered',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                        new OA\Property(property: 'firstName', type: 'string', example: 'Alice'),
                        new OA\Property(property: 'lastName', type: 'string', example: 'Martin'),
                        new OA\Property(property: 'email', type: 'string', format: 'email'),
                        new OA\Property(property: 'phone', type: 'string', example: '+33612345678'),
                        new OA\Property(property: 'registeredAt', type: 'string', format: 'date-time'),
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
                description: 'Validation error',
                content: new OA\MediaType(
                    mediaType: 'application/problem+json',
                    schema: new OA\Schema(ref: '#/components/schemas/ValidationProblemDetail'),
                ),
            ),
            new OA\Response(response: Response::HTTP_UNSUPPORTED_MEDIA_TYPE, description: 'Unsupported media type', content: new OA\MediaType(mediaType: 'application/problem+json', schema: new OA\Schema(ref: '#/components/schemas/ProblemDetail'))),
        ],
    )]
    public function __invoke(
        #[MapRequestPayload(acceptFormat: 'json')]
        RegisterOperatorRequest $request,
    ): Response {
        $command = $this->commandFactory->create(
            $request->firstName ?? '',
            $request->lastName ?? '',
            $request->email ?? '',
            $request->phone ?? '',
            $request->password ?? '',
        );
        $this->commandBus->execute($command);

        return new JsonResponse([
            'id' => $command->id->value,
            'firstName' => $command->firstName,
            'lastName' => $command->lastName,
            'email' => $command->email,
            'phone' => $command->phone,
            'registeredAt' => $command->registeredAt->format(\DateTimeInterface::ATOM),
        ], Response::HTTP_CREATED);
    }
}
