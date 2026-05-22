<?php

declare(strict_types=1);

namespace App\Hotel\Application\UseCase\ListHotels;

use App\Hotel\Domain\Model\HotelPage;
use App\Shared\Application\Bus\SyncQueryInterface;

/**
 * @implements SyncQueryInterface<HotelPage>
 */
final readonly class ListHotelsQuery implements SyncQueryInterface
{
    public function __construct(
        public int $page = 1,
        public int $limit = 20,
        public ?string $city = null,
        public ?string $country = null,
        public ?int $minStars = null,
    ) {
    }
}
