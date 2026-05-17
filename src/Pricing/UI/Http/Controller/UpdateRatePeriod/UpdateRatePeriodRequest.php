<?php

declare(strict_types=1);

namespace App\Pricing\UI\Http\Controller\UpdateRatePeriod;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class UpdateRatePeriodRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Date]
        #[OA\Property(type: 'string', format: 'date', example: '2025-07-01')]
        public ?string $checkIn = null,
        #[Assert\NotBlank]
        #[Assert\Date]
        #[Assert\GreaterThan(propertyPath: 'checkIn')]
        #[OA\Property(type: 'string', format: 'date', example: '2025-08-31')]
        public ?string $checkOut = null,
        #[Assert\NotBlank]
        #[Assert\Positive]
        #[OA\Property(type: 'number', format: 'float', example: 160.00)]
        public ?float $amount = null,
    ) {
    }
}
