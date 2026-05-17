<?php

declare(strict_types=1);

namespace App\Pricing\UI\Http\Controller\GetPricingQuote;

use App\Pricing\Application\UseCase\GetPricingQuote\GetPricingQuoteQuery;
use App\Shared\Application\Bus\SyncQueryBusInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final readonly class GetPricingQuoteController
{
    public function __construct(
        private SyncQueryBusInterface $queryBus,
    ) {
    }

    #[Route('/api/rooms/{roomId}/pricing-quote', name: 'pricing_get_pricing_quote', requirements: ['roomId' => Requirement::UUID_V4], methods: ['GET'])]
    #[OA\Get(
        summary: 'Get pricing quote for a room',
        tags: ['Pricing'],
        parameters: [
            new OA\Parameter(name: 'roomId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(
                response: Response::HTTP_OK,
                description: 'Pricing quote',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'roomId', type: 'string', format: 'uuid'),
                        new OA\Property(property: 'checkIn', type: 'string', format: 'date'),
                        new OA\Property(property: 'checkOut', type: 'string', format: 'date'),
                        new OA\Property(property: 'totalAmountCents', type: 'integer'),
                        new OA\Property(
                            property: 'nights',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'date', type: 'string', format: 'date'),
                                    new OA\Property(property: 'rateAmountCents', type: 'integer'),
                                    new OA\Property(property: 'discountPercent', type: 'integer', nullable: true),
                                    new OA\Property(property: 'effectiveAmountCents', type: 'integer'),
                                ],
                                type: 'object',
                            ),
                        ),
                    ],
                ),
            ),
            new OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Room not found', content: new OA\MediaType(mediaType: 'application/problem+json', schema: new OA\Schema(ref: '#/components/schemas/ProblemDetail'))),
            new OA\Response(response: Response::HTTP_UNPROCESSABLE_ENTITY, description: 'No base rate or validation error', content: new OA\MediaType(mediaType: 'application/problem+json', schema: new OA\Schema(ref: '#/components/schemas/ValidationProblemDetail'))),
        ],
    )]
    public function __invoke(
        string $roomId,
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)] GetPricingQuoteRequest $request,
    ): Response {
        $query = new GetPricingQuoteQuery(
            roomId: $roomId,
            checkIn: new \DateTimeImmutable($request->checkIn),
            checkOut: new \DateTimeImmutable($request->checkOut),
        );

        /** @var array{roomId: string, checkIn: string, checkOut: string, totalAmountCents: int, nights: list<array{date: string, rateAmountCents: int, discountPercent: int|null, effectiveAmountCents: int}>} $quote */
        $quote = $this->queryBus->ask($query);

        return new JsonResponse([
            'roomId' => $quote['roomId'],
            'checkIn' => $quote['checkIn'],
            'checkOut' => $quote['checkOut'],
            'totalAmountCents' => $quote['totalAmountCents'],
            'nights' => array_map(
                static fn(array $night) => [
                    'date' => $night['date'],
                    'amountCents' => $night['rateAmountCents'],
                    'discountPercent' => $night['discountPercent'],
                    'effectiveAmountCents' => $night['effectiveAmountCents'],
                ],
                $quote['nights'],
            ),
        ]);
    }
}
