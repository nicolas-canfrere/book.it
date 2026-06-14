<?php

declare(strict_types=1);

namespace App\Search\Application\UseCase\UpdateSearchHotelStarRating;

use App\Shared\Application\Bus\AsyncCommandInterface;
use App\Shared\Domain\ValueObject\HotelId;

final readonly class UpdateSearchHotelStarRatingCommand implements AsyncCommandInterface
{
    public function __construct(
        public HotelId $hotelId,
        public ?int $starRating,
    ) {
    }
}
