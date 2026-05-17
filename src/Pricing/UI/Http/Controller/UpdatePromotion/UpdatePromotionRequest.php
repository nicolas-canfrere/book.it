<?php

declare(strict_types=1);

namespace App\Pricing\UI\Http\Controller\UpdatePromotion;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class UpdatePromotionRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Date]
        #[OA\Property(type: 'string', format: 'date', example: '2025-07-01')]
        public ?string $checkIn = null,
        #[Assert\NotBlank]
        #[Assert\Date]
        #[OA\Property(type: 'string', format: 'date', example: '2025-08-31')]
        public ?string $checkOut = null,
        #[Assert\NotBlank]
        #[Assert\Range(min: 1, max: 99)]
        #[OA\Property(type: 'integer', example: 20)]
        public ?int $discountPercent = null,
    ) {
    }
}
