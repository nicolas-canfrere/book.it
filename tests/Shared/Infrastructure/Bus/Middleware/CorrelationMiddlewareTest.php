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
    public function test_dispatch_attaches_correlation_stamp_and_amqp_stamp(): void
    {
        $context = new RequestCorrelationContext();
        $context->setId('req-dispatch-001');
        $middleware = new CorrelationMiddleware($context);

        $capture = new EnvelopeCapture();
        $middleware->handle(new Envelope(new \stdClass()), $this->makeStack($capture));

        $captured = $capture->envelope;
        self::assertNotNull($captured);

        $correlationStamp = $captured->last(CorrelationStamp::class);
        self::assertInstanceOf(CorrelationStamp::class, $correlationStamp);
        self::assertSame('req-dispatch-001', $correlationStamp->getRequestId());

        $amqpStamp = $captured->last(AmqpStamp::class);
        self::assertInstanceOf(AmqpStamp::class, $amqpStamp);
        $attributes = $amqpStamp->getAttributes();
        self::assertIsArray($attributes['headers']);
        self::assertSame('req-dispatch-001', $attributes['headers']['X-Request-Id']);
    }

    public function test_receive_restores_context_from_stamp(): void
    {
        $context = new RequestCorrelationContext();
        $middleware = new CorrelationMiddleware($context);

        $stamp = new CorrelationStamp('req-receive-002');
        $envelope = (new Envelope(new \stdClass()))->with($stamp);

        $capture = new EnvelopeCapture();
        $middleware->handle($envelope, $this->makeStack($capture));

        self::assertSame('req-receive-002', $context->getId());
    }

    public function test_receive_does_not_add_extra_stamps(): void
    {
        $context = new RequestCorrelationContext();
        $middleware = new CorrelationMiddleware($context);

        $stamp = new CorrelationStamp('req-003');
        $envelope = (new Envelope(new \stdClass()))->with($stamp);

        $capture = new EnvelopeCapture();
        $middleware->handle($envelope, $this->makeStack($capture));

        $captured = $capture->envelope;
        self::assertNotNull($captured);
        self::assertCount(1, $captured->all(CorrelationStamp::class));
    }

    private function makeNextMiddleware(EnvelopeCapture $capture): MiddlewareInterface
    {
        return new class($capture) implements MiddlewareInterface {
            public function __construct(private readonly EnvelopeCapture $capture)
            {
            }

            public function handle(Envelope $envelope, StackInterface $stack): Envelope
            {
                $this->capture->envelope = $envelope;

                return $envelope;
            }
        };
    }

    private function makeStack(EnvelopeCapture $capture): StackInterface
    {
        $next = $this->makeNextMiddleware($capture);

        return new class($next) implements StackInterface {
            public function __construct(private readonly MiddlewareInterface $next)
            {
            }

            public function next(): MiddlewareInterface
            {
                return $this->next;
            }
        };
    }
}
