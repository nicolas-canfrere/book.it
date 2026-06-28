<?php

declare(strict_types=1);

namespace App\Hotel\Application\UseCase\GetHotel;

use App\Hotel\Domain\Model\Hotel;
use App\Hotel\Domain\Port\HotelPublicReaderInterface;
use App\Shared\Application\Bus\SyncQueryHandlerInterface;

final readonly class GetHotelQueryHandler implements SyncQueryHandlerInterface
{
    public function __construct(private HotelPublicReaderInterface $hotelPublicReader)
    {
    }

    public function __invoke(GetHotelQuery $query): ?Hotel
    {
        return $this->hotelPublicReader->get($query->hotelId);
    }
}
