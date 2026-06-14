<?php

declare(strict_types=1);

namespace App\Hotel\Application\UseCase\GetHotel;

use App\Hotel\Domain\Model\Hotel;
use App\Shared\Application\Bus\SyncQueryInterface;
use App\Shared\Domain\ValueObject\HotelId;

/** @implements SyncQueryInterface<Hotel|null> */
final readonly class GetHotelQuery implements SyncQueryInterface
{
    public function __construct(public HotelId $hotelId)
    {
    }
}
