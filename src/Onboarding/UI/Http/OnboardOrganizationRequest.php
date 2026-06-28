<?php

declare(strict_types=1);

namespace App\Onboarding\UI\Http;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class OnboardOrganizationRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(min: 1, max: 255)]
        #[OA\Property(type: 'string', example: 'Hôtel Bellevue', maxLength: 255, minLength: 1)]
        public string $organizationName,
        #[Assert\NotBlank]
        #[Assert\Email]
        #[OA\Property(type: 'string', format: 'email', example: 'owner@bellevue.com')]
        public string $contactEmail,
        #[Assert\NotBlank]
        #[Assert\Length(min: 1, max: 100)]
        #[OA\Property(type: 'string', example: 'Alice', maxLength: 100, minLength: 1)]
        public string $ownerFirstName,
        #[Assert\NotBlank]
        #[Assert\Length(min: 1, max: 100)]
        #[OA\Property(type: 'string', example: 'Martin', maxLength: 100, minLength: 1)]
        public string $ownerLastName,
        #[Assert\NotBlank]
        #[Assert\Length(min: 5, max: 50)]
        #[OA\Property(type: 'string', example: '+33612345678', maxLength: 50, minLength: 5)]
        public string $ownerPhone,
        #[Assert\NotBlank]
        #[Assert\Length(min: 8, max: 100)]
        #[OA\Property(type: 'string', example: 'MySecurePassword123!', minLength: 8, maxLength: 100)]
        public string $password,
    ) {
    }
}
