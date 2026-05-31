<?php

declare(strict_types=1);

namespace App\Room\UI\Http\Controller\DeclareRoomTypeAmenities;

use App\Room\Domain\ValueObject\RoomAmenity;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class DeclareRoomTypeAmenitiesRequest
{
    /**
     * @param string[] $amenities
     */
    public function __construct(
        #[Assert\All(constraints: [new Assert\Choice(callback: [RoomAmenity::class, 'values'])])]
        #[Assert\Unique]
        #[OA\Property(
            type: 'array',
            items: new OA\Items(type: 'string'),
            example: ['wifi', 'tv', 'minibar'],
        )]
        public array $amenities = [],
    ) {
    }
}
