<?php

declare(strict_types=1);

namespace App\Reservation\UI\Http\Controller\CheckOut;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CheckOutRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Date]
        public string $actualDepartureDate,
    ) {
    }
}
