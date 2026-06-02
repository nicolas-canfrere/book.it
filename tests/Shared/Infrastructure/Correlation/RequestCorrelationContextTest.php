<?php

declare(strict_types=1);

namespace App\Tests\Shared\Infrastructure\Correlation;

use App\Shared\Infrastructure\Correlation\RequestCorrelationContext;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class RequestCorrelationContextTest extends TestCase
{
    #[Test]
    public function itGetIdWithoutSetReturnsValidUuidV4(): void
    {
        $context = new RequestCorrelationContext();

        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $context->getId()
        );
    }

    #[Test]
    public function itGetIdReturnsIdSetViaSetId(): void
    {
        $context = new RequestCorrelationContext();
        $context->setId('my-trace-abc-123');

        self::assertSame('my-trace-abc-123', $context->getId());
    }

    #[Test]
    public function itTwoInstancesProduceDifferentDefaultIds(): void
    {
        $a = new RequestCorrelationContext();
        $b = new RequestCorrelationContext();

        self::assertNotSame($a->getId(), $b->getId());
    }
}
