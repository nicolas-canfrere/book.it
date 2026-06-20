<?php

declare(strict_types=1);

namespace App\Geo\Application\Contract;

use App\Shared\Domain\ValueObject\GeoPlaceId;

interface GeoPlaceCheckerInterface
{
    public function exists(GeoPlaceId $id): bool;
}
