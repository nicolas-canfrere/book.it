<?php

declare(strict_types=1);

namespace App\Tests\Payment\Application\UseCase\HandlePaymentSuccess;

use App\Payment\Application\UseCase\HandlePaymentSuccess\HandlePaymentSuccessCommand;
use App\Payment\Application\UseCase\HandlePaymentSuccess\HandlePaymentSuccessCommandHandler;
use App\Shared\Domain\Event\PaymentConfirmed;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

#[Group('unit')]
final class HandlePaymentSuccessCommandHandlerTest extends TestCase
{
    public function test_it_dispatches_payment_confirmed_event(): void
    {
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with(new PaymentConfirmed('reservation-id-123'));

        $handler = new HandlePaymentSuccessCommandHandler($dispatcher);
        $handler(new HandlePaymentSuccessCommand('reservation-id-123'));
    }
}
