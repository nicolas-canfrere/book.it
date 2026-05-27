<?php

declare(strict_types=1);

namespace App\Notification\Domain\Port;

use App\Notification\Domain\ReadModel\BookerContact;

interface BookerContactFetcherInterface
{
    public function fetch(string $bookerId): ?BookerContact;
}
