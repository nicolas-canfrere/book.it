<?php

declare(strict_types=1);

namespace App\Hotel\Infrastructure\Persistence\Doctrine;

use App\Hotel\Domain\Model\Address;
use App\Hotel\Domain\Model\Hotel;
use App\Hotel\Domain\Port\HotelRepositoryInterface;
use Doctrine\DBAL\Connection;

final readonly class HotelRepository implements HotelRepositoryInterface
{
    public function __construct(private Connection $bookit)
    {
    }

    public function add(Hotel $hotel): void
    {
        $this->bookit->insert('hotel', [
            'id' => $hotel->id,
            'name' => $hotel->name,
            'created_at' => $hotel->createdAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function existsByNameAndAddress(string $name, Address $address): bool
    {
        return false; // implemented in Task 6
    }

    public function get(string $id): ?Hotel
    {
        /** @var array{id: string, name: string, created_at: string}|false $row */
        $row = $this->bookit->fetchAssociative(
            'SELECT id, name, created_at FROM hotel WHERE id = :id',
            ['id' => $id],
        );

        if (false === $row) {
            return null;
        }

        return new Hotel(
            $row['id'],
            $row['name'],
            new \DateTimeImmutable($row['created_at']),
        );
    }
}
