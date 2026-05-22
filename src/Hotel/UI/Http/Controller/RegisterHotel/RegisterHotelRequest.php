<?php

declare(strict_types=1);

namespace App\Hotel\UI\Http\Controller\RegisterHotel;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[Assert\Expression(
    expression: 'not (this.superior and this.stars === null)',
    message: 'stars must be provided when superior is true.',
)]
final readonly class RegisterHotelRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(min: 2, max: 255)]
        #[OA\Property(type: 'string', example: 'Hotel Ibis Paris', maxLength: 255, minLength: 2)]
        public ?string $name = null,
        #[Assert\NotBlank]
        #[Assert\Length(min: 2, max: 255)]
        #[OA\Property(type: 'string', example: '15 rue de Rivoli', maxLength: 255, minLength: 2)]
        public ?string $streetAddress = null,
        #[Assert\NotBlank]
        #[Assert\Length(min: 1, max: 20)]
        #[OA\Property(type: 'string', example: '75001', maxLength: 20, minLength: 1)]
        public ?string $postalCode = null,
        #[Assert\NotBlank]
        #[Assert\Length(min: 1, max: 255)]
        #[OA\Property(type: 'string', example: 'Paris', maxLength: 255, minLength: 1)]
        public ?string $city = null,
        #[Assert\NotBlank]
        #[Assert\Length(exactly: 2)]
        #[Assert\Country]
        #[OA\Property(type: 'string', example: 'FR', maxLength: 2, minLength: 2)]
        public ?string $country = null,
        #[Assert\Range(min: 1, max: 5)]
        #[OA\Property(type: 'integer', example: 4, minimum: 1, maximum: 5, nullable: true)]
        public ?int $stars = null,
        #[OA\Property(type: 'boolean', example: false)]
        public bool $superior = false,
    ) {
    }
}
