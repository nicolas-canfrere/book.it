<?php

declare(strict_types=1);

namespace App\Translation\UI\Http\Controller\SetTranslation;

use App\Shared\Application\Bus\SyncCommandBusInterface;
use App\Translation\Application\UseCase\SetTranslation\SetTranslationCommand;
use App\Translation\Domain\ValueObject\SubjectType;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final readonly class SetTranslationController
{
    public function __construct(private SyncCommandBusInterface $commandBus)
    {
    }

    #[Route(
        '/translations/{subjectType}/{subjectId}',
        name: 'translation_set',
        requirements: ['subjectType' => 'hotel|room_type', 'subjectId' => Requirement::UUID_V4],
        methods: ['PUT'],
    )]
    #[OA\Put(
        summary: 'Set a translation for a hotel or room type',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['locale', 'text'],
                properties: [
                    new OA\Property(property: 'locale', type: 'string', example: 'fr_FR'),
                    new OA\Property(property: 'text', type: 'string', example: 'Un magnifique hôtel.'),
                ],
            ),
        ),
        tags: ['Translation'],
        parameters: [
            new OA\Parameter(name: 'subjectType', in: 'path', required: true, schema: new OA\Schema(type: 'string', enum: ['hotel', 'room_type'])),
            new OA\Parameter(name: 'subjectId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: Response::HTTP_NO_CONTENT, description: 'Translation set'),
            new OA\Response(
                response: Response::HTTP_UNPROCESSABLE_ENTITY,
                description: 'Validation error or unsupported locale',
                content: new OA\MediaType(mediaType: 'application/problem+json', schema: new OA\Schema(ref: '#/components/schemas/ProblemDetail')),
            ),
            new OA\Response(
                response: Response::HTTP_UNSUPPORTED_MEDIA_TYPE,
                description: 'Unsupported media type',
                content: new OA\MediaType(mediaType: 'application/problem+json', schema: new OA\Schema(ref: '#/components/schemas/ProblemDetail')),
            ),
        ],
    )]
    public function __invoke(
        string $subjectType,
        string $subjectId,
        #[MapRequestPayload(acceptFormat: 'json', validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)] SetTranslationRequest $request,
    ): Response {
        $this->commandBus->execute(new SetTranslationCommand(
            SubjectType::from($subjectType),
            $subjectId,
            $request->locale,
            $request->text,
        ));

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
