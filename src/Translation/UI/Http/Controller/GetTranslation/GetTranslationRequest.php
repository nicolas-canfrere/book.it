<?php

declare(strict_types=1);

namespace App\Translation\UI\Http\Controller\GetTranslation;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class GetTranslationRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 10)]
        public string $locale = '',
    ) {
    }
}
