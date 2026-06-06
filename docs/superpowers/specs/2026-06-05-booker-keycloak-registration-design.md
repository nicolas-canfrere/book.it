# Booker Registration with Keycloak — Flow A Design

**Date:** 2026-06-05
**Branch:** feat/security-user
**Status:** Approved

## Context

book.it is a multi-app system: a Next.js frontend, a Symfony API, a Next.js backoffice, and Keycloak as IdP. This spec covers the registration flow for a new Booker using Flow A: the Symfony API orchestrates all steps (domain validation → Keycloak account creation → domain persistence).

## Goals

- Register a Booker with a single `POST /bookers` request that includes credentials
- Keep Keycloak as a pure infrastructure concern — the Booker domain has no knowledge of Keycloak
- Support future user types (hotel staff, admins) via a generic Security context
- Compensate synchronously on failure (best-effort Keycloak account deletion if DB save fails)

## Non-Goals

- Email verification (accounts created with `emailVerified: true`)
- Async event-driven registration
- End-to-end Keycloak tests in CI

---

## Architecture

### New `Security` Bounded Context

A new `Security` context owns all Keycloak interaction and the generic identity mapping.

```
Security\
  Application\
    Contract\
      AccountRegistrarInterface        // published contract
  Infrastructure\
    Keycloak\
      KeycloakAccountRegistrar         // implements AccountRegistrarInterface
    Persistence\
      IdentityMappingRepository
```

**Published contract:**
```php
// Security\Application\Contract\AccountRegistrarInterface
interface AccountRegistrarInterface
{
    public function register(string $internalId, string $context, string $email, string $password): void;
    public function unregister(string $internalId, string $context): void;
}
```

**Identity mapping table:**
```sql
identity_mapping(
    internal_id  UUID         NOT NULL,
    context      VARCHAR(50)  NOT NULL,  -- 'booker' | 'hotel_staff' | 'admin' ...
    external_id  UUID         NOT NULL,
    PRIMARY KEY (internal_id, context)
)
```

`KeycloakAccountRegistrar`:
1. Calls Keycloak Admin REST API — creates account with `emailVerified: true`
2. Retrieves the created user's `sub` (UUID) as `external_id`
3. Persists `identity_mapping(internalId, context, keycloakId)` via `IdentityMappingRepository`

### Booker Context Changes

**Deleted:**
- `RegisterBookerCommand` + `RegisterBookerCommandHandler`
- `RegisterBookerCommandFactory`

**New domain port:**
```
Booker\Domain\Port\ExternalAccountRegistrarInterface
    ::register(string $bookerId, string $email, string $password): void
    ::unregister(string $bookerId): void
```

**New Infrastructure adapter (cross-context bridge):**
```
Booker\Infrastructure\Contract\SecurityAccountRegistrarAdapter
    implements ExternalAccountRegistrarInterface
    delegates to Security\Application\Contract\AccountRegistrarInterface
```
The adapter passes `context = 'booker'` — the Booker context never knows the string.

**New use case:**
```
Booker\Application\UseCase\RegisterBookerWithCredentials\
    RegisterBookerWithCredentialsCommand
    RegisterBookerWithCredentialsCommandHandler
```

**Modified:**
- `RegisterBookerRequest` — adds `password` field
- `RegisterBookerController` — calls `RegisterBookerWithCredentialsCommand`

---

## Data Flow

```
POST /bookers
{ firstName, lastName, email, phone, dateOfBirth, password }
        │
        ▼
RegisterBookerController (Booker\UI)
        │
        ▼
RegisterBookerWithCredentialsCommand
{ bookerId (generated), firstName, lastName, email, phone, dateOfBirth, password, registeredAt }
        │
        ▼
RegisterBookerWithCredentialsCommandHandler
        │
        ├─ 1. age < 18 ?          → BookerUnderageException (422)
        │
        ├─ 2. email duplicate ?   → BookerAlreadyExistsException (409)
        │
        ├─ 3. ExternalAccountRegistrarInterface::register(bookerId, email, password)
        │       └─ Keycloak failure  →  ExternalAccountCreationException (500), nothing to compensate
        │
        ├─ 4. BookerRepositoryInterface::add(booker)
        │       └─ DB failure  →  ::unregister(bookerId) [best-effort]  →  500
        │
        └─ 5. return bookerId
```

`bookerId` is generated inside the handler via `BookerIdGeneratorInterface` — it is book.it's own UUID, independent of the Keycloak `sub`.

---

## Error Handling

| Situation | Exception | HTTP status |
|---|---|---|
| Booker under 18 | `BookerUnderageException` | 422 |
| Email already registered | `BookerAlreadyExistsException` | 409 |
| Keycloak account creation failed | `ExternalAccountCreationException` | 500 |
| DB save failed (after compensation) | generic | 500 |

`ExternalAccountCreationException` lives in `Booker\Domain\Exception\` and is mapped in `config/services/exceptions.yaml`.

**Compensation:** `unregister()` is best-effort. If Keycloak is unreachable during compensation, the error is logged and the 500 is returned. Orphaned Keycloak accounts can be reconciled by a background job.

---

## Architecture Rules

`deptrac-contexts.yaml` must be updated to allow:
- `Booker\Infrastructure\Contract\` → `Security\Application\Contract\`

No other cross-context dependency is introduced.

---

## Tests

| Test | Type | Group |
|---|---|---|
| `RegisterBookerWithCredentialsCommandHandlerTest` | age, email, Keycloak failure, DB failure, happy path | `unit` |
| `KeycloakAccountRegistrarTest` | Keycloak HTTP request construction, mapping persistence | `unit` |
| `SecurityAccountRegistrarAdapterTest` | port delegation to Security contract | `integration` |
| `RegisterBookerControllerTest` | 201, 409, 422, 500 (mocked `ExternalAccountRegistrarInterface`) | `functional` |

---

## Files to Create

```
src/Security/Application/Contract/AccountRegistrarInterface.php
src/Security/Infrastructure/Keycloak/KeycloakAccountRegistrar.php
src/Security/Infrastructure/Persistence/IdentityMappingRepository.php

src/Booker/Domain/Port/ExternalAccountRegistrarInterface.php
src/Booker/Domain/Exception/ExternalAccountCreationException.php
src/Booker/Infrastructure/Contract/SecurityAccountRegistrarAdapter.php
src/Booker/Application/UseCase/RegisterBookerWithCredentials/RegisterBookerWithCredentialsCommand.php
src/Booker/Application/UseCase/RegisterBookerWithCredentials/RegisterBookerWithCredentialsCommandHandler.php
```

## Files to Modify

```
src/Booker/UI/Http/Controller/RegisterBooker/RegisterBookerRequest.php   (add password)
src/Booker/UI/Http/Controller/RegisterBooker/RegisterBookerController.php (use new command)
config/services/exceptions.yaml                                           (map ExternalAccountCreationException)
config/deptrac-contexts.yaml                                              (allow Booker Infra → Security Contract)
docker-compose.yml                                                        (add Keycloak service)
```

## Files to Delete

```
src/Booker/Application/UseCase/RegisterBooker/RegisterBookerCommand.php
src/Booker/Application/UseCase/RegisterBooker/RegisterBookerCommandHandler.php
src/Booker/Application/Service/RegisterBookerCommandFactory.php
```
