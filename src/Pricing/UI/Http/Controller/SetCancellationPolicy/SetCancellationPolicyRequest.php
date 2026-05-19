<?php

declare(strict_types=1);

namespace App\Pricing\UI\Http\Controller\SetCancellationPolicy;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class SetCancellationPolicyRequest
{
    public function __construct(
        #[OA\Property(type: 'integer', minimum: 1, example: 14)]
        #[Assert\NotBlank]
        #[Assert\Positive]
        public int $daysThreshold,
    ) {
    }
}
