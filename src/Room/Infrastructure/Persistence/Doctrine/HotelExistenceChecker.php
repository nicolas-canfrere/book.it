<?php

declare(strict_types=1);

namespace App\Room\Infrastructure\Persistence\Doctrine;

use App\Hotel\Application\Contract\HotelFinderInterface;
use App\Room\Domain\Port\HotelExistsInterface;
use App\Shared\Domain\ValueObject\HotelId;

final readonly class HotelExistenceChecker implements HotelExistsInterface
{
    public function __construct(private HotelFinderInterface $hotels)
    {
    }

    public function exists(HotelId $hotelId): bool
    {
        return null !== $this->hotels->find($hotelId);
    }
}
