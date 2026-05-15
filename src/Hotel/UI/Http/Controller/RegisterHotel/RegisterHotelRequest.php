<?php

declare(strict_types=1);

namespace App\Hotel\UI\Http\Controller\RegisterHotel;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class RegisterHotelRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(min: 2, max: 255)]
        #[OA\Property(type: 'string', example: 'Hotel Ibis Paris', maxLength: 255, minLength: 2)]
        public string $name,

        #[Assert\NotBlank]
        #[Assert\Length(min: 2, max: 255)]
        #[OA\Property(type: 'string', example: '15 rue de Rivoli', maxLength: 255, minLength: 2)]
        public string $streetAddress,

        #[Assert\NotBlank]
        #[Assert\Length(min: 1, max: 20)]
        #[OA\Property(type: 'string', example: '75001', maxLength: 20, minLength: 1)]
        public string $postalCode,

        #[Assert\NotBlank]
        #[Assert\Length(min: 1, max: 255)]
        #[OA\Property(type: 'string', example: 'Paris', maxLength: 255, minLength: 1)]
        public string $city,

        #[Assert\NotBlank]
        #[Assert\Length(exactly: 2)]
        #[Assert\Country]
        #[OA\Property(type: 'string', example: 'FR', minLength: 2, maxLength: 2)]
        public string $country,
    ) {
    }
}
