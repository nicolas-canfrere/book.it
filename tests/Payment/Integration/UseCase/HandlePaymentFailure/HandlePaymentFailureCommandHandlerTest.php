<?php

declare(strict_types=1);

namespace App\Tests\Payment\Integration\UseCase\HandlePaymentFailure;

use App\Payment\Application\UseCase\HandlePaymentFailure\HandlePaymentFailureCommand;
use App\Payment\Application\UseCase\HandlePaymentFailure\HandlePaymentFailureCommandHandler;
use App\Payment\Domain\Port\ProcessedWebhookEventRepositoryInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('integration')]
final class HandlePaymentFailureCommandHandlerTest extends KernelTestCase
{
    #[Test]
    public function itDoesNothingAndDoesNotThrow(): void
    {
        $repository = new class() implements ProcessedWebhookEventRepositoryInterface {
            public function record(string $eventId): bool
            {
                return true;
            }
        };

        $handler = new HandlePaymentFailureCommandHandler($repository);
        ($handler)(new HandlePaymentFailureCommand('res-001', 'event-001'));

        $this->addToAssertionCount(1);
    }
}
