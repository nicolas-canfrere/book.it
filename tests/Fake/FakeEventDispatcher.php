<?php

declare(strict_types=1);

namespace App\Tests\Fake;

use Psr\EventDispatcher\EventDispatcherInterface;

final class FakeEventDispatcher implements EventDispatcherInterface
{
    /** @var list<object> */
    private array $dispatched = [];

    public function dispatch(object $event): object
    {
        $this->dispatched[] = $event;

        return $event;
    }

    /** @return list<object> */
    public function getDispatched(): array
    {
        return $this->dispatched;
    }

    public function getLastDispatched(): ?object
    {
        if ([] === $this->dispatched) {
            return null;
        }

        return $this->dispatched[array_key_last($this->dispatched)];
    }
}
