<?php

declare(strict_types=1);

namespace App\Search\Application\UseCase\UpdateSearchHotelStarRating;

use App\Shared\Application\Bus\AsyncCommandInterface;

final readonly class UpdateSearchHotelStarRatingCommand implements AsyncCommandInterface
{
    public function __construct(
        public string $hotelId,
        public ?int $starRating,
    ) {
    }
}
