<?php

declare(strict_types=1);

namespace App\Translation\Application\UseCase\SetTranslation;

use App\Shared\Application\Bus\SyncCommandHandlerInterface;
use App\Translation\Domain\Exception\UnsupportedLocaleException;
use App\Translation\Domain\Model\Translation;
use App\Translation\Domain\Port\TranslationIdGeneratorInterface;
use App\Translation\Domain\Port\TranslationRepositoryInterface;

final readonly class SetTranslationCommandHandler implements SyncCommandHandlerInterface
{
    /** @param list<string> $supportedLocales */
    public function __construct(
        private TranslationRepositoryInterface $translationRepository,
        private TranslationIdGeneratorInterface $translationIdGenerator,
        private array $supportedLocales,
    ) {
    }

    public function __invoke(SetTranslationCommand $command): void
    {
        if (!in_array($command->locale, $this->supportedLocales, true)) {
            throw new UnsupportedLocaleException($command->locale);
        }

        $existing = $this->translationRepository->findBySubjectAndLocale(
            $command->subjectType,
            $command->subjectId,
            $command->locale,
        );

        if (null !== $existing) {
            $translation = new Translation(
                $existing->id,
                $command->subjectType,
                $command->subjectId,
                $command->locale,
                $command->text,
                $existing->createdAt,
            );
        } else {
            $translation = new Translation(
                $this->translationIdGenerator->generate(),
                $command->subjectType,
                $command->subjectId,
                $command->locale,
                $command->text,
                new \DateTimeImmutable(),
            );
        }

        $this->translationRepository->save($translation);
    }
}
