<?php

declare(strict_types=1);

namespace App\Hotel\Application\UseCase\ClassifyHotel;

use App\Shared\Application\Bus\SyncCommandInterface;

final readonly class ClassifyHotelCommand implements SyncCommandInterface
{
    public function __construct(
        public string $hotelId,
        public ?int $stars,
        public bool $superior,
    ) {
    }
}
