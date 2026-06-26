<?php

declare(strict_types=1);

namespace App\Pricing\UI\Http\Controller\CreateRatePeriod;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateRatePeriodRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Date]
        #[OA\Property(type: 'string', format: 'date', example: '2025-07-01')]
        public string $checkIn,
        #[Assert\NotBlank]
        #[Assert\Date]
        #[Assert\GreaterThan(propertyPath: 'checkIn')]
        #[OA\Property(type: 'string', format: 'date', example: '2025-08-31')]
        public string $checkOut,
        #[Assert\NotBlank]
        #[Assert\Positive]
        #[OA\Property(type: 'number', format: 'float', example: 150.00)]
        public float $amount,
    ) {
    }
}
