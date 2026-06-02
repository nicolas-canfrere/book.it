<?php

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
