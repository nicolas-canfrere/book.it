<?php

declare(strict_types=1);

namespace App\Availability\UI\Http\Controller\BlockPeriod;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class BlockPeriodRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Date]
        #[OA\Property(type: 'string', format: 'date', example: '2025-06-10')]
        public string $checkIn,
        #[Assert\NotBlank]
        #[Assert\Date]
        #[OA\Property(type: 'string', format: 'date', example: '2025-06-13')]
        public string $checkOut,
    ) {
    }
}
