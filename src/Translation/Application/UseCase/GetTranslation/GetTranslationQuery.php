<?php

declare(strict_types=1);

namespace App\Translation\Application\UseCase\GetTranslation;

use App\Shared\Application\Bus\SyncQueryInterface;
use App\Translation\Domain\Model\Translation;
use App\Translation\Domain\ValueObject\SubjectType;

/**
 * @implements SyncQueryInterface<?Translation>
 */
final readonly class GetTranslationQuery implements SyncQueryInterface
{
    public function __construct(
        public SubjectType $subjectType,
        public string $subjectId,
        public string $requestedLocale,
    ) {
    }
}
