<?php

declare(strict_types=1);

namespace App\Hotel\UI\Http\Controller\ListHotels;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class ListHotelsRequest
{
    public function __construct(
        #[Assert\GreaterThanOrEqual(1)]
        #[OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1, minimum: 1))]
        public int $page = 1,
        #[Assert\GreaterThanOrEqual(1)]
        #[Assert\LessThanOrEqual(100)]
        #[OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', default: 20, maximum: 100, minimum: 1))]
        public int $limit = 20,
        #[Assert\Length(min: 1, max: 255)]
        #[OA\Parameter(name: 'city', in: 'query', schema: new OA\Schema(type: 'string', nullable: true))]
        public ?string $city = null,
        #[Assert\Country]
        #[OA\Parameter(name: 'country', in: 'query', schema: new OA\Schema(type: 'string', example: 'FR', nullable: true))]
        public ?string $country = null,
    ) {
    }
}
