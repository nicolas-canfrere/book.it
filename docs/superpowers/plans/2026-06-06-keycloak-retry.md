# Keycloak HTTP Retry Mechanism Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add retry logic (exponential backoff, Retry-After respect, 401 token refresh) to all Keycloak HTTP calls, configurable via env vars.

**Architecture:** Extract a `KeycloakHttpClient` class that owns the retry loop, token cache, and URL construction. `KeycloakAccountRegistrar` is simplified to pure business logic and delegates HTTP to `KeycloakHttpClient` via `KeycloakHttpClientInterface`. `KeycloakRetryPolicy` is a readonly value object carrying the retry config.

**Tech Stack:** PHP 8.4, Symfony 8.0, `symfony/http-client`, PHPUnit 11

---

### Task 1: Infrastructure scaffolding — policy, exception, env vars, exception mapping

**Files:**
- Create: `src/Security/Infrastructure/Keycloak/KeycloakRetryPolicy.php`
- Create: `src/Security/Infrastructure/Keycloak/KeycloakUnavailableException.php`
- Modify: `config/services/exceptions.yaml` — add 503 mapping
- Modify: `.env` — add two new env vars

- [ ] **Step 1: Create `KeycloakRetryPolicy`**

```php
<?php

declare(strict_types=1);

namespace App\Security\Infrastructure\Keycloak;

final readonly class KeycloakRetryPolicy
{
    public function __construct(
        public int $maxAttempts,
        public int $baseDelayMs,
    ) {}
}
```

- [ ] **Step 2: Create `KeycloakUnavailableException`**

```php
<?php

declare(strict_types=1);

namespace App\Security\Infrastructure\Keycloak;

final class KeycloakUnavailableException extends \RuntimeException {}
```

- [ ] **Step 3: Add env vars to `.env`**

Append after the existing `keycloak_CLIENT_SECRET` line:

```dotenv
KEYCLOAK_MAX_RETRIES=3
KEYCLOAK_RETRY_BASE_DELAY=500
```

- [ ] **Step 4: Map exception to 503 in `config/services/exceptions.yaml`**

Add inside the `$map:` block, alongside existing exception mappings:

```yaml
                App\Security\Infrastructure\Keycloak\KeycloakUnavailableException:
                    type: 'https://book.it/problems/keycloak-unavailable'
                    title: 'Keycloak Service Unavailable'
                    status: 503
```

- [ ] **Step 5: Commit**

```bash
git add src/Security/Infrastructure/Keycloak/KeycloakRetryPolicy.php
git add src/Security/Infrastructure/Keycloak/KeycloakUnavailableException.php
git add config/services/exceptions.yaml .env
git commit -m "feat(security): add KeycloakRetryPolicy, KeycloakUnavailableException and 503 mapping"
```

---

### Task 2: `KeycloakHttpClientInterface`

**Files:**
- Create: `src/Security/Infrastructure/Keycloak/KeycloakHttpClientInterface.php`

- [ ] **Step 1: Create the interface**

```php
<?php

declare(strict_types=1);

namespace App\Security\Infrastructure\Keycloak;

use Symfony\Contracts\HttpClient\ResponseInterface;

interface KeycloakHttpClientInterface
{
    public function createUser(string $email, string $password): ResponseInterface;

    public function deleteUser(string $keycloakId): void;
}
```

- [ ] **Step 2: Commit**

```bash
git add src/Security/Infrastructure/Keycloak/KeycloakHttpClientInterface.php
git commit -m "feat(security): add KeycloakHttpClientInterface"
```

---

### Task 3: Write failing tests for `KeycloakHttpClient`

**Files:**
- Create: `tests/Security/Infrastructure/Keycloak/KeycloakHttpClientTest.php`

> All tests will fail (class does not exist yet). Run `make unit-test` after creating the file and expect failures.

- [ ] **Step 1: Create the test file**

```php
<?php

declare(strict_types=1);

namespace App\Tests\Security\Infrastructure\Keycloak;

use App\Security\Infrastructure\Keycloak\KeycloakHttpClient;
use App\Security\Infrastructure\Keycloak\KeycloakRetryPolicy;
use App\Security\Infrastructure\Keycloak\KeycloakUnavailableException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

#[Group('unit')]
final class KeycloakHttpClientTest extends TestCase
{
    private HttpClientInterface&MockObject $httpClient;
    /** @var list<int> */
    private array $sleepCalls;
    private KeycloakHttpClient $client;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->sleepCalls = [];
        $sleeper = function (int $us): void {
            $this->sleepCalls[] = $us;
        };

        $this->client = new KeycloakHttpClient(
            httpClient: $this->httpClient,
            retryPolicy: new KeycloakRetryPolicy(maxAttempts: 3, baseDelayMs: 100),
            keycloakBaseUrl: 'https://keycloak.test',
            keycloakRealm: 'test-realm',
            keycloakClientId: 'client-id',
            keycloakClientSecret: 'client-secret',
            logger: new NullLogger(),
            sleeper: \Closure::fromCallable($sleeper),
        );
    }

    #[Test]
    public function itReturnsResponseOnFirstAttempt(): void
    {
        // token fetch (call 1) + user create (call 2)
        $this->httpClient->expects($this->exactly(2))
            ->method('request')
            ->willReturnOnConsecutiveCalls(
                $this->makeTokenResponse('token123'),
                $this->makeResponse(201),
            );

        $result = $this->client->createUser('test@example.com', 'password');

        self::assertSame(201, $result->getStatusCode());
        self::assertEmpty($this->sleepCalls);
    }

    #[Test]
    public function itRetriesOnServerErrorAndSucceeds(): void
    {
        // token fetch (call 1), 500 (call 2), 201 without re-fetching token (call 3)
        $this->httpClient->expects($this->exactly(3))
            ->method('request')
            ->willReturnOnConsecutiveCalls(
                $this->makeTokenResponse('token123'),
                $this->makeResponse(500),
                $this->makeResponse(201),
            );

        $result = $this->client->createUser('test@example.com', 'password');

        self::assertSame(201, $result->getStatusCode());
        self::assertCount(1, $this->sleepCalls);
    }

    #[Test]
    public function itThrowsAfterMaxAttemptsOnServerError(): void
    {
        // token fetch (call 1) + 3× 500 (calls 2-4)
        $this->httpClient->expects($this->exactly(4))
            ->method('request')
            ->willReturnOnConsecutiveCalls(
                $this->makeTokenResponse('token123'),
                $this->makeResponse(500),
                $this->makeResponse(500),
                $this->makeResponse(500),
            );

        $this->expectException(KeycloakUnavailableException::class);
        $this->client->createUser('test@example.com', 'password');
    }

    #[Test]
    public function itInvalidatesTokenOn401AndRetries(): void
    {
        // token fetch (call 1), 401 (call 2) → clear token, re-fetch (call 3), 201 (call 4)
        $this->httpClient->expects($this->exactly(4))
            ->method('request')
            ->willReturnOnConsecutiveCalls(
                $this->makeTokenResponse('expired-token'),
                $this->makeResponse(401),
                $this->makeTokenResponse('new-token'),
                $this->makeResponse(201),
            );

        $result = $this->client->createUser('test@example.com', 'password');

        self::assertSame(201, $result->getStatusCode());
        self::assertEmpty($this->sleepCalls); // no sleep on 401
    }

    #[Test]
    public function itRespectsRetryAfterHeaderOn429(): void
    {
        // token fetch (call 1), 429 (call 2), 201 without re-fetching token (call 3)
        $this->httpClient->expects($this->exactly(3))
            ->method('request')
            ->willReturnOnConsecutiveCalls(
                $this->makeTokenResponse('token'),
                $this->makeResponse(429, ['retry-after' => ['2']]),
                $this->makeResponse(201),
            );

        $result = $this->client->createUser('test@example.com', 'password');

        self::assertSame(201, $result->getStatusCode());
        self::assertSame([2_000_000], $this->sleepCalls); // 2 seconds in microseconds
    }

    #[Test]
    public function itThrowsImmediatelyOnNonRetriable4xx(): void
    {
        // token fetch (call 1), 400 (call 2) → throw immediately, no retry
        $this->httpClient->expects($this->exactly(2))
            ->method('request')
            ->willReturnOnConsecutiveCalls(
                $this->makeTokenResponse('token'),
                $this->makeResponse(400),
            );

        $this->expectException(KeycloakUnavailableException::class);
        $this->client->createUser('test@example.com', 'password');
    }

    #[Test]
    public function itRetriesOnNetworkError(): void
    {
        // call 1 (token fetch) → network exception; call 2 (token re-fetch) → ok; call 3 (user create) → 201
        $callCount = 0;
        $tokenResponse = $this->makeTokenResponse('token');
        $success = $this->makeResponse(201);

        $this->httpClient->method('request')
            ->willReturnCallback(function () use (&$callCount, $tokenResponse, $success) {
                ++$callCount;
                if (1 === $callCount) {
                    throw new \RuntimeException('Connection refused');
                }

                return 2 === $callCount ? $tokenResponse : $success;
            });

        $result = $this->client->createUser('test@example.com', 'password');

        self::assertSame(201, $result->getStatusCode());
        self::assertCount(1, $this->sleepCalls);
    }

    #[Test]
    public function itDeletesUser(): void
    {
        $this->httpClient->expects($this->exactly(2))
            ->method('request')
            ->willReturnOnConsecutiveCalls(
                $this->makeTokenResponse('token'),
                $this->makeResponse(204),
            );

        $this->client->deleteUser('keycloak-user-id');
        self::assertEmpty($this->sleepCalls);
    }

    private function makeTokenResponse(string $token): ResponseInterface&MockObject
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->willReturn(['access_token' => $token]);
        $response->method('getHeaders')->willReturn([]);

        return $response;
    }

    private function makeResponse(int $status, array $headers = []): ResponseInterface&MockObject
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn($status);
        $response->method('toArray')->willReturn([]);
        $response->method('getHeaders')->willReturn($headers);

        return $response;
    }
}
```

- [ ] **Step 2: Run tests — expect failures**

```bash
make unit-test 2>&1 | grep -E "KeycloakHttpClient|ERROR|FAIL" | head -20
```

Expected: class `KeycloakHttpClient` not found.

- [ ] **Step 3: Commit**

```bash
git add tests/Security/Infrastructure/Keycloak/KeycloakHttpClientTest.php
git commit -m "test(security): add failing tests for KeycloakHttpClient retry logic"
```

---

### Task 4: Implement `KeycloakHttpClient`

**Files:**
- Create: `src/Security/Infrastructure/Keycloak/KeycloakHttpClient.php`

- [ ] **Step 1: Implement `KeycloakHttpClient`**

```php
<?php

declare(strict_types=1);

namespace App\Security\Infrastructure\Keycloak;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class KeycloakHttpClient implements KeycloakHttpClientInterface
{
    private ?string $adminToken = null;
    private \Closure $sleeper;

    /**
     * @param \Closure(int): void|null $sleeper injectable for tests; defaults to usleep
     */
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly KeycloakRetryPolicy $retryPolicy,
        private readonly string $keycloakBaseUrl,
        private readonly string $keycloakRealm,
        private readonly string $keycloakClientId,
        private readonly string $keycloakClientSecret,
        private readonly LoggerInterface $logger,
        ?\Closure $sleeper = null,
    ) {
        $this->sleeper = $sleeper ?? static function (int $us): void { usleep($us); };
    }

    public function createUser(string $email, string $password): ResponseInterface
    {
        return $this->request('POST', "/admin/realms/{$this->keycloakRealm}/users", [
            'json' => [
                'email' => $email,
                'username' => $email,
                'emailVerified' => true,
                'enabled' => true,
                'credentials' => [[
                    'type' => 'password',
                    'value' => $password,
                    'temporary' => false,
                ]],
            ],
        ]);
    }

    public function deleteUser(string $keycloakId): void
    {
        $this->request('DELETE', "/admin/realms/{$this->keycloakRealm}/users/{$keycloakId}");
    }

    private function request(string $method, string $path, array $options = []): ResponseInterface
    {
        $attempt = 0;
        while (true) {
            ++$attempt;

            try {
                $options['auth_bearer'] = $this->ensureToken();
                $response = $this->httpClient->request($method, $this->keycloakBaseUrl.$path, $options);
                $status = $response->getStatusCode();
            } catch (\Throwable $e) {
                if ($attempt >= $this->retryPolicy->maxAttempts) {
                    throw new KeycloakUnavailableException('Keycloak is unreachable after retries', 0, $e);
                }
                ($this->sleeper)($this->exponentialDelayUs($attempt));
                continue;
            }

            if ($status >= 200 && $status < 300) {
                return $response;
            }

            if (401 === $status) {
                $this->adminToken = null;
                if ($attempt >= $this->retryPolicy->maxAttempts) {
                    throw new KeycloakUnavailableException('Keycloak authentication failed after retries');
                }
                continue;
            }

            if (429 === $status) {
                if ($attempt >= $this->retryPolicy->maxAttempts) {
                    throw new KeycloakUnavailableException('Keycloak rate limit exceeded after retries');
                }
                $retryAfterSeconds = (int) ($response->getHeaders(false)['retry-after'][0] ?? 1);
                ($this->sleeper)($retryAfterSeconds * 1_000_000);
                continue;
            }

            if ($status >= 500) {
                if ($attempt >= $this->retryPolicy->maxAttempts) {
                    throw new KeycloakUnavailableException("Keycloak server error {$status} after retries");
                }
                ($this->sleeper)($this->exponentialDelayUs($attempt));
                continue;
            }

            throw new KeycloakUnavailableException("Keycloak non-retriable error: HTTP {$status}");
        }
    }

    private function ensureToken(): string
    {
        if (null !== $this->adminToken) {
            return $this->adminToken;
        }

        $this->logger->debug('Fetching Keycloak admin token', ['realm' => $this->keycloakRealm]);

        $response = $this->httpClient->request(
            'POST',
            "{$this->keycloakBaseUrl}/realms/{$this->keycloakRealm}/protocol/openid-connect/token",
            [
                'body' => [
                    'grant_type' => 'client_credentials',
                    'client_id' => $this->keycloakClientId,
                    'client_secret' => $this->keycloakClientSecret,
                ],
            ],
        );

        $token = $response->toArray()['access_token'];
        $this->adminToken = \is_string($token) ? $token : '';

        return $this->adminToken;
    }

    private function exponentialDelayUs(int $attempt): int
    {
        $jitterMs = random_int(0, 100);

        return ($this->retryPolicy->baseDelayMs * (2 ** ($attempt - 1)) + $jitterMs) * 1_000;
    }
}
```

- [ ] **Step 2: Run tests — all should pass**

```bash
make unit-test 2>&1 | grep -E "KeycloakHttpClient|OK|FAIL|ERROR"
```

Expected: all 8 `KeycloakHttpClientTest` tests pass.

- [ ] **Step 3: Run static analysis**

```bash
make static-code-analysis 2>&1 | grep -i "keycloak"
```

Expected: no errors.

- [ ] **Step 4: Commit**

```bash
git add src/Security/Infrastructure/Keycloak/KeycloakHttpClient.php
git commit -m "feat(security): implement KeycloakHttpClient with retry, 401 refresh, and Retry-After support"
```

---

### Task 5: Refactor `KeycloakAccountRegistrar` and update its tests

**Files:**
- Modify: `src/Security/Infrastructure/Keycloak/KeycloakAccountRegistrar.php`
- Modify: `tests/Security/Infrastructure/Keycloak/KeycloakAccountRegistrarTest.php`

- [ ] **Step 1: Rewrite `KeycloakAccountRegistrar`**

Replace the entire file content:

```php
<?php

declare(strict_types=1);

namespace App\Security\Infrastructure\Keycloak;

use App\Security\Application\Contract\AccountRegistrarInterface;
use App\Security\Application\Contract\AccountRegistrationFailedException;
use App\Security\Infrastructure\Persistence\IdentityMappingRepository;
use Psr\Log\LoggerInterface;

final class KeycloakAccountRegistrar implements AccountRegistrarInterface
{
    public function __construct(
        private readonly KeycloakHttpClientInterface $keycloakClient,
        private readonly IdentityMappingRepository $mappingRepository,
        private readonly LoggerInterface $logger,
    ) {}

    public function register(string $internalId, string $context, string $email, string $password): void
    {
        $response = $this->keycloakClient->createUser($email, $password);
        $statusCode = $response->getStatusCode();

        if (201 !== $statusCode) {
            $this->logger->error('Keycloak account creation failed', [
                'internal_id' => $internalId,
                'context' => $context,
                'email' => $email,
                'status_code' => $statusCode,
            ]);
            throw new AccountRegistrationFailedException($email);
        }

        $location = $response->getHeaders(false)['location'][0] ?? '';
        $keycloakId = basename($location);

        $this->mappingRepository->save($internalId, $context, $keycloakId);

        $this->logger->info('Keycloak account created', [
            'internal_id' => $internalId,
            'context' => $context,
            'keycloak_id' => $keycloakId,
        ]);
    }

    public function unregister(string $internalId, string $context): void
    {
        $keycloakId = $this->mappingRepository->findExternalId($internalId, $context);
        if (null === $keycloakId) {
            $this->logger->debug('Keycloak unregister skipped: no mapping found', [
                'internal_id' => $internalId,
                'context' => $context,
            ]);

            return;
        }

        try {
            $this->keycloakClient->deleteUser($keycloakId);
            $this->logger->info('Keycloak account deleted', [
                'internal_id' => $internalId,
                'context' => $context,
                'keycloak_id' => $keycloakId,
            ]);
        } catch (\Throwable $e) {
            $this->logger->warning('Keycloak account deletion failed (best-effort)', [
                'internal_id' => $internalId,
                'context' => $context,
                'keycloak_id' => $keycloakId,
                'error' => $e->getMessage(),
            ]);
        }

        $this->mappingRepository->delete($internalId, $context);
    }
}
```

- [ ] **Step 2: Rewrite `KeycloakAccountRegistrarTest`**

Replace the entire file content (stubs the interface instead of `HttpClientInterface`):

```php
<?php

declare(strict_types=1);

namespace App\Tests\Security\Infrastructure\Keycloak;

use App\Security\Application\Contract\AccountRegistrationFailedException;
use App\Security\Infrastructure\Keycloak\KeycloakAccountRegistrar;
use App\Security\Infrastructure\Keycloak\KeycloakHttpClientInterface;
use App\Security\Infrastructure\Keycloak\KeycloakUnavailableException;
use App\Security\Infrastructure\Persistence\IdentityMappingRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Contracts\HttpClient\ResponseInterface;

#[Group('unit')]
final class KeycloakAccountRegistrarTest extends TestCase
{
    private KeycloakHttpClientInterface&MockObject $keycloakClient;
    private IdentityMappingRepository&MockObject $mappingRepository;
    private KeycloakAccountRegistrar $registrar;

    protected function setUp(): void
    {
        $this->keycloakClient = $this->createMock(KeycloakHttpClientInterface::class);
        $this->mappingRepository = $this->createMock(IdentityMappingRepository::class);
        $this->registrar = new KeycloakAccountRegistrar(
            $this->keycloakClient,
            $this->mappingRepository,
            new NullLogger(),
        );
    }

    #[Test]
    public function itCreatesAccountAndSavesMapping(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(201);
        $response->method('getHeaders')->willReturn([
            'location' => ['https://keycloak.test/admin/realms/test/users/keycloak-uuid'],
        ]);

        $this->keycloakClient->expects(self::once())
            ->method('createUser')
            ->with('test@example.com', 'password123')
            ->willReturn($response);

        $this->mappingRepository->expects(self::once())
            ->method('save')
            ->with('booker-uuid', 'booker', 'keycloak-uuid');

        $this->registrar->register('booker-uuid', 'booker', 'test@example.com', 'password123');
    }

    #[Test]
    public function itThrowsOnNon201Response(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(409);

        $this->keycloakClient->method('createUser')->willReturn($response);

        $this->expectException(AccountRegistrationFailedException::class);
        $this->registrar->register('booker-uuid', 'booker', 'test@example.com', 'password123');
    }

    #[Test]
    public function itPropagatesKeycloakUnavailableException(): void
    {
        $this->keycloakClient->method('createUser')
            ->willThrowException(new KeycloakUnavailableException('exhausted'));

        $this->expectException(KeycloakUnavailableException::class);
        $this->registrar->register('booker-uuid', 'booker', 'test@example.com', 'password123');
    }

    #[Test]
    public function itUnregistersAccountAndRemovesMapping(): void
    {
        $this->mappingRepository->method('findExternalId')
            ->with('booker-uuid', 'booker')
            ->willReturn('keycloak-uuid');

        $this->keycloakClient->expects(self::once())
            ->method('deleteUser')
            ->with('keycloak-uuid');

        $this->mappingRepository->expects(self::once())
            ->method('delete')
            ->with('booker-uuid', 'booker');

        $this->registrar->unregister('booker-uuid', 'booker');
    }

    #[Test]
    public function itSkipsUnregisterWhenMappingNotFound(): void
    {
        $this->mappingRepository->method('findExternalId')->willReturn(null);

        $this->keycloakClient->expects(self::never())->method('deleteUser');
        $this->mappingRepository->expects(self::never())->method('delete');

        $this->registrar->unregister('booker-uuid', 'booker');
    }

    #[Test]
    public function itDeletesMappingEvenWhenDeleteUserFails(): void
    {
        $this->mappingRepository->method('findExternalId')->willReturn('keycloak-uuid');
        $this->keycloakClient->method('deleteUser')
            ->willThrowException(new KeycloakUnavailableException('exhausted'));

        $this->mappingRepository->expects(self::once())
            ->method('delete')
            ->with('booker-uuid', 'booker');

        $this->registrar->unregister('booker-uuid', 'booker');
    }
}
```

- [ ] **Step 3: Run unit tests — all should pass**

```bash
make unit-test 2>&1 | grep -E "KeycloakAccount|OK|FAIL|ERROR"
```

Expected: all 6 `KeycloakAccountRegistrarTest` tests + 8 `KeycloakHttpClientTest` tests pass.

- [ ] **Step 4: Run static analysis**

```bash
make static-code-analysis 2>&1 | grep -i "keycloak"
```

Expected: no errors.

- [ ] **Step 5: Commit**

```bash
git add src/Security/Infrastructure/Keycloak/KeycloakAccountRegistrar.php
git add tests/Security/Infrastructure/Keycloak/KeycloakAccountRegistrarTest.php
git commit -m "refactor(security): simplify KeycloakAccountRegistrar to delegate HTTP to KeycloakHttpClient"
```

---

### Task 6: Wire DI — `config/services/security.yaml`

**Files:**
- Modify: `config/services/security.yaml`

- [ ] **Step 1: Update `security.yaml`**

Replace the entire file content:

```yaml
parameters: {}

services:
    _defaults:
        autowire: true
        autoconfigure: true

    App\Security\Application\Contract\AccountRegistrarInterface: '@App\Security\Infrastructure\Keycloak\KeycloakAccountRegistrar'

    App\Security\Infrastructure\:
        resource: '../../src/Security/Infrastructure/'
        exclude:
            - '../../src/Security/Infrastructure/**/*Exception.php'

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

    App\Security\Infrastructure\Keycloak\KeycloakHttpClientInterface: '@App\Security\Infrastructure\Keycloak\KeycloakHttpClient'

    App\Security\Infrastructure\Persistence\IdentityMappingRepository:
        arguments:
            $connection: '@doctrine.dbal.security_connection'

    bookit.doctrine.middleware.search_path.security:
        class: App\Shared\Infrastructure\Doctrine\SearchPathMiddleware
        arguments:
            $schema: 'security'
        tags:
            - {name: doctrine.middleware, connection: security}
```

Key changes from the previous version:
- `KeycloakRetryPolicy` wired with `KEYCLOAK_MAX_RETRIES` + `KEYCLOAK_RETRY_BASE_DELAY`
- `KeycloakHttpClient` wired with Keycloak connection env vars (moved from `KeycloakAccountRegistrar`)
- `KeycloakHttpClientInterface` aliased to `KeycloakHttpClient` for autowiring
- `KeycloakAccountRegistrar` no longer has explicit arguments (autowired)

- [ ] **Step 2: Run lint to verify container compiles**

```bash
make lint 2>&1 | tail -20
```

Expected: no errors. If Symfony raises a container compilation error about missing env vars, ensure `.env` has the new vars from Task 1 Step 3.

- [ ] **Step 3: Run full test suite**

```bash
make test 2>&1 | tail -30
```

Expected: all tests pass.

- [ ] **Step 4: Commit**

```bash
git add config/services/security.yaml
git commit -m "feat(security): wire KeycloakHttpClient and KeycloakRetryPolicy in DI"
```
