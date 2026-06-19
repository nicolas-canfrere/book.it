<?php

declare(strict_types=1);

namespace App\Booker\Application\Contract;

use App\Shared\Domain\ValueObject\BookerId;

interface BookerFinderInterface
{
    public function find(BookerId $bookerId): ?BookerView;
}
