# Keycloak HTTP Retry Mechanism

**Date:** 2026-06-06  
**Branch:** feat/security-user  
**Status:** Approved

## Context

`KeycloakAccountRegistrar` makes HTTP calls to Keycloak (token fetch, user creation, user deletion). Currently there is no retry logic — any transient failure surfaces immediately as `AccountRegistrationFailedException`.

## Requirements

- Retry on: network errors, 5xx, 401 (expired admin token), 429 (rate limiting)
- Backoff: respect `Retry-After` header for 429; exponential + jitter for all other retriable errors
- Max attempts: configurable via `KEYCLOAK_MAX_RETRIES` env var (default: 3, includes the initial attempt)
- Base delay: configurable via `KEYCLOAK_RETRY_BASE_DELAY` env var (default: 500ms)

## Architecture

### New components

**`KeycloakHttpClient`** (`Security\Infrastructure\Keycloak\KeycloakHttpClient`)

Façade HTTP dedicated to Keycloak. Takes ownership of:
- Admin token lifecycle (fetch, cache, invalidate on 401)
- Retry loop with backoff
- URL construction from base URL + realm

Exposes a single method:
```php
public function request(string $method, string $path, array $options = []): ResponseInterface
```

The `$path` is relative to the Keycloak base URL (e.g. `/admin/realms/{realm}/users`).

Retry logic per attempt:
```
2xx            → return response immediately
401            → invalidate cached token, re-fetch, retry (counts as one attempt)
429            → read Retry-After header (seconds), sleep, retry
5xx / network  → sleep 2^(attempt-1) × baseDelayMs + jitter(0..100ms), retry
other 4xx      → throw KeycloakUnavailableException immediately (not retriable)
maxAttempts    → throw KeycloakUnavailableException
```

A `$sleeper` callable (default: `usleep`) is injected to allow test doubles that skip real waits.

**`KeycloakRetryPolicy`** (`Security\Infrastructure\Keycloak\KeycloakRetryPolicy`)

Immutable value object:
```php
final readonly class KeycloakRetryPolicy {
    public function __construct(
        public int $maxAttempts,
        public int $baseDelayMs,
    ) {}
}
```

**`KeycloakUnavailableException`** (`Security\Infrastructure\Keycloak\KeycloakUnavailableException`)

Thrown when all retry attempts are exhausted or a non-retriable error occurs. Mapped in `config/services/exceptions.yaml` to HTTP 503.

### Modified component

**`KeycloakAccountRegistrar`**

Simplified: receives `KeycloakHttpClient` instead of `HttpClientInterface` directly. No longer owns token management or retry logic. Focuses on:
- Calling `$this->keycloakClient->request(...)` for register/unregister
- Extracting `keycloakId` from the `Location` header
- Persisting the identity mapping
- Structured logging

## Configuration

### Environment variables

```dotenv
KEYCLOAK_MAX_RETRIES=3
KEYCLOAK_RETRY_BASE_DELAY=500
```

### `config/services/security.yaml`

```yaml
App\Security\Infrastructure\Keycloak\KeycloakRetryPolicy:
    arguments:
        $maxAttempts: '%env(int:KEYCLOAK_MAX_RETRIES)%'
        $baseDelayMs: '%env(int:KEYCLOAK_RETRY_BASE_DELAY)%'

App\Security\Infrastructure\Keycloak\KeycloakHttpClient:
    arguments:
        $keycloakBaseUrl: '%env(KEYCLOAK_BASE_URL)%'
        $keycloakRealm: '%env(KEYCLOAK_REALM)%'
        $keycloakClientId: '%env(keycloak_CLIENT_ID)%'
        $keycloakClientSecret: '%env(keycloak_CLIENT_SECRET)%'

App\Security\Infrastructure\Keycloak\KeycloakAccountRegistrar:
    # no explicit arguments — autowiring resolves KeycloakHttpClient
```

The Keycloak connection env vars (`KEYCLOAK_BASE_URL`, `KEYCLOAK_REALM`, `keycloak_CLIENT_ID`, `keycloak_CLIENT_SECRET`) migrate from `KeycloakAccountRegistrar` to `KeycloakHttpClient`.

## Error handling

| HTTP status | Action |
|---|---|
| 2xx | Success — return response |
| 401 | Invalidate token cache, re-fetch token, retry |
| 429 | Respect `Retry-After` (seconds), sleep, retry |
| 5xx | Exponential backoff + jitter, retry |
| Other 4xx | `KeycloakUnavailableException` immediately |
| Exhausted | `KeycloakUnavailableException` |

`KeycloakUnavailableException` → mapped to 503 in `exceptions.yaml`.

## Tests

### `KeycloakHttpClientTest` — `#[Group('unit')]`

Stubs `HttpClientInterface`. Covers:

| Scenario | Expected |
|---|---|
| First attempt → 201 | Returns response, no retry |
| First → 5xx, second → 201 | Returns response, 1 retry, delay applied |
| 3× 5xx | Throws `KeycloakUnavailableException` |
| First → 401, second → 201 | Token invalidated, re-fetched, returns response |
| First → 429 with `Retry-After: 2` | Sleeps ~2s, retries |
| First → 400 | Throws immediately, no retry |

`$sleeper` injected as a no-op in tests to avoid real waits.

### `KeycloakAccountRegistrarTest` — `#[Group('unit')]`

Stubs `KeycloakHttpClient`. Covers:
- Extracts `keycloakId` from `Location` header, saves mapping
- Propagates `KeycloakUnavailableException` from client

Existing tests (`it_throws_underage_exception_before_calling_keycloak`, `it_throws_already_exists_before_calling_keycloak`) are unchanged.
