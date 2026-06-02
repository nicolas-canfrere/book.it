<?php

declare(strict_types=1);

namespace App\Tests\Payment\Integration\UseCase\HandlePaymentSuccess;

use App\Payment\Application\UseCase\HandlePaymentSuccess\HandlePaymentSuccessCommand;
use App\Payment\Application\UseCase\HandlePaymentSuccess\HandlePaymentSuccessCommandHandler;
use App\Payment\Domain\Port\ReservationPaymentConfirmerInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('integration')]
final class HandlePaymentSuccessCommandHandlerTest extends KernelTestCase
{
    #[Test]
    public function itCallsConfirmerWithReservationId(): void
    {
        $confirmer = new class implements ReservationPaymentConfirmerInterface {
            public ?string $calledWith = null;

            public function confirm(string $reservationId): void
            {
                $this->calledWith = $reservationId;
            }
        };

        $handler = new HandlePaymentSuccessCommandHandler($confirmer);
        ($handler)(new HandlePaymentSuccessCommand('res-001'));

        self::assertSame('res-001', $confirmer->calledWith);
    }
}
