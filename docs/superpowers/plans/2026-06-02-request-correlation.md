# Request Correlation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Propagate `X-Request-Id` through HTTP requests and async RabbitMQ messages so all logs — HTTP-side and consumer-side — can be correlated by request.

**Architecture:** A `RequestCorrelationContext` singleton holds the current request ID. A Symfony kernel listener reads or generates the ID on `kernel.request` and echoes it on `kernel.response`. A Messenger middleware attaches a `CorrelationStamp` (+ AMQP header) when dispatching and restores the context when consuming.

**Tech Stack:** PHP 8.4, Symfony 8, Symfony Messenger, symfony/amqp-messenger, Monolog 3

---

## File Map

```
Create:
  src/Shared/Infrastructure/Correlation/RequestCorrelationContext.php
  src/Shared/Infrastructure/Http/RequestCorrelationListener.php
  src/Shared/Infrastructure/Log/CorrelationMonologProcessor.php
  src/Shared/Infrastructure/Bus/CorrelationStamp.php
  src/Shared/Infrastructure/Bus/Middleware/CorrelationMiddleware.php

Modify:
  config/packages/messenger.yaml   ← add CorrelationMiddleware to messenger.bus.default

Tests:
  tests/Shared/Infrastructure/Correlation/RequestCorrelationContextTest.php
  tests/Shared/Infrastructure/Bus/CorrelationStampTest.php
  tests/Shared/Infrastructure/Log/CorrelationMonologProcessorTest.php
  tests/Shared/Infrastructure/Bus/Middleware/CorrelationMiddlewareTest.php
  tests/Shared/Infrastructure/Http/RequestCorrelationListenerTest.php       ← functional
```

---

## Task 1: RequestCorrelationContext

Mutable singleton that holds the current request ID. Auto-generated UUID v4 at construction — never `null`.

**Files:**
- Create: `src/Shared/Infrastructure/Correlation/RequestCorrelationContext.php`
- Test: `tests/Shared/Infrastructure/Correlation/RequestCorrelationContextTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Shared/Infrastructure/Correlation/RequestCorrelationContextTest.php
declare(strict_types=1);

namespace App\Tests\Shared\Infrastructure\Correlation;

use App\Shared\Infrastructure\Correlation\RequestCorrelationContext;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class RequestCorrelationContextTest extends TestCase
{
    public function test_get_id_without_set_returns_valid_uuid_v4(): void
    {
        $context = new RequestCorrelationContext();

        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $context->getId()
        );
    }

    public function test_get_id_returns_id_set_via_set_id(): void
    {
        $context = new RequestCorrelationContext();
        $context->setId('my-trace-abc-123');

        self::assertSame('my-trace-abc-123', $context->getId());
    }

    public function test_two_instances_produce_different_default_ids(): void
    {
        $a = new RequestCorrelationContext();
        $b = new RequestCorrelationContext();

        self::assertNotSame($a->getId(), $b->getId());
    }
}
```

- [ ] **Step 2: Run to verify the test fails**

```bash
make unit-test
```

Expected: FAIL — class `RequestCorrelationContext` not found.

- [ ] **Step 3: Implement**

```php
<?php
// src/Shared/Infrastructure/Correlation/RequestCorrelationContext.php
declare(strict_types=1);

namespace App\Shared\Infrastructure\Correlation;

use Symfony\Component\Uid\Uuid;

final class RequestCorrelationContext
{
    private string $id;

    public function __construct()
    {
        $this->id = Uuid::v4()->toRfc4122();
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function getId(): string
    {
        return $this->id;
    }
}
```

Note: Not `readonly` — this class is intentionally mutable. Not `final readonly` because `setId()` mutates state.

- [ ] **Step 4: Run to verify the test passes**

```bash
make unit-test
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Shared/Infrastructure/Correlation/RequestCorrelationContext.php \
        tests/Shared/Infrastructure/Correlation/RequestCorrelationContextTest.php
git commit -m "feat(correlation): add RequestCorrelationContext"
```

---

## Task 2: CorrelationStamp

A Messenger `StampInterface` that carries the `request_id` across the AMQP transport boundary.

**Files:**
- Create: `src/Shared/Infrastructure/Bus/CorrelationStamp.php`
- Test: `tests/Shared/Infrastructure/Bus/CorrelationStampTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Shared/Infrastructure/Bus/CorrelationStampTest.php
declare(strict_types=1);

namespace App\Tests\Shared\Infrastructure\Bus;

use App\Shared\Infrastructure\Bus\CorrelationStamp;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Stamp\StampInterface;

#[Group('unit')]
final class CorrelationStampTest extends TestCase
{
    public function test_carries_request_id(): void
    {
        $stamp = new CorrelationStamp('req-abc-456');

        self::assertSame('req-abc-456', $stamp->getRequestId());
    }

    public function test_implements_stamp_interface(): void
    {
        self::assertInstanceOf(StampInterface::class, new CorrelationStamp('any'));
    }
}
```

- [ ] **Step 2: Run to verify the test fails**

```bash
make unit-test
```

Expected: FAIL — class `CorrelationStamp` not found.

- [ ] **Step 3: Implement**

```php
<?php
// src/Shared/Infrastructure/Bus/CorrelationStamp.php
declare(strict_types=1);

namespace App\Shared\Infrastructure\Bus;

use Symfony\Component\Messenger\Stamp\StampInterface;

final readonly class CorrelationStamp implements StampInterface
{
    public function __construct(private string $requestId) {}

    public function getRequestId(): string
    {
        return $this->requestId;
    }
}
```

- [ ] **Step 4: Run to verify the test passes**

```bash
make unit-test
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Shared/Infrastructure/Bus/CorrelationStamp.php \
        tests/Shared/Infrastructure/Bus/CorrelationStampTest.php
git commit -m "feat(correlation): add CorrelationStamp"
```

---

## Task 3: CorrelationMonologProcessor

Injects `extra['request_id']` into every Monolog log record. Autoconfigured via `ProcessorInterface` — no explicit DI tag needed.

**Files:**
- Create: `src/Shared/Infrastructure/Log/CorrelationMonologProcessor.php`
- Test: `tests/Shared/Infrastructure/Log/CorrelationMonologProcessorTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Shared/Infrastructure/Log/CorrelationMonologProcessorTest.php
declare(strict_types=1);

namespace App\Tests\Shared\Infrastructure\Log;

use App\Shared\Infrastructure\Correlation\RequestCorrelationContext;
use App\Shared\Infrastructure\Log\CorrelationMonologProcessor;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class CorrelationMonologProcessorTest extends TestCase
{
    public function test_injects_request_id_into_extra(): void
    {
        $context = new RequestCorrelationContext();
        $context->setId('trace-xyz-789');
        $processor = new CorrelationMonologProcessor($context);

        $record = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'app',
            level: Level::Info,
            message: 'something happened',
            context: [],
            extra: [],
        );

        $result = $processor($record);

        self::assertSame('trace-xyz-789', $result->extra['request_id']);
    }

    public function test_preserves_existing_extra_fields(): void
    {
        $context = new RequestCorrelationContext();
        $context->setId('trace-id');
        $processor = new CorrelationMonologProcessor($context);

        $record = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'app',
            level: Level::Debug,
            message: 'msg',
            context: [],
            extra: ['existing_key' => 'existing_value'],
        );

        $result = $processor($record);

        self::assertSame('existing_value', $result->extra['existing_key']);
        self::assertSame('trace-id', $result->extra['request_id']);
    }
}
```

- [ ] **Step 2: Run to verify the test fails**

```bash
make unit-test
```

Expected: FAIL — class `CorrelationMonologProcessor` not found.

- [ ] **Step 3: Implement**

```php
<?php
// src/Shared/Infrastructure/Log/CorrelationMonologProcessor.php
declare(strict_types=1);

namespace App\Shared\Infrastructure\Log;

use App\Shared\Infrastructure\Correlation\RequestCorrelationContext;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

final readonly class CorrelationMonologProcessor implements ProcessorInterface
{
    public function __construct(private RequestCorrelationContext $context) {}

    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(extra: [...$record->extra, 'request_id' => $this->context->getId()]);
    }
}
```

`ProcessorInterface` is autoconfigured by MonologBundle — Symfony auto-tags any class implementing it with `monolog.processor`. No explicit wiring in `shared.yaml` is needed.

- [ ] **Step 4: Run to verify the test passes**

```bash
make unit-test
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Shared/Infrastructure/Log/CorrelationMonologProcessor.php \
        tests/Shared/Infrastructure/Log/CorrelationMonologProcessorTest.php
git commit -m "feat(correlation): add CorrelationMonologProcessor"
```

---

## Task 4: CorrelationMiddleware

Messenger middleware on `messenger.bus.default`. On dispatch: attaches `CorrelationStamp` + `AmqpStamp` with `X-Request-Id` header. On receive (consumer side): restores the context from the stamp.

**Files:**
- Create: `src/Shared/Infrastructure/Bus/Middleware/CorrelationMiddleware.php`
- Test: `tests/Shared/Infrastructure/Bus/Middleware/CorrelationMiddlewareTest.php`
- Modify: `config/packages/messenger.yaml`

- [ ] **Step 1: Write the failing tests**

```php
<?php
// tests/Shared/Infrastructure/Bus/Middleware/CorrelationMiddlewareTest.php
declare(strict_types=1);

namespace App\Tests\Shared\Infrastructure\Bus\Middleware;

use App\Shared\Infrastructure\Bus\CorrelationStamp;
use App\Shared\Infrastructure\Bus\Middleware\CorrelationMiddleware;
use App\Shared\Infrastructure\Correlation\RequestCorrelationContext;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpStamp;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

#[Group('unit')]
final class CorrelationMiddlewareTest extends TestCase
{
    private function makeStack(?Envelope &$captured): StackInterface
    {
        return new class($captured) implements StackInterface {
            public function __construct(private ?Envelope &$captured) {}

            public function next(): MiddlewareInterface
            {
                return new class($this->captured) implements MiddlewareInterface {
                    public function __construct(private ?Envelope &$captured) {}

                    public function handle(Envelope $envelope, StackInterface $stack): Envelope
                    {
                        $this->captured = $envelope;

                        return $envelope;
                    }
                };
            }
        };
    }

    public function test_dispatch_attaches_correlation_stamp_and_amqp_stamp(): void
    {
        $context = new RequestCorrelationContext();
        $context->setId('req-dispatch-001');
        $middleware = new CorrelationMiddleware($context);

        $captured = null;
        $stack = $this->makeStack($captured);

        $middleware->handle(new Envelope(new \stdClass()), $stack);

        self::assertNotNull($captured);

        $correlationStamp = $captured->last(CorrelationStamp::class);
        self::assertInstanceOf(CorrelationStamp::class, $correlationStamp);
        self::assertSame('req-dispatch-001', $correlationStamp->getRequestId());

        $amqpStamp = $captured->last(AmqpStamp::class);
        self::assertInstanceOf(AmqpStamp::class, $amqpStamp);
        self::assertSame('req-dispatch-001', $amqpStamp->getAttributes()['headers']['X-Request-Id']);
    }

    public function test_receive_restores_context_from_stamp(): void
    {
        $context = new RequestCorrelationContext();
        $middleware = new CorrelationMiddleware($context);

        $stamp = new CorrelationStamp('req-receive-002');
        $envelope = (new Envelope(new \stdClass()))->with($stamp);

        $captured = null;
        $stack = $this->makeStack($captured);

        $middleware->handle($envelope, $stack);

        self::assertSame('req-receive-002', $context->getId());
    }

    public function test_receive_does_not_add_extra_stamps(): void
    {
        $context = new RequestCorrelationContext();
        $middleware = new CorrelationMiddleware($context);

        $stamp = new CorrelationStamp('req-003');
        $envelope = (new Envelope(new \stdClass()))->with($stamp);

        $captured = null;
        $stack = $this->makeStack($captured);

        $middleware->handle($envelope, $stack);

        self::assertNotNull($captured);
        self::assertCount(1, $captured->all(CorrelationStamp::class));
    }
}
```

- [ ] **Step 2: Run to verify the tests fail**

```bash
make unit-test
```

Expected: FAIL — class `CorrelationMiddleware` not found.

- [ ] **Step 3: Implement**

```php
<?php
// src/Shared/Infrastructure/Bus/Middleware/CorrelationMiddleware.php
declare(strict_types=1);

namespace App\Shared\Infrastructure\Bus\Middleware;

use App\Shared\Infrastructure\Bus\CorrelationStamp;
use App\Shared\Infrastructure\Correlation\RequestCorrelationContext;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpStamp;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

final readonly class CorrelationMiddleware implements MiddlewareInterface
{
    public function __construct(private RequestCorrelationContext $context) {}

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $stamp = $envelope->last(CorrelationStamp::class);

        if ($stamp instanceof CorrelationStamp) {
            $this->context->setId($stamp->getRequestId());

            return $stack->next()->handle($envelope, $stack);
        }

        $id = $this->context->getId();
        $envelope = $envelope
            ->with(new CorrelationStamp($id))
            ->with(new AmqpStamp(null, AMQP_NOPARAM, ['headers' => ['X-Request-Id' => $id]]));

        return $stack->next()->handle($envelope, $stack);
    }
}
```

- [ ] **Step 4: Run to verify the tests pass**

```bash
make unit-test
```

Expected: PASS.

- [ ] **Step 5: Wire the middleware onto the async bus**

In `config/packages/messenger.yaml`, add `CorrelationMiddleware` to `messenger.bus.default`:

```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        default_bus: messenger.bus.default
        buses:
            sync.command.bus:
                middleware:
                    - 'App\Shared\Infrastructure\Bus\Middleware\ExceptionThrowerMiddleware'
            sync.query.bus:
                middleware:
                    - 'App\Shared\Infrastructure\Bus\Middleware\ExceptionThrowerMiddleware'
            messenger.bus.default:
                middleware:
                    - 'App\Shared\Infrastructure\Bus\Middleware\CorrelationMiddleware'
        # ... rest of the file unchanged
```

- [ ] **Step 6: Verify the container compiles**

```bash
make lint
```

Expected: PASS (no container build errors).

- [ ] **Step 7: Commit**

```bash
git add src/Shared/Infrastructure/Bus/Middleware/CorrelationMiddleware.php \
        tests/Shared/Infrastructure/Bus/Middleware/CorrelationMiddlewareTest.php \
        config/packages/messenger.yaml
git commit -m "feat(correlation): add CorrelationMiddleware on async bus"
```

---

## Task 5: RequestCorrelationListener

Symfony kernel listener: reads `X-Request-Id` from the request (or generates a UUID v4), stores it in `RequestCorrelationContext`, and echoes it back in the response header.

**Files:**
- Create: `src/Shared/Infrastructure/Http/RequestCorrelationListener.php`
- Test: `tests/Shared/Infrastructure/Http/RequestCorrelationListenerTest.php`

- [ ] **Step 1: Write the failing functional tests**

```php
<?php
// tests/Shared/Infrastructure/Http/RequestCorrelationListenerTest.php
declare(strict_types=1);

namespace App\Tests\Shared\Infrastructure\Http;

use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group('functional')]
final class RequestCorrelationListenerTest extends WebTestCase
{
    public function test_response_echoes_incoming_x_request_id(): void
    {
        $client = static::createClient();
        $client->request(
            'GET',
            '/v1/hotels',
            server: [
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_REQUEST_ID' => 'my-trace-id-abc123',
            ]
        );

        self::assertSame(
            'my-trace-id-abc123',
            $client->getResponse()->headers->get('X-Request-Id')
        );
    }

    public function test_response_generates_x_request_id_when_header_absent(): void
    {
        $client = static::createClient();
        $client->request(
            'GET',
            '/v1/hotels',
            server: ['HTTP_ACCEPT' => 'application/json']
        );

        $id = $client->getResponse()->headers->get('X-Request-Id');
        self::assertNotNull($id);
        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $id
        );
    }
}
```

- [ ] **Step 2: Run to verify the tests fail**

```bash
make functional-test
```

Expected: FAIL — `X-Request-Id` header absent in response.

- [ ] **Step 3: Implement**

```php
<?php
// src/Shared/Infrastructure/Http/RequestCorrelationListener.php
declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use App\Shared\Infrastructure\Correlation\RequestCorrelationContext;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Uid\Uuid;

final readonly class RequestCorrelationListener
{
    public function __construct(private RequestCorrelationContext $context) {}

    #[AsEventListener(event: KernelEvents::REQUEST, priority: 10)]
    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $id = $event->getRequest()->headers->get('X-Request-Id') ?? Uuid::v4()->toRfc4122();
        $this->context->setId($id);
    }

    #[AsEventListener(event: KernelEvents::RESPONSE)]
    public function onResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $event->getResponse()->headers->set('X-Request-Id', $this->context->getId());
    }
}
```

`priority: 10` on `onRequest` ensures the ID is set before any other listener (e.g. the Monolog processor) needs it.

- [ ] **Step 4: Run to verify the tests pass**

```bash
make functional-test
```

Expected: PASS.

- [ ] **Step 5: Run full test suite and linter**

```bash
make test && make lint
```

Expected: all green.

- [ ] **Step 6: Commit**

```bash
git add src/Shared/Infrastructure/Http/RequestCorrelationListener.php \
        tests/Shared/Infrastructure/Http/RequestCorrelationListenerTest.php
git commit -m "feat(correlation): add RequestCorrelationListener (HTTP in + response out)"
```
