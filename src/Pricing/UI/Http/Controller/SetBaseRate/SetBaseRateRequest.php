<?php

declare(strict_types=1);

namespace App\Pricing\UI\Http\Controller\SetBaseRate;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class SetBaseRateRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Positive]
        #[OA\Property(type: 'number', format: 'float', example: 120.00)]
        public ?float $amount = null,
    ) {
    }
}
