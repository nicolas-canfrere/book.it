<?php

declare(strict_types=1);

namespace App\Tests\Payment\Integration\UseCase\HandlePaymentSuccess;

use App\Payment\Application\UseCase\HandlePaymentSuccess\HandlePaymentSuccessCommand;
use App\Payment\Application\UseCase\HandlePaymentSuccess\HandlePaymentSuccessCommandHandler;
use App\Payment\Domain\Port\ProcessedWebhookEventRepositoryInterface;
use App\Shared\Domain\Event\PaymentConfirmed;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('integration')]
final class HandlePaymentSuccessCommandHandlerTest extends KernelTestCase
{
    #[Test]
    public function itDispatchesPaymentConfirmedEvent(): void
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

        $repository = new class() implements ProcessedWebhookEventRepositoryInterface {
            public function record(string $eventId): bool
            {
                return true;
            }
        };

        $handler = new HandlePaymentSuccessCommandHandler($dispatcher, $repository);
        ($handler)(new HandlePaymentSuccessCommand('res-001', 'event-001'));

        self::assertCount(1, $dispatched);
        self::assertEquals(new PaymentConfirmed('res-001'), $dispatched[0]);
    }
}
