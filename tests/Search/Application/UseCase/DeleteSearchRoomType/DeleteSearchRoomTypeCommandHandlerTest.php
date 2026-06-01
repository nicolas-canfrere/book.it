<?php

declare(strict_types=1);

namespace App\Tests\Search\Application\UseCase\DeleteSearchRoomType;

use App\Search\Application\UseCase\DeleteSearchRoomType\DeleteSearchRoomTypeCommand;
use App\Search\Application\UseCase\DeleteSearchRoomType\DeleteSearchRoomTypeCommandHandler;
use App\Search\Domain\Port\HotelRoomTypeWriterInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class DeleteSearchRoomTypeCommandHandlerTest extends TestCase
{
    #[Test]
    public function itDelegatesDeletionToWriter(): void
    {
        $writer = $this->createMock(HotelRoomTypeWriterInterface::class);
        $writer->expects($this->once())
            ->method('deleteRoomType')
            ->with('rt-id-1');

        $handler = new DeleteSearchRoomTypeCommandHandler($writer);
        ($handler)(new DeleteSearchRoomTypeCommand(roomTypeId: 'rt-id-1'));
    }
}
