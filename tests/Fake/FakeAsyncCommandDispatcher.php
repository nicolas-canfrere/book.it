<?php

declare(strict_types=1);

namespace App\Tests\Fake;

use App\Shared\Application\Bus\AsyncCommandDispatcherInterface;
use App\Shared\Application\Bus\AsyncCommandInterface;

final class FakeAsyncCommandDispatcher implements AsyncCommandDispatcherInterface
{
    /** @var list<array{command: AsyncCommandInterface, delayMs: int}> */
    private array $dispatched = [];

    public function dispatch(AsyncCommandInterface $command, int $delayMs = 0): void
    {
        $this->dispatched[] = ['command' => $command, 'delayMs' => $delayMs];
    }

    /** @return list<array{command: AsyncCommandInterface, delayMs: int}> */
    public function getDispatched(): array
    {
        return $this->dispatched;
    }

    public function getLastDispatched(): ?AsyncCommandInterface
    {
        if ([] === $this->dispatched) {
            return null;
        }

        return $this->dispatched[array_key_last($this->dispatched)]['command'];
    }
}
