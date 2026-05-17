<?php

declare(strict_types=1);

namespace App\Pricing\UI\Http\Controller\GetPricingQuote;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class GetPricingQuoteRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Date]
        public ?string $checkIn = null,
        #[Assert\NotBlank]
        #[Assert\Date]
        #[Assert\GreaterThan(propertyPath: 'checkIn')]
        public ?string $checkOut = null,
    ) {
    }
}
