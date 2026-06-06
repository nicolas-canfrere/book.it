<?php

declare(strict_types=1);

namespace App\Translation\Infrastructure\Persistence\Doctrine;

use App\Translation\Domain\Model\Translation;
use App\Translation\Domain\Port\TranslationRepositoryInterface;
use App\Translation\Domain\ValueObject\SubjectType;
use Doctrine\DBAL\Connection;

final readonly class TranslationRepository implements TranslationRepositoryInterface
{
    public function __construct(private Connection $translationConnection)
    {
    }

    public function save(Translation $translation): void
    {
        $this->translationConnection->executeStatement(
            'INSERT INTO translation (id, subject_type, subject_id, locale, text, created_at, updated_at)
             VALUES (:id, :subjectType, :subjectId, :locale, :text, :createdAt, :updatedAt)
             ON CONFLICT (subject_type, subject_id, locale)
             DO UPDATE SET text = EXCLUDED.text, updated_at = EXCLUDED.updated_at',
            [
                'id' => $translation->id,
                'subjectType' => $translation->subjectType->value,
                'subjectId' => $translation->subjectId,
                'locale' => $translation->locale,
                'text' => $translation->text,
                'createdAt' => $translation->createdAt->format('Y-m-d H:i:s'),
                'updatedAt' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ],
        );
    }

    public function findBySubjectAndLocale(
        SubjectType $subjectType,
        string $subjectId,
        string $locale,
    ): ?Translation {
        /** @var array{id: string, subject_type: string, subject_id: string, locale: string, text: string, created_at: string}|false $row */
        $row = $this->translationConnection->fetchAssociative(
            'SELECT id, subject_type, subject_id, locale, text, created_at
             FROM translation
             WHERE subject_type = :subjectType AND subject_id = :subjectId AND locale = :locale',
            [
                'subjectType' => $subjectType->value,
                'subjectId' => $subjectId,
                'locale' => $locale,
            ],
        );

        if (false === $row) {
            return null;
        }

        return new Translation(
            $row['id'],
            SubjectType::from($row['subject_type']),
            $row['subject_id'],
            $row['locale'],
            $row['text'],
            new \DateTimeImmutable($row['created_at']),
        );
    }
}
