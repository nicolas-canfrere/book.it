<?php

declare(strict_types=1);

namespace App\Room\UI\Http\Controller\ListRoomTypesByAmenity;

use App\Room\Domain\ValueObject\RoomAmenity;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class ListRoomTypesByAmenityRequest
{
    /**
     * @param string[] $amenities
     */
    public function __construct(
        #[Assert\All(constraints: [new Assert\Choice(callback: [RoomAmenity::class, 'values'])])]
        #[OA\Parameter(
            name: 'amenities[]',
            in: 'query',
            schema: new OA\Schema(
                type: 'array',
                items: new OA\Items(type: 'string'),
            ),
        )]
        public array $amenities = [],
        #[Assert\GreaterThanOrEqual(1)]
        #[OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1, minimum: 1))]
        public int $page = 1,
        #[Assert\GreaterThanOrEqual(1)]
        #[Assert\LessThanOrEqual(100)]
        #[OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', default: 20, maximum: 100, minimum: 1))]
        public int $limit = 20,
    ) {
    }
}
