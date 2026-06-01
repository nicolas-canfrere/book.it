<?php

declare(strict_types=1);

namespace App\Search\Application\UseCase\SearchAvailableRoomTypes;

use App\Search\Domain\AvailableRoomType;
use App\Shared\Application\Bus\SyncQueryInterface;

/** @implements SyncQueryInterface<list<AvailableRoomType>> */
final readonly class SearchAvailableRoomTypesQuery implements SyncQueryInterface
{
    public function __construct(
        public string $city,
        public \DateTimeImmutable $checkIn,
        public \DateTimeImmutable $checkOut,
        public int $guests,
    ) {
    }
}
