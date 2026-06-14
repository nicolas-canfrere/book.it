<?php

declare(strict_types=1);

namespace App\Tests\Room\Infrastructure;

use App\Room\Domain\Port\HotelExistsInterface;
use App\Shared\Domain\ValueObject\HotelId;

final class FakeHotelExistenceChecker implements HotelExistsInterface
{
    private bool $hotelExists = true;

    public function setExists(bool $exists): void
    {
        $this->hotelExists = $exists;
    }

    public function exists(HotelId $hotelId): bool
    {
        return $this->hotelExists;
    }
}
