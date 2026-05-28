<?php

declare(strict_types=1);

namespace App\Tests\Reservation\Infrastructure\Service;

use App\Reservation\Domain\Port\GuestIdGeneratorInterface;

final class SequentialGuestIdGenerator implements GuestIdGeneratorInterface
{
    private int $counter = 0;

    public function generate(): string
    {
        return sprintf('guest-%d', ++$this->counter);
    }
}
