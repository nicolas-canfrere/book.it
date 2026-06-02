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
    public function __construct(private RequestCorrelationContext $context)
    {
    }

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
