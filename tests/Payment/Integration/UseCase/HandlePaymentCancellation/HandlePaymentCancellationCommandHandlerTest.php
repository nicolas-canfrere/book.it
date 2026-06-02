<?php

declare(strict_types=1);

namespace App\Tests\Payment\Integration\UseCase\HandlePaymentCancellation;

use App\Payment\Application\UseCase\HandlePaymentCancellation\HandlePaymentCancellationCommand;
use App\Payment\Application\UseCase\HandlePaymentCancellation\HandlePaymentCancellationCommandHandler;
use App\Payment\Domain\Port\ReservationPaymentCancellerInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('integration')]
final class HandlePaymentCancellationCommandHandlerTest extends KernelTestCase
{
    #[Test]
    public function itCallsCancellerWithReservationId(): void
    {
        $canceller = new class implements ReservationPaymentCancellerInterface {
            public ?string $calledWith = null;

            public function cancel(string $reservationId): void
            {
                $this->calledWith = $reservationId;
            }
        };

        $handler = new HandlePaymentCancellationCommandHandler($canceller);
        ($handler)(new HandlePaymentCancellationCommand('res-001'));

        self::assertSame('res-001', $canceller->calledWith);
    }
}
