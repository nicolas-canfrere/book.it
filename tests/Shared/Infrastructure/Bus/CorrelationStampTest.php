<?php

// tests/Shared/Infrastructure/Bus/CorrelationStampTest.php
declare(strict_types=1);

namespace App\Tests\Shared\Infrastructure\Bus;

use App\Shared\Infrastructure\Bus\CorrelationStamp;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Stamp\StampInterface;

#[Group('unit')]
final class CorrelationStampTest extends TestCase
{
    #[Test]
    public function itCarriesRequestId(): void
    {
        $stamp = new CorrelationStamp('req-abc-456');

        self::assertSame('req-abc-456', $stamp->getRequestId());
    }

    #[Test]
    public function itImplementsStampInterface(): void
    {
        // @phpstan-ignore staticMethod.alreadyNarrowedType
        self::assertInstanceOf(StampInterface::class, new CorrelationStamp('any'));
    }
}
