<?php

declare(strict_types=1);

namespace App\Translation\UI\Http\Controller;

use App\Translation\Domain\Model\Translation;

final readonly class TranslationSerializer
{
    /** @return array{locale: string, text: string} */
    public function serialize(Translation $translation): array
    {
        return [
            'locale' => $translation->locale,
            'text' => $translation->text,
        ];
    }
}
