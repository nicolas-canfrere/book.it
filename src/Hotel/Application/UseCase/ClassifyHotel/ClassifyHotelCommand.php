<?php

declare(strict_types=1);

namespace App\Hotel\Application\UseCase\ClassifyHotel;

use App\Hotel\Domain\ValueObject\StarRating;
use App\Shared\Application\Bus\SyncCommandInterface;
use App\Shared\Domain\ValueObject\HotelId;

final readonly class ClassifyHotelCommand implements SyncCommandInterface
{
    public function __construct(
        public HotelId $hotelId,
        public ?StarRating $starRating,
    ) {
    }
}
