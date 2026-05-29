<?php

declare(strict_types=1);

namespace App\Shared\Domain\Port;

interface BookerProviderInterface
{
    public function exists(string $bookerId): bool;
}
