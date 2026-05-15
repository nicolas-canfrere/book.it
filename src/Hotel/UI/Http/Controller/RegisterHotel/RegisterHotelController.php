<?php

declare(strict_types=1);

namespace App\Hotel\UI\Http\Controller\RegisterHotel;

use App\Hotel\Application\Service\RegisterHotelCommandFactory;
use App\Hotel\Application\UseCase\GetHotel\GetHotelQuery;
use App\Hotel\Domain\Exception\HotelAlreadyExistsException;
use App\Shared\Application\Bus\SyncCommandBusInterface;
use App\Shared\Application\Bus\SyncQueryBusInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

final readonly class RegisterHotelController
{
    public function __construct(
        private RegisterHotelCommandFactory $commandFactory,
        private SyncCommandBusInterface $commandBus,
        private SyncQueryBusInterface $queryBus,
        private RegisteredHotelSerializer $registeredHotelSerializer,
    ) {
    }

    #[Route('/api/hotels', name: 'hotel_register_hotel', methods: ['POST'])]
    #[OA\Post(
        summary: 'Register a new hotel',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: new Model(type: RegisterHotelRequest::class)),
        ),
        tags: ['Hotels'],
        responses: [
            new OA\Response(
                response: Response::HTTP_CREATED,
                description: 'Hotel registered',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                        new OA\Property(property: 'name', type: 'string', example: 'Hotel Ibis Paris'),
                        new OA\Property(property: 'streetAddress', type: 'string', example: '15 rue de Rivoli'),
                        new OA\Property(property: 'postalCode', type: 'string', example: '75001'),
                        new OA\Property(property: 'city', type: 'string', example: 'Paris'),
                        new OA\Property(property: 'country', type: 'string', example: 'FR'),
                        new OA\Property(property: 'createdAt', description: 'Unix timestamp', type: 'integer'),
                    ],
                ),
            ),
            new OA\Response(response: Response::HTTP_CONFLICT, description: 'Hotel already exists'),
            new OA\Response(response: Response::HTTP_UNPROCESSABLE_ENTITY, description: 'Validation error'),
        ],
    )]
    public function __invoke(
        #[MapRequestPayload(acceptFormat: 'json')]
        RegisterHotelRequest $request,
    ): Response {
        try {
            $command = $this->commandFactory->create(
                $request->name,
                $request->streetAddress,
                $request->postalCode,
                $request->city,
                $request->country,
            );
            $this->commandBus->execute($command);
        } catch (HotelAlreadyExistsException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_CONFLICT);
        }

        $hotel = $this->queryBus->ask(new GetHotelQuery($command->id));
        if (null === $hotel) {
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse(
            $this->registeredHotelSerializer->serialize($hotel),
            Response::HTTP_CREATED
        );
    }
}
