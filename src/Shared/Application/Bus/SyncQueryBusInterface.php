<?php

declare(strict_types=1);

namespace App\Shared\Application\Bus;

interface SyncQueryBusInterface
{
    /**
     * @template TResult
     *
     * @param SyncQueryInterface<TResult> $query
     *
     * @return TResult
     */
    public function ask(SyncQueryInterface $query): mixed;
}
