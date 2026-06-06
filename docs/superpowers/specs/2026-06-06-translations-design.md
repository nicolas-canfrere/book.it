# Translations — Design Spec

**Date:** 2026-06-06
**Branch:** feat/translations
**Status:** Approved

---

## Problem

Hotels and Room Types have no description field. Operators need to provide free-text descriptions in multiple languages so that bookers can read them in their preferred locale.

---

## Decisions

| # | Decision |
|---|----------|
| 1 | Descriptions apply to both `Hotel` and `RoomType` |
| 2 | Supported locales are configurable (Symfony parameters) — not hardcoded |
| 3 | Fallback: requested locale → `app.default_locale` → null |
| 4 | Write: one command per locale (`SetTranslation`) — upsert semantics |
| 5 | All endpoints live in the `Translation` bounded context — no changes to Hotel or Room |
| 6 | No cross-context existence check for `subjectId` — orphan translations are harmless |

---

## Architecture

`Translation` is a fully autonomous bounded context. It has no dependency on Hotel, Room, or any other context. Hotel and Room contexts are not modified.

```
Translation/
├── Domain/
│   ├── Model/          Translation
│   ├── Port/           TranslationRepositoryInterface
│   ├── ValueObject/    Locale, SubjectType (enum)
│   └── Exception/      UnsupportedLocaleException, TranslationNotFoundException
├── Application/
│   └── UseCase/
│       ├── SetTranslation/    SetTranslationCommand + Handler
│       └── GetTranslation/    GetTranslationQuery  + Handler
├── Infrastructure/
│   └── Persistence/
│       └── Doctrine/   TranslationRepository (DBAL)
└── UI/
    └── Http/
        └── Controller/
            ├── SetTranslation/
            └── GetTranslation/
```

---

## Domain Model

### `Translation`

```php
final readonly class Translation
{
    public function __construct(
        public string $id,               // UUID v4
        public SubjectType $subjectType,
        public string $subjectId,        // UUID of the target entity
        public string $locale,           // e.g. 'fr_FR', 'en_GB'
        public string $text,             // non-empty free text
        public \DateTimeImmutable $createdAt,
    ) {}
}
```

`updated_at` is an infrastructure concern — managed at the SQL level, not in the domain model.

### `SubjectType`

```php
enum SubjectType: string
{
    case Hotel    = 'hotel';
    case RoomType = 'room_type';
}
```

The enum lives in the Translation domain. Knowing which subjects are translatable is Translation's explicit responsibility. Adding a new translatable entity requires a new case — intentional friction that makes coupling visible and reviewable.

### `Locale`

A simple value object that validates format only (non-empty, max 10 chars, BCP 47-ish pattern). It does **not** validate against the configured list — that is the Application layer's responsibility.

```php
final readonly class Locale
{
    public function __construct(public string $value)
    {
        // validates: non-empty, ≤10 chars, matches /^[a-z]{2,3}(_[A-Z]{2})?$/
    }
}
```

---

## Configuration

```yaml
# config/services.yaml
parameters:
    app.supported_locales: ['fr_FR', 'en_GB', 'de_DE']
    app.default_locale:    'en_GB'
```

`SetTranslationCommandHandler` receives `$supportedLocales` and `$defaultLocale` by injection. If the submitted locale is not in `$supportedLocales`, it throws `UnsupportedLocaleException` (mapped to HTTP 422).

---

## Persistence

Single table, shared across all subject types:

```sql
CREATE TABLE translation (
    id           UUID         NOT NULL PRIMARY KEY,
    subject_type VARCHAR(50)  NOT NULL,
    subject_id   UUID         NOT NULL,
    locale       VARCHAR(10)  NOT NULL,
    text         TEXT         NOT NULL,
    created_at   TIMESTAMP    NOT NULL,
    updated_at   TIMESTAMP    NOT NULL,
    UNIQUE (subject_type, subject_id, locale)
);

CREATE INDEX ON translation (subject_type, subject_id);
```

`TranslationRepositoryInterface` (in `Domain\Port\`):

```php
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

`save()` performs an upsert on the `(subject_type, subject_id, locale)` unique constraint. The repository updates `updated_at` at the SQL level on conflict.

The implementation follows the existing DBAL style (named connection `bookit`, raw SQL — same pattern as `HotelRepository`).

---

## Use Cases

### `SetTranslation`

```
SetTranslationCommand(subjectType: SubjectType, subjectId: string, locale: string, text: string)
```

Handler steps:
1. Validate locale against `$supportedLocales` — throw `UnsupportedLocaleException` if absent
2. Build `Locale` value object (format validation)
3. Find existing translation for `(subjectType, subjectId, locale)`
4. If found: replace `text`, keep original `id` and `createdAt`, upsert
5. If not found: create new `Translation` with a new UUID and `createdAt = now()`
6. Call `repository->save()`

### `GetTranslation`

```
GetTranslationQuery(subjectType: SubjectType, subjectId: string, requestedLocale: string)
```

Handler steps:
1. Try `findBySubjectAndLocale(subjectType, subjectId, requestedLocale)`
2. If null and `requestedLocale !== $defaultLocale`: try `findBySubjectAndLocale(subjectType, subjectId, $defaultLocale)`
3. Return `?Translation` (null if both attempts fail)

The caller (controller) maps null to HTTP 404.

---

## HTTP Endpoints

### Write

```
PUT /translations/{subjectType}/{subjectId}

Path requirements: subjectType matches 'hotel|room_type', subjectId matches UUID v4

Body:
{
    "locale": "fr_FR",
    "text":   "Un hôtel magnifique..."
}

Response: 204 No Content
Errors:   404 if subjectType does not match requirement (Symfony route rejection)
          422 if locale not in supported list
          422 if text is blank
          400 if body is malformed
```

Single `SetTranslationController` — `subjectType` from the URL is cast to `SubjectType` enum in the controller.

### Read

```
GET /translations/{subjectType}/{subjectId}?locale=fr_FR

Path requirements: subjectType matches 'hotel|room_type', subjectId matches UUID v4

Response 200:
{
    "locale": "fr_FR",
    "text":   "Un hôtel magnifique..."
}

Response 404: if neither requested locale nor default locale has a translation
```

Single `GetTranslationController`. The `locale` field in the response reflects the **actual locale returned**, which may differ from the requested locale when fallback is applied.

---

## Error Handling

All errors follow the project's RFC 7807 pattern via `ProblemDetailExceptionListener`.

| Exception | HTTP | type slug |
|-----------|------|-----------|
| `UnsupportedLocaleException` | 422 | `unsupported-locale` |
| Translation not found (read) | 404 | mapped inline in controller |

Mappings go in `config/services/exceptions.yaml`.

---

## Testing

- `SetTranslationCommandHandlerTest` — unit, `#[Group('unit')]`
  - creates new translation
  - updates existing translation (upsert)
  - rejects unsupported locale
  - rejects blank text
- `GetTranslationQueryHandlerTest` — unit, `#[Group('unit')]`
  - returns requested locale when available
  - falls back to default locale
  - returns null when nothing found
- `SetTranslationControllerTest` — functional, `#[Group('functional')]`
  - sets hotel translation
  - sets room-type translation
  - rejects unknown subjectType (404)
- `GetTranslationControllerTest` — functional, `#[Group('functional')]`
  - returns translation for requested locale
  - returns fallback locale
  - returns 404 when nothing found

---

## Out of Scope

- Bulk set (multiple locales in one request)
- Delete a translation
- List all translations for a subject
- Full-text search on descriptions
- Translating fields other than `description` (e.g. `name`)
- Existence validation of `subjectId` against Hotel/Room contexts
