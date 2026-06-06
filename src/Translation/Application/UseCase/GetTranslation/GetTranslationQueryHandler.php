<?php

declare(strict_types=1);

namespace App\Translation\Application\UseCase\GetTranslation;

use App\Shared\Application\Bus\SyncQueryHandlerInterface;
use App\Translation\Domain\Model\Translation;
use App\Translation\Domain\Port\TranslationRepositoryInterface;

final readonly class GetTranslationQueryHandler implements SyncQueryHandlerInterface
{
    public function __construct(
        private TranslationRepositoryInterface $translationRepository,
        private string $defaultLocale,
    ) {
    }

    public function __invoke(GetTranslationQuery $query): ?Translation
    {
        $translation = $this->translationRepository->findBySubjectAndLocale(
            $query->subjectType,
            $query->subjectId,
            $query->requestedLocale,
        );

        if (null !== $translation) {
            return $translation;
        }

        if ($query->requestedLocale === $this->defaultLocale) {
            return null;
        }

        return $this->translationRepository->findBySubjectAndLocale(
            $query->subjectType,
            $query->subjectId,
            $this->defaultLocale,
        );
    }
}
