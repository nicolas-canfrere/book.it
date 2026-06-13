<?php

declare(strict_types=1);

namespace App\Tests\Payment\Application\UseCase\HandlePaymentSuccess;

use App\Payment\Application\UseCase\HandlePaymentSuccess\HandlePaymentSuccessCommand;
use App\Payment\Application\UseCase\HandlePaymentSuccess\HandlePaymentSuccessCommandHandler;
use App\Payment\Domain\Port\ProcessedWebhookEventRepositoryInterface;
use App\Shared\Domain\Event\PaymentConfirmed;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

#[Group('unit')]
final class HandlePaymentSuccessCommandHandlerTest extends TestCase
{
    private EventDispatcherInterface&\PHPUnit\Framework\MockObject\MockObject $dispatcher;
    private ProcessedWebhookEventRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject $repository;
    private HandlePaymentSuccessCommandHandler $handler;

    protected function setUp(): void
    {
        $this->dispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->repository = $this->createMock(ProcessedWebhookEventRepositoryInterface::class);
        $this->handler = new HandlePaymentSuccessCommandHandler($this->dispatcher, $this->repository);
    }

    #[Test]
    public function itDispatchesPaymentConfirmedEventForNewEvent(): void
    {
        $this->repository->expects($this->once())
            ->method('record')
            ->with('event-uuid-111')
            ->willReturn(true);

        $this->dispatcher->expects($this->once())
            ->method('dispatch')
            ->with(new PaymentConfirmed('reservation-id-123'));

        ($this->handler)(new HandlePaymentSuccessCommand('reservation-id-123', 'event-uuid-111'));
    }

    #[Test]
    public function itSkipsDispatchForAlreadyProcessedEvent(): void
    {
        $this->repository->expects($this->once())
            ->method('record')
            ->with('event-uuid-222')
            ->willReturn(false);

        $this->dispatcher->expects($this->never())->method('dispatch');

        ($this->handler)(new HandlePaymentSuccessCommand('reservation-id-123', 'event-uuid-222'));
    }
}
