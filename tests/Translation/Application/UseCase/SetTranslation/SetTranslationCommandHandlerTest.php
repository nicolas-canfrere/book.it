<?php

declare(strict_types=1);

namespace App\Tests\Translation\Application\UseCase\SetTranslation;

use App\Translation\Application\UseCase\SetTranslation\SetTranslationCommand;
use App\Translation\Application\UseCase\SetTranslation\SetTranslationCommandHandler;
use App\Translation\Domain\Exception\UnsupportedLocaleException;
use App\Translation\Domain\Model\Translation;
use App\Translation\Domain\Port\TranslationIdGeneratorInterface;
use App\Translation\Domain\Port\TranslationRepositoryInterface;
use App\Translation\Domain\ValueObject\SubjectType;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class SetTranslationCommandHandlerTest extends TestCase
{
    private TranslationRepositoryInterface&MockObject $repository;
    private TranslationIdGeneratorInterface&MockObject $idGenerator;
    private SetTranslationCommandHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(TranslationRepositoryInterface::class);
        $this->idGenerator = $this->createMock(TranslationIdGeneratorInterface::class);
        $this->handler = new SetTranslationCommandHandler(
            $this->repository,
            $this->idGenerator,
            ['fr_FR', 'en_GB'],
        );
    }

    #[Test]
    public function itCreatesNewTranslationWhenNoneExists(): void
    {
        $this->idGenerator->method('generate')->willReturn('550e8400-e29b-41d4-a716-446655440000');
        $this->repository->method('findBySubjectAndLocale')->willReturn(null);
        $this->repository->expects($this->once())->method('save')->with(
            $this->callback(
                static fn(Translation $t): bool => '550e8400-e29b-41d4-a716-446655440000' === $t->id
                && SubjectType::Hotel === $t->subjectType
                && 'hotel-uuid' === $t->subjectId
                && 'fr_FR' === $t->locale
                && 'Bel hôtel' === $t->text
            )
        );

        ($this->handler)(new SetTranslationCommand(SubjectType::Hotel, 'hotel-uuid', 'fr_FR', 'Bel hôtel'));
    }

    #[Test]
    public function itUpdatesExistingTranslationKeepingOriginalIdAndCreatedAt(): void
    {
        $createdAt = new \DateTimeImmutable('2026-01-01 12:00:00');
        $existing = new Translation('existing-id', SubjectType::Hotel, 'hotel-uuid', 'fr_FR', 'Old text', $createdAt);
        $this->repository->method('findBySubjectAndLocale')->willReturn($existing);
        $this->repository->expects($this->once())->method('save')->with(
            $this->callback(
                static fn(Translation $t): bool => 'existing-id' === $t->id
                && 'New text' === $t->text
                && $createdAt === $t->createdAt
            )
        );

        ($this->handler)(new SetTranslationCommand(SubjectType::Hotel, 'hotel-uuid', 'fr_FR', 'New text'));
    }

    #[Test]
    public function itThrowsWhenLocaleIsNotSupported(): void
    {
        $this->expectException(UnsupportedLocaleException::class);
        $this->expectExceptionMessage('Locale "de_DE" is not supported.');

        ($this->handler)(new SetTranslationCommand(SubjectType::Hotel, 'hotel-uuid', 'de_DE', 'Text'));
    }

    #[Test]
    public function itWorksForRoomTypeSubject(): void
    {
        $this->idGenerator->method('generate')->willReturn('550e8400-e29b-41d4-a716-446655440001');
        $this->repository->method('findBySubjectAndLocale')->willReturn(null);
        $this->repository->expects($this->once())->method('save')->with(
            $this->callback(static fn(Translation $t): bool => SubjectType::RoomType === $t->subjectType)
        );

        ($this->handler)(new SetTranslationCommand(SubjectType::RoomType, 'room-type-uuid', 'en_GB', 'Cosy room'));
    }
}
