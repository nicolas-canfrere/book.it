<?php

declare(strict_types=1);

namespace App\Search\UI\Http\Controller\SearchAvailableRoomTypes;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[Assert\Callback([SearchAvailableRoomTypesRequest::class, 'validateDateRange'])]
final readonly class SearchAvailableRoomTypesRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        #[OA\Parameter(
            name: 'geoPlaceId',
            in: 'query',
            required: true,
            description: 'GeoNames id of the place selected via Geo Place Search autocomplete — sole filtering criterion',
            schema: new OA\Schema(type: 'string', example: '2988507'),
        )]
        public ?string $geoPlaceId = null,
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        #[OA\Parameter(
            name: 'city',
            in: 'query',
            required: true,
            description: 'Free-text city name typed by the visitor — informational only, not used for filtering',
            schema: new OA\Schema(type: 'string', example: 'Paris'),
        )]
        public ?string $city = null,
        #[Assert\NotBlank]
        #[Assert\Date]
        #[OA\Parameter(name: 'checkIn', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'date', example: '2026-07-01'))]
        public ?string $checkIn = null,
        #[Assert\NotBlank]
        #[Assert\Date]
        #[OA\Parameter(name: 'checkOut', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'date', example: '2026-07-05'))]
        public ?string $checkOut = null,
        #[Assert\NotBlank]
        #[Assert\GreaterThanOrEqual(1)]
        #[Assert\LessThanOrEqual(20)]
        #[OA\Parameter(name: 'guests', in: 'query', required: true, schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 20, example: 2))]
        public ?int $guests = null,
    ) {
    }

    public static function validateDateRange(self $request, ExecutionContextInterface $context): void
    {
        if (null === $request->checkIn || null === $request->checkOut) {
            return;
        }

        if (new \DateTimeImmutable($request->checkOut) <= new \DateTimeImmutable($request->checkIn)) {
            $context->buildViolation('checkOut must be after checkIn.')
                ->atPath('checkOut')
                ->addViolation();
        }
    }
}
