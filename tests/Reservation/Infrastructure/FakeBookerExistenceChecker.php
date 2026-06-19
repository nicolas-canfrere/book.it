<?php

declare(strict_types=1);

namespace App\Tests\Reservation\Infrastructure;

use App\Reservation\Domain\Port\BookerExistsInterface;
use App\Shared\Domain\ValueObject\BookerId;

final class FakeBookerExistenceChecker implements BookerExistsInterface
{
    private bool $exists = true;

    public function setExists(bool $exists): void
    {
        $this->exists = $exists;
    }

    public function exists(BookerId $bookerId): bool
    {
        return $this->exists;
    }
}
