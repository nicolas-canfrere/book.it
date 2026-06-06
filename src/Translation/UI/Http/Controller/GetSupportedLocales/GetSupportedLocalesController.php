<?php

declare(strict_types=1);

namespace App\Translation\UI\Http\Controller\GetSupportedLocales;

use App\Shared\Application\Bus\SyncQueryBusInterface;
use App\Translation\Application\UseCase\GetSupportedLocales\GetSupportedLocalesQuery;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final readonly class GetSupportedLocalesController
{
    public function __construct(private SyncQueryBusInterface $queryBus)
    {
    }

    #[Route('/translations/locales', name: 'translation_supported_locales', methods: ['GET'])]
    #[OA\Get(
        summary: 'List supported locales and the default fallback locale',
        tags: ['Translation'],
        responses: [
            new OA\Response(
                response: Response::HTTP_OK,
                description: 'Supported locales',
                content: new OA\JsonContent(
                    required: ['supported', 'default'],
                    properties: [
                        new OA\Property(property: 'supported', type: 'array', items: new OA\Items(type: 'string'), example: ['fr_FR', 'en_GB', 'de_DE']),
                        new OA\Property(property: 'default', type: 'string', example: 'en_GB'),
                    ],
                ),
            ),
        ],
    )]
    public function __invoke(): Response
    {
        $view = $this->queryBus->ask(new GetSupportedLocalesQuery());

        return new JsonResponse([
            'supported' => $view->supported,
            'default' => $view->default,
        ]);
    }
}
