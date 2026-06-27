<?php

declare(strict_types=1);

namespace App\Hotel\Infrastructure\Persistence\Doctrine;

use App\Hotel\Domain\Model\Address;
use App\Hotel\Domain\Model\Hotel;
use App\Hotel\Domain\Port\HotelPublicReaderInterface;
use App\Hotel\Domain\ValueObject\HotelAmenity;
use App\Hotel\Domain\ValueObject\StarRating;
use App\Shared\Domain\ValueObject\GeoPlaceId;
use App\Shared\Domain\ValueObject\HotelId;
use App\Shared\Domain\ValueObject\OrganizationId;
use Doctrine\DBAL\Connection;

final readonly class HotelPublicReader implements HotelPublicReaderInterface
{
    public function __construct(private Connection $hotelConnection)
    {
    }

    public function get(HotelId $id): ?Hotel
    {
        /** @var array{id: string, name: string, street_address: string, postal_code: string, city: string, country: string, geo_place_id: string|null, created_at: string, stars: int|null, superior: string|bool, amenities: string, organization_id: string}|false $row */
        $row = $this->hotelConnection->fetchAssociative(
            'SELECT id, name, street_address, postal_code, city, country, geo_place_id, created_at, stars, superior, amenities, organization_id FROM hotel WHERE id = :id',
            ['id' => $id->value],
        );

        if (false === $row) {
            return null;
        }

        return $this->hydrate($row);
    }

    /**
     * @param array{id: string, name: string, street_address: string, postal_code: string, city: string, country: string, geo_place_id: string|null, created_at: string, stars: int|null, superior: string|bool, amenities: string, organization_id: string} $row
     */
    private function hydrate(array $row): Hotel
    {
        $starRating = null !== $row['stars']
            ? new StarRating((int) $row['stars'], 't' === $row['superior'] || true === $row['superior'])
            : null;

        return new Hotel(
            new HotelId($row['id']),
            $row['name'],
            new Address(
                $row['street_address'],
                $row['postal_code'],
                $row['city'],
                $row['country'],
                null !== $row['geo_place_id'] ? new GeoPlaceId((string) $row['geo_place_id']) : null,
            ),
            new \DateTimeImmutable($row['created_at']),
            new OrganizationId($row['organization_id']),
            $starRating,
            $this->parseAmenities($row['amenities']),
        );
    }

    /** @return array<HotelAmenity> */
    private function parseAmenities(string $raw): array
    {
        if ('{}' === $raw) {
            return [];
        }

        preg_match_all('/"([^"]+)"|([^,{}]+)/', $raw, $matches);
        $values = array_map(
            static fn(string $quoted, string $plain): string => '' !== $quoted ? $quoted : $plain,
            $matches[1],
            $matches[2],
        );

        return array_map(HotelAmenity::from(...), $values);
    }
}
