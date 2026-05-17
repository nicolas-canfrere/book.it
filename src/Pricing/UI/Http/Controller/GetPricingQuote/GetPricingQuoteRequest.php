<?php

declare(strict_types=1);

namespace App\Pricing\UI\Http\Controller\GetPricingQuote;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class GetPricingQuoteRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Date]
        #[OA\Parameter(name: 'checkIn', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'date', example: '2025-07-01'))]
        public ?string $checkIn = null,
        #[Assert\NotBlank]
        #[Assert\Date]
        #[Assert\GreaterThan(propertyPath: 'checkIn')]
        #[OA\Parameter(name: 'checkOut', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'date', example: '2025-07-05'))]
        public ?string $checkOut = null,
    ) {
    }
}
