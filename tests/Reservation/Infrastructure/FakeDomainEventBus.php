<?php
declare(strict_types=1);

namespace App\Tests\Reservation\Infrastructure;

use App\Shared\Application\Bus\DomainEventBusInterface;

final class FakeDomainEventBus implements DomainEventBusInterface
{
    /** @var list<object> */
    private array $dispatched = [];

    public function dispatch(object $event): void
    {
        $this->dispatched[] = $event;
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
