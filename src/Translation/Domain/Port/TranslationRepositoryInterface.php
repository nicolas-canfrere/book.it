<?php

declare(strict_types=1);

namespace App\Translation\Domain\Port;

use App\Translation\Domain\Model\Translation;
use App\Translation\Domain\ValueObject\SubjectType;

interface TranslationRepositoryInterface
{
    public function save(Translation $translation): void;

    public function findBySubjectAndLocale(
        SubjectType $subjectType,
        string $subjectId,
        string $locale,
    ): ?Translation;
}
