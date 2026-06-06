# Translations Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement a `Translation` bounded context exposing `PUT /translations/{subjectType}/{subjectId}`, `GET /translations/{subjectType}/{subjectId}?locale=fr_FR`, and `GET /translations/locales` so operators can set multi-locale descriptions on Hotels and Room Types, and the backoffice can discover available locales.

**Architecture:** Autonomous `Translation` bounded context — no dependency on Hotel or Room. One DBAL table `translation(subject_type, subject_id, locale, text)` with a unique constraint and upsert semantics. Three use cases: `SetTranslation` (command, upsert), `GetTranslation` (query, with fallback to `app.default_locale`), and `GetSupportedLocales` (query, no DB — reads injected config). Two controllers sharing the `{subjectType}` path parameter, one dedicated locales endpoint.

**Tech Stack:** PHP 8.4, Symfony 8.0, Doctrine DBAL (raw SQL, `$translationConnection`), PHPUnit 13, `#[MapRequestPayload]` / `#[MapQueryString]`.

---

## File Map

**Create:**
```
src/Translation/Domain/Model/Translation.php
src/Translation/Domain/ValueObject/SubjectType.php
src/Translation/Domain/ValueObject/Locale.php
src/Translation/Domain/Port/TranslationRepositoryInterface.php
src/Translation/Domain/Port/TranslationIdGeneratorInterface.php
src/Translation/Domain/Exception/UnsupportedLocaleException.php
src/Translation/Application/UseCase/SetTranslation/SetTranslationCommand.php
src/Translation/Application/UseCase/SetTranslation/SetTranslationCommandHandler.php
src/Translation/Application/UseCase/GetTranslation/GetTranslationQuery.php
src/Translation/Application/UseCase/GetTranslation/GetTranslationQueryHandler.php
src/Translation/Application/UseCase/GetSupportedLocales/GetSupportedLocalesQuery.php
src/Translation/Application/UseCase/GetSupportedLocales/GetSupportedLocalesQueryHandler.php
src/Translation/Application/UseCase/GetSupportedLocales/SupportedLocalesView.php
src/Translation/Infrastructure/Persistence/Doctrine/TranslationRepository.php
src/Translation/Infrastructure/Service/TranslationUuidGenerator.php
src/Translation/UI/Http/Controller/SetTranslation/SetTranslationController.php
src/Translation/UI/Http/Controller/SetTranslation/SetTranslationRequest.php
src/Translation/UI/Http/Controller/GetTranslation/GetTranslationController.php
src/Translation/UI/Http/Controller/GetTranslation/GetTranslationRequest.php
src/Translation/UI/Http/Controller/GetSupportedLocales/GetSupportedLocalesController.php
src/Translation/UI/Http/Controller/TranslationSerializer.php
config/services/translation.yaml
tests/Translation/Domain/ValueObject/LocaleTest.php
tests/Translation/Application/UseCase/SetTranslation/SetTranslationCommandHandlerTest.php
tests/Translation/Application/UseCase/GetTranslation/GetTranslationQueryHandlerTest.php
tests/Translation/Application/UseCase/GetSupportedLocales/GetSupportedLocalesQueryHandlerTest.php
tests/Translation/UI/Http/Controller/SetTranslation/SetTranslationControllerTest.php
tests/Translation/UI/Http/Controller/GetTranslation/GetTranslationControllerTest.php
tests/Translation/UI/Http/Controller/GetSupportedLocales/GetSupportedLocalesControllerTest.php
```

**Modify:**
```
config/packages/doctrine.yaml             — add translation DBAL connection
config/services.yaml                      — add app.supported_locales + app.default_locale parameters
config/services/exceptions.yaml          — add UnsupportedLocaleException mapping
```

---

## Task 1: Domain layer

**Files:**
- Create: `src/Translation/Domain/Model/Translation.php`
- Create: `src/Translation/Domain/ValueObject/SubjectType.php`
- Create: `src/Translation/Domain/ValueObject/Locale.php`
- Create: `src/Translation/Domain/Port/TranslationRepositoryInterface.php`
- Create: `src/Translation/Domain/Port/TranslationIdGeneratorInterface.php`
- Create: `src/Translation/Domain/Exception/UnsupportedLocaleException.php`
- Test: `tests/Translation/Domain/ValueObject/LocaleTest.php`

- [ ] **Step 1: Write the failing Locale test**

```php
// tests/Translation/Domain/ValueObject/LocaleTest.php
<?php
declare(strict_types=1);

namespace App\Tests\Translation\Domain\ValueObject;

use App\Translation\Domain\ValueObject\Locale;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class LocaleTest extends TestCase
{
    public function test_accepts_language_with_region(): void
    {
        $locale = new Locale('fr_FR');
        self::assertSame('fr_FR', $locale->value);
    }

    public function test_accepts_language_only(): void
    {
        $locale = new Locale('en');
        self::assertSame('en', $locale->value);
    }

    public function test_rejects_empty_string(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Locale('');
    }

    public function test_rejects_string_exceeding_ten_characters(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Locale('fr_FR_EXTRA1');
    }

    public function test_rejects_uppercase_language_code(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Locale('FR_FR');
    }

    public function test_rejects_lowercase_region_code(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Locale('fr_fr');
    }
}
```

- [ ] **Step 2: Run test to confirm it fails**

```bash
make unit-test
```

Expected: failures — `App\Translation\Domain\ValueObject\Locale` does not exist.

- [ ] **Step 3: Create the domain files**

```php
// src/Translation/Domain/ValueObject/SubjectType.php
<?php
declare(strict_types=1);

namespace App\Translation\Domain\ValueObject;

enum SubjectType: string
{
    case Hotel = 'hotel';
    case RoomType = 'room_type';
}
```

```php
// src/Translation/Domain/ValueObject/Locale.php
<?php
declare(strict_types=1);

namespace App\Translation\Domain\ValueObject;

final readonly class Locale
{
    public function __construct(public string $value)
    {
        if ('' === $value) {
            throw new \InvalidArgumentException('Locale cannot be empty.');
        }
        if (strlen($value) > 10) {
            throw new \InvalidArgumentException('Locale must not exceed 10 characters.');
        }
        if (!preg_match('/^[a-z]{2,3}(_[A-Z]{2})?$/', $value)) {
            throw new \InvalidArgumentException(sprintf('"%s" is not a valid locale format.', $value));
        }
    }
}
```

```php
// src/Translation/Domain/Model/Translation.php
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
```

```php
// src/Translation/Domain/Port/TranslationRepositoryInterface.php
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
```

```php
// src/Translation/Domain/Port/TranslationIdGeneratorInterface.php
<?php
declare(strict_types=1);

namespace App\Translation\Domain\Port;

interface TranslationIdGeneratorInterface
{
    public function generate(): string;
}
```

```php
// src/Translation/Domain/Exception/UnsupportedLocaleException.php
<?php
declare(strict_types=1);

namespace App\Translation\Domain\Exception;

final class UnsupportedLocaleException extends \DomainException
{
    public function __construct(string $locale)
    {
        parent::__construct(sprintf('Locale "%s" is not supported.', $locale));
    }
}
```

- [ ] **Step 4: Run tests to confirm they pass**

```bash
make unit-test
```

Expected: all `LocaleTest` cases pass.

- [ ] **Step 5: Commit**

```bash
git add src/Translation/Domain/ tests/Translation/Domain/
git commit -m "feat(translation): add Translation domain layer"
```

---

## Task 2: SetTranslation use case

**Files:**
- Create: `src/Translation/Application/UseCase/SetTranslation/SetTranslationCommand.php`
- Create: `src/Translation/Application/UseCase/SetTranslation/SetTranslationCommandHandler.php`
- Test: `tests/Translation/Application/UseCase/SetTranslation/SetTranslationCommandHandlerTest.php`

- [ ] **Step 1: Write the failing handler test**

```php
// tests/Translation/Application/UseCase/SetTranslation/SetTranslationCommandHandlerTest.php
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

    public function test_creates_new_translation_when_none_exists(): void
    {
        $this->idGenerator->method('generate')->willReturn('550e8400-e29b-41d4-a716-446655440000');
        $this->repository->method('findBySubjectAndLocale')->willReturn(null);
        $this->repository->expects($this->once())->method('save')->with(
            $this->callback(static fn(Translation $t): bool =>
                '550e8400-e29b-41d4-a716-446655440000' === $t->id
                && SubjectType::Hotel === $t->subjectType
                && 'hotel-uuid' === $t->subjectId
                && 'fr_FR' === $t->locale
                && 'Bel hôtel' === $t->text
            )
        );

        ($this->handler)(new SetTranslationCommand(SubjectType::Hotel, 'hotel-uuid', 'fr_FR', 'Bel hôtel'));
    }

    public function test_updates_existing_translation_keeping_original_id_and_created_at(): void
    {
        $createdAt = new \DateTimeImmutable('2026-01-01 12:00:00');
        $existing = new Translation('existing-id', SubjectType::Hotel, 'hotel-uuid', 'fr_FR', 'Old text', $createdAt);
        $this->repository->method('findBySubjectAndLocale')->willReturn($existing);
        $this->repository->expects($this->once())->method('save')->with(
            $this->callback(static fn(Translation $t): bool =>
                'existing-id' === $t->id
                && 'New text' === $t->text
                && $createdAt === $t->createdAt
            )
        );

        ($this->handler)(new SetTranslationCommand(SubjectType::Hotel, 'hotel-uuid', 'fr_FR', 'New text'));
    }

    public function test_throws_when_locale_is_not_supported(): void
    {
        $this->expectException(UnsupportedLocaleException::class);
        $this->expectExceptionMessage('"de_DE" is not supported');

        ($this->handler)(new SetTranslationCommand(SubjectType::Hotel, 'hotel-uuid', 'de_DE', 'Text'));
    }

    public function test_works_for_room_type_subject(): void
    {
        $this->idGenerator->method('generate')->willReturn('550e8400-e29b-41d4-a716-446655440001');
        $this->repository->method('findBySubjectAndLocale')->willReturn(null);
        $this->repository->expects($this->once())->method('save')->with(
            $this->callback(static fn(Translation $t): bool => SubjectType::RoomType === $t->subjectType)
        );

        ($this->handler)(new SetTranslationCommand(SubjectType::RoomType, 'room-type-uuid', 'en_GB', 'Cosy room'));
    }
}
```

- [ ] **Step 2: Run test to confirm it fails**

```bash
make unit-test
```

Expected: failures — `SetTranslationCommand` and `SetTranslationCommandHandler` do not exist.

- [ ] **Step 3: Implement the command and handler**

```php
// src/Translation/Application/UseCase/SetTranslation/SetTranslationCommand.php
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
```

```php
// src/Translation/Application/UseCase/SetTranslation/SetTranslationCommandHandler.php
<?php
declare(strict_types=1);

namespace App\Translation\Application\UseCase\SetTranslation;

use App\Shared\Application\Bus\SyncCommandHandlerInterface;
use App\Translation\Domain\Exception\UnsupportedLocaleException;
use App\Translation\Domain\Model\Translation;
use App\Translation\Domain\Port\TranslationIdGeneratorInterface;
use App\Translation\Domain\Port\TranslationRepositoryInterface;
use App\Translation\Domain\ValueObject\Locale;

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

        new Locale($command->locale);

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
```

- [ ] **Step 4: Run tests to confirm they pass**

```bash
make unit-test
```

Expected: all `SetTranslationCommandHandlerTest` cases pass.

- [ ] **Step 5: Commit**

```bash
git add src/Translation/Application/UseCase/SetTranslation/ tests/Translation/Application/UseCase/SetTranslation/
git commit -m "feat(translation): add SetTranslation use case"
```

---

## Task 3: GetTranslation use case

**Files:**
- Create: `src/Translation/Application/UseCase/GetTranslation/GetTranslationQuery.php`
- Create: `src/Translation/Application/UseCase/GetTranslation/GetTranslationQueryHandler.php`
- Test: `tests/Translation/Application/UseCase/GetTranslation/GetTranslationQueryHandlerTest.php`

- [ ] **Step 1: Write the failing handler test**

```php
// tests/Translation/Application/UseCase/GetTranslation/GetTranslationQueryHandlerTest.php
<?php
declare(strict_types=1);

namespace App\Tests\Translation\Application\UseCase\GetTranslation;

use App\Translation\Application\UseCase\GetTranslation\GetTranslationQuery;
use App\Translation\Application\UseCase\GetTranslation\GetTranslationQueryHandler;
use App\Translation\Domain\Model\Translation;
use App\Translation\Domain\Port\TranslationRepositoryInterface;
use App\Translation\Domain\ValueObject\SubjectType;
use PHPUnit\Framework\Attributes\Group;
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

    public function test_returns_translation_for_requested_locale(): void
    {
        $translation = new Translation('id', SubjectType::Hotel, 'hotel-uuid', 'fr_FR', 'Bel hôtel', new \DateTimeImmutable());
        $this->repository->method('findBySubjectAndLocale')
            ->with(SubjectType::Hotel, 'hotel-uuid', 'fr_FR')
            ->willReturn($translation);

        $result = ($this->handler)(new GetTranslationQuery(SubjectType::Hotel, 'hotel-uuid', 'fr_FR'));

        self::assertSame($translation, $result);
    }

    public function test_falls_back_to_default_locale_when_requested_locale_not_found(): void
    {
        $fallback = new Translation('id', SubjectType::Hotel, 'hotel-uuid', 'en_GB', 'Nice hotel', new \DateTimeImmutable());
        $this->repository->method('findBySubjectAndLocale')
            ->willReturnCallback(static function (SubjectType $type, string $id, string $locale) use ($fallback): ?Translation {
                return 'en_GB' === $locale ? $fallback : null;
            });

        $result = ($this->handler)(new GetTranslationQuery(SubjectType::Hotel, 'hotel-uuid', 'de_DE'));

        self::assertSame($fallback, $result);
    }

    public function test_returns_null_when_neither_requested_nor_default_locale_found(): void
    {
        $this->repository->method('findBySubjectAndLocale')->willReturn(null);

        $result = ($this->handler)(new GetTranslationQuery(SubjectType::Hotel, 'hotel-uuid', 'de_DE'));

        self::assertNull($result);
    }

    public function test_does_not_double_query_when_requested_locale_is_default(): void
    {
        $this->repository->expects($this->once())
            ->method('findBySubjectAndLocale')
            ->with(SubjectType::Hotel, 'hotel-uuid', 'en_GB')
            ->willReturn(null);

        $result = ($this->handler)(new GetTranslationQuery(SubjectType::Hotel, 'hotel-uuid', 'en_GB'));

        self::assertNull($result);
    }
}
```

- [ ] **Step 2: Run test to confirm it fails**

```bash
make unit-test
```

Expected: failures — `GetTranslationQuery` and `GetTranslationQueryHandler` do not exist.

- [ ] **Step 3: Implement the query and handler**

```php
// src/Translation/Application/UseCase/GetTranslation/GetTranslationQuery.php
<?php
declare(strict_types=1);

namespace App\Translation\Application\UseCase\GetTranslation;

use App\Shared\Application\Bus\SyncQueryInterface;
use App\Translation\Domain\ValueObject\SubjectType;

final readonly class GetTranslationQuery implements SyncQueryInterface
{
    public function __construct(
        public SubjectType $subjectType,
        public string $subjectId,
        public string $requestedLocale,
    ) {
    }
}
```

```php
// src/Translation/Application/UseCase/GetTranslation/GetTranslationQueryHandler.php
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
```

- [ ] **Step 4: Run tests to confirm they pass**

```bash
make unit-test
```

Expected: all `GetTranslationQueryHandlerTest` cases pass.

- [ ] **Step 5: Commit**

```bash
git add src/Translation/Application/UseCase/GetTranslation/ tests/Translation/Application/UseCase/GetTranslation/
git commit -m "feat(translation): add GetTranslation use case"
```

---

## Task 4: Infrastructure — repository and UUID generator

**Files:**
- Create: `src/Translation/Infrastructure/Persistence/Doctrine/TranslationRepository.php`
- Create: `src/Translation/Infrastructure/Service/TranslationUuidGenerator.php`

No unit tests for the repository at this stage — it will be covered by functional tests in Tasks 7 & 8.

- [ ] **Step 1: Create the UUID generator**

```php
// src/Translation/Infrastructure/Service/TranslationUuidGenerator.php
<?php
declare(strict_types=1);

namespace App\Translation\Infrastructure\Service;

use App\Translation\Domain\Port\TranslationIdGeneratorInterface;
use Symfony\Component\Uid\Uuid;

final readonly class TranslationUuidGenerator implements TranslationIdGeneratorInterface
{
    public function generate(): string
    {
        return Uuid::v4()->toRfc4122();
    }
}
```

- [ ] **Step 2: Create the DBAL repository**

```php
// src/Translation/Infrastructure/Persistence/Doctrine/TranslationRepository.php
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
```

- [ ] **Step 3: Commit**

```bash
git add src/Translation/Infrastructure/
git commit -m "feat(translation): add DBAL repository and UUID generator"
```

---

## Task 5: DBAL connection and migration

**Files:**
- Modify: `config/packages/doctrine.yaml` — add `translation` connection
- Create: migration file via `make generate-migration` then filled manually

- [ ] **Step 1: Add the translation DBAL connection**

In `config/packages/doctrine.yaml`, inside `doctrine.dbal.connections`, add after the existing connections:

```yaml
            translation:
                server_version: '16'
                url: '%env(resolve:BOOKIT_DATABASE_URL)%' # same DB, isolated via search_path=translation (set by SearchPathMiddleware)
                profiling_collect_backtrace: '%kernel.debug%'
```

- [ ] **Step 2: Generate an empty migration file**

```bash
make generate-migration
```

Note the generated filename (e.g. `migrations/Version20260606XXXXXX.php`).

- [ ] **Step 3: Fill the migration with the translation schema**

Replace the `up()` and `down()` method bodies in the generated file:

```php
public function up(Schema $schema): void
{
    $this->addSql("CREATE SCHEMA IF NOT EXISTS translation");
    $this->addSql("
        CREATE TABLE translation.translation (
            id           UUID         NOT NULL,
            subject_type VARCHAR(50)  NOT NULL,
            subject_id   UUID         NOT NULL,
            locale       VARCHAR(10)  NOT NULL,
            text         TEXT         NOT NULL,
            created_at   TIMESTAMP    NOT NULL,
            updated_at   TIMESTAMP    NOT NULL,
            PRIMARY KEY (id),
            UNIQUE (subject_type, subject_id, locale)
        )
    ");
    $this->addSql("CREATE INDEX ON translation.translation (subject_type, subject_id)");
}

public function down(Schema $schema): void
{
    $this->addSql("DROP TABLE IF EXISTS translation.translation");
    $this->addSql("DROP SCHEMA IF EXISTS translation");
}
```

- [ ] **Step 4: Run the migration**

```bash
make migrate
```

Expected: migration runs without error.

- [ ] **Step 5: Commit**

```bash
git add config/packages/doctrine.yaml migrations/
git commit -m "feat(translation): add DBAL connection and translation table migration"
```

---

## Task 6: DI config and parameters

**Files:**
- Create: `config/services/translation.yaml`
- Modify: `config/services.yaml` — add parameters
- Modify: `config/services/exceptions.yaml` — add exception mapping

- [ ] **Step 1: Create `config/services/translation.yaml`**

```yaml
parameters: {}

services:
    _defaults:
        autowire: true
        autoconfigure: true
    _instanceof:
        App\Shared\Application\Bus\SyncCommandHandlerInterface:
            tags:
                - {name: messenger.message_handler, bus: sync.command.bus}
        App\Shared\Application\Bus\SyncQueryHandlerInterface:
            tags:
                - {name: messenger.message_handler, bus: sync.query.bus}

    App\Translation\Domain\:
        resource: '../../src/Translation/Domain/'
        exclude:
            - '../../src/Translation/Domain/Model/'

    App\Translation\Application\:
        resource: '../../src/Translation/Application/'
        exclude:
            - '../../src/Translation/Application/**/*Command.php'
            - '../../src/Translation/Application/**/*Query.php'

    App\Translation\Infrastructure\:
        resource: '../../src/Translation/Infrastructure/'

    App\Translation\UI\:
        resource: '../../src/Translation/UI/'
        exclude:
            - '../../src/Translation/UI/**/*Request.php'

    App\Translation\Application\UseCase\SetTranslation\SetTranslationCommandHandler:
        arguments:
            $supportedLocales: '%app.supported_locales%'

    App\Translation\Application\UseCase\GetTranslation\GetTranslationQueryHandler:
        arguments:
            $defaultLocale: '%app.default_locale%'

    bookit.doctrine.middleware.search_path.translation:
        class: App\Shared\Infrastructure\Doctrine\SearchPathMiddleware
        arguments:
            $schema: 'translation'
        tags:
            - {name: doctrine.middleware, connection: translation}
```

- [ ] **Step 2: Add locale parameters to `config/services.yaml`**

Add the following under the `parameters:` key in `config/services.yaml`:

```yaml
    app.supported_locales: ['fr_FR', 'en_GB', 'de_DE']
    app.default_locale:    'en_GB'
```

- [ ] **Step 3: Register the exception in `config/services/exceptions.yaml`**

Inside the `$map:` block of `ExceptionProblemRegistry`, add:

```yaml
                App\Translation\Domain\Exception\UnsupportedLocaleException:
                    type: 'https://book.it/problems/unsupported-locale'
                    title: 'Unsupported Locale'
                    status: 422
```

- [ ] **Step 4: Verify the container compiles**

```bash
make lint
```

Expected: no DI errors. If `NoHandlerForMessageException` appears, check that `_instanceof` is present in `translation.yaml` and that handlers implement `SyncCommandHandlerInterface` / `SyncQueryHandlerInterface`.

- [ ] **Step 5: Commit**

```bash
git add config/services/translation.yaml config/services.yaml config/services/exceptions.yaml
git commit -m "feat(translation): wire DI config, locale parameters and exception mapping"
```

---

## Task 7: GetSupportedLocalesController

**Files:**
- Create: `src/Translation/Application/UseCase/GetSupportedLocales/GetSupportedLocalesQuery.php`
- Create: `src/Translation/Application/UseCase/GetSupportedLocales/SupportedLocalesView.php`
- Create: `src/Translation/Application/UseCase/GetSupportedLocales/GetSupportedLocalesQueryHandler.php`
- Create: `src/Translation/UI/Http/Controller/GetSupportedLocales/GetSupportedLocalesController.php`
- Test: `tests/Translation/Application/UseCase/GetSupportedLocales/GetSupportedLocalesQueryHandlerTest.php`
- Test: `tests/Translation/UI/Http/Controller/GetSupportedLocales/GetSupportedLocalesControllerTest.php`

- [ ] **Step 1: Write the failing handler test**

```php
// tests/Translation/Application/UseCase/GetSupportedLocales/GetSupportedLocalesQueryHandlerTest.php
<?php
declare(strict_types=1);

namespace App\Tests\Translation\Application\UseCase\GetSupportedLocales;

use App\Translation\Application\UseCase\GetSupportedLocales\GetSupportedLocalesQuery;
use App\Translation\Application\UseCase\GetSupportedLocales\GetSupportedLocalesQueryHandler;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class GetSupportedLocalesQueryHandlerTest extends TestCase
{
    public function test_returns_supported_locales_and_default(): void
    {
        $handler = new GetSupportedLocalesQueryHandler(['fr_FR', 'en_GB', 'de_DE'], 'en_GB');

        $view = ($handler)(new GetSupportedLocalesQuery());

        self::assertSame(['fr_FR', 'en_GB', 'de_DE'], $view->supported);
        self::assertSame('en_GB', $view->default);
    }
}
```

- [ ] **Step 2: Run test to confirm it fails**

```bash
make unit-test
```

Expected: failures — `GetSupportedLocalesQuery` and `GetSupportedLocalesQueryHandler` do not exist.

- [ ] **Step 3: Write the failing functional test**

```php
// tests/Translation/UI/Http/Controller/GetSupportedLocales/GetSupportedLocalesControllerTest.php
<?php
declare(strict_types=1);

namespace App\Tests\Translation\UI\Http\Controller\GetSupportedLocales;

use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group('functional')]
final class GetSupportedLocalesControllerTest extends WebTestCase
{
    public function test_returns_200_with_supported_locales_and_default(): void
    {
        $client = static::createClient();
        $client->request('GET', '/translations/locales');

        self::assertResponseStatusCodeSame(200);
        $body = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertArrayHasKey('supported', $body);
        self::assertArrayHasKey('default', $body);
        self::assertIsArray($body['supported']);
        self::assertNotEmpty($body['supported']);
        self::assertContains($body['default'], $body['supported']);
    }
}
```

- [ ] **Step 4: Run functional test to confirm it fails**

```bash
make functional-test
```

Expected: failure — route `/translations/locales` not found.

- [ ] **Step 5: Implement query, view and handler**

```php
// src/Translation/Application/UseCase/GetSupportedLocales/GetSupportedLocalesQuery.php
<?php
declare(strict_types=1);

namespace App\Translation\Application\UseCase\GetSupportedLocales;

use App\Shared\Application\Bus\SyncQueryInterface;

final readonly class GetSupportedLocalesQuery implements SyncQueryInterface
{
}
```

```php
// src/Translation/Application/UseCase/GetSupportedLocales/SupportedLocalesView.php
<?php
declare(strict_types=1);

namespace App\Translation\Application\UseCase\GetSupportedLocales;

final readonly class SupportedLocalesView
{
    /** @param list<string> $supported */
    public function __construct(
        public array $supported,
        public string $default,
    ) {
    }
}
```

```php
// src/Translation/Application/UseCase/GetSupportedLocales/GetSupportedLocalesQueryHandler.php
<?php
declare(strict_types=1);

namespace App\Translation\Application\UseCase\GetSupportedLocales;

use App\Shared\Application\Bus\SyncQueryHandlerInterface;

final readonly class GetSupportedLocalesQueryHandler implements SyncQueryHandlerInterface
{
    /** @param list<string> $supportedLocales */
    public function __construct(
        private array $supportedLocales,
        private string $defaultLocale,
    ) {
    }

    public function __invoke(GetSupportedLocalesQuery $query): SupportedLocalesView
    {
        return new SupportedLocalesView($this->supportedLocales, $this->defaultLocale);
    }
}
```

- [ ] **Step 6: Implement the controller**

```php
// src/Translation/UI/Http/Controller/GetSupportedLocales/GetSupportedLocalesController.php
<?php
declare(strict_types=1);

namespace App\Translation\UI\Http\Controller\GetSupportedLocales;

use App\Shared\Application\Bus\SyncQueryBusInterface;
use App\Translation\Application\UseCase\GetSupportedLocales\GetSupportedLocalesQuery;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final readonly class GetSupportedLocalesController
{
    public function __construct(private SyncQueryBusInterface $queryBus)
    {
    }

    #[Route('/translations/locales', name: 'translation_supported_locales', methods: ['GET'])]
    #[OA\Get(
        summary: 'List supported locales and the default fallback locale',
        tags: ['Translation'],
        responses: [
            new OA\Response(
                response: Response::HTTP_OK,
                description: 'Supported locales',
                content: new OA\JsonContent(
                    required: ['supported', 'default'],
                    properties: [
                        new OA\Property(property: 'supported', type: 'array', items: new OA\Items(type: 'string'), example: ['fr_FR', 'en_GB', 'de_DE']),
                        new OA\Property(property: 'default', type: 'string', example: 'en_GB'),
                    ],
                ),
            ),
        ],
    )]
    public function __invoke(): Response
    {
        $view = $this->queryBus->ask(new GetSupportedLocalesQuery());

        return new JsonResponse([
            'supported' => $view->supported,
            'default'   => $view->default,
        ]);
    }
}
```

- [ ] **Step 7: Wire the handler in `config/services/translation.yaml`**

Add after the `GetTranslationQueryHandler` explicit wiring:

```yaml
    App\Translation\Application\UseCase\GetSupportedLocales\GetSupportedLocalesQueryHandler:
        arguments:
            $supportedLocales: '%app.supported_locales%'
            $defaultLocale: '%app.default_locale%'
```

- [ ] **Step 8: Run all tests to confirm they pass**

```bash
make unit-test
make functional-test
```

Expected: all `GetSupportedLocalesQueryHandlerTest` and `GetSupportedLocalesControllerTest` cases pass.

- [ ] **Step 9: Commit**

```bash
git add src/Translation/Application/UseCase/GetSupportedLocales/ \
        src/Translation/UI/Http/Controller/GetSupportedLocales/ \
        tests/Translation/Application/UseCase/GetSupportedLocales/ \
        tests/Translation/UI/Http/Controller/GetSupportedLocales/ \
        config/services/translation.yaml
git commit -m "feat(translation): add GetSupportedLocales use case and controller"
```

---

## Task 9: SetTranslationController

**Files:**
- Create: `src/Translation/UI/Http/Controller/SetTranslation/SetTranslationRequest.php`
- Create: `src/Translation/UI/Http/Controller/SetTranslation/SetTranslationController.php`
- Test: `tests/Translation/UI/Http/Controller/SetTranslation/SetTranslationControllerTest.php`

- [ ] **Step 1: Write the failing functional test**

```php
// tests/Translation/UI/Http/Controller/SetTranslation/SetTranslationControllerTest.php
<?php
declare(strict_types=1);

namespace App\Tests\Translation\UI\Http\Controller\SetTranslation;

use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group('functional')]
final class SetTranslationControllerTest extends WebTestCase
{
    private static function request(KernelBrowser $client, string $subjectType, string $subjectId, array $body): void
    {
        $client->request(
            'PUT',
            "/translations/{$subjectType}/{$subjectId}",
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            (string) json_encode($body),
        );
    }

    public function test_sets_hotel_translation_and_returns_204(): void
    {
        $client = static::createClient();
        self::request($client, 'hotel', '550e8400-e29b-41d4-a716-446655440000', [
            'locale' => 'fr_FR',
            'text'   => 'Un magnifique hôtel au cœur de Paris.',
        ]);

        self::assertResponseStatusCodeSame(204);
    }

    public function test_sets_room_type_translation_and_returns_204(): void
    {
        $client = static::createClient();
        self::request($client, 'room_type', '550e8400-e29b-41d4-a716-446655440001', [
            'locale' => 'en_GB',
            'text'   => 'A cosy room with a sea view.',
        ]);

        self::assertResponseStatusCodeSame(204);
    }

    public function test_returns_422_when_locale_is_not_supported(): void
    {
        $client = static::createClient();
        self::request($client, 'hotel', '550e8400-e29b-41d4-a716-446655440000', [
            'locale' => 'ja_JP',
            'text'   => 'テキスト',
        ]);

        self::assertResponseStatusCodeSame(422);
    }

    public function test_returns_422_when_text_is_blank(): void
    {
        $client = static::createClient();
        self::request($client, 'hotel', '550e8400-e29b-41d4-a716-446655440000', [
            'locale' => 'fr_FR',
            'text'   => '',
        ]);

        self::assertResponseStatusCodeSame(422);
    }

    public function test_returns_404_for_unknown_subject_type(): void
    {
        $client = static::createClient();
        self::request($client, 'unknown', '550e8400-e29b-41d4-a716-446655440000', [
            'locale' => 'fr_FR',
            'text'   => 'Some text',
        ]);

        self::assertResponseStatusCodeSame(404);
    }

    public function test_upsert_overwrites_existing_translation(): void
    {
        $client = static::createClient();
        $hotelId = '550e8400-e29b-41d4-a716-446655440002';

        self::request($client, 'hotel', $hotelId, ['locale' => 'fr_FR', 'text' => 'Premier texte']);
        self::assertResponseStatusCodeSame(204);

        self::request($client, 'hotel', $hotelId, ['locale' => 'fr_FR', 'text' => 'Texte mis à jour']);
        self::assertResponseStatusCodeSame(204);
    }
}
```

- [ ] **Step 2: Run test to confirm it fails**

```bash
make functional-test
```

Expected: failures — route not found (404 for all requests).

- [ ] **Step 3: Create the request DTO and controller**

```php
// src/Translation/UI/Http/Controller/SetTranslation/SetTranslationRequest.php
<?php
declare(strict_types=1);

namespace App\Translation\UI\Http\Controller\SetTranslation;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class SetTranslationRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 10)]
        #[Assert\Regex(pattern: '/^[a-z]{2,3}(_[A-Z]{2})?$/')]
        public string $locale,

        #[Assert\NotBlank]
        public string $text,
    ) {
    }
}
```

```php
// src/Translation/UI/Http/Controller/SetTranslation/SetTranslationController.php
<?php
declare(strict_types=1);

namespace App\Translation\UI\Http\Controller\SetTranslation;

use App\Shared\Application\Bus\SyncCommandBusInterface;
use App\Translation\Application\UseCase\SetTranslation\SetTranslationCommand;
use App\Translation\Domain\ValueObject\SubjectType;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final readonly class SetTranslationController
{
    public function __construct(private SyncCommandBusInterface $commandBus)
    {
    }

    #[Route(
        '/translations/{subjectType}/{subjectId}',
        name: 'translation_set',
        requirements: ['subjectType' => 'hotel|room_type', 'subjectId' => Requirement::UUID_V4],
        methods: ['PUT'],
    )]
    #[OA\Put(
        summary: 'Set a translation for a hotel or room type',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['locale', 'text'],
                properties: [
                    new OA\Property(property: 'locale', type: 'string', example: 'fr_FR'),
                    new OA\Property(property: 'text', type: 'string', example: 'Un magnifique hôtel.'),
                ],
            ),
        ),
        tags: ['Translation'],
        parameters: [
            new OA\Parameter(name: 'subjectType', in: 'path', required: true, schema: new OA\Schema(type: 'string', enum: ['hotel', 'room_type'])),
            new OA\Parameter(name: 'subjectId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: Response::HTTP_NO_CONTENT, description: 'Translation set'),
            new OA\Response(response: Response::HTTP_UNPROCESSABLE_ENTITY, description: 'Validation error or unsupported locale', content: new OA\MediaType(mediaType: 'application/problem+json', schema: new OA\Schema(ref: '#/components/schemas/ProblemDetail'))),
        ],
    )]
    public function __invoke(
        string $subjectType,
        string $subjectId,
        #[MapRequestPayload(acceptFormat: 'json', validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)] SetTranslationRequest $request,
    ): Response {
        $this->commandBus->execute(new SetTranslationCommand(
            SubjectType::from($subjectType),
            $subjectId,
            $request->locale,
            $request->text,
        ));

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
```

- [ ] **Step 4: Run tests to confirm they pass**

```bash
make functional-test
```

Expected: all `SetTranslationControllerTest` cases pass.

- [ ] **Step 5: Commit**

```bash
git add src/Translation/UI/Http/Controller/SetTranslation/ tests/Translation/UI/Http/Controller/SetTranslation/
git commit -m "feat(translation): add SetTranslationController"
```

---

## Task 10: GetTranslationController

**Files:**
- Create: `src/Translation/UI/Http/Controller/TranslationSerializer.php`
- Create: `src/Translation/UI/Http/Controller/GetTranslation/GetTranslationRequest.php`
- Create: `src/Translation/UI/Http/Controller/GetTranslation/GetTranslationController.php`
- Test: `tests/Translation/UI/Http/Controller/GetTranslation/GetTranslationControllerTest.php`

- [ ] **Step 1: Write the failing functional test**

```php
// tests/Translation/UI/Http/Controller/GetTranslation/GetTranslationControllerTest.php
<?php
declare(strict_types=1);

namespace App\Tests\Translation\UI\Http\Controller\GetTranslation;

use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group('functional')]
final class GetTranslationControllerTest extends WebTestCase
{
    private static function put(KernelBrowser $client, string $subjectType, string $subjectId, string $locale, string $text): void
    {
        $client->request(
            'PUT',
            "/translations/{$subjectType}/{$subjectId}",
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            (string) json_encode(['locale' => $locale, 'text' => $text]),
        );
    }

    private static function get(KernelBrowser $client, string $subjectType, string $subjectId, string $locale): void
    {
        $client->request('GET', "/translations/{$subjectType}/{$subjectId}?locale={$locale}");
    }

    public function test_returns_200_with_locale_and_text(): void
    {
        $client = static::createClient();
        $hotelId = '660e8400-e29b-41d4-a716-446655440000';
        self::put($client, 'hotel', $hotelId, 'fr_FR', 'Bel hôtel parisien.');

        self::get($client, 'hotel', $hotelId, 'fr_FR');

        self::assertResponseStatusCodeSame(200);
        $body = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame('fr_FR', $body['locale']);
        self::assertSame('Bel hôtel parisien.', $body['text']);
    }

    public function test_returns_fallback_locale_with_actual_locale_in_response(): void
    {
        $client = static::createClient();
        $hotelId = '660e8400-e29b-41d4-a716-446655440001';
        self::put($client, 'hotel', $hotelId, 'en_GB', 'Nice hotel.');

        self::get($client, 'hotel', $hotelId, 'de_DE');

        self::assertResponseStatusCodeSame(200);
        $body = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame('en_GB', $body['locale']); // fallback locale returned
        self::assertSame('Nice hotel.', $body['text']);
    }

    public function test_returns_404_when_no_translation_found(): void
    {
        $client = static::createClient();
        self::get($client, 'hotel', '660e8400-e29b-41d4-a716-446655440099', 'fr_FR');

        self::assertResponseStatusCodeSame(404);
    }

    public function test_returns_room_type_translation(): void
    {
        $client = static::createClient();
        $roomTypeId = '660e8400-e29b-41d4-a716-446655440002';
        self::put($client, 'room_type', $roomTypeId, 'en_GB', 'Cosy room with sea view.');

        self::get($client, 'room_type', $roomTypeId, 'en_GB');

        self::assertResponseStatusCodeSame(200);
        $body = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame('en_GB', $body['locale']);
        self::assertSame('Cosy room with sea view.', $body['text']);
    }
}
```

- [ ] **Step 2: Run test to confirm it fails**

```bash
make functional-test
```

Expected: failures — GET route not found.

- [ ] **Step 3: Create the serializer, request DTO and controller**

```php
// src/Translation/UI/Http/Controller/TranslationSerializer.php
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
            'text'   => $translation->text,
        ];
    }
}
```

```php
// src/Translation/UI/Http/Controller/GetTranslation/GetTranslationRequest.php
<?php
declare(strict_types=1);

namespace App\Translation\UI\Http\Controller\GetTranslation;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class GetTranslationRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 10)]
        public string $locale = '',
    ) {
    }
}
```

```php
// src/Translation/UI/Http/Controller/GetTranslation/GetTranslationController.php
<?php
declare(strict_types=1);

namespace App\Translation\UI\Http\Controller\GetTranslation;

use App\Shared\Application\Bus\SyncQueryBusInterface;
use App\Translation\Application\UseCase\GetTranslation\GetTranslationQuery;
use App\Translation\Domain\ValueObject\SubjectType;
use App\Translation\UI\Http\Controller\TranslationSerializer;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final readonly class GetTranslationController
{
    public function __construct(
        private SyncQueryBusInterface $queryBus,
        private TranslationSerializer $serializer,
    ) {
    }

    #[Route(
        '/translations/{subjectType}/{subjectId}',
        name: 'translation_get',
        requirements: ['subjectType' => 'hotel|room_type', 'subjectId' => Requirement::UUID_V4],
        methods: ['GET'],
    )]
    #[OA\Get(
        summary: 'Get the translation for a hotel or room type',
        tags: ['Translation'],
        parameters: [
            new OA\Parameter(name: 'subjectType', in: 'path', required: true, schema: new OA\Schema(type: 'string', enum: ['hotel', 'room_type'])),
            new OA\Parameter(name: 'subjectId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'locale', in: 'query', required: true, schema: new OA\Schema(type: 'string', example: 'fr_FR')),
        ],
        responses: [
            new OA\Response(
                response: Response::HTTP_OK,
                description: 'Translation found',
                content: new OA\JsonContent(
                    required: ['locale', 'text'],
                    properties: [
                        new OA\Property(property: 'locale', type: 'string', example: 'fr_FR', description: 'Actual locale returned — may differ from requested if fallback applied'),
                        new OA\Property(property: 'text', type: 'string', example: 'Un magnifique hôtel.'),
                    ],
                ),
            ),
            new OA\Response(response: Response::HTTP_NOT_FOUND, description: 'No translation found for subject', content: new OA\MediaType(mediaType: 'application/problem+json', schema: new OA\Schema(ref: '#/components/schemas/ProblemDetail'))),
        ],
    )]
    public function __invoke(
        string $subjectType,
        string $subjectId,
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)] GetTranslationRequest $request,
    ): Response {
        $translation = $this->queryBus->ask(new GetTranslationQuery(
            SubjectType::from($subjectType),
            $subjectId,
            $request->locale,
        ));

        if (null === $translation) {
            throw new NotFoundHttpException();
        }

        return new JsonResponse($this->serializer->serialize($translation));
    }
}
```

- [ ] **Step 4: Run tests to confirm they pass**

```bash
make functional-test
```

Expected: all `GetTranslationControllerTest` cases pass.

- [ ] **Step 5: Commit**

```bash
git add src/Translation/UI/Http/Controller/ tests/Translation/UI/Http/Controller/GetTranslation/
git commit -m "feat(translation): add GetTranslationController and TranslationSerializer"
```

---

## Task 11: OpenAPI, lint and full test suite

- [ ] **Step 1: Regenerate the OpenAPI spec**

```bash
make openapi
```

Expected: `openapi.yaml` updated with the two new Translation endpoints.

- [ ] **Step 2: Run the full lint suite**

```bash
make lint
```

Expected: CS Fixer, PHPStan and Deptrac all pass with no errors.

If deptrac reports a violation, it is most likely because `Translation\UI` imports `Translation\Domain` directly — verify all imports go through the Application layer where required.

- [ ] **Step 3: Run the full test suite**

```bash
make test
```

Expected: all tests pass (unit + functional).

- [ ] **Step 4: Commit**

```bash
git add openapi.yaml
git commit -m "chore(translation): regenerate openapi spec"
```
