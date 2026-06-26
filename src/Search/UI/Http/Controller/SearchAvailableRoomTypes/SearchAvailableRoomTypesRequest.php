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
            description: 'GeoNames id of the place selected via Geo Place Search autocomplete — sole filtering criterion',
            in: 'query',
            required: true,
            schema: new OA\Schema(type: 'string', example: '2988507', maxLength: 255),
        )]
        public string $geoPlaceId,
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        #[OA\Parameter(
            name: 'city',
            description: 'Free-text city name typed by the visitor — informational only, not used for filtering',
            in: 'query',
            required: true,
            schema: new OA\Schema(type: 'string', example: 'Paris', maxLength: 255),
        )]
        public string $city,
        #[Assert\NotBlank]
        #[Assert\Date]
        #[OA\Parameter(
            name: 'checkIn',
            in: 'query',
            required: true,
            schema: new OA\Schema(type: 'string', format: 'date', example: '2026-07-01'),
        )]
        public string $checkIn,
        #[Assert\NotBlank]
        #[Assert\Date]
        #[OA\Parameter(
            name: 'checkOut',
            in: 'query',
            required: true,
            schema: new OA\Schema(type: 'string', format: 'date', example: '2026-07-05'),
        )]
        public string $checkOut,
        #[Assert\NotBlank]
        #[Assert\GreaterThanOrEqual(1)]
        #[Assert\LessThanOrEqual(20)]
        #[OA\Parameter(
            name: 'guests',
            in: 'query',
            required: true,
            schema: new OA\Schema(type: 'integer', example: 2, minimum: 1, maximum: 20),
        )]
        public int $guests,
    ) {
    }

    public static function validateDateRange(self $request, ExecutionContextInterface $context): void
    {
        if ('' === $request->checkIn || '' === $request->checkOut) {
            return;
        }

        if (new \DateTimeImmutable($request->checkOut) <= new \DateTimeImmutable($request->checkIn)) {
            $context->buildViolation('checkOut must be after checkIn.')
                ->atPath('checkOut')
                ->addViolation();
        }
    }
}
