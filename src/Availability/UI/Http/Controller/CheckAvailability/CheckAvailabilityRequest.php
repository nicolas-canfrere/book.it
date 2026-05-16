<?php

declare(strict_types=1);

namespace App\Availability\UI\Http\Controller\CheckAvailability;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class CheckAvailabilityRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Date]
        #[OA\Parameter(in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'date', example: '2025-06-10'))]
        public ?string $checkIn = null,
        #[Assert\NotBlank]
        #[Assert\Date]
        #[OA\Parameter(in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'date', example: '2025-06-13'))]
        public ?string $checkOut = null,
    ) {
    }
}
