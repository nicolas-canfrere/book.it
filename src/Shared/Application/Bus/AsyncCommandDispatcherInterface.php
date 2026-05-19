<?php

declare(strict_types=1);

namespace App\Shared\Application\Bus;

interface AsyncCommandDispatcherInterface
{
    public function dispatch(AsyncCommandInterface $command, int $delayMs = 0): void;
}
