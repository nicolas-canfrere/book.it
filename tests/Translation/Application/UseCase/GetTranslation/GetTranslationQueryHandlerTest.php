<?php

declare(strict_types=1);

namespace App\Tests\Translation\Application\UseCase\GetTranslation;

use App\Translation\Application\UseCase\GetTranslation\GetTranslationQuery;
use App\Translation\Application\UseCase\GetTranslation\GetTranslationQueryHandler;
use App\Translation\Domain\Model\Translation;
use App\Translation\Domain\Port\TranslationRepositoryInterface;
use App\Translation\Domain\ValueObject\SubjectType;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class GetTranslationQueryHandlerTest extends TestCase
{
    private TranslationRepositoryInterface&MockObject $repository;
    private GetTranslationQueryHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(TranslationRepositoryInterface::class);
        $this->handler = new GetTranslationQueryHandler($this->repository, 'en_GB');
    }

    #[Test]
    public function itReturnsTranslationForRequestedLocale(): void
    {
        $translation = new Translation('id', SubjectType::Hotel, 'hotel-uuid', 'fr_FR', 'Bel hôtel', new \DateTimeImmutable());
        $this->repository
            ->expects($this->once())
            ->method('findBySubjectAndLocale')
            ->with(SubjectType::Hotel, 'hotel-uuid', 'fr_FR')
            ->willReturn($translation);

        $result = ($this->handler)(new GetTranslationQuery(SubjectType::Hotel, 'hotel-uuid', 'fr_FR'));

        self::assertSame($translation, $result);
    }

    #[Test]
    public function itFallsBackToDefaultLocaleWhenRequestedLocaleNotFound(): void
    {
        $fallback = new Translation('id', SubjectType::Hotel, 'hotel-uuid', 'en_GB', 'Nice hotel', new \DateTimeImmutable());
        $this->repository->method('findBySubjectAndLocale')
            ->willReturnCallback(static function (SubjectType $type, string $id, string $locale) use ($fallback): ?Translation {
                return 'en_GB' === $locale ? $fallback : null;
            });

        $result = ($this->handler)(new GetTranslationQuery(SubjectType::Hotel, 'hotel-uuid', 'de_DE'));

        self::assertSame($fallback, $result);
    }

    #[Test]
    public function itReturnsNullWhenNeitherRequestedNorDefaultLocaleFound(): void
    {
        $this->repository->method('findBySubjectAndLocale')->willReturn(null);

        $result = ($this->handler)(new GetTranslationQuery(SubjectType::Hotel, 'hotel-uuid', 'de_DE'));

        self::assertNull($result);
    }

    #[Test]
    public function itDoesNotDoubleQueryWhenRequestedLocaleIsDefault(): void
    {
        $this->repository->expects($this->once())
            ->method('findBySubjectAndLocale')
            ->with(SubjectType::Hotel, 'hotel-uuid', 'en_GB')
            ->willReturn(null);

        $result = ($this->handler)(new GetTranslationQuery(SubjectType::Hotel, 'hotel-uuid', 'en_GB'));

        self::assertNull($result);
    }
}
