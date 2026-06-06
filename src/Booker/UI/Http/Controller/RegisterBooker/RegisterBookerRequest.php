<?php

declare(strict_types=1);

namespace App\Booker\UI\Http\Controller\RegisterBooker;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class RegisterBookerRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(min: 1, max: 100)]
        #[OA\Property(type: 'string', example: 'Jean', maxLength: 100, minLength: 1)]
        public ?string $firstName = null,
        #[Assert\NotBlank]
        #[Assert\Length(min: 1, max: 100)]
        #[OA\Property(type: 'string', example: 'Dupont', maxLength: 100, minLength: 1)]
        public ?string $lastName = null,
        #[Assert\NotBlank]
        #[Assert\Email]
        #[OA\Property(type: 'string', format: 'email', example: 'jean.dupont@example.com')]
        public ?string $email = null,
        #[Assert\NotBlank]
        #[Assert\Length(min: 5, max: 50)]
        #[OA\Property(type: 'string', example: '+33612345678', maxLength: 50, minLength: 5)]
        public ?string $phone = null,
        #[Assert\NotBlank]
        #[Assert\Date]
        #[OA\Property(type: 'string', format: 'date', example: '1990-05-15')]
        public ?string $dateOfBirth = null,
        #[Assert\NotBlank]
        #[Assert\Length(min: 8, max: 100)]
        #[OA\Property(type: 'string', example: 'MySecurePassword123!', minLength: 8, maxLength: 100)]
        public ?string $password = null,
    ) {
    }
}
