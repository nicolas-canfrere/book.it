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
