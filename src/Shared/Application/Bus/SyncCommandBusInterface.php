<?php

declare(strict_types=1);

namespace App\Shared\Application\Bus;

interface SyncCommandBusInterface
{
    public function execute(SyncCommandInterface $command): void;
}
