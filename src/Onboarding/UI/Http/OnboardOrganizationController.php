<?php

declare(strict_types=1);

namespace App\Onboarding\UI\Http;

use App\Onboarding\Application\UseCase\OnboardOrganization\OnboardOrganizationCommand;
use App\Shared\Application\Bus\SyncCommandBusInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final readonly class OnboardOrganizationController
{
    public function __construct(private SyncCommandBusInterface $commandBus)
    {
    }

    #[Route('/onboarding', name: 'onboarding_register', methods: ['POST'])]
    #[OA\Post(
        summary: 'Self-service hotel onboarding — creates an Organization and its owner Operator',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: new Model(type: OnboardOrganizationRequest::class)),
        ),
        tags: ['Onboarding'],
        responses: [
            new OA\Response(
                response: Response::HTTP_CREATED,
                description: 'Organization and owner Operator created (Organization status: pending)',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'organizationId', type: 'string', format: 'uuid'),
                        new OA\Property(property: 'operatorId', type: 'string', format: 'uuid'),
                    ],
                ),
            ),
            new OA\Response(
                response: Response::HTTP_CONFLICT,
                description: 'Email already registered',
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
            new OA\Response(
                response: Response::HTTP_UNSUPPORTED_MEDIA_TYPE,
                description: 'Unsupported media type',
                content: new OA\MediaType(
                    mediaType: 'application/problem+json',
                    schema: new OA\Schema(ref: '#/components/schemas/ProblemDetail'),
                ),
            ),
        ],
    )]
    public function __invoke(
        #[MapRequestPayload(
            acceptFormat: 'json',
            validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY,
        )]
        OnboardOrganizationRequest $request,
    ): Response {
        $organizationId = Uuid::v4()->toString();
        $operatorId = Uuid::v4()->toString();

        $this->commandBus->execute(new OnboardOrganizationCommand(
            organizationId: $organizationId,
            operatorId: $operatorId,
            organizationName: $request->organizationName,
            contactEmail: $request->contactEmail,
            ownerFirstName: $request->ownerFirstName,
            ownerLastName: $request->ownerLastName,
            ownerPhone: $request->ownerPhone,
            password: $request->password,
            registeredAt: new \DateTimeImmutable(),
        ));

        return new JsonResponse([
            'organizationId' => $organizationId,
            'operatorId' => $operatorId,
        ], Response::HTTP_CREATED);
    }
}
