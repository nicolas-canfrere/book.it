<?php

declare(strict_types=1);

namespace App\Translation\UI\Http\Controller\SetTranslation;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class SetTranslationRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 10)]
        #[Assert\Regex(pattern: '/^[a-z]{2,3}(_[A-Z]{2})?$/')]
        public string $locale,
        #[Assert\NotBlank]
        public string $text,
    ) {
    }
}
