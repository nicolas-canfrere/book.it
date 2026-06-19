<?php

declare(strict_types=1);

namespace App\Tests\Reservation\Infrastructure\Service;

use App\Reservation\Domain\Port\GuestIdGeneratorInterface;
use App\Shared\Domain\ValueObject\GuestId;

final class SequentialGuestIdGenerator implements GuestIdGeneratorInterface
{
    private int $counter = 0;

    public function generate(): GuestId
    {
        return new GuestId(sprintf('guest-%d', ++$this->counter));
    }
}
