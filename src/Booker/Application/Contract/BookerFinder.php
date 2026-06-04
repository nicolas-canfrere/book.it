<?php

declare(strict_types=1);

namespace App\Booker\Application\Contract;

interface BookerFinder
{
    public function find(string $bookerId): ?BookerView;
}
