<?php

declare(strict_types=1);

namespace App\Geo\Infrastructure\Persistence;

use App\Geo\Domain\Port\GeoPlaceWriterInterface;
use App\Shared\Domain\ValueObject\GeoPlaceId;
use Doctrine\DBAL\Connection;

final readonly class DbalGeoPlaceWriter implements GeoPlaceWriterInterface
{
    public function __construct(private Connection $geoConnection)
    {
    }

    public function upsert(
        GeoPlaceId $id,
        string $name,
        string $asciiName,
        string $countryCode,
        ?string $admin1Code,
    ): void {
        $this->geoConnection->executeStatement(
            <<<'SQL'
            INSERT INTO geo_place (geoname_id, name, ascii_name, country_code, admin1_code)
            VALUES (:geonameId, :name, :asciiName, :countryCode, :admin1Code)
            ON CONFLICT (geoname_id) DO UPDATE SET
                name = EXCLUDED.name,
                ascii_name = EXCLUDED.ascii_name,
                country_code = EXCLUDED.country_code,
                admin1_code = EXCLUDED.admin1_code
            SQL,
            [
                'geonameId' => $id->value,
                'name' => $name,
                'asciiName' => $asciiName,
                'countryCode' => $countryCode,
                'admin1Code' => $admin1Code,
            ],
        );
    }
}
