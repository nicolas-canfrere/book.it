<?php

declare(strict_types=1);

namespace App\Translation\UI\Http\Controller\GetTranslation;

use App\Shared\Application\Bus\SyncQueryBusInterface;
use App\Translation\Application\UseCase\GetTranslation\GetTranslationQuery;
use App\Translation\Domain\ValueObject\SubjectType;
use App\Translation\UI\Http\Controller\TranslationSerializer;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final readonly class GetTranslationController
{
    public function __construct(
        private SyncQueryBusInterface $queryBus,
        private TranslationSerializer $serializer,
    ) {
    }

    #[Route(
        '/translations/{subjectType}/{subjectId}',
        name: 'translation_get',
        requirements: ['subjectType' => 'hotel|room_type', 'subjectId' => Requirement::UUID_V4],
        methods: ['GET'],
    )]
    #[OA\Get(
        summary: 'Get the translation for a hotel or room type',
        tags: ['Translation'],
        parameters: [
            new OA\Parameter(name: 'subjectType', in: 'path', required: true, schema: new OA\Schema(type: 'string', enum: ['hotel', 'room_type'])),
            new OA\Parameter(name: 'subjectId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'locale', in: 'query', required: true, schema: new OA\Schema(type: 'string', example: 'fr_FR')),
        ],
        responses: [
            new OA\Response(
                response: Response::HTTP_OK,
                description: 'Translation found',
                content: new OA\JsonContent(
                    required: ['locale', 'text'],
                    properties: [
                        new OA\Property(property: 'locale', type: 'string', example: 'fr_FR', description: 'Actual locale returned — may differ from requested if fallback applied'),
                        new OA\Property(property: 'text', type: 'string', example: 'Un magnifique hôtel.'),
                    ],
                ),
            ),
            new OA\Response(response: Response::HTTP_NOT_FOUND, description: 'No translation found for subject', content: new OA\MediaType(mediaType: 'application/problem+json', schema: new OA\Schema(ref: '#/components/schemas/ProblemDetail'))),
        ],
    )]
    public function __invoke(
        string $subjectType,
        string $subjectId,
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)] GetTranslationRequest $request,
    ): Response {
        $translation = $this->queryBus->ask(new GetTranslationQuery(
            SubjectType::from($subjectType),
            $subjectId,
            $request->locale,
        ));

        if (null === $translation) {
            throw new NotFoundHttpException();
        }

        return new JsonResponse($this->serializer->serialize($translation));
    }
}
