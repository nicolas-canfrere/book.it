<?php

declare(strict_types=1);

namespace App\Tests\Payment\Integration\UseCase\HandlePaymentFailure;

use App\Payment\Application\UseCase\HandlePaymentFailure\HandlePaymentFailureCommand;
use App\Payment\Application\UseCase\HandlePaymentFailure\HandlePaymentFailureCommandHandler;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('integration')]
final class HandlePaymentFailureCommandHandlerTest extends KernelTestCase
{
    #[Test]
    public function itDoesNothingAndDoesNotThrow(): void
    {
        $handler = new HandlePaymentFailureCommandHandler();
        ($handler)(new HandlePaymentFailureCommand('res-001'));

        $this->addToAssertionCount(1);
    }
}
