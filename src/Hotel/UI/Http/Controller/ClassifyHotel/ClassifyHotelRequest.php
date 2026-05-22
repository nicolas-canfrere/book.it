<?php

declare(strict_types=1);

namespace App\Hotel\UI\Http\Controller\ClassifyHotel;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[Assert\Expression(
    expression: 'not (this.superior and this.stars === null)',
    message: 'stars must be provided when superior is true.',
)]
final readonly class ClassifyHotelRequest
{
    public function __construct(
        #[Assert\Range(min: 1, max: 5)]
        #[OA\Property(type: 'integer', example: 4, minimum: 1, maximum: 5, nullable: true)]
        public ?int $stars = null,
        #[OA\Property(type: 'boolean', example: false)]
        public bool $superior = false,
    ) {
    }
}
