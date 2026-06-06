<?php

declare(strict_types=1);

namespace App\Translation\Domain\Model;

use App\Translation\Domain\ValueObject\SubjectType;

final readonly class Translation
{
    public function __construct(
        public string $id,
        public SubjectType $subjectType,
        public string $subjectId,
        public string $locale,
        public string $text,
        public \DateTimeImmutable $createdAt,
    ) {
    }
}
