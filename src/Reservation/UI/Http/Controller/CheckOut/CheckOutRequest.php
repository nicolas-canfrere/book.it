<?php

declare(strict_types=1);

namespace App\Reservation\UI\Http\Controller\CheckOut;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class CheckOutRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Date]
        #[OA\Property(type: 'string', format: 'date', example: '2026-06-13')]
        public string $actualDepartureDate,
    ) {
    }
}
