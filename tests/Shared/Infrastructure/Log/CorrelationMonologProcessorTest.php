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
