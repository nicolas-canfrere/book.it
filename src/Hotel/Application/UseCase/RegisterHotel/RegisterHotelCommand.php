<?php

declare(strict_types=1);

namespace App\Hotel\Application\UseCase\RegisterHotel;

use App\Hotel\Domain\Model\Address;
use App\Hotel\Domain\ValueObject\StarRating;
use App\Shared\Application\Bus\SyncCommandInterface;
use App\Shared\Domain\ValueObject\HotelId;

final readonly class RegisterHotelCommand implements SyncCommandInterface
{
    public function __construct(
        public HotelId $id,
        public string $name,
        public Address $address,
        public \DateTimeImmutable $createdAt,
        public ?StarRating $starRating = null,
    ) {
    }
}
