<?php

declare(strict_types=1);

namespace App\Shared\Domain\Event;

final readonly class StarRatingClassified
{
    public function __construct(
        public string $hotelId,
        public ?int $starRating,
    ) {
    }
}
