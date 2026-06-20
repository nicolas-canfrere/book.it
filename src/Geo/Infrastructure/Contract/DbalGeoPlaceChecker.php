<?php

declare(strict_types=1);

namespace App\Geo\Infrastructure\Contract;

use App\Geo\Application\Contract\GeoPlaceCheckerInterface;
use App\Shared\Domain\ValueObject\GeoPlaceId;
use Doctrine\DBAL\Connection;

final readonly class DbalGeoPlaceChecker implements GeoPlaceCheckerInterface
{
    public function __construct(private Connection $geoConnection)
    {
    }

    public function exists(GeoPlaceId $id): bool
    {
        $count = $this->geoConnection->fetchOne(
            'SELECT COUNT(*) FROM geo_place WHERE geoname_id = :id',
            ['id' => $id->value],
        );

        return $count > 0;
    }
}
