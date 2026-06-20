<?php

declare(strict_types=1);

namespace App\Hotel\Domain\Port;

use App\Shared\Domain\ValueObject\GeoPlaceId;

interface GeoPlaceCheckerInterface
{
    public function exists(GeoPlaceId $id): bool;
}
