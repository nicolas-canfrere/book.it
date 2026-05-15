<?php

declare(strict_types=1);

namespace App\Hotel\Application\UseCase\RegisterHotel;

use App\Hotel\Domain\Model\Address;
use App\Shared\Application\Bus\SyncCommandInterface;

final readonly class RegisterHotelCommand implements SyncCommandInterface
{
    public function __construct(
        public string $id,
        public string $name,
        public Address $address,
        public \DateTimeImmutable $createdAt,
    ) {
    }
}
