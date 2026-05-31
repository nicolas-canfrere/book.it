<?php

declare(strict_types=1);

namespace App\Hotel\UI\Http\Controller\DeclareHotelAmenities;

use App\Hotel\Domain\ValueObject\HotelAmenity;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class DeclareHotelAmenitiesRequest
{
    /**
     * @param string[] $amenities
     */
    public function __construct(
        #[Assert\All(constraints: [new Assert\Choice(callback: [HotelAmenity::class, 'values'])])]
        #[Assert\Unique]
        #[OA\Property(
            type: 'array',
            items: new OA\Items(type: 'string'),
            example: ['pool', 'gym', 'parking'],
        )]
        public array $amenities = [],
    ) {
    }
}
