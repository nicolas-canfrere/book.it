<?php

declare(strict_types=1);

namespace App\Hotel\Infrastructure\Service;

use App\Geo\Application\Contract\GeoPlaceCheckerInterface as GeoPlaceCheckerContract;
use App\Hotel\Domain\Port\GeoPlaceCheckerInterface;
use App\Shared\Domain\ValueObject\GeoPlaceId;

final readonly class GeoPlaceChecker implements GeoPlaceCheckerInterface
{
    public function __construct(private GeoPlaceCheckerContract $geoPlaceChecker)
    {
    }

    public function exists(GeoPlaceId $id): bool
    {
        return $this->geoPlaceChecker->exists($id);
    }
}
