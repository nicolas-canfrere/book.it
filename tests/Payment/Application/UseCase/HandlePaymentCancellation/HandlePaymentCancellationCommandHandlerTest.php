<?php

declare(strict_types=1);

namespace App\Tests\Payment\Application\UseCase\HandlePaymentCancellation;

use App\Payment\Application\UseCase\HandlePaymentCancellation\HandlePaymentCancellationCommand;
use App\Payment\Application\UseCase\HandlePaymentCancellation\HandlePaymentCancellationCommandHandler;
use App\Shared\Domain\Event\PaymentCancelled;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

#[Group('unit')]
final class HandlePaymentCancellationCommandHandlerTest extends TestCase
{
    #[Test]
    public function itDispatchesPaymentCancelledEvent(): void
    {
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with(new PaymentCancelled('reservation-id-456'));

        $handler = new HandlePaymentCancellationCommandHandler($dispatcher);
        $handler(new HandlePaymentCancellationCommand('reservation-id-456'));
    }
}
