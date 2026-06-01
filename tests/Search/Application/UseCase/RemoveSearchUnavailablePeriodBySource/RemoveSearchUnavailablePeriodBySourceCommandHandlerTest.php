<?php

declare(strict_types=1);

namespace App\Tests\Search\Application\UseCase\RemoveSearchUnavailablePeriodBySource;

use App\Search\Application\UseCase\RemoveSearchUnavailablePeriodBySource\RemoveSearchUnavailablePeriodBySourceCommand;
use App\Search\Application\UseCase\RemoveSearchUnavailablePeriodBySource\RemoveSearchUnavailablePeriodBySourceCommandHandler;
use App\Search\Domain\Port\UnavailablePeriodWriterInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class RemoveSearchUnavailablePeriodBySourceCommandHandlerTest extends TestCase
{
    #[Test]
    public function itDelegatesRemoveBySourceToWriter(): void
    {
        $writer = $this->createMock(UnavailablePeriodWriterInterface::class);
        $writer->expects($this->once())
            ->method('removeBySource')
            ->with('res-id-1');

        $handler = new RemoveSearchUnavailablePeriodBySourceCommandHandler($writer);
        ($handler)(new RemoveSearchUnavailablePeriodBySourceCommand(sourceId: 'res-id-1'));
    }
}
