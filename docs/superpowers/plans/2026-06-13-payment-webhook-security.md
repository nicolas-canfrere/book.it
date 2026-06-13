# Payment Webhook Security Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Secure the three payment webhook endpoints (`/api/v1/payment/webhooks/{success,failed,cancel}`) behind a dedicated firewall with HMAC-SHA256 signature verification and idempotency by `event_id`.

**Architecture:** A new Symfony firewall `payment_webhooks` (pattern `^/api/v1/payment/webhooks`) intercepts calls before the operator firewall and validates a `X-Webhook-Signature: t=<timestamp>,v1=<hmac>` header using a shared secret (`PAYMENT_WEBHOOK_SECRET`). Each webhook payload gains a mandatory `event_id` (UUID v4); the Application layer records it to `payment.webhook_events` before dispatching the domain event — duplicate calls are silently ignored (idempotent 204).

**Tech Stack:** PHP 8.4 / Symfony 8.0, Doctrine DBAL (bookit connection), PHPUnit 11, `hash_hmac('sha256', ...)`.

---

## File Map

| Action | Path | Responsibility |
|--------|------|----------------|
| Create | `src/Payment/Infrastructure/Security/WebhookUser.php` | Minimal `UserInterface` returned by the HMAC authenticator |
| Create | `src/Payment/Infrastructure/Security/HmacWebhookAuthenticator.php` | Symfony `AbstractAuthenticator` — parses + validates HMAC header |
| Create | `src/Payment/Domain/Port/ProcessedWebhookEventRepositoryInterface.php` | Domain port — marks an event as processed, returns bool (false = duplicate) |
| Create | `src/Payment/Infrastructure/Persistence/DoctrineProcessedWebhookEventRepository.php` | DBAL impl — INSERT with unique constraint, catches duplicate → returns false |
| Create | `migrations/Version20260613000001.php` | Creates `payment` schema + `payment.webhook_events(event_id PK, processed_at)` |
| Create | `tests/Payment/Infrastructure/Security/HmacWebhookAuthenticatorTest.php` | Unit tests for the authenticator |
| Modify | `config/packages/security.yaml` | Add `payment_webhooks` firewall + `ROLE_WEBHOOK` access_control entry |
| Modify | `config/services/payment.yaml` | Wire `$secret` into `HmacWebhookAuthenticator` |
| Modify | `.env` | Add `PAYMENT_WEBHOOK_SECRET=change_me` |
| Modify | `.env.test` | Add `PAYMENT_WEBHOOK_SECRET=test-webhook-secret` |
| Modify | `src/Payment/UI/Http/Controller/HandlePaymentSuccess/HandlePaymentSuccessRequest.php` | Add `event_id` field |
| Modify | `src/Payment/UI/Http/Controller/HandlePaymentFailure/HandlePaymentFailureRequest.php` | Add `event_id` field |
| Modify | `src/Payment/UI/Http/Controller/HandlePaymentCancellation/HandlePaymentCancellationRequest.php` | Add `event_id` field |
| Modify | `src/Payment/UI/Http/Controller/HandlePaymentSuccess/HandlePaymentSuccessController.php` | Pass `$request->eventId` to command |
| Modify | `src/Payment/UI/Http/Controller/HandlePaymentFailure/HandlePaymentFailureController.php` | Pass `$request->eventId` to command |
| Modify | `src/Payment/UI/Http/Controller/HandlePaymentCancellation/HandlePaymentCancellationController.php` | Pass `$request->eventId` to command |
| Modify | `src/Payment/Application/UseCase/HandlePaymentSuccess/HandlePaymentSuccessCommand.php` | Add `eventId` constructor param |
| Modify | `src/Payment/Application/UseCase/HandlePaymentFailure/HandlePaymentFailureCommand.php` | Add `eventId` constructor param |
| Modify | `src/Payment/Application/UseCase/HandlePaymentCancellation/HandlePaymentCancellationCommand.php` | Add `eventId` constructor param |
| Modify | `src/Payment/Application/UseCase/HandlePaymentSuccess/HandlePaymentSuccessCommandHandler.php` | Inject repo, check idempotency before dispatching |
| Modify | `src/Payment/Application/UseCase/HandlePaymentFailure/HandlePaymentFailureCommandHandler.php` | Inject repo, check idempotency |
| Modify | `src/Payment/Application/UseCase/HandlePaymentCancellation/HandlePaymentCancellationCommandHandler.php` | Inject repo, check idempotency before dispatching |
| Modify | `tests/Payment/Application/UseCase/HandlePaymentSuccess/HandlePaymentSuccessCommandHandlerTest.php` | Add idempotency test cases |
| Modify | `tests/Payment/Application/UseCase/HandlePaymentCancellation/HandlePaymentCancellationCommandHandlerTest.php` | Add idempotency test cases |
| Modify | `tests/Payment/Functional/Controller/PaymentWebhookControllerTest.php` | Replace Bearer auth with HMAC signature helper |

---

## Task 1: Create feature branch

- [ ] **Step 1: Check current branch and create feature branch**

```bash
git branch --show-current
git checkout -b fix/payment-webhook-security
```

Expected: prompt shows `fix/payment-webhook-security`.

---

## Task 2: HMAC Authenticator

### Files
- Create: `src/Payment/Infrastructure/Security/WebhookUser.php`
- Create: `src/Payment/Infrastructure/Security/HmacWebhookAuthenticator.php`
- Create: `tests/Payment/Infrastructure/Security/HmacWebhookAuthenticatorTest.php`

- [ ] **Step 1: Write the failing unit tests**

Create `tests/Payment/Infrastructure/Security/HmacWebhookAuthenticatorTest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Tests\Payment\Infrastructure\Security;

use App\Payment\Infrastructure\Security\HmacWebhookAuthenticator;
use App\Payment\Infrastructure\Security\WebhookUser;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

#[Group('unit')]
final class HmacWebhookAuthenticatorTest extends TestCase
{
    private const string SECRET = 'test-secret';
    private HmacWebhookAuthenticator $authenticator;

    protected function setUp(): void
    {
        $this->authenticator = new HmacWebhookAuthenticator(self::SECRET);
    }

    #[Test]
    public function itSupportsRequestWithSignatureHeader(): void
    {
        $request = Request::create('/payment/webhooks/success', 'POST');
        $request->headers->set('X-Webhook-Signature', 't=1234,v1=abc');

        self::assertTrue($this->authenticator->supports($request));
    }

    #[Test]
    public function itDoesNotSupportRequestWithoutSignatureHeader(): void
    {
        $request = Request::create('/payment/webhooks/success', 'POST');

        self::assertFalse($this->authenticator->supports($request));
    }

    #[Test]
    public function itAuthenticatesValidSignature(): void
    {
        $body = '{"reservation_id":"abc","event_id":"def"}';
        $timestamp = time();
        $payload = $timestamp . "\n" . $body;
        $hmac = hash_hmac('sha256', $payload, self::SECRET);
        $header = "t={$timestamp},v1={$hmac}";

        $request = Request::create('/payment/webhooks/success', 'POST', [], [], [], [], $body);
        $request->headers->set('X-Webhook-Signature', $header);

        $passport = $this->authenticator->authenticate($request);

        self::assertInstanceOf(SelfValidatingPassport::class, $passport);
        self::assertInstanceOf(WebhookUser::class, $passport->getUser());
    }

    #[Test]
    public function itRejectsInvalidSignature(): void
    {
        $this->expectException(AuthenticationException::class);

        $body = '{"reservation_id":"abc"}';
        $timestamp = time();
        $header = "t={$timestamp},v1=invalidsignature";

        $request = Request::create('/payment/webhooks/success', 'POST', [], [], [], [], $body);
        $request->headers->set('X-Webhook-Signature', $header);

        $this->authenticator->authenticate($request);
    }

    #[Test]
    public function itRejectsMalformedHeader(): void
    {
        $this->expectException(AuthenticationException::class);

        $request = Request::create('/payment/webhooks/success', 'POST');
        $request->headers->set('X-Webhook-Signature', 'not-valid-format');

        $this->authenticator->authenticate($request);
    }

    #[Test]
    public function itRejectsExpiredTimestamp(): void
    {
        $this->expectException(AuthenticationException::class);

        $body = '{"reservation_id":"abc"}';
        $timestamp = time() - 400;
        $payload = $timestamp . "\n" . $body;
        $hmac = hash_hmac('sha256', $payload, self::SECRET);
        $header = "t={$timestamp},v1={$hmac}";

        $request = Request::create('/payment/webhooks/success', 'POST', [], [], [], [], $body);
        $request->headers->set('X-Webhook-Signature', $header);

        $this->authenticator->authenticate($request);
    }

    #[Test]
    public function itReturns401OnAuthenticationFailure(): void
    {
        $request = Request::create('/payment/webhooks/success', 'POST');
        $response = $this->authenticator->onAuthenticationFailure($request, new AuthenticationException('Invalid'));

        self::assertSame(401, $response->getStatusCode());
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
docker compose run --rm php vendor/bin/phpunit tests/Payment/Infrastructure/Security/HmacWebhookAuthenticatorTest.php
```

Expected: Fatal error — classes not found.

- [ ] **Step 3: Create `WebhookUser`**

Create `src/Payment/Infrastructure/Security/WebhookUser.php`:

```php
<?php

declare(strict_types=1);

namespace App\Payment\Infrastructure\Security;

use Symfony\Component\Security\Core\User\UserInterface;

final class WebhookUser implements UserInterface
{
    public function getRoles(): array
    {
        return ['ROLE_WEBHOOK'];
    }

    public function eraseCredentials(): void
    {
    }

    public function getUserIdentifier(): string
    {
        return 'payment-provider';
    }
}
```

- [ ] **Step 4: Create `HmacWebhookAuthenticator`**

Create `src/Payment/Infrastructure/Security/HmacWebhookAuthenticator.php`:

```php
<?php

declare(strict_types=1);

namespace App\Payment\Infrastructure\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

final readonly class HmacWebhookAuthenticator extends AbstractAuthenticator
{
    private const int TIMESTAMP_TOLERANCE_SECONDS = 300;

    public function __construct(private string $secret)
    {
    }

    public function supports(Request $request): bool
    {
        return $request->headers->has('X-Webhook-Signature');
    }

    public function authenticate(Request $request): Passport
    {
        $header = $request->headers->get('X-Webhook-Signature', '');

        if (!preg_match('/^t=(\d+),v1=([a-f0-9]+)$/', $header, $matches)) {
            throw new AuthenticationException('Invalid X-Webhook-Signature header format');
        }

        $timestamp = (int) $matches[1];
        $receivedHmac = $matches[2];

        if (abs(time() - $timestamp) > self::TIMESTAMP_TOLERANCE_SECONDS) {
            throw new AuthenticationException('Webhook timestamp is too old or in the future');
        }

        $signedPayload = $timestamp . "\n" . $request->getContent();
        $expectedHmac = hash_hmac('sha256', $signedPayload, $this->secret);

        if (!hash_equals($expectedHmac, $receivedHmac)) {
            throw new AuthenticationException('Invalid webhook signature');
        }

        return new SelfValidatingPassport(
            new UserBadge('payment-provider', static fn() => new WebhookUser())
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
    }
}
```

- [ ] **Step 5: Run tests to verify they pass**

```bash
docker compose run --rm php vendor/bin/phpunit tests/Payment/Infrastructure/Security/HmacWebhookAuthenticatorTest.php
```

Expected: 6 tests, 0 failures.

- [ ] **Step 6: Commit**

```bash
git add src/Payment/Infrastructure/Security/WebhookUser.php \
        src/Payment/Infrastructure/Security/HmacWebhookAuthenticator.php \
        tests/Payment/Infrastructure/Security/HmacWebhookAuthenticatorTest.php
git commit -m "feat(payment): add HMAC webhook authenticator and WebhookUser"
```

---

## Task 3: Security configuration + env vars

### Files
- Modify: `config/packages/security.yaml`
- Modify: `config/services/payment.yaml`
- Modify: `.env`
- Modify: `.env.test`

- [ ] **Step 1: Add env vars**

In `.env`, after `KEYCLOAK_CLIENT_SECRET=...`:
```
PAYMENT_WEBHOOK_SECRET=change_me
```

In `.env.test`, after `BOOKIT_DATABASE_URL=...`:
```
PAYMENT_WEBHOOK_SECRET=test-webhook-secret
```

- [ ] **Step 2: Wire secret in `config/services/payment.yaml`**

Add after the `parameters: {}` block (before `services:`):

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

    App\Payment\Infrastructure\Security\HmacWebhookAuthenticator:
        arguments:
            $secret: '%env(PAYMENT_WEBHOOK_SECRET)%'

    App\Payment\Application\:
        resource: '../../src/Payment/Application/'
        exclude:
            - '../../src/Payment/Application/**/*Command.php'

    App\Payment\Infrastructure\:
        resource: '../../src/Payment/Infrastructure/'

    App\Payment\UI\:
        resource: '../../src/Payment/UI/'
        exclude:
            - '../../src/Payment/UI/**/*Request.php'
```

- [ ] **Step 3: Update `config/packages/security.yaml`**

Replace the entire file content:

```yaml
security:
    firewalls:
        dev:
            pattern: ^/(_profiler|_wdt|assets|build)/
            security: false
        payment_webhooks:
            pattern: ^/api/v1/payment/webhooks
            stateless: true
            custom_authenticators:
                - App\Payment\Infrastructure\Security\HmacWebhookAuthenticator
        api:
            pattern: ^/
            stateless: true
            custom_authenticators:
                - App\Security\Infrastructure\Keycloak\BearerTokenAuthenticator

    access_control:
        - { path: ^/api/v1/payment/webhooks, roles: ROLE_WEBHOOK }
        - { path: ^/api/v1/operators$, roles: ROLE_ADMIN, methods: [POST] }
        - { path: ^/, roles: ROLE_OPERATOR }

when@test:
    security:
        password_hashers:
            Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface:
                algorithm: auto
                cost: 4
                time_cost: 3
                memory_cost: 10
```

- [ ] **Step 4: Verify container compiles**

```bash
docker compose run --rm php bin/console debug:router | grep webhook
```

Expected: three webhook routes listed (`payment_webhook_success`, `payment_webhook_failed`, `payment_webhook_cancel`).

- [ ] **Step 5: Commit**

```bash
git add config/packages/security.yaml config/services/payment.yaml .env .env.test
git commit -m "feat(payment): add dedicated payment_webhooks firewall with HMAC auth"
```

---

## Task 4: Add `event_id` to request DTOs, commands, and controllers

### Files
- Modify: `src/Payment/UI/Http/Controller/HandlePaymentSuccess/HandlePaymentSuccessRequest.php`
- Modify: `src/Payment/UI/Http/Controller/HandlePaymentFailure/HandlePaymentFailureRequest.php`
- Modify: `src/Payment/UI/Http/Controller/HandlePaymentCancellation/HandlePaymentCancellationRequest.php`
- Modify: `src/Payment/UI/Http/Controller/HandlePaymentSuccess/HandlePaymentSuccessController.php`
- Modify: `src/Payment/UI/Http/Controller/HandlePaymentFailure/HandlePaymentFailureController.php`
- Modify: `src/Payment/UI/Http/Controller/HandlePaymentCancellation/HandlePaymentCancellationController.php`
- Modify: `src/Payment/Application/UseCase/HandlePaymentSuccess/HandlePaymentSuccessCommand.php`
- Modify: `src/Payment/Application/UseCase/HandlePaymentFailure/HandlePaymentFailureCommand.php`
- Modify: `src/Payment/Application/UseCase/HandlePaymentCancellation/HandlePaymentCancellationCommand.php`

- [ ] **Step 1: Update `HandlePaymentSuccessRequest`**

Replace `src/Payment/UI/Http/Controller/HandlePaymentSuccess/HandlePaymentSuccessRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Payment\UI\Http\Controller\HandlePaymentSuccess;

use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class HandlePaymentSuccessRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Uuid(versions: [Assert\Uuid::V4_RANDOM])]
        #[SerializedName('reservation_id')]
        public string $reservationId = '',

        #[Assert\NotBlank]
        #[Assert\Uuid(versions: [Assert\Uuid::V4_RANDOM])]
        #[SerializedName('event_id')]
        public string $eventId = '',
    ) {
    }
}
```

- [ ] **Step 2: Update `HandlePaymentFailureRequest`**

Replace `src/Payment/UI/Http/Controller/HandlePaymentFailure/HandlePaymentFailureRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Payment\UI\Http\Controller\HandlePaymentFailure;

use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class HandlePaymentFailureRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Uuid(versions: [Assert\Uuid::V4_RANDOM])]
        #[SerializedName('reservation_id')]
        public string $reservationId = '',

        #[Assert\NotBlank]
        #[Assert\Uuid(versions: [Assert\Uuid::V4_RANDOM])]
        #[SerializedName('event_id')]
        public string $eventId = '',
    ) {
    }
}
```

- [ ] **Step 3: Update `HandlePaymentCancellationRequest`**

Replace `src/Payment/UI/Http/Controller/HandlePaymentCancellation/HandlePaymentCancellationRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Payment\UI\Http\Controller\HandlePaymentCancellation;

use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class HandlePaymentCancellationRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Uuid(versions: [Assert\Uuid::V4_RANDOM])]
        #[SerializedName('reservation_id')]
        public string $reservationId = '',

        #[Assert\NotBlank]
        #[Assert\Uuid(versions: [Assert\Uuid::V4_RANDOM])]
        #[SerializedName('event_id')]
        public string $eventId = '',
    ) {
    }
}
```

- [ ] **Step 4: Update `HandlePaymentSuccessCommand`**

Replace `src/Payment/Application/UseCase/HandlePaymentSuccess/HandlePaymentSuccessCommand.php`:

```php
<?php

declare(strict_types=1);

namespace App\Payment\Application\UseCase\HandlePaymentSuccess;

use App\Shared\Application\Bus\SyncCommandInterface;

final readonly class HandlePaymentSuccessCommand implements SyncCommandInterface
{
    public function __construct(
        public string $reservationId,
        public string $eventId,
    ) {
    }
}
```

- [ ] **Step 5: Update `HandlePaymentFailureCommand`**

Replace `src/Payment/Application/UseCase/HandlePaymentFailure/HandlePaymentFailureCommand.php`:

```php
<?php

declare(strict_types=1);

namespace App\Payment\Application\UseCase\HandlePaymentFailure;

use App\Shared\Application\Bus\SyncCommandInterface;

final readonly class HandlePaymentFailureCommand implements SyncCommandInterface
{
    public function __construct(
        public string $reservationId,
        public string $eventId,
    ) {
    }
}
```

- [ ] **Step 6: Update `HandlePaymentCancellationCommand`**

Replace `src/Payment/Application/UseCase/HandlePaymentCancellation/HandlePaymentCancellationCommand.php`:

```php
<?php

declare(strict_types=1);

namespace App\Payment\Application\UseCase\HandlePaymentCancellation;

use App\Shared\Application\Bus\SyncCommandInterface;

final readonly class HandlePaymentCancellationCommand implements SyncCommandInterface
{
    public function __construct(
        public string $reservationId,
        public string $eventId,
    ) {
    }
}
```

- [ ] **Step 7: Update `HandlePaymentSuccessController`**

Replace `src/Payment/UI/Http/Controller/HandlePaymentSuccess/HandlePaymentSuccessController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Payment\UI\Http\Controller\HandlePaymentSuccess;

use App\Payment\Application\UseCase\HandlePaymentSuccess\HandlePaymentSuccessCommand;
use App\Shared\Application\Bus\SyncCommandBusInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

final readonly class HandlePaymentSuccessController
{
    public function __construct(private SyncCommandBusInterface $commandBus)
    {
    }

    #[Route('/payment/webhooks/success', name: 'payment_webhook_success', methods: ['POST'])]
    #[OA\Post(
        summary: 'Payment success webhook',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['reservation_id', 'event_id'],
                properties: [
                    new OA\Property(property: 'reservation_id', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'event_id', type: 'string', format: 'uuid'),
                ],
            ),
        ),
        tags: ['Payment'],
        responses: [
            new OA\Response(response: 204, description: 'Acknowledged'),
            new OA\Response(response: 422, description: 'Validation error'),
            new OA\Response(response: Response::HTTP_UNSUPPORTED_MEDIA_TYPE, description: 'Unsupported media type', content: new OA\MediaType(mediaType: 'application/problem+json', schema: new OA\Schema(ref: '#/components/schemas/ProblemDetail'))),
        ],
    )]
    public function __invoke(
        #[MapRequestPayload(acceptFormat: 'json', validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)]
        HandlePaymentSuccessRequest $request,
    ): Response {
        $this->commandBus->execute(new HandlePaymentSuccessCommand($request->reservationId, $request->eventId));

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
```

- [ ] **Step 8: Update `HandlePaymentFailureController`**

Replace `src/Payment/UI/Http/Controller/HandlePaymentFailure/HandlePaymentFailureController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Payment\UI\Http\Controller\HandlePaymentFailure;

use App\Payment\Application\UseCase\HandlePaymentFailure\HandlePaymentFailureCommand;
use App\Shared\Application\Bus\SyncCommandBusInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

final readonly class HandlePaymentFailureController
{
    public function __construct(private SyncCommandBusInterface $commandBus)
    {
    }

    #[Route('/payment/webhooks/failed', name: 'payment_webhook_failed', methods: ['POST'])]
    #[OA\Post(
        summary: 'Payment failure webhook',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['reservation_id', 'event_id'],
                properties: [
                    new OA\Property(property: 'reservation_id', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'event_id', type: 'string', format: 'uuid'),
                ],
            ),
        ),
        tags: ['Payment'],
        responses: [
            new OA\Response(response: 204, description: 'Acknowledged'),
            new OA\Response(response: 422, description: 'Validation error'),
            new OA\Response(response: Response::HTTP_UNSUPPORTED_MEDIA_TYPE, description: 'Unsupported media type', content: new OA\MediaType(mediaType: 'application/problem+json', schema: new OA\Schema(ref: '#/components/schemas/ProblemDetail'))),
        ],
    )]
    public function __invoke(
        #[MapRequestPayload(acceptFormat: 'json', validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)]
        HandlePaymentFailureRequest $request,
    ): Response {
        $this->commandBus->execute(new HandlePaymentFailureCommand($request->reservationId, $request->eventId));

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
```

- [ ] **Step 9: Update `HandlePaymentCancellationController`**

Replace `src/Payment/UI/Http/Controller/HandlePaymentCancellation/HandlePaymentCancellationController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Payment\UI\Http\Controller\HandlePaymentCancellation;

use App\Payment\Application\UseCase\HandlePaymentCancellation\HandlePaymentCancellationCommand;
use App\Shared\Application\Bus\SyncCommandBusInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

final readonly class HandlePaymentCancellationController
{
    public function __construct(private SyncCommandBusInterface $commandBus)
    {
    }

    #[Route('/payment/webhooks/cancel', name: 'payment_webhook_cancel', methods: ['POST'])]
    #[OA\Post(
        summary: 'Payment cancellation webhook',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['reservation_id', 'event_id'],
                properties: [
                    new OA\Property(property: 'reservation_id', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'event_id', type: 'string', format: 'uuid'),
                ],
            ),
        ),
        tags: ['Payment'],
        responses: [
            new OA\Response(response: 204, description: 'Acknowledged'),
            new OA\Response(response: 422, description: 'Validation error'),
            new OA\Response(response: Response::HTTP_UNSUPPORTED_MEDIA_TYPE, description: 'Unsupported media type', content: new OA\MediaType(mediaType: 'application/problem+json', schema: new OA\Schema(ref: '#/components/schemas/ProblemDetail'))),
        ],
    )]
    public function __invoke(
        #[MapRequestPayload(acceptFormat: 'json', validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)]
        HandlePaymentCancellationRequest $request,
    ): Response {
        $this->commandBus->execute(new HandlePaymentCancellationCommand($request->reservationId, $request->eventId));

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
```

- [ ] **Step 10: Commit**

```bash
git add \
  src/Payment/UI/Http/Controller/HandlePaymentSuccess/HandlePaymentSuccessRequest.php \
  src/Payment/UI/Http/Controller/HandlePaymentFailure/HandlePaymentFailureRequest.php \
  src/Payment/UI/Http/Controller/HandlePaymentCancellation/HandlePaymentCancellationRequest.php \
  src/Payment/UI/Http/Controller/HandlePaymentSuccess/HandlePaymentSuccessController.php \
  src/Payment/UI/Http/Controller/HandlePaymentFailure/HandlePaymentFailureController.php \
  src/Payment/UI/Http/Controller/HandlePaymentCancellation/HandlePaymentCancellationController.php \
  src/Payment/Application/UseCase/HandlePaymentSuccess/HandlePaymentSuccessCommand.php \
  src/Payment/Application/UseCase/HandlePaymentFailure/HandlePaymentFailureCommand.php \
  src/Payment/Application/UseCase/HandlePaymentCancellation/HandlePaymentCancellationCommand.php
git commit -m "feat(payment): add event_id to webhook request DTOs and commands"
```

---

## Task 5: Idempotency — domain port + infrastructure + migration

### Files
- Create: `src/Payment/Domain/Port/ProcessedWebhookEventRepositoryInterface.php`
- Create: `src/Payment/Infrastructure/Persistence/DoctrineProcessedWebhookEventRepository.php`
- Create: `migrations/Version20260613000001.php`

- [ ] **Step 1: Create domain port**

Create `src/Payment/Domain/Port/ProcessedWebhookEventRepositoryInterface.php`:

```php
<?php

declare(strict_types=1);

namespace App\Payment\Domain\Port;

interface ProcessedWebhookEventRepositoryInterface
{
    /**
     * Records the event as processed.
     * Returns true if the event was new (caller should proceed).
     * Returns false if already processed (caller should skip — idempotent).
     */
    public function record(string $eventId): bool;
}
```

- [ ] **Step 2: Create DBAL infrastructure implementation**

Create `src/Payment/Infrastructure/Persistence/DoctrineProcessedWebhookEventRepository.php`:

```php
<?php

declare(strict_types=1);

namespace App\Payment\Infrastructure\Persistence;

use App\Payment\Domain\Port\ProcessedWebhookEventRepositoryInterface;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

final readonly class DoctrineProcessedWebhookEventRepository implements ProcessedWebhookEventRepositoryInterface
{
    public function __construct(private Connection $bookitConnection)
    {
    }

    public function record(string $eventId): bool
    {
        try {
            $this->bookitConnection->executeStatement(
                'INSERT INTO payment.webhook_events (event_id, processed_at) VALUES (:id, NOW())',
                ['id' => $eventId],
            );

            return true;
        } catch (UniqueConstraintViolationException) {
            return false;
        }
    }
}
```

- [ ] **Step 3: Write the migration**

Create `migrations/Version20260613000001.php`:

```php
<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260613000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create payment schema and webhook_events idempotency table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SCHEMA IF NOT EXISTS payment');
        $this->addSql('CREATE TABLE payment.webhook_events (
            event_id UUID NOT NULL,
            processed_at TIMESTAMP(0) WITH TIME ZONE NOT NULL DEFAULT NOW(),
            PRIMARY KEY (event_id)
        )');
        $this->addSql("COMMENT ON TABLE payment.webhook_events IS 'Idempotency log for payment provider webhooks — prevents duplicate processing of the same event_id.'");
        $this->addSql("COMMENT ON COLUMN payment.webhook_events.event_id IS 'UUID v4 sent by the payment provider — acts as the idempotency key'");
        $this->addSql("COMMENT ON COLUMN payment.webhook_events.processed_at IS 'Timestamp when this event was first processed'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE payment.webhook_events');
        $this->addSql('DROP SCHEMA IF EXISTS payment');
    }
}
```

- [ ] **Step 4: Apply migration**

```bash
make migrate
```

Expected: `[OK] 1 migration executed`.

- [ ] **Step 5: Commit**

```bash
git add \
  src/Payment/Domain/Port/ProcessedWebhookEventRepositoryInterface.php \
  src/Payment/Infrastructure/Persistence/DoctrineProcessedWebhookEventRepository.php \
  migrations/Version20260613000001.php
git commit -m "feat(payment): add idempotency port, DBAL repository, and migration"
```

---

## Task 6: Update handlers with idempotency (TDD)

### Files
- Modify: `tests/Payment/Application/UseCase/HandlePaymentSuccess/HandlePaymentSuccessCommandHandlerTest.php`
- Modify: `tests/Payment/Application/UseCase/HandlePaymentCancellation/HandlePaymentCancellationCommandHandlerTest.php`
- Modify: `src/Payment/Application/UseCase/HandlePaymentSuccess/HandlePaymentSuccessCommandHandler.php`
- Modify: `src/Payment/Application/UseCase/HandlePaymentFailure/HandlePaymentFailureCommandHandler.php`
- Modify: `src/Payment/Application/UseCase/HandlePaymentCancellation/HandlePaymentCancellationCommandHandler.php`

- [ ] **Step 1: Update `HandlePaymentSuccessCommandHandlerTest` (red)**

Replace `tests/Payment/Application/UseCase/HandlePaymentSuccess/HandlePaymentSuccessCommandHandlerTest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Tests\Payment\Application\UseCase\HandlePaymentSuccess;

use App\Payment\Application\UseCase\HandlePaymentSuccess\HandlePaymentSuccessCommand;
use App\Payment\Application\UseCase\HandlePaymentSuccess\HandlePaymentSuccessCommandHandler;
use App\Payment\Domain\Port\ProcessedWebhookEventRepositoryInterface;
use App\Shared\Domain\Event\PaymentConfirmed;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

#[Group('unit')]
final class HandlePaymentSuccessCommandHandlerTest extends TestCase
{
    private EventDispatcherInterface&\PHPUnit\Framework\MockObject\MockObject $dispatcher;
    private ProcessedWebhookEventRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject $repository;
    private HandlePaymentSuccessCommandHandler $handler;

    protected function setUp(): void
    {
        $this->dispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->repository = $this->createMock(ProcessedWebhookEventRepositoryInterface::class);
        $this->handler = new HandlePaymentSuccessCommandHandler($this->dispatcher, $this->repository);
    }

    #[Test]
    public function itDispatchesPaymentConfirmedEventForNewEvent(): void
    {
        $this->repository->expects($this->once())
            ->method('record')
            ->with('event-uuid-111')
            ->willReturn(true);

        $this->dispatcher->expects($this->once())
            ->method('dispatch')
            ->with(new PaymentConfirmed('reservation-id-123'));

        ($this->handler)(new HandlePaymentSuccessCommand('reservation-id-123', 'event-uuid-111'));
    }

    #[Test]
    public function itSkipsDispatchForAlreadyProcessedEvent(): void
    {
        $this->repository->expects($this->once())
            ->method('record')
            ->with('event-uuid-222')
            ->willReturn(false);

        $this->dispatcher->expects($this->never())->method('dispatch');

        ($this->handler)(new HandlePaymentSuccessCommand('reservation-id-123', 'event-uuid-222'));
    }
}
```

- [ ] **Step 2: Update `HandlePaymentCancellationCommandHandlerTest` (red)**

Replace `tests/Payment/Application/UseCase/HandlePaymentCancellation/HandlePaymentCancellationCommandHandlerTest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Tests\Payment\Application\UseCase\HandlePaymentCancellation;

use App\Payment\Application\UseCase\HandlePaymentCancellation\HandlePaymentCancellationCommand;
use App\Payment\Application\UseCase\HandlePaymentCancellation\HandlePaymentCancellationCommandHandler;
use App\Payment\Domain\Port\ProcessedWebhookEventRepositoryInterface;
use App\Shared\Domain\Event\PaymentCancelled;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

#[Group('unit')]
final class HandlePaymentCancellationCommandHandlerTest extends TestCase
{
    private EventDispatcherInterface&\PHPUnit\Framework\MockObject\MockObject $dispatcher;
    private ProcessedWebhookEventRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject $repository;
    private HandlePaymentCancellationCommandHandler $handler;

    protected function setUp(): void
    {
        $this->dispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->repository = $this->createMock(ProcessedWebhookEventRepositoryInterface::class);
        $this->handler = new HandlePaymentCancellationCommandHandler($this->dispatcher, $this->repository);
    }

    #[Test]
    public function itDispatchesPaymentCancelledEventForNewEvent(): void
    {
        $this->repository->expects($this->once())
            ->method('record')
            ->with('event-uuid-333')
            ->willReturn(true);

        $this->dispatcher->expects($this->once())
            ->method('dispatch')
            ->with(new PaymentCancelled('reservation-id-456'));

        ($this->handler)(new HandlePaymentCancellationCommand('reservation-id-456', 'event-uuid-333'));
    }

    #[Test]
    public function itSkipsDispatchForAlreadyProcessedEvent(): void
    {
        $this->repository->expects($this->once())
            ->method('record')
            ->with('event-uuid-444')
            ->willReturn(false);

        $this->dispatcher->expects($this->never())->method('dispatch');

        ($this->handler)(new HandlePaymentCancellationCommand('reservation-id-456', 'event-uuid-444'));
    }
}
```

- [ ] **Step 3: Run unit tests to verify they are red**

```bash
make unit-test 2>&1 | grep -E "(FAIL|ERROR|OK|Tests:)"
```

Expected: failures on both handler tests (wrong constructor signature).

- [ ] **Step 4: Update `HandlePaymentSuccessCommandHandler` (green)**

Replace `src/Payment/Application/UseCase/HandlePaymentSuccess/HandlePaymentSuccessCommandHandler.php`:

```php
<?php

declare(strict_types=1);

namespace App\Payment\Application\UseCase\HandlePaymentSuccess;

use App\Payment\Domain\Port\ProcessedWebhookEventRepositoryInterface;
use App\Shared\Application\Bus\SyncCommandHandlerInterface;
use App\Shared\Domain\Event\PaymentConfirmed;
use Psr\EventDispatcher\EventDispatcherInterface;

final readonly class HandlePaymentSuccessCommandHandler implements SyncCommandHandlerInterface
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private ProcessedWebhookEventRepositoryInterface $processedEvents,
    ) {
    }

    public function __invoke(HandlePaymentSuccessCommand $command): void
    {
        if (!$this->processedEvents->record($command->eventId)) {
            return;
        }

        $this->eventDispatcher->dispatch(new PaymentConfirmed($command->reservationId));
    }
}
```

- [ ] **Step 5: Update `HandlePaymentFailureCommandHandler` (green)**

Replace `src/Payment/Application/UseCase/HandlePaymentFailure/HandlePaymentFailureCommandHandler.php`:

```php
<?php

declare(strict_types=1);

namespace App\Payment\Application\UseCase\HandlePaymentFailure;

use App\Payment\Domain\Port\ProcessedWebhookEventRepositoryInterface;
use App\Shared\Application\Bus\SyncCommandHandlerInterface;

final readonly class HandlePaymentFailureCommandHandler implements SyncCommandHandlerInterface
{
    public function __construct(private ProcessedWebhookEventRepositoryInterface $processedEvents)
    {
    }

    public function __invoke(HandlePaymentFailureCommand $command): void
    {
        $this->processedEvents->record($command->eventId);
    }
}
```

- [ ] **Step 6: Update `HandlePaymentCancellationCommandHandler` (green)**

Replace `src/Payment/Application/UseCase/HandlePaymentCancellation/HandlePaymentCancellationCommandHandler.php`:

```php
<?php

declare(strict_types=1);

namespace App\Payment\Application\UseCase\HandlePaymentCancellation;

use App\Payment\Domain\Port\ProcessedWebhookEventRepositoryInterface;
use App\Shared\Application\Bus\SyncCommandHandlerInterface;
use App\Shared\Domain\Event\PaymentCancelled;
use Psr\EventDispatcher\EventDispatcherInterface;

final readonly class HandlePaymentCancellationCommandHandler implements SyncCommandHandlerInterface
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private ProcessedWebhookEventRepositoryInterface $processedEvents,
    ) {
    }

    public function __invoke(HandlePaymentCancellationCommand $command): void
    {
        if (!$this->processedEvents->record($command->eventId)) {
            return;
        }

        $this->eventDispatcher->dispatch(new PaymentCancelled($command->reservationId));
    }
}
```

- [ ] **Step 7: Run unit tests to verify they are green**

```bash
make unit-test 2>&1 | grep -E "(FAIL|ERROR|OK|Tests:)"
```

Expected: all tests pass.

- [ ] **Step 8: Commit**

```bash
git add \
  src/Payment/Application/UseCase/HandlePaymentSuccess/HandlePaymentSuccessCommandHandler.php \
  src/Payment/Application/UseCase/HandlePaymentFailure/HandlePaymentFailureCommandHandler.php \
  src/Payment/Application/UseCase/HandlePaymentCancellation/HandlePaymentCancellationCommandHandler.php \
  tests/Payment/Application/UseCase/HandlePaymentSuccess/HandlePaymentSuccessCommandHandlerTest.php \
  tests/Payment/Application/UseCase/HandlePaymentCancellation/HandlePaymentCancellationCommandHandlerTest.php
git commit -m "feat(payment): add idempotency check to webhook command handlers"
```

---

## Task 7: Update functional tests to use HMAC

### Files
- Modify: `tests/Payment/Functional/Controller/PaymentWebhookControllerTest.php`

The existing tests extend `AuthenticatedWebTestCase` and send Bearer tokens. After our changes, the webhook firewall uses HMAC — Bearer tokens are ignored by it. Replace the test class with a `WebTestCase` that generates proper HMAC signatures.

- [ ] **Step 1: Replace `PaymentWebhookControllerTest`**

Replace `tests/Payment/Functional/Controller/PaymentWebhookControllerTest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Tests\Payment\Functional\Controller;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group('functional')]
final class PaymentWebhookControllerTest extends WebTestCase
{
    private const string RESERVATION_ID = '550e8400-e29b-41d4-a716-446655440001';
    private const string EVENT_ID = '550e8400-e29b-41d4-a716-446655440002';

    #[Test]
    public function itSuccessWebhookReturns204(): void
    {
        $client = static::createClient();
        $this->postSigned($client, '/api/v1/payment/webhooks/success', [
            'reservation_id' => self::RESERVATION_ID,
            'event_id' => self::EVENT_ID,
        ]);
        self::assertResponseStatusCodeSame(204);
    }

    #[Test]
    public function itFailedWebhookReturns204(): void
    {
        $client = static::createClient();
        $this->postSigned($client, '/api/v1/payment/webhooks/failed', [
            'reservation_id' => self::RESERVATION_ID,
            'event_id' => '550e8400-e29b-41d4-a716-446655440003',
        ]);
        self::assertResponseStatusCodeSame(204);
    }

    #[Test]
    public function itCancelWebhookReturns204(): void
    {
        $client = static::createClient();
        $this->postSigned($client, '/api/v1/payment/webhooks/cancel', [
            'reservation_id' => self::RESERVATION_ID,
            'event_id' => '550e8400-e29b-41d4-a716-446655440004',
        ]);
        self::assertResponseStatusCodeSame(204);
    }

    #[Test]
    public function itSuccessWebhookReturns422IfEventIdMissing(): void
    {
        $client = static::createClient();
        $this->postSigned($client, '/api/v1/payment/webhooks/success', [
            'reservation_id' => self::RESERVATION_ID,
        ]);
        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function itSuccessWebhookReturns422IfReservationIdMissing(): void
    {
        $client = static::createClient();
        $this->postSigned($client, '/api/v1/payment/webhooks/success', [
            'event_id' => self::EVENT_ID,
        ]);
        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function itReturns401WithoutSignatureHeader(): void
    {
        $client = static::createClient();
        $body = json_encode(['reservation_id' => self::RESERVATION_ID, 'event_id' => self::EVENT_ID], \JSON_THROW_ON_ERROR);
        $client->request(
            method: 'POST',
            uri: '/api/v1/payment/webhooks/success',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: $body,
        );
        self::assertResponseStatusCodeSame(401);
    }

    #[Test]
    public function itReturns401WithInvalidSignature(): void
    {
        $client = static::createClient();
        $body = json_encode(['reservation_id' => self::RESERVATION_ID, 'event_id' => self::EVENT_ID], \JSON_THROW_ON_ERROR);
        $timestamp = time();
        $client->request(
            method: 'POST',
            uri: '/api/v1/payment/webhooks/success',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_WEBHOOK_SIGNATURE' => "t={$timestamp},v1=invalidsignature",
            ],
            content: $body,
        );
        self::assertResponseStatusCodeSame(401);
    }

    /** @param array<string, string> $body */
    private function postSigned(KernelBrowser $client, string $url, array $body): void
    {
        $content = json_encode($body, \JSON_THROW_ON_ERROR);
        $secret = $_ENV['PAYMENT_WEBHOOK_SECRET'] ?? 'test-webhook-secret';
        $timestamp = time();
        $signedPayload = $timestamp . "\n" . $content;
        $hmac = hash_hmac('sha256', $signedPayload, $secret);

        $client->request(
            method: 'POST',
            uri: $url,
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_WEBHOOK_SIGNATURE' => "t={$timestamp},v1={$hmac}",
            ],
            content: $content,
        );
    }
}
```

- [ ] **Step 2: Run functional tests for Payment**

```bash
docker compose run --rm php vendor/bin/phpunit tests/Payment/Functional/ --group functional -v 2>&1 | tail -20
```

Expected: 7 tests, 0 failures (note: idempotency tests for the same `event_id` — each test uses a distinct UUID so no conflict).

- [ ] **Step 3: Commit**

```bash
git add tests/Payment/Functional/Controller/PaymentWebhookControllerTest.php
git commit -m "test(payment): update webhook functional tests to use HMAC signature"
```

---

## Task 8: Final validation + openapi + cleanup

- [ ] **Step 1: Run the full unit test suite**

```bash
make unit-test 2>&1 | tail -5
```

Expected: all pass.

- [ ] **Step 2: Run the full functional test suite**

```bash
make functional-test 2>&1 | tail -10
```

Expected: all pass.

- [ ] **Step 3: Run lint (CS Fixer + PHPStan + Deptrac)**

```bash
make lint 2>&1 | tail -20
```

Fix any issues before proceeding.

- [ ] **Step 4: Regenerate openapi.yaml**

```bash
make openapi
```

Then verify the webhook endpoints show `event_id` as required in `openapi.yaml`:

```bash
grep -A 5 "event_id" openapi.yaml | head -20
```

- [ ] **Step 5: Commit openapi**

```bash
git add openapi.yaml
git commit -m "docs: regenerate openapi.yaml with event_id on webhook endpoints"
```

- [ ] **Step 6: Push and open PR**

```bash
git push -u origin fix/payment-webhook-security
```

Then open a PR via `gh pr create` or the GitHub UI. Use the `superpowers:finishing-a-development-branch` skill to guide the PR creation step.
