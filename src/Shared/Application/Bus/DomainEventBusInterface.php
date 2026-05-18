<?php

declare(strict_types=1);

namespace App\Shared\Application\Bus;

interface DomainEventBusInterface
{
    public function dispatch(object $event): void;
}
