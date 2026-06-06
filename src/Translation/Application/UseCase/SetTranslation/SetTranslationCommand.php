<?php

declare(strict_types=1);

namespace App\Translation\Application\UseCase\SetTranslation;

use App\Shared\Application\Bus\SyncCommandInterface;
use App\Translation\Domain\ValueObject\SubjectType;

final readonly class SetTranslationCommand implements SyncCommandInterface
{
    public function __construct(
        public SubjectType $subjectType,
        public string $subjectId,
        public string $locale,
        public string $text,
    ) {
    }
}
