<?php

declare(strict_types=1);

namespace App\Hotel\Infrastructure\Persistence\Doctrine;

use App\Hotel\Domain\Model\Address;
use App\Hotel\Domain\Model\Hotel;
use App\Hotel\Domain\Port\HotelRepositoryInterface;
use Doctrine\DBAL\Connection;
use Symfony\Component\String\Slugger\SluggerInterface;

final readonly class HotelRepository implements HotelRepositoryInterface
{
    public function __construct(
        private Connection $bookit,
        private SluggerInterface $slugger,
    ) {
    }

    public function add(Hotel $hotel): void
    {
        $this->bookit->insert('hotel', [
            'id' => $hotel->id,
            'name' => $hotel->name,
            'street_address' => $hotel->address->streetAddress,
            'postal_code' => $hotel->address->postalCode,
            'city' => $hotel->address->city,
            'country' => $hotel->address->country,
            'search_key' => $this->buildSearchKey($hotel->name, $hotel->address),
            'created_at' => $hotel->createdAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function get(string $id): ?Hotel
    {
        /** @var array{id: string, name: string, street_address: string, postal_code: string, city: string, country: string, created_at: string}|false $row */
        $row = $this->bookit->fetchAssociative(
            'SELECT id, name, street_address, postal_code, city, country, created_at FROM hotel WHERE id = :id',
            ['id' => $id],
        );

        if (false === $row) {
            return null;
        }

        return new Hotel(
            $row['id'],
            $row['name'],
            new Address($row['street_address'], $row['postal_code'], $row['city'], $row['country']),
            new \DateTimeImmutable($row['created_at']),
        );
    }

    public function existsByNameAndAddress(string $name, Address $address): bool
    {
        $count = $this->bookit->fetchOne(
            'SELECT COUNT(*) FROM hotel WHERE search_key = :key',
            ['key' => $this->buildSearchKey($name, $address)],
        );

        return $count > 0;
    }

    private function buildSearchKey(string $name, Address $address): string
    {
        return implode('|', [
            $this->slugger->slug($name)->lower()->toString(),
            $this->slugger->slug($address->streetAddress)->lower()->toString(),
            strtolower($address->postalCode),
            $this->slugger->slug($address->city)->lower()->toString(),
            strtolower($address->country),
        ]);
    }
}
