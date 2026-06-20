<?php

declare(strict_types=1);

namespace App\Geo\UI\Http\Controller\SearchGeoPlaces;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class SearchGeoPlacesRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(min: 2, max: 255)]
        #[OA\Parameter(name: 'query', in: 'query', required: true, schema: new OA\Schema(type: 'string', example: 'pari'))]
        public ?string $query = null,
    ) {
    }
}
