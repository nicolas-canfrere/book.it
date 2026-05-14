<?php

declare(strict_types=1);

namespace App\Hotel\UI\Http\Controller\RegisterHotel;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class RegisterHotelRequest
{
    public function __construct(
        #[Assert\NotBlank()]
        #[Assert\Length(min: 2, max: 255)]
        #[OA\Property(type: 'string', example: 'Hotel Ibis Paris', maxLength: 255, minLength: 2)]
        public string $name,
    ) {
    }
}
