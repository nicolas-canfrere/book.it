<?php

declare(strict_types=1);

namespace App\Tests\Payment\Integration\UseCase\HandlePaymentFailure;

use App\Payment\Application\UseCase\HandlePaymentFailure\HandlePaymentFailureCommand;
use App\Payment\Application\UseCase\HandlePaymentFailure\HandlePaymentFailureCommandHandler;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('integration')]
final class HandlePaymentFailureCommandHandlerTest extends KernelTestCase
{
    public function test_does_nothing_and_does_not_throw(): void
    {
        $handler = new HandlePaymentFailureCommandHandler();
        ($handler)(new HandlePaymentFailureCommand('res-001'));

        $this->addToAssertionCount(1);
    }
}
