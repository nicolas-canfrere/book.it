<?php

declare(strict_types=1);

namespace App\Tests\Payment\Integration\UseCase\HandlePaymentCancellation;

use App\Payment\Application\UseCase\HandlePaymentCancellation\HandlePaymentCancellationCommand;
use App\Payment\Application\UseCase\HandlePaymentCancellation\HandlePaymentCancellationCommandHandler;
use App\Shared\Domain\Event\PaymentCancelled;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('integration')]
final class HandlePaymentCancellationCommandHandlerTest extends KernelTestCase
{
    #[Test]
    public function itDispatchesPaymentCancelledEvent(): void
    {
        $dispatched = [];
        $dispatcher = new class($dispatched) implements EventDispatcherInterface {
            /**
             * @param array<int, object> $dispatched
             *
             * @phpstan-ignore-next-line property.onlyWritten
             */
            public function __construct(private array &$dispatched)
            {
            }

            public function dispatch(object $event): object
            {
                $this->dispatched[] = $event;

                return $event;
            }
        };

        $handler = new HandlePaymentCancellationCommandHandler($dispatcher);
        ($handler)(new HandlePaymentCancellationCommand('res-001'));

        self::assertCount(1, $dispatched);
        self::assertEquals(new PaymentCancelled('res-001'), $dispatched[0]);
    }
}
